# Compatibility and persistence decisions

The API uses one representation and one set of routes. The current specification is the [API reference](reference.en.md); selected routes are described in [canonical routes](routes.en.md).

| Topic | Implementation |
|---|---|
| Envelope | `ok: true` with data in top-level fields; `ok: false` with `error` for failures. Errors include a request identifier. |
| Authorization | `access_level SMALLINT`: 0 regular, 1 moderator, 2 founder. `author` is derived from publications and grants no staff permissions. Changing roles, banning or modifying another staff account requires the corresponding permissions. |
| CSRF | Session cookie plus token and Origin on all writes, including login and registration. `/auth/session` provides the anonymous token. |
| Drafts | Expire after 30 days. Linked files inherit that lifetime; orphaned files last 24 hours. |
| Version | Global name reserved only on submission. The addon/expansion/version combination is unique among pending and approved submissions. The slug does not change. |
| Editing | Drafts accept `revision` to reject an outdated copy of the form. The model layer checks row versions with `xmin` on PostgreSQL and `_row_version` on MySQL and SQLite. |
| Comments | PUT `/addons/{slug}/my-comment` creates or updates the user's review with a required rating. Listings use current ratings. If an older review does not specify an expansion, the profile's expansion or the latest published one is used. |
| Downloads | GET `/downloads/{versionId}` issues a signed link valid for 5 minutes. Deduplicated by version and IP HMAC over a rolling 24-hour period. Records a delivery issued, not bytes fully received by the user. |
| Packs | Explicit members per expansion, latest available version, cached by exact IDs. A path collision within ZIP files prevents building the pack. Addons for a role are not selected arbitrarily. |
| Requests | Voting/following are idempotent desired states. Votes freeze in final states. State changes and notifications share a transaction. Links require a compatible submission or addon; an arbitrary state without its cause is not accepted. |
| Reports | One open case per type and target, one entry per reporter. Takedown affects the target; reopening does not restore it. A later scan of published content can quarantine it and open an automatic report. ZIP files blocked before submission stay private and prevent submission. |
| Invitations | Email is verified before granting level 1. POST `/auth/invitations/accept` is provided for an already verified account: the invitation link alone does not change permissions. |
| Messages | An external message remains `queued` until the worker hands it to the transport. `sendEmailCopy=false` saves the staff message; the API provides no public reading inbox. |
| Contact | The ticket is always saved. Configurable mailboxes; one PNG/JPEG attachment up to 2 MiB, stored as a private file. |
| IDs and unknown values | Numeric entity IDs and UUIDs for temporary ZIP files and public file links. Neutral icon when no image exists. Geolocation, license, original author, donations and achievements are not invented. |
| Editorial configuration | Configurable taxonomy and reference names. Client patches are reference data, not a query for updated versions. Features, languages, licenses and achievements can be loaded from models/configuration; they have no editing endpoint. |

## Calculated data

Downloads, ratings, votes, followers, compatibility, public role, effective account state, counters and series are calculated from persisted facts. `catalog_metrics` is a calculated copy that avoids scanning all history for every listing. It does not replace the source tables.

Presence records events at most once per minute and counts users seen in the last five minutes as online. Administrative series group counted downloads; migration compares consecutive expansions for identified accounts. City is nullable because no geolocation provider has been integrated.

Formulas not defined in the specification are explicit in code: points = 100 per published version + 5 per comment; level = 1 + integer points/100; reputation based on followers. `calculationRules` adjusts points and action weights for threat. Achievement titles and dates are kept in the profile; definitions are loaded in settings. Without definitions, empty lists are returned.

Records are calculated when enough facts exist: fastest review, largest ZIP, consecutive days with downloads, number of active days, latest download before 06:00 and highest download growth between two 30-day periods. A record without data does not appear. These rules are initial product decisions, not formulas inferred from the frontend presentation.

Request `replies/replyCount` and review `helpful` values are 0: the API defines no writes
for those features and does not simulate activity.

## JSONB and editing

Users edit fields allowed by each service: for example, a draft's description, tags and confirmations. These end up in `submissions.fields`. Approval copies the published listing to `addons.details`. No endpoint allows freely replacing an entire JSONB value or setting `decision`, `access_level`, scanner results or audit data from the browser.

JSONB suits file metadata, variable listing fields, event snapshots, small configuration and encrypted email payloads. Ownership, permissions, state, expiry, version, report targets and file links use columns and foreign keys because they require integrity and transactional queries.

Sessions, codes, invitations, drafts, reports, votes, followers, comments, queues and events require persistence. A frontend configuration JSON file or cache does not cover those lifecycles. Binary files are stored outside the database and public directory.

## Validation and queries

Validation uses PHP with real PostgreSQL, MySQL and SQLite databases in disposable local
environments. SMTP checks with Ethereal verify the transport and API email workflows;
their scope is described in [mail tests](tests.en.md). The scanner checks ZIP structures,
executable extensions, sizes and paths. Moderators approve content; the scanner is not
antivirus software.

Queries use parameters and model identifiers are validated. The custom layer has no general
query language, automatic relationship loading or automatic destructive schema updates.
Normal connections use the existing structure.

## Initialization from models

Models are the source of tables and columns. Setup and `php bin/schema.php create` create the initial structure on an empty schema. PostgreSQL and SQLite create the schema in a transaction; MySQL keeps verifiable progress because its DDL commits each statement. The database named in the DSN can be created only during this explicit operation, if allowed and the account has the required permissions.

`Db` only connects during normal operation. It does not check model versions, add columns or change existing tables. The web account can use limited permissions after installation.

No migration history or snapshots are maintained. Recovery from an interrupted installation remains part of setup: it uses its journal and the installation marker in settings to finish without duplicating the founder.
