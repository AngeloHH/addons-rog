# Running API tests

From api/, with PHP 8.2+:

    php tests/run.php sqlite
    php tests/run.php mysql
    php tests/run.php pgsql

Without an argument, PostgreSQL is used. API runners do not need Node.js or npm.
Frontend tests, in scripts/tests/, still use JavaScript tooling.

## Preparation

PHP must have pdo_sqlite, pdo_mysql or pdo_pgsql, depending on the selected engine, plus
intl, zip, mbstring, openssl and fileinfo. It must be able to create processes with proc_open
and open local ports.

MySQL requires MySQL 8.4+ binaries and PostgreSQL requires PostgreSQL 15+ binaries.
PowerShell example:

    $env:MYSQL_BIN = 'C:\ruta\mysql\bin'
    $env:PG_BIN = 'C:\ruta\postgresql\bin'
    php tests/run.php mysql
    php tests/run.php pgsql

On Linux/macOS, for example:

    PG_BIN=/ruta/postgresql/bin php tests/run.php pgsql
    MYSQL_BIN=/ruta/mysql/bin php tests/run.php mysql

PostgreSQL must run as a regular user: initdb does not accept root.

By default, child processes use the same PHP executable. PHP_BIN selects another one,
and PHP_EXTENSION_DIR explicitly loads extensions from a directory.
Without that variable, extensions must be enabled in the normal php.ini.

The run rejects config/config.local.php. Use a clean copy of the API so that local
configuration cannot override the test target. Individual tests require
ROG_TEST_MODE=disposable and user rog_test; the runner sets both conditions.

## What runs

run.php is the entry point and support/TestRunner.php prepares and closes processes. Each run creates
a new temporary directory, free ports and random credentials. SQLite uses a new file;
MySQL and PostgreSQL use private servers restricted to 127.0.0.1.

Checks cover the initial schema, web installation from a private copy, recovery from
interruptions, models, HTTP routes, validation, permissions, CSRF,
Unicode, JSON, files and publication. Four concurrent scenarios are also tested:
edits, votes, downloads and reviews. Cleanup and the mail worker run at the end.

Servers stop when the run ends, even if a check fails, and temporary files used to
initialize credentials are removed. Logs and stopped databases remain in the directory
printed at the end. ROG_TEST_WORKDIR selects the parent directory;
each run creates its own subdirectory.

Deliberate constraint and installation errors are part of the tests. In this suite, email is
written to a local spool: no external messages are sent. Its scope covers functionality and
concurrency; it does not measure a hosting environment's maximum capacity.

## SMTP verification with Ethereal

On October 17, 2026, an additional check used a temporary Ethereal account and an isolated
API copy. The existing PHP Mailer sent five messages over SMTP with STARTTLS and certificate
verification; messages were downloaded from the web inbox over HTTPS. The received code
activated an account through the API. Checks also covered the approval notification,
contact delivery to a founder and a moderator, and intact accents and symbols in subjects
and message content.

An incorrect SMTP password left the message queued with one failed attempt. After correcting
it and advancing availability only in the isolated database, the retry was delivered.
A subsequent worker run did not resend completed messages. The development installation
remained unchanged. Credentials and captured messages stayed in the private check directory,
excluded from Git and deployment.

Ethereal captures messages: this check verifies SMTP transport and API flows, but does not
certify delivery to real inboxes or the production provider configuration.

## Support files and results

The structure separates API areas from test infrastructure:

    tests/
      run.php                     Entry point for SQLite, MySQL and PostgreSQL
      http/
        run.php                   HTTP scenario order
        authentication/           Registration, sessions, permissions, CSRF and invitations
        catalog/                  Public listings and taxonomy
        submissions/              Drafts, publication, versions and rejection
        comments/                 Creating and editing reviews
        downloads/                Signed downloads and packs
        requests/                 Request votes, following and moderation
        reports/                  Warnings, reopening and takedown
        profiles/                 Profile following and privacy
        contact/                  Messages and attachments
        admin/                    Accounts, dashboard and statistics
        files/                    ZIP and image validation
        routing/                  Routes, methods and compatibility
        response-formats.php      Response types and structure
      database/                   Schemas, models and SQL portability
      installation/               Web installer and failure recovery
      concurrency/                Concurrent edits, votes, downloads and reviews
      notifications/              Email delivery through the worker
      validation/                 Dates and passwords
      fixtures/                   Frontend data and setup
      support/                    Runner, HTTP client, assertions and workers

Scenarios have names such as publish-addon.php, signed-files-and-packs.php and
roles-and-csrf.php. http/run.php declares execution order: they share a disposable
database and some use users or addons created by earlier scenarios.
Run them through run.php rather than opening each file separately.

Output identifies each scenario and how many checks it passed. Concurrency and startup
workers run as independent PHP processes.
fixtures/frontend.php prepares frontend test data.

fixtures/routes.json contains the expected route inventory and
fixtures/response-schemas.json contains response types. Reader documentation
is in the [API reference](reference.en.md).

JSON reports are saved in the results/ folder of each run's temporary directory.
The runner prints its path when it finishes. They include test-results-<engine>.json,
installer-results-<engine>.json, concurrency-results-<engine>.json and
initial-schema-results-<engine>.json. Reports are not written to content/ or source code.
