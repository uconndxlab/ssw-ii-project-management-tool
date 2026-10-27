import copy
import json
import os
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import preview


def environment(environment_id, schema_id, automated=False):
    return {"id": environment_id, "type": "environments",
            "attributes": {"created_from_automation": automated, "environment_variables": []},
            "relationships": {"application": {"data": {"type": "applications", "id": "app"}},
                              "database": {"data": {"type": "databaseSchemas", "id": schema_id}},
                              "branch": {"data": {"type": "branches", "id": "branch"}}}}


class PreviewTest(unittest.TestCase):
    def setUp(self):
        self.production = {"data": environment("production", "source-schema")}
        self.destination = {"data": environment("preview", "target-schema", automated=True)}
        self.connection = {"hostname": "target.example.com", "port": 5432, "username": "target-user",
                           "password": "target-secret", "driver": "pgsql", "database": "target-db"}

    def test_rejects_shared_production_environment_database_or_application(self):
        for change in (lambda resource: resource.update(id="production"),
                       lambda resource: resource["relationships"]["database"]["data"].update(id="source-schema"),
                       lambda resource: resource["relationships"]["application"]["data"].update(id="other-app"),
                       lambda resource: resource["attributes"].update(created_from_automation=False)):
            with self.subTest(change=change):
                destination = copy.deepcopy(self.destination)
                change(destination["data"])
                with self.assertRaises(RuntimeError):
                    preview.validate_preview(destination, self.production)

    def test_rejects_database_overrides_in_variables_and_linked_secrets(self):
        for key in ("DB_URL", "DATABASE_URL", "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD"):
            for secret in (False, True):
                with self.subTest(key=key, secret=secret):
                    destination = copy.deepcopy(self.destination)
                    if secret:
                        destination["included"] = [{"type": "secrets", "attributes": {"key": key}}]
                    else:
                        destination["data"]["attributes"]["environment_variables"] = [{"key": key, "value": "production"}]
                    with self.assertRaises(RuntimeError):
                        preview.validate_preview(destination, self.production)

    def test_selects_exact_branch_and_rejects_ambiguous_automations(self):
        branches = [{"type": "branches", "id": "branch", "attributes": {"name": "preview/example"}}]
        with patch.object(preview, "resources", return_value=([self.destination["data"]], branches)):
            self.assertEqual(preview.find_preview("app", "production", "preview/example"), "preview")
            self.assertIsNone(preview.find_preview("app", "production", "preview/another"))
        with patch.object(preview, "resources", return_value=([self.destination["data"]] * 2, branches)):
            with self.assertRaisesRegex(RuntimeError, "Multiple"):
                preview.find_preview("app", "production", "preview/example")

    def test_rejects_distinct_databases_inside_the_production_cluster(self):
        cluster = {"id": "shared", "relationships": {"databases": {"data": [
            {"id": "source-schema"}, {"id": "target-schema"}]}}}
        with patch.object(preview, "resources", return_value=([cluster], [])):
            with self.assertRaisesRegex(RuntimeError, "separate database cluster"):
                preview.database_clusters("target-schema", "source-schema")

    def test_source_url_must_match_production_and_decodes_credentials(self):
        cluster = {"attributes": {"connection": {"hostname": "source.example.com", "port": 5432}}}
        source = preview.source_connection("postgresql://reader:p%40ss@source-pooler.example.com/source-db", cluster, "source-db")
        self.assertEqual(source["password"], "p@ss")
        self.assertEqual(source["hostname"], "source.example.com")
        for url in ("postgresql://reader:pass@other.example.com/source-db",
                    "postgresql://reader:pass@source.example.com/other-db"):
            with self.subTest(url=url), self.assertRaises(RuntimeError):
                preview.source_connection(url, cluster, "source-db")

    def test_initialized_preview_keeps_data_and_rejects_foreign_marker(self):
        with patch.object(preview, "postgres", side_effect=["t", "preview"]) as postgres:
            preview.initialize_database({}, self.connection, "preview")
            self.assertEqual(postgres.call_count, 2)
        with patch.object(preview, "postgres", side_effect=["t", "other-preview"]):
            with self.assertRaisesRegex(RuntimeError, "another environment"):
                preview.initialize_database({}, self.connection, "preview")

    def test_nonempty_uninitialized_preview_is_never_overwritten(self):
        with patch.object(preview, "postgres", side_effect=["f", "12"]) as postgres:
            with self.assertRaisesRegex(RuntimeError, "refusing to overwrite"):
                preview.initialize_database({}, self.connection, "preview")
            self.assertEqual(postgres.call_count, 2)

    def test_import_and_initialization_marker_share_a_transaction(self):
        calls = []

        def postgres(tool, connection, arguments, **streams):
            calls.append((tool, connection, arguments))
            if tool == "pg_dump":
                streams["stdout"].write(b"CREATE TABLE example (id integer);\n")
            elif "--file=-" in arguments:
                self.assertIn("--single-transaction", arguments)
                self.assertIn("--set=ON_ERROR_STOP=1", arguments)
                sql = streams["stdin"].read().decode()
                self.assertIn("CREATE TABLE example", sql)
                self.assertIn("INSERT INTO preview_automation.initialization VALUES ('preview')", sql)
            else:
                return "f" if "to_regclass" in arguments[-1] else "0"

        with patch.object(preview, "postgres", side_effect=postgres):
            preview.initialize_database({"hostname": "source"}, self.connection, "preview")
        self.assertEqual([call[0] for call in calls], ["psql", "psql", "pg_dump", "psql"])
        self.assertEqual(calls[-1][1], self.connection)
        self.assertIn("--exclude-table-data=public.sessions", calls[2][2])

    def test_pg_tools_force_tls_and_readonly_export_without_password_arguments(self):
        self.connection["version"] = 16
        with patch.object(preview.subprocess, "run") as run:
            run.return_value.returncode = 0
            run.return_value.stdout = b""
            preview.postgres("pg_dump", self.connection, ["--no-owner"])
            arguments, options = run.call_args
            self.assertNotIn("target-secret", " ".join(arguments[0]))
            self.assertIn("postgres:16", arguments[0])
            self.assertEqual(options["env"]["PGSSLMODE"], "verify-full")
            self.assertIn("default_transaction_read_only=on", options["env"]["PGOPTIONS"])

    def test_clients_match_actual_versions_and_cannot_restore_to_an_older_server(self):
        source, target = {}, {}
        with patch.object(preview, "postgres", side_effect=["160011", "170007"]):
            preview.database_versions(source, target)
        self.assertEqual((source["version"], target["version"]), (16, 17))
        with patch.object(preview, "postgres", side_effect=["180001", "160011"]):
            with self.assertRaisesRegex(RuntimeError, "older than production"):
                preview.database_versions({}, {})

    def test_pagination_cannot_send_api_token_to_another_host(self):
        with patch.object(preview.http.client, "HTTPSConnection") as connection:
            with self.assertRaisesRegex(RuntimeError, "pagination URL"):
                preview.cloud("https://attacker.example/api/environments")
            connection.assert_not_called()

    def test_fork_and_nonpreview_pull_requests_cannot_access_cloud(self):
        for repo, branch in (("attacker/fork", "preview/example"), ("owner/repo", "chore/example")):
            event = {"repository": {"full_name": "owner/repo"}, "pull_request": {
                "head": {"repo": {"full_name": repo}, "ref": branch}, "base": {"ref": "main"}}}
            with self.subTest(repo=repo, branch=branch), tempfile.TemporaryDirectory() as directory:
                path = Path(directory) / "event.json"
                path.write_text(json.dumps(event))
                values = {key: "configured" for key in ("LARAVEL_CLOUD_TOKEN", "PRODUCTION_DATABASE_URL",
                          "CLOUD_APPLICATION_ID", "CLOUD_PRODUCTION_ENVIRONMENT_ID")}
                with patch.dict(os.environ, {**values, "GITHUB_EVENT_PATH": str(path)}), patch.object(preview, "cloud") as cloud:
                    with self.assertRaisesRegex(RuntimeError, "same-repository"):
                        preview.main()
                    cloud.assert_not_called()

    def test_deploys_only_the_preview_and_never_deploys_after_a_failed_import(self):
        for failed in (False, True):
            with self.subTest(failed=failed), tempfile.TemporaryDirectory() as directory:
                event = {"repository": {"full_name": "owner/repo"}, "pull_request": {
                    "head": {"repo": {"full_name": "owner/repo"}, "ref": "preview/example"}, "base": {"ref": "main"}}}
                event_path = Path(directory) / "event.json"
                event_path.write_text(json.dumps(event))
                values = {"GITHUB_EVENT_PATH": str(event_path), "LARAVEL_CLOUD_TOKEN": "token",
                          "PRODUCTION_DATABASE_URL": "postgresql://reader:secret@source.example.com/source-db",
                          "CLOUD_APPLICATION_ID": "app", "CLOUD_PRODUCTION_ENVIRONMENT_ID": "production"}
                target = {"id": "target-cluster"}
                source = {"attributes": {"connection": {"hostname": "source.example.com", "port": 5432, "driver": "pgsql"}}}
                target["attributes"] = {"connection": self.connection}
                calls = []

                def cloud(path, method="GET", payload=None):
                    calls.append((path, method, payload))
                    if path == "/api/databases/clusters/target-cluster":
                        return {"data": target}
                    if path == "/api/environments/preview" and method == "PATCH":
                        return self.destination
                    if path == "/api/environments/preview/deployments" and method == "POST":
                        return {"data": {"id": "deployment"}}
                    if path == "/api/deployments/deployment":
                        return {"data": {"attributes": {"status": "deployment.succeeded"}}}
                    self.fail("Unexpected Cloud operation: " + method + " " + path)

                with patch.dict(os.environ, values), patch.object(preview, "cloud", side_effect=cloud), \
                     patch.object(preview, "find_preview", return_value="preview"), \
                     patch.object(preview, "environment", side_effect=lambda key: self.production if key == "production" else self.destination), \
                     patch.object(preview, "resources", return_value=([], [])), \
                     patch.object(preview, "database_clusters", return_value=(target, source, "target-db", "source-db")), \
                     patch.object(preview, "postgres", side_effect=lambda tool, connection, args: "160011" if args[-1] == "SHOW server_version_num" else "1"), \
                     patch.object(preview, "initialize_database", side_effect=RuntimeError("Import failed") if failed else None):
                    if failed:
                        with self.assertRaisesRegex(RuntimeError, "Import failed"):
                            preview.main()
                    else:
                        preview.main()
                deploys = [path for path, method, _ in calls if method == "POST"]
                self.assertEqual(deploys, [] if failed else ["/api/environments/preview/deployments"])


if __name__ == "__main__":
    unittest.main()
