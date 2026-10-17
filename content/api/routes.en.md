# Unified API

The application exposes a single API format. The chosen routes were retained: `/api/auth/session`, `/api/profiles/{username}` and the `/api/submissions`, `/api/admin/submissions`, `/api/uploads`, `/api/media` and `/api/downloads` families.

## Responses and requests

- JSON success: `{"ok": true, ...data}`. Error: `{"ok": false, "error": {"code": "...", "message": "...", "requestId": "..."}}`.
- Entity IDs are numbers; temporary ZIP files and public file links use UUIDs.
- Listings return `items` and `page` with `number`, `size`, `total` and `totalPages`.
- Obtain the session and CSRF token through `GET /api/auth/session`. Writes keep cookies and send `X-CSRF-Token`; the browser supplies `Origin`.
- Installation uses its own temporary key.
- Routes do not accept the `.php` suffix.

## Reviews and taxonomy

`PUT /api/addons/{slug}/my-comment` accepts `content` (1–4000 characters), `rating` (1–5) and `expansionId` with a published version of the addon. It returns 201 on creation and 200 on update. It reuses the same user's review for the addon, even with simultaneous requests. `GET /api/addons/{slug}/comments` lists current reviews; the API does not create unrated comments.

`GET /api/taxonomy` combines `expansions`, the `category`, `classes`, `professions`, `role` groups and upload limits in `limits`: `summaryMax`, `screenshotsMax`, `addonZipMaxBytes` and `imageMaxBytes`.

Drafts use the fields from `/api/submissions`: `uploadId`, `name`, `version`, `summary`, `description`, etc. `revision` detects outdated edits. The `rulesAccepted` and `testedConfirmed` confirmations must be saved before submitting for review.

Downloads from `/api/downloads/{versionId}` redirect to the signed link at `/api/download-files/{versionId}`. Files retain their access and expiry controls.

## Additional routes

Operations without an alternative were retained. The main profile therefore uses `/api/profiles/{username}`, while its listings and following remain at `/api/users/{username}/addons`, `/journal`, `/reviews` and `/follow`.

This unification changes no tables and deletes no data. The full field specification is in the [API reference](reference.en.md).

## Complete inventory

| Method | Route | Access |
|---|---|---|
| GET | `/api/about` | public |
| GET | `/api/addons` | public |
| GET | `/api/addons/check-name` | user |
| GET | `/api/addons/{slug}` | public |
| GET | `/api/addons/{slug}/comments` | public |
| PUT | `/api/addons/{slug}/favorite` | user |
| PUT | `/api/addons/{slug}/my-comment` | user |
| GET | `/api/admin/accounts` | staff |
| PATCH | `/api/admin/accounts/{accountId}` | staff |
| POST | `/api/admin/accounts/{accountId}/message` | staff |
| GET | `/api/admin/activity` | staff |
| GET | `/api/admin/activity/export` | staff |
| GET | `/api/admin/counts` | staff |
| GET | `/api/admin/dashboard` | staff |
| POST | `/api/admin/invitations` | founder |
| GET | `/api/admin/live` | staff |
| GET | `/api/admin/reports` | staff |
| POST | `/api/admin/reports/{reportId}/action` | staff |
| POST | `/api/admin/requests/{requestId}/action` | staff |
| POST | `/api/admin/requests/{requestId}/notify-authors` | staff |
| GET | `/api/admin/stats` | staff |
| GET | `/api/admin/submissions` | staff |
| GET | `/api/admin/submissions/{submissionId}` | staff |
| POST | `/api/admin/submissions/{submissionId}/decision` | staff |
| GET | `/api/admin/submissions/{submissionId}/file` | staff |
| POST | `/api/auth/invitations/accept` | user |
| POST | `/api/auth/login` | public |
| POST | `/api/auth/logout` | public |
| POST | `/api/auth/register` | public |
| GET | `/api/auth/session` | public |
| POST | `/api/auth/verify` | public |
| POST | `/api/auth/verify/resend` | public |
| GET | `/api/contact` | public |
| POST | `/api/contact` | public |
| GET | `/api/default-icon.svg` | public |
| GET | `/api/download-files/{versionId}` | public |
| GET | `/api/downloads/{versionId}` | public |
| GET | `/api/files/{fileId}` | public |
| GET | `/api/health` | public |
| GET | `/api/home` | public |
| POST | `/api/media` | uploader |
| GET | `/api/packs/{packId}/download` | public |
| GET | `/api/profiles/{username}` | public |
| POST | `/api/reports` | user |
| GET | `/api/requests` | public |
| POST | `/api/requests` | user |
| PUT | `/api/requests/{requestId}/follow` | user |
| PUT | `/api/requests/{requestId}/vote` | user |
| POST | `/api/setup/database` | setup |
| POST | `/api/setup/install` | setup |
| GET | `/api/setup/requirements` | setup |
| GET | `/api/setup/status` | setup |
| GET | `/api/site/stats` | public |
| GET | `/api/submissions` | user |
| POST | `/api/submissions` | uploader |
| GET | `/api/submissions/{submissionId}` | user |
| PATCH | `/api/submissions/{submissionId}` | uploader |
| POST | `/api/submissions/{submissionId}/submit` | uploader |
| GET | `/api/taxonomy` | public |
| POST | `/api/uploads` | uploader |
| GET | `/api/users/{username}/addons` | public |
| PUT | `/api/users/{username}/follow` | user |
| GET | `/api/users/{username}/journal` | public |
| GET | `/api/users/{username}/reviews` | public |
