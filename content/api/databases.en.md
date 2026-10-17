# Supported databases

The API uses the same 15 models with PostgreSQL 15+, MySQL 8.4+ or SQLite 3.38+. MariaDB is not included. PHP needs `intl`, `mbstring`, `zip`, `fileinfo`, `openssl` and the PDO driver for the selected engine. The installer shows available drivers; installing all three is not required.

## PostgreSQL

```json
{"database":{"driver":"pgsql","host":"127.0.0.1","port":5432,"name":"rog_addons","username":"rog","password":"REPLACE","sslMode":"prefer","createIfMissing":true}}
```

Uses UTF-8 and the `rog_design` schema. Creating a missing database requires `CREATEDB`; creating its objects requires the corresponding schema permissions. The connection test creates neither databases nor tables. The structure and founder are created within a transaction.

## MySQL

```json
{"database":{"driver":"mysql","host":"127.0.0.1","port":3306,"name":"rog_addons","username":"rog","password":"REPLACE","sslCa":"","createIfMissing":true}}
```

Requires MySQL 8.4+, InnoDB and utf8mb4. The installation account needs permission to create tables, indexes, views, triggers and functions, as well as read and write data. With binlog enabled, the administrator must allow creation of the included deterministic functions. The installer does not change global server options. Permission to create the database is needed only if it does not already exist.

`sslCa` accepts the path to a CA certificate on the PHP server and enables TLS with certificate verification. Without a CA, the driver's default connection is used. PostgreSQL `sslMode` values do not apply to MySQL. Direct configuration supports `ROG_DB_SSL_CA`.

MySQL commits structural operations immediately. The installer records the progress of its initial attempt and resumes after a failure without deleting tables. The installation identifier and creation plan must match; an unrelated database or a changed plan is rejected. The founder and settings are saved together in a transaction. Recovery has been tested after the first table, partway through creation, and before/after committing the data.

After installation, an account with `SELECT`, `INSERT`, `UPDATE`, `DELETE` and `EXECUTE` on the application database can be used. Normal connections do not need table creation permissions. Triggers and views retain the definer that created them.

## SQLite

```json
{"database":{"driver":"sqlite","path":"/srv/rog-private/rog.sqlite","createIfMissing":true}}
```

On Windows, for example: `D:/rog-private/rog.sqlite`. The path must be absolute, end in `.sqlite`, `.sqlite3` or `.db`, be outside the public directory and have an existing writable parent directory. Protected API directories and symbolic links for the file are also rejected. No host, port, username or password is needed.

The connection test never creates the target file. If it is missing and creation is allowed, only installation creates it. Normal connections reject a missing file. The structure and founder are created within a transaction. SQLite serializes writes with `BEGIN IMMEDIATE` and a 15-second timeout; it suits small installations, but its write concurrency differs from PostgreSQL/MySQL servers.

The application registers SQLite functions for Unicode, JSON and dates on each connection. Use `Db` and the project's PHP commands to write data or create the schema; a generic SQLite client does not include these functions.

## Direct configuration and shared behavior

Examples of `ROG_DSN`:

```text
pgsql:host=127.0.0.1;port=5432;dbname=rog_addons
mysql:host=127.0.0.1;port=3306;dbname=rog_addons;charset=utf8mb4
sqlite:/srv/rog-private/rog.sqlite
```

`php bin/schema.php create` creates only an empty structure. For MySQL installations that must recover from interruptions, use the web installer and retain its private journal. `php bin/schema.php export mysql` (or `pgsql`, `sqlite`) lets you review generated SQL without a connection. Exported SQLite SQL needs the functions registered by the application.

Dates are stored in UTC. Statistical intervals respect the time zone and daylight saving changes. JSON preserves the distinction between missing properties and null values. Binary keys and lists are converted when reading/writing models. Requests retain their display names and use Unicode normalization to prevent duplicates; MySQL stores a technical key calculated by `Db` because it lacks native NFKC normalization.

PostgreSQL maintains the catalog's materialized view, which `bin/worker.php` refreshes
after processing pending messages. MySQL and SQLite use a regular view: totals are
calculated when queried.

There are no migrations or automatic synchronization. Models define the structure of new
installations. Each engine retains its own data; changing the DSN neither converts nor
transfers it.

## Verification

Tests create disposable targets, exercise the single HTTP format and check installation, recovery, models, Unicode, JSON, files, publication, moderation and concurrent access. They do not connect to production or send external email. Per-engine results are saved in the results/ folder of the temporary directory printed by the runner. Instructions are in [API tests](tests.en.md). These are not large-scale load tests or validation of a remote provider's TLS.
