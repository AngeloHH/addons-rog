# Project setup and development

React + Vite frontend with a lightweight PHP API. Development does not require Apache, XAMPP, Nginx, or Docker.

## Requirements

- Node.js 20.19+ or 22.12+ with npm, as required by Vite

## Setup

```sh
npm run setup
```

On Windows PowerShell, use `npm.cmd run setup` if the execution policy blocks `npm.ps1`.

The Node setup checks PHP 8.2+, enables bundled PHP extensions locally when possible, installs missing PHP packages with the available system package manager, and installs npm dependencies. Development dependencies include Playwright and an automatic Chromium download for browser tests. It does not change the global `php.ini`. The development PHP server uses the same extension settings and allows 50 MiB uploads and 60 MiB POST bodies. Run `npm run setup:check` to verify PHP without installing anything.

Package managers may request administrator access. If your platform has no supported package manager or cannot supply a required PHP extension, setup reports the missing requirement so it can be installed manually.
If you have multiple PHP installations, set `PHP_BIN` to the executable you want the project to use.

## Development

```sh
npm run dev
```

Local URLs:

```txt
React/Vite: http://127.0.0.1:5173/en
PHP API:    http://127.0.0.1:8000
```

The local PHP server serves only `api/public/`. Email HTML sources live privately in `api/templates/emails/` and are copied with the API during builds.

Development defaults `ROG_ORIGIN` to `http://127.0.0.1:5173` for the PHP process. An explicitly set value takes precedence. Open that exact address (not `localhost`); Vite keeps port 5173 fixed so the installer and API agree on the origin.

Opening `http://127.0.0.1:5173` redirects the app URL to `/en` by default. Changing the language updates the first URL segment, for example `/es`, `/pt`, or `/de`.

The API supports PostgreSQL 15+, MySQL 8.4+ and SQLite 3.38+. The installer creates the initial structure from the same models; normal connections never create or update tables. See [database configuration](../api/databases.en.md).

The site installer is available in React at `http://127.0.0.1:5173/en/setup` (or `/:lang/setup`). It uses the existing `/api/setup` endpoints. Generate a temporary installation key on the server as described in [the installer guide](../api/installation.en.md); the frontend does not store it. The separate `/:lang/install` route remains the addon installation guide.

## Build

```sh
npm run build
```

The production output is created in `dist/`. The PHP API is copied to `dist/api/`.

`public/.htaccess` is copied into `dist/` so Apache/XAMPP can refresh language URLs such as `/en` or `/es` without returning a 404.

## Preview and command reference

After building, `npm run preview` serves the compiled frontend together with the local PHP API
at the same addresses used by development. Stop `npm run dev` before starting preview: both use
ports 5173 and 8000 so the configured origin remains valid. Both commands stop their companion
process if either server exits. Preview uses the source installation's API configuration;
the clean API under `dist/api/` is intended for deployment and installer checks.

| Command | Purpose |
| --- | --- |
| `setup` | Prepare PHP, npm dependencies and the browser used by tests. |
| `setup:check` | Check PHP without installing anything. |
| `dev` | Start Vite and the PHP API. |
| `build` | Compile the frontend and package a clean API in `dist/`. |
| `preview` | Run the compiled frontend with the local PHP API. |
| `fixtures:addons` | Generate synthetic addon files without changing the database. |
| `test:client` | Check the frontend API client and shared data helpers. |
| `test:build` | Check deployment contents and private-file exclusion. |
| `test:installation` | Check installer routing with simulated API responses. |
| `test:frontend` | Check browser workflows against a temporary API and SQLite database. |

Run commands with `npm run`, for example `npm run test:frontend`.
See [frontend verification](frontend-integration.en.md) for test requirements and artifacts.
