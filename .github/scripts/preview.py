"""Initialize an isolated Cloud preview from production, then deploy its branch."""

import http.client
import json
import os
import re
import subprocess
import sys
import tempfile
import time
from pathlib import Path
from urllib.parse import unquote, urlsplit


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def identifier(value):
    require(isinstance(value, str) and re.fullmatch(r"[A-Za-z0-9_-]+", value),
            "Missing or invalid Cloud resource identifier.")
    return value


def cloud(path, method="GET", payload=None):
    url = urlsplit(path)
    require(not url.netloc or (url.scheme == "https" and url.netloc == "cloud.laravel.com"),
            "Refusing an unexpected Cloud pagination URL.")
    path = url.path + ("?" + url.query if url.query else "")
    require(path.startswith("/api/"), "Invalid Cloud API path.")
    connection = http.client.HTTPSConnection("cloud.laravel.com", timeout=30)
    try:
        connection.request(method, path, body=json.dumps(payload) if payload is not None else None,
                           headers={"Authorization": "Bearer " + os.environ["LARAVEL_CLOUD_TOKEN"],
                                    "Accept": "application/json", "Content-Type": "application/json"})
        response = connection.getresponse()
        # API bodies can contain credentials; report only the status on errors.
        require(200 <= response.status < 300, f"Cloud API {method} failed (HTTP {response.status}).")
        return json.load(response)
    finally:
        connection.close()


def resources(path):
    records, included = [], []
    while path:
        page = cloud(path)
        records.extend(page["data"])
        included.extend(page.get("included", []))
        path = page.get("links", {}).get("next")
    return records, included


def wait_for(description, operation, seconds=900):
    print(description, flush=True)
    deadline = time.monotonic() + seconds
    while time.monotonic() < deadline:
        result = operation()
        if result is not None:
            return result
        time.sleep(10)
    raise RuntimeError("Timed out: " + description)


def relation(resource, name):
    return resource.get("relationships", {}).get(name, {}).get("data")


def find_preview(application_id, production_id, branch):
    environments, included = resources(f"/api/applications/{application_id}/environments?include=branch")
    branches = {item["id"]: item["attributes"]["name"] for item in included if item["type"] == "branches"}
    matches = [item for item in environments
               if item["attributes"]["created_from_automation"] is True
               and branches.get((relation(item, "branch") or {}).get("id")) == branch]
    require(len(matches) <= 1, "Multiple Cloud previews match this branch; refusing to choose one.")
    if matches:
        require(matches[0]["id"] != production_id, "Refusing to use the production environment.")
        return identifier(matches[0]["id"])


def environment(environment_id):
    return cloud(f"/api/environments/{environment_id}?include=application,database,secrets")


def validate_preview(document, production_document):
    preview, production = document["data"], production_document["data"]
    require(preview["id"] != production["id"] and preview["attributes"]["created_from_automation"] is True,
            "The destination must be an automated preview, separate from production.")
    require(relation(preview, "application") and relation(preview, "application") == relation(production, "application"),
            "Preview and production must belong to the same application.")
    require(relation(preview, "database") and relation(production, "database")
            and relation(preview, "database") != relation(production, "database"),
            "The preview must have its own attached database.")
    forbidden = {"DB_URL", "DATABASE_URL", "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"}
    require("environment_variables" in preview["attributes"], "Cloud token must be able to read preview environment variables.")
    variables = preview["attributes"]["environment_variables"]
    keys = {item["key"] for item in variables}
    secret_keys = {item["attributes"]["key"] for item in document.get("included", []) if item["type"] == "secrets"}
    keys.update(secret_keys)
    require(not keys.intersection(forbidden),
            "Remove explicit database credentials from preview variables/secrets; use Cloud's injected values.")
    require(all(item["value"] == "pgsql" for item in variables if item["key"] == "DB_CONNECTION"),
            "Preview DB_CONNECTION must be pgsql.")
    require("DB_CONNECTION" not in secret_keys, "Set preview DB_CONNECTION=pgsql as a variable, not a linked secret.")


def database_clusters(preview_schema, production_schema):
    clusters, schemas = resources("/api/databases/clusters?include=databases")
    by_schema = {schema["id"]: cluster for cluster in clusters
                 for schema in (relation(cluster, "databases") or [])}
    if preview_schema not in by_schema:
        return None
    require(production_schema in by_schema, "Cannot find the production database cluster.")
    preview, production = by_schema[preview_schema], by_schema[production_schema]
    require(preview["id"] != production["id"], "Preview must use a separate database cluster.")
    if preview["attributes"]["status"] != "available":
        return None
    names = {item["id"]: item["attributes"]["name"] for item in schemas if item["type"] == "databaseSchemas"}
    require(preview_schema in names and production_schema in names, "Missing database names in Cloud response.")
    return preview, production, names[preview_schema], names[production_schema]


def hostname(host):
    # Neon direct and pooled endpoints address the same underlying cluster.
    return host.lower().replace("-pooler.", ".").rstrip(".")


def source_connection(url, production, database_name):
    parsed = urlsplit(url)
    connection = production["attributes"]["connection"]
    require(parsed.scheme in ("postgres", "postgresql") and parsed.hostname and parsed.username and parsed.password,
            "PRODUCTION_DATABASE_URL must contain PostgreSQL read-only credentials.")
    require(hostname(parsed.hostname) == hostname(connection["hostname"])
            and (parsed.port or 5432) == connection["port"]
            and unquote(parsed.path.removeprefix("/")) == database_name,
            "The production export URL does not match the configured production database.")
    return {"hostname": hostname(connection["hostname"]), "port": parsed.port or 5432,
            "username": unquote(parsed.username), "password": unquote(parsed.password), "database": database_name}


def postgres(tool, connection, arguments, **streams):
    values = {"PGHOST": connection["hostname"], "PGPORT": str(connection["port"]),
              "PGDATABASE": connection["database"], "PGUSER": connection["username"],
              "PGPASSWORD": connection["password"], "PGSSLMODE": "verify-full",
              "PGSSLROOTCERT": "/etc/ssl/certs/ca-certificates.crt", "PGCONNECT_TIMEOUT": "10",
              "PGOPTIONS": "-c default_transaction_read_only=on" if tool == "pg_dump" else ""}
    command = ["docker", "run", "--rm", "--interactive"]
    for key in values:
        command.extend(["--env", key])
    command.extend([f"postgres:{connection.get('version', 18)}", tool, *arguments])
    result = subprocess.run(command, env={**os.environ, **values}, stderr=subprocess.DEVNULL,
                            stdout=streams.pop("stdout", subprocess.PIPE), **streams)
    require(result.returncode == 0, f"PostgreSQL {tool} failed; database details are withheld from CI logs.")
    return result.stdout.decode().strip() if result.stdout is not None else None


def database_versions(source, target):
    for connection in (source, target):
        version = int(postgres("psql", connection, ["-XAt", "--command", "SHOW server_version_num"])) // 10000
        require(13 <= version <= 18, "Unsupported PostgreSQL major version; update the automation's client images.")
        connection["version"] = version
    require(target["version"] >= source["version"], "Preview PostgreSQL must not be older than production.")


def initialize_database(source, target, preview_id):
    marker = postgres("psql", target, ["-XAt", "--command",
                      "SELECT to_regclass('preview_automation.initialization') IS NOT NULL"])
    if marker == "t":
        owner = postgres("psql", target, ["-XAt", "--command",
                         "SELECT environment_id FROM preview_automation.initialization"])
        require(owner == preview_id, "Database initialization marker belongs to another environment.")
        print("Preview already initialized; keeping its data.", flush=True)
        return
    tables = postgres("psql", target, ["-XAt", "--command",
                      "SELECT count(*) FROM information_schema.tables WHERE table_schema NOT IN "
                      "('pg_catalog', 'information_schema') AND table_type = 'BASE TABLE'"])
    require(tables == "0", "Preview database is not empty and has no initialization marker; refusing to overwrite it.")
    with tempfile.TemporaryDirectory(prefix="preview-database-") as directory:
        dump = Path(directory) / "production.sql"
        with dump.open("wb") as output:
            arguments = ["--no-owner", "--no-acl", "--no-publications", "--no-subscriptions"]
            # Preserve business records and password hashes, but not live sessions, jobs or caches.
            for table in ("sessions", "cache", "cache_locks", "jobs", "job_batches", "failed_jobs", "password_reset_tokens"):
                arguments.append("--exclude-table-data=public." + table)
            print("Exporting production with read-only transactions.", flush=True)
            postgres("pg_dump", source, arguments, stdout=output)
        with dump.open("a") as output:
            output.write("\nCREATE SCHEMA preview_automation;\n"
                         "CREATE TABLE preview_automation.initialization (environment_id text PRIMARY KEY);\n"
                         f"INSERT INTO preview_automation.initialization VALUES ('{identifier(preview_id)}');\n")
        with dump.open("rb") as data:
            print("Importing into the empty preview database in one transaction.", flush=True)
            postgres("psql", target, ["-X", "--set=ON_ERROR_STOP=1", "--single-transaction", "--file=-"],
                     stdin=data, stdout=subprocess.DEVNULL)


def terminal(status):
    return status in {"deployment.succeeded", "build.failed", "deployment.failed", "failed", "cancelled"}


def main():
    os.umask(0o077)
    for key in ("LARAVEL_CLOUD_TOKEN", "PRODUCTION_DATABASE_URL", "CLOUD_APPLICATION_ID", "CLOUD_PRODUCTION_ENVIRONMENT_ID"):
        require(os.environ.get(key), "Missing GitHub configuration: " + key)
    event = json.loads(Path(os.environ["GITHUB_EVENT_PATH"]).read_text())
    pull_request, repository = event["pull_request"], event["repository"]
    require(pull_request["head"]["repo"]["full_name"] == repository["full_name"]
            and pull_request["head"]["ref"].startswith("preview/") and pull_request["base"]["ref"] == "main",
            "Only same-repository preview/ pull requests into main are eligible.")
    application_id = identifier(os.environ["CLOUD_APPLICATION_ID"])
    production_id = identifier(os.environ["CLOUD_PRODUCTION_ENVIRONMENT_ID"])
    preview_id = wait_for("Waiting for Cloud to create the branch preview.",
                          lambda: find_preview(application_id, production_id, pull_request["head"]["ref"]))
    cloud(f"/api/environments/{preview_id}", "PATCH",
          {"uses_push_to_deploy": False, "deploy_command": "php artisan migrate --force"})
    production = environment(production_id)

    def attached_databases():
        preview = environment(preview_id)
        if not relation(preview["data"], "database"):
            return None
        validate_preview(preview, production)
        return preview

    preview = wait_for("Waiting for the preview database attachment.", attached_databases)

    def deployments_idle():
        deployments, _ = resources(f"/api/environments/{preview_id}/deployments")
        return True if all(terminal(item["attributes"]["status"]) for item in deployments) else None

    wait_for("Waiting for any existing preview deployment to finish.", deployments_idle)
    preview_schema = identifier(relation(preview["data"], "database")["id"])
    production_schema = identifier(relation(production["data"], "database")["id"])
    target_cluster, source_cluster, target_name, source_name = wait_for(
        "Waiting for the isolated PostgreSQL cluster.", lambda: database_clusters(preview_schema, production_schema))
    require(target_cluster["attributes"]["connection"]["driver"] == "pgsql"
            and source_cluster["attributes"]["connection"]["driver"] == "pgsql", "Both databases must use PostgreSQL.")
    target_id = identifier(target_cluster["id"])
    target = cloud(f"/api/databases/clusters/{target_id}")["data"]["attributes"]["connection"]
    require(all(target.get(key) for key in ("hostname", "port", "username", "password")),
            "Cloud token cannot read preview database connection credentials.")
    target = {**target, "database": target_name}
    source = source_connection(os.environ["PRODUCTION_DATABASE_URL"], source_cluster, source_name)
    require(hostname(source["hostname"]) != hostname(target["hostname"]), "Preview and production endpoints must differ.")

    def database_ready():
        try:
            return True if postgres("psql", target, ["-XAt", "--command", "SELECT 1"]) == "1" else None
        except RuntimeError:
            return None

    wait_for("Waiting for an authenticated preview database connection.", database_ready)
    database_versions(source, target)
    initialize_database(source, target, preview_id)
    # Recheck the attachment and overrides immediately before deploying application code.
    latest = environment(preview_id)
    validate_preview(latest, production)
    require(relation(latest["data"], "database")["id"] == preview_schema, "Preview database attachment changed during import.")
    deployment = cloud(f"/api/environments/{preview_id}/deployments", "POST")["data"]
    deployment_id = identifier(deployment["id"])

    def deployment_finished():
        result = cloud(f"/api/deployments/{deployment_id}")["data"]
        return result if terminal(result["attributes"]["status"]) else None

    result = wait_for("Waiting for Cloud to deploy the preview.", deployment_finished, seconds=1200)
    require(result["attributes"]["status"] == "deployment.succeeded", "Preview deployment failed; inspect its logs in Cloud.")
    print("Preview database is initialized and deployment succeeded.", flush=True)


if __name__ == "__main__":
    try:
        main()
    except (RuntimeError, KeyError, ValueError, OSError, http.client.HTTPException) as error:
        # Unexpected responses may contain credentials; never print their values or a traceback.
        print(str(error) if isinstance(error, RuntimeError) else "Preview automation failed: " + type(error).__name__, file=sys.stderr)
        sys.exit(1)
