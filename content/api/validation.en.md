# API validation

Tests use disposable databases, local servers and temporary credentials.
Instructions and scenario organization are described in [API tests](tests.en.md).

## Scope

- Initial installation, requirements, authorization, locking and failure recovery.
- Canonical routes, sessions, CSRF, permissions and response types.
- Models, JSON, Unicode and queries on PostgreSQL, MySQL and SQLite.
- Multipart uploads, addon and version publication, moderation and files.
- Requests, reviews, profiles, contact and statistics.
- Concurrent edits, votes, downloads and reviews.
- Cleanup, metrics and email delivery to local files.

Additional SMTP checks with Ethereal are described in [mail tests](tests.en.md).

The expected route inventory is in [routes.json](../../api/tests/fixtures/routes.json).
Scenarios are identified by their API area and name during execution.

## Reports

Each run generates JSON reports in results/ inside its temporary directory.
The runner prints the location when it finishes. Reports belong to that run;
historical results are not kept in the documentation or source code.

Frontend tests save frontend-test-results.json in their own temporary directory.

## Scope of results

Results describe the functional and concurrent scenarios run in disposable local
environments; they do not establish a maximum number of simultaneous users. SMTP checks
verify messages captured by Ethereal. ZIP scanning checks structure and dangerous
extensions; it does not replace antivirus software.
