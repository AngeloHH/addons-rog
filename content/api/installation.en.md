# Installation service

The service is available before configuring the database and application key. It runs with PHP/PDO and the custom models, creating the initial structure directly from them. After installation, normal connections do not create or update tables. No migrations run.

## Enabling the installer

### Preparing the deployment package

Run `npm run build` and deploy the contents of `dist/`. The API package includes runtime code,
database schema files, console commands, base/example configuration and an empty storage directory.
It excludes private configuration, installation locks, temporary setup credentials and journals,
stored files, SQLite databases and API tests. Your local installation remains unchanged.

This package is ready for a fresh installation. On the destination server, set `ROG_ORIGIN`,
generate a new setup token and select an empty database as described below. A preconfigured
`ROG_DSN` still disables the installer. Publishing over an existing installation does not reset
its configuration or data; preserve the server's `api/config` and `api/storage` when updating.

### Server configuration

With PHP and the chosen engine ready, set `ROG_ORIGIN` to the origin from which the installer will be called, for example `https://addons.example.com`. It must match the `Origin` header exactly, without a path or trailing slash. The screen and API must be served from that origin; cross-site CORS is not enabled. With `npm run dev`, the PHP server uses `http://127.0.0.1:5173` by default unless `ROG_ORIGIN` was explicitly set. Open that exact address, rather than `localhost`. The API fallback outside the development startup remains `http://127.0.0.1:8080`.

From `api/`, in a copy that has not yet been configured, run:

```sh
php bin/setup-token.php
```

It returns a temporary key valid for one hour. Keep the `token` value to send in `X-Setup-Token`. The private file stores only its hash. Alternatively, set `ROG_SETUP_TOKEN` to a random key between 32 and 256 characters; this mode has no time expiry, but is also locked when installation completes. When using that variable, the command is unnecessary. Remove it after use.

The temporary key allows installation without letting a visitor claim the first account. It does not replace the founder's password or become the application's secret key. Do not put it in a URL, repository or request log.

## Routes

| Method and route | Authorization | Result |
|---|---|---|
| `GET /api/setup/status` | Public | Indicates whether the site is configured, available or awaiting recovery; returns no credentials. |
| `GET /api/setup/requirements` | `X-Setup-Token` | Actual versions and extensions, permissions and effective PHP limits. |
| `POST /api/setup/database` | `X-Setup-Token` and `Origin` | Tests the connection and checks the database, its permissions and schema without creating anything. |
| `POST /api/setup/install` | `X-Setup-Token` and `Origin` | Creates the structure and founder, saves configuration and locks the installer. |

Send POST requests with `Content-Type: application/json`. These routes use the installation key without an application session or CSRF token, since those do not exist yet. They return the same single API format: `ok: true` with data or `ok: false` with `error`. Credential contents are neither returned nor logged.

## Testing the connection

```json
{
  "database": {
    "driver": "pgsql",
    "host": "127.0.0.1",
    "port": 5432,
    "name": "rog_addons",
    "username": "rog",
    "password": "REPLACE",
    "sslMode": "prefer",
    "createIfMissing": true
  }
}
```

Supports PostgreSQL 15+ with UTF-8, MySQL 8.4+ with utf8mb4 and SQLite 3.38+. The example above is for PostgreSQL. See [engine configuration](databases.en.md) for MySQL and SQLite. Database names must start with a lowercase letter or underscore and contain lowercase letters, numbers or underscores, up to 63 characters. `postgres`, `template0`, `template1`, `mysql`, `sys`, `performance_schema` and `information_schema` are not installation targets. The host accepts DNS names and IP addresses; the browser cannot submit a free-form DSN or SQL. Supported SSL modes are `disable`, `prefer`, `require`, `verify-ca` and `verify-full`; use the mode appropriate for the hosting environment.

On PostgreSQL, if the database does not exist, the check queries the `postgres` maintenance database. `createIfMissing: true` allows its creation **only during installation**, with an account that has `CREATEDB`. If the host restricts this operation, create an empty database in its panel and send `false`. An existing database requires schema creation permissions. The PostgreSQL user must already exist.

Example response for a missing database that can be created:

```json
{
  "ok": true,
  "connected": true,
  "databaseExists": false,
  "canCreateDatabase": true,
  "schemaEmpty": true,
  "serverVersion": "17.5",
  "ready": true,
  "reason": null
}
```

A valid connection can return `ready: false`, for example with `reason: "DATABASE_NOT_EMPTY"`. The installer requires an empty target: the `rog_design` schema on PostgreSQL, the selected database on MySQL or the selected file on SQLite. It does not adopt an existing installation.

## Installing

Send the same database configuration to the installation endpoint, adding:

```json
{
  "database": {
    "driver": "pgsql",
    "host": "127.0.0.1",
    "port": 5432,
    "name": "rog_addons",
    "username": "rog",
    "password": "REPLACE",
    "createIfMissing": true
  },
  "site": {
    "url": "https://addons.example.com",
    "name": "RoG Addons",
    "language": "es",
    "timezone": "UTC"
  },
  "admin": {
    "username": "Angelo",
    "email": "angelo@example.com",
    "password": "REPLACE_WITH_YOUR_OWN_PASSWORD",
    "passwordConfirmation": "REPLACE_WITH_YOUR_OWN_PASSWORD"
  },
  "mail": { "transport": "spool" }
}
```

`site.url` is an HTTP(S) origin without a path, parameters or credentials. Use HTTPS in production. The founder requires a 3–24 character username, valid email and matching 12–128 character passwords. The account is saved as verified with a hashed password and `access_level = 2`, allowing immediate login without waiting for email.

`site.storagePath` is optional: the default is `api/storage`. If specified, it must be an absolute private path, separate from the public directory and code/configuration. Paths containing `..` or protected directories are rejected. The directory is created during installation, not during the connection test.

The service generates a random secret key, creates tables from models and writes
`config/config.local.php` with the keys consumed by this API. It does not replace
`config/config.php`. The site name, language and time zone are saved in `settings.site`;
persistence remains in UTC.

SMTP accepts `transport: "smtp"`, `from`, `host`, `port`, `username` and `password`. Configuration is saved for the existing STARTTLS transport; installation does not send a test email or prove the SMTP provider will accept it. `spool` allows installation to complete without external email.

The final response uses HTTP 201 and contains `installed`, `locked`, `resumed`, `founderId`, `siteUrl`, `mailTransport` and a list of steps actually completed. The operation is synchronous: percentages are not invented and timers do not mark steps as complete.

## Locking and recovery

- A file lock prevents two simultaneous installations of the same copy. A database lock also coordinates initial creation and the account.
- On PostgreSQL and SQLite, tables, founder and metadata are created in a transaction. A failure before commit rolls back those operations. If a new database was created, it may remain empty: `CREATE DATABASE` is not part of that transaction.
- Configuration is prepared in a complete private file before commit and published by renaming. A private journal allows completion if the process stops after committing data.
- MySQL commits each structural change immediately. During setup, a temporary marker is saved in `settings.initial_schema`, tied to the private installation identifier and initial SQL. An ownership comment on the first table also protects against failure between its creation and progress recording. Only that attempt can resume; tables are not deleted and unrelated databases are not converted. The marker is removed when the founder is saved. It is not migration history and is not checked during normal use. If schema code changes while installation is pending, continuing with a different plan is rejected.
- On `INSTALL_FAILED` or `INSTALL_FINALIZE_FAILED`, fix the server cause and resend `POST /api/setup/install` with `{}` and the valid temporary key. Original data is reused; do not send different configuration as a way to change it. If the key expired, the server administrator can generate another to resume.
- A complete installation returns `SETUP_LOCKED` from operational endpoints. Existing configuration, an already configured DSN or the `config/install.lock` marker prevents reinstallation. Deleting only `config.local.php` does not remove the lock.

Do not delete a pending journal to force reinstallation. Its paths and names are fixed and private; they are not accepted from the request. Once complete, the journal and file-based temporary key are removed. Protect these files with system permissions too, and keep `public/` as the web root.

## Installation screen

The installation screen is integrated into React at `/:lang/setup`. Its buttons call these
routes and display actual responses. The screen offers PostgreSQL, MySQL and SQLite with
the corresponding fields. The service does not support table prefixes, changing upload
limits, invitation-only registration, disabling moderation or inserting demo data.
Unknown fields are rejected.

Reproducible validation: [installer tests](tests.en.md). Per-engine reports are saved in each run's temporary directory.
