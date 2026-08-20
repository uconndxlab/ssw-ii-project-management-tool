# Innovate Impact - UConn sswii

Administrative tool for tracking and reporting staff activity to fulfill agreements between organizations across states, programs, and projects.

## Requirements

- PHP 8.3+ (PDO SQLite, or PDO MySQL if you switch databases)
- Composer
- Node.js 20.19+ or 22.12+ and npm

Local setup uses SQLite. No MySQL, Redis, or mail server required.

## Setup

```bash
composer setup
php artisan storage:link
php artisan db:seed
```

`composer setup` copies `.env.example` to `.env` if needed, generates `APP_KEY`, runs migrations, and builds assets.

If migrate fails because the SQLite file is missing:

```bash
touch database/database.sqlite
php artisan migrate
```

`storage:link` is required for agreement attachment downloads.

## Running locally

```bash
composer dev
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

### Demo logins

Seeded by `DemoSeeder`. Password for all: `password`.

| Email | Role |
| --- | --- |
| `admin@example.com` | admin |
| `staff1@example.com` | staff |
| `consultant1@example.com` | consultant |

Local-only. Do not use these in production.

## Environment variables

Copy `.env.example` to `.env`. `composer setup` generates `APP_KEY`. Leave the rest of Laravel’s defaults unless you need to change these:

- **`APP_URL`** — URL you open in the browser. With `composer dev`, use `http://127.0.0.1:8000`. Used for password-reset links and public file URLs.
- **`DB_CONNECTION`** — `sqlite` by default (`database/database.sqlite`). For MySQL, set `mysql` and uncomment `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
- **`MAIL_FROM_ADDRESS`** — If empty, “Forgot password?” is hidden and reset routes 404. `.env.example` sets a placeholder so the flow is on locally.
- **`MAIL_MAILER`** — `log` writes mail to `storage/logs` instead of sending it. Switch to `smtp` (and set host/credentials) when you need real delivery.
- **`UPLOAD_MAX_KB`** — Max size in kilobytes for agreement attachments and activity document fields. Default `51200` (50 MB). PHP `upload_max_filesize` and `post_max_size` must be at least this large.
