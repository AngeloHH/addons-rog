# Frontend integration with the API

The screens use the single API under `/api`. The browser does not know which database engine is running: SQLite, MySQL and PostgreSQL remain the backend's responsibility.

Contact shows individual founders, the shared Moderators card and explicitly configured mailboxes.
The shared card is available with a founder alone and notifies all verified, non-banned moderators and founders.
The selected ID comes from `GET /api/contact`; names and delivery addresses are resolved by the server.
Email addresses are not included in the public recipient list. With no available recipients, sending is disabled.

## Connected features

| Screen | Data and actions |
| --- | --- |
| Sessions and accounts | Login, logout, registration, verification, email resend and changes, invitation acceptance. |
| Home and catalog | Addons, categories, expansions, searches, filters, sorting, pagination and pack downloads. |
| Addon details | Description, screenshots, versions, downloads, favorites, reviews and reports. |
| Uploads | ZIP uploads and server scanning, images, taxonomy, saving and reopening drafts, new versions and submission for review. |
| Requests | Creation, votes, following, states, pagination and links to addons or submissions. |
| Profiles | Statistics, addons, achievements, activity, reviews and following. |
| Contact | Real recipients and queue, sending with an attachment and ticket confirmation. |
| Administration | Counters, review and decisions, reports, requests, accounts, messages, invitations, activity, statistics and CSV. |
| 404 page | Suggestions from the published catalog. |

`InstallationGate` checks `/api/setup/status` before mounting the session and pages. If `installed` is `false`, it redirects to `/{language}/setup`. Network failures allow a retry; they are not treated as an installation awaiting completion. The status is checked again when leaving the installer.

`src/api/client.js` centralizes sessions, CSRF, JSON, files and errors. It does not automatically repeat write operations. `SessionContext` maintains the shared identity; `useApi` cancels stale reads and separates data when the user changes. Empty states and errors are visible, and reads allow retries.

Editorial text, colors, illustrations and achievement definitions remain in the frontend.
Activity lists and figures come from the server. Accounts use the website's own registration
and login; password assistance is handled through contact.

## Verification

```sh
npm run test:client
npm run test:build
npm run test:installation
npm run test:frontend
npm run build
```

Client and package tests use Node and do not need a server. `npm install` or `npm run setup`
installs the Playwright library and downloads Chromium as development dependencies. No external
package directory or installed Edge is needed. Browser tests only access their local servers.
The full test requires PHP 8.2+ with `pdo_sqlite`, `intl`, `zip`, `mbstring`, `openssl` and
`fileinfo`. It uses the same PHP discovery, local extensions and upload limits as development.
`PHP_BIN` selects another PHP executable; `npm run setup:check` checks its requirements.

Backend rules and responses are checked in `api/tests/`. Browser tests check forms, controls, navigation and visible results; direct API calls only prepare supporting fixtures. Draft field and four-screenshot persistence are covered in `http/submissions/draft-fields.php`; secondary category filters and counts are covered in `http/catalog/secondary-categories.php`.

The full test copies API runtime files and its fixture into a temporary directory, excluding
local configuration, installation markers, stored files and databases. It can run while the
website is installed. It starts PHP and Vite on temporary ports, creates an isolated SQLite
schema and performs real actions through Chromium. Project mail and database environment
settings are excluded from the test configuration. Files, accounts and queued emails belong
only to the temporary environment; no mail worker runs. The temporary servers stop when the
test ends. Screenshots and logs remain in the temporary directory printed by the test.

The frontend-test-results.json report is saved in the run's temporary directory alongside its logs. This browser suite verifies SQLite; it does not claim to have repeated these browser flows on MySQL or PostgreSQL. Services for those engines retain their existing independent tests.

The build generates the frontend and copies the API to `dist/api`. Vite may warn about the size of the main JavaScript bundle; the warning does not prevent compilation.

## Synthetic addons for manual tests

```sh
npm run fixtures:addons
npm run fixtures:addons -- .test-data/another-generation 20261017
```

The PHP generator creates 30 distinct addons and 90 ZIPs: six addons with each version count,
from one to five. It distributes addons across the seven supported clients and catalog categories.
Each ZIP contains a stable addon folder, its TOC, valid Lua files and reproducible random text
using the selected seed. Every archive passes the scanner before generation finishes.
These are synthetic website fixtures, not real gameplay addons.

Each addon includes a 256 × 256 icon and four 640 × 360 screenshots for the upload form.
`manifest.json` lists names, categories, clients, versions, paths and SHA-256 hashes.
The output directory must be new: the command neither overwrites existing files nor changes
the database. Without arguments, it creates a timestamped directory inside `.test-data/`.

`.test-data/` is excluded from Git and the deployment package. The generator stays in
`api/tests/fixtures/generate-addons.php`, which is also excluded from production builds.
