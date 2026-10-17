# ROG API — PHP with PostgreSQL, MySQL and SQLite

Code paths and commands in this guide are relative to `api/` at the project root.

A single API using PHP/PDO and a custom layer for models and initial schema creation.
It uses no Composer, framework or external ORM. The code keeps practical classes,
`snake_case` names, prepared queries and early returns.

**Status:** 64 method/route combinations, including infrastructure and installation operations. Single specification: [API reference](reference.en.md). Complete inventory: [route inventory](../../api/tests/fixtures/routes.json). See [unified API](routes.en.md).

The implementation has run against local PostgreSQL, MySQL and SQLite test databases, with servers stopped afterward. SMTP was also validated with messages captured by Ethereal. No production database was connected and no messages were delivered to real inboxes.

## Installation

Requirements: PHP 8.2+, `intl`, `mbstring`, `zip`, `fileinfo`, `openssl` and the PDO driver for the chosen engine: PostgreSQL 15+ (`pdo_pgsql`), MySQL 8.4+ (`pdo_mysql`) or SQLite 3.38+ (`pdo_sqlite`). MySQL must use utf8mb4; PostgreSQL, UTF-8. MariaDB is not included. See [compatibility and differences](databases.en.md).

**Installation through a screen:** the [installation service](installation.en.md) is already available. It checks requirements and the connection, saves configuration, creates the founder and locks when finished. Enable it with a temporary key (`php bin/setup-token.php` or `ROG_SETUP_TOKEN`); it uses models and needs no manual SQL. The instructions below are the direct configuration alternative without the wizard. Do not mix both: existing configuration locks the web installer.

1. Copy `config/config.example.php` to `config/config.local.php` or set the environment variables in `config/config.php`. Local secrets are excluded from Git.
2. Set `ROG_DSN`, `ROG_DB_USER`, `ROG_DB_PASSWORD`, `ROG_SECRET` and `ROG_ORIGIN`. The key must be random, at least 32 characters long and remain stable: it protects sessions, CSRF, signed downloads and encrypted email.
3. Run `php bin/schema.php create` once, with an empty application schema. It creates tables from models and rejects an existing structure. If the database named in `dbname` does not exist and `create_database` is enabled, it can create it with the chosen engine's permissions. SQLite creates only the specified private file in an existing writable directory. The installation account needs permission to create the schema and its objects.
4. Create the first founder with a password supplied through `ROG_FOUNDER_PASSWORD`, without writing it to command history:

```sh
php bin/create-founder.php Angelo angelo@example.com
```

This command requires the structure created in the previous step. Normal connections never create databases, tables or columns.

5. Configure the web server's public root as **`public/`**. An `.htaccess` is included for Apache 2.4. Storage, configuration, contracts and models stay outside that root. The parent directory's `.htaccess` offers an alternative if hosting requires publishing `api/`, but `public/` is recommended.
6. For local development:

```sh
php -S 127.0.0.1:8080 -t public public/router.php
```

Use `ROG_ORIGIN=http://127.0.0.1:8080` and `ROG_SECURE_COOKIE=0` only for local HTTP. Production requires HTTPS and secure cookies. PHP's development server is for testing; deployment needs Apache/PHP-FPM or equivalent.

For PostgreSQL or MySQL, the server must be running and the account must exist. SQLite needs no separate server. If hosting does not allow database creation, create an empty database through its panel: the installer will create tables from models. Database creation during installation accepts an explicit `dbname` up to 63 characters, using lowercase letters, numbers and underscores; it must start with a letter or underscore. Existing databases connect through the normal DSN.

Environment variables are read in `config/config.php`; an existing `config.local.php` takes precedence. `ROG_CREATE_DATABASE=0` prevents creating a missing database during installation. There is no automatic synchronization option: application connections use only the existing structure. To separate the installation account from a limited web account, prepare the schema first and grant only the required runtime permissions. [PostgreSQL permissions](../../api/database/grants.sql) applies only to PostgreSQL; MySQL permissions are described in [databases](databases.en.md).

## Frontend use

- JSON responses use `ok: true` with their data; errors use `ok: false` and `error`.
- Before a write, call `GET /api/auth/session`, keep cookies and send `csrfToken` in `X-CSRF-Token`. The browser sends `Origin`.
- After login, account verification or logout, refresh the CSRF token before another write.
- Entity IDs are numbers; temporary ZIP IDs and public file links use UUIDs.
- Public URLs include `/api` and do not accept the `.php` suffix. The router also accepts the internal path without a prefix, as required by the development proxy.
- Draft PATCH accepts `revision`; after a 409, reload before resending changes.
- Each user creates or updates their review through `PUT /api/addons/{slug}/my-comment`. Catalog taxonomy and upload limits are available from `GET /api/taxonomy`.

Public responses use explicit selections. **Do not serialize an `Account` model directly:** the persistence object contains internal data such as the password hash.

## Custom models

```php
$config = require 'bootstrap.php';
$db = new Rog\Database\Db($config);

$users = new Rog\Models\Account($db);
$user = $users->get(42);
if ($user !== null) {
    $user->profile->realm = 'Example Realm';
    $user->save();
}

$settings = new Rog\Models\Setting($db);
$setting = $settings->create([
    'key' => 'example',
    'value' => (object) ['enabled' => true],
]);
$setting->value->enabled = false;
$setting->save();
```

Each model declares its table, columns, types and constraints **once**. That declaration produces SQL and guides object reading, conversion and writing. The base class provides `get`, `filter`, `create`, `save`, `delete` and `to_array`. It saves only modified columns, detects changes within JSONB and rejects unknown columns. An object loaded before another write fails with `Conflict` on save or delete, using `xmin` on PostgreSQL or a row version maintained by triggers on MySQL and SQLite. Services also lock rows involved in business transitions.

Columns are declared in `COLUMNS` and model classes are registered in `database/models.php`.
Those definitions create the structure of new installations. Normal connections use the
existing tables and do not apply schema changes.

`php bin/schema.php export pgsql|mysql|sqlite` prints initial SQL for the selected engine without connecting. Without an argument, it uses the DSN's engine, or PostgreSQL if no configuration exists yet. `php bin/schema.php create` explicitly applies it to an empty schema. PostgreSQL and SQLite create the structure in a transaction. MySQL commits structural operations immediately: the web installer records progress and resumes only its own attempt without deleting tables. PostgreSQL views, functions and indexes are in `database/domains.sql` and `database/objects.sql`; `PortableSchema` generates their MySQL and SQLite equivalents.

There is no migrator, automatic version comparison or change history. A technical history table from an older installation remains unused; the application neither deletes nor queries it. The former `diff`, `make` and `snapshot` modes are no longer available.

Services define editable fields, permissions and endpoints. Editable JSONB values are
received through allowed fields; the browser never accesses the database directly.

## Operations and configurable data

```sh
php bin/worker.php 100
php bin/cleanup.php
php bin/rescan.php 123
php bin/settings.php support config/support.json
```

- `worker`: processes the durable queue. By default, it saves test email in `storage/mail/`. For real delivery: `ROG_MAIL_TRANSPORT=smtp`, host/port, username, password and sender. The transport implements STARTTLS with certificate verification. Local mode and SMTP against Ethereal were tested; see [mail tests](tests.en.md).
- `cleanup`: expires 30-day drafts, invalidates tokens and deletes expired orphaned files and old rate-limit records. It can run hourly. It retains decided submissions, history and files still referenced.
- `rescan`: scans a ZIP again; if a published file is blocked, it becomes unavailable for download and a scanner report, event and notice are created. Newly uploaded ZIP files are already scanned before responding. Scanning checks structure and dangerous extensions; it is not an antivirus engine.
- `settings`: loads a JSON object for operational configuration. See [operational settings](settings.en.md) and the dictionary.

The same `worker` refreshes PostgreSQL catalog metrics after processing the queue, even when
there are no pending messages. MySQL and SQLite calculate them on query. Rating writes return
their exact result; PostgreSQL listings reflect the metrics from the latest refresh.

Verified, non-banned founders have individual contact cards. The shared Moderators card sends
one ticket to all verified, non-banned moderators and founders through separate notifications.
It is available even with a founder alone. `contactRecipients` adds optional mailboxes;
staff members and the automatic group come from accounts. The server rejects unavailable
destinations. `packDefinitions` defines packs using published slugs, and `support` holds the
project's support information. Without configured packs, the list is empty. Raised amounts,
pack members and editorial achievements come from configuration and stored data.

Contact attachments are retained and identified in the notification; staff can read them through the protected file route. The queue represents handoff to the transport, not a read receipt. A failure after SMTP delivery but before database confirmation can cause a retry: semantics are **at least once delivery**, with a deduplication key and stable Message-ID.

## Structure

```text
public/              HTTP entry point and web configuration
src/Http/            Request, response, validation and router
src/Database/        PDO, models and initial schema creation
src/Models/          The 15 functional tables
src/Services/        Rules, queries and endpoint adapters
src/Support/         Passwords and native SMTP
database/            Model registry and initial SQL objects
tests/fixtures/      Routes and response types expected by tests
bin/                 Maintenance tools
tests/               HTTP, model, format and concurrency tests
../content/api/      Reference, installation, decisions and tests
storage/             Private files and test email; excluded from Git
```

There are 15 functional tables and one metrics view, materialized only on PostgreSQL. Important relationships and states have columns and foreign keys; JSONB holds metadata and flexible content. [Dictionary with JSON examples](schema.en.md).

## Checks

Tests for all three engines generate reports in each run's temporary directory. The runner prints the path when finished. See [API tests](tests.en.md) to reproduce checks for routes, sessions, files, publication, moderation, models and concurrency.

Installer tests check its routes from an unconfigured copy, authorization, connection without writes, founder creation, locking and failure recovery. Their reports share the temporary results directory.

To reproduce using a **new, isolated** database, see [API tests](tests.en.md). The runner creates a local cluster, checks explicit creation from models and the absence of structural changes during normal connections, stops it afterward and uses local email. It does not reuse your credentials or an existing database.

Tests check API behavior and contention cases in disposable local environments. Their
results describe those scenarios and do not establish a maximum number of simultaneous
users or requests.
