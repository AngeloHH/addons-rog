# API reference

Documentation of routes, fields and rules for the Addons RoG API. Implementation is in the PHP services; this reference is maintained alongside code changes.

Test data is in the [route inventory](../../api/tests/fixtures/routes.json) and [response types](../../api/tests/fixtures/response-schemas.json). Tests do not read this documentation.

## General information

- API: Addons RoG, version 3.0.0.
- Route prefix: `/api`.
- Routes do not include `.php`. The PHP backend uses a front controller.
- In development, the Vite proxy removes the `/api` prefix before reaching PHP; the router accepts both forms.
- All JSON responses use a single format with the `ok` field.

## Conventions

| Field | Definition |
|---|---|
| typeNotation.string \| integer \| number \| boolean | JSON types. integer does not accept decimals. |
| typeNotation.string&lt;date-time&gt; | ISO 8601 in UTC with Z, e.g. 2026-09-21T14:32:00Z. |
| typeNotation.string&lt;date&gt; | YYYY-MM-DD. |
| typeNotation.string&lt;url&gt; | Absolute URL or path starting with /. |
| typeNotation.'a' \| 'b' | Closed enumeration: any other value returns 422. |
| typeNotation.T \| null | The key is always present; its value can be null. |
| typeNotation.key? | The key is optional: it may be omitted from requests or responses. |
| typeNotation.T[] | Array of T. Size limits appear in parentheses. |
| typeNotation.#Name | Reference to a schema in 'schemas'. |
| typeNotation.(a..b) | Inclusive length (text), size (array) or range (numbers). |
| typeNotation.default | Value the server uses when the parameter is absent. |
| naming | camelCase for keys and enumeration values. Catalog IDs (expansions, categories, rules, achievements) are lowercase slugs; entity IDs (users, addons, versions, requests...) are integer. |
| numbers | Figures are sent as raw values: downloads as integer, sizes in bytes, durations in hours or seconds, percentages 0..100. The frontend handles formatting (12.4k, 4.8 MB, '3 days ago'). |
| presentation | The API sends neither expansion, severity, state or group colors nor translatable interface text: it returns IDs and the frontend resolves them through src/config and i18n. The only exception is the user's avatar color, which is account data. |
| requests.json | JSON bodies use Content-Type: application/json; charset=utf-8. |
| requests.multipart | File uploads use multipart/form-data, indicated in request.contentType. |
| requests.unknownFields | Unknown body fields are ignored. A query parameter with a value outside its enumeration returns 422. |
| success.shape | { "ok": true, ...endpoint fields } |
| success.statusCodes.200 | Successful read or modification. |
| success.statusCodes.201 | Resource created. The body includes the resource. |
| success.binary | Downloads return 302 with Location pointing to the file, or 200 with binary content; both include Content-Disposition: attachment; filename="...". |
| errors.shape | #Error |
| errors.statusCodes.400 | BAD_REQUEST: malformed JSON or unreadable multipart. |
| errors.statusCodes.401 | UNAUTHENTICATED: session required. |
| errors.statusCodes.403 | FORBIDDEN, EMAIL_NOT_VERIFIED, ACCOUNT_SUSPENDED or ACCOUNT_BANNED. |
| errors.statusCodes.404 | NOT_FOUND: the resource does not exist or the user cannot view it (other users' private resources are not disclosed). |
| errors.statusCodes.409 | State conflict: NAME_TAKEN, VERSION_EXISTS, INVALID_STATE, REVISION_MISMATCH, ALREADY_REPORTED. |
| errors.statusCodes.410 | GONE: the temporary resource expired (UPLOAD_EXPIRED, MEDIA_EXPIRED, VERIFICATION_EXPIRED). |
| errors.statusCodes.413 | FILE_TOO_LARGE. |
| errors.statusCodes.415 | UNSUPPORTED_MEDIA_TYPE. |
| errors.statusCodes.422 | VALIDATION_FAILED: error.fields lists invalid fields. |
| errors.statusCodes.429 | RATE_LIMITED: the Retry-After header and error.retryAfterSeconds indicate when to retry. |
| errors.statusCodes.500 | INTERNAL. |
| pagination.pageBased | Query page (integer &gt;= 1, default 1) and pageSize (integer, per-endpoint limits). The response includes items and page: #Page. A page above totalPages returns empty items, not an error. |
| pagination.cursorBased | Only for continually growing admin activity: query before (opaque cursor) and limit. The response includes items and nextCursor (null at the end). |
| idempotency | State PUTs (vote, follow, favorite) accept the desired state and are idempotent: repeating the same request returns 200 with the same result and does not change counters. Resource-creating POSTs are not idempotent; the frontend must disable the button while waiting. |
| i18n | The API does not translate. User-written text (descriptions, comments, requests) is returned as written. |

## Authentication and permissions

| Field | Definition |
|---|---|
| session.mechanism | Session cookie rog_session: HttpOnly, Secure in production, SameSite=Lax, Path=/. The body never returns tokens. |
| session.creation | Created by POST /api/auth/login and POST /api/auth/verify (verification starts a session). |
| session.lifetime | With remember=true, lasts 30 days and renews on use; with remember=false, lasts for the browser session and expires after 12 h of inactivity. |
| session.csrf | Requests that modify data keep the session cookie and send X-CSRF-Token. The server also checks Origin. GET /api/auth/session provides the token. |
| session.discovery | The frontend calls GET /api/auth/session on startup to identify the user and render AuthBar, SiteHeader, upload and dashboard. |
| roles.player | Verified account. Downloads, comments, rates, requests, votes, follows and saves favorites. |
| roles.author | Player with at least one published addon. The server assigns it automatically when approving the first submission; it is not assigned manually. |
| roles.moderator | Reviews the queue, manages reports, requests and the state of player and author accounts. |
| roles.founder | All moderator capabilities, plus changing roles, inviting moderators and banning. |
| accountStatus.active | No restrictions. |
| accountStatus.suspended | Until suspendedUntil, cannot upload files, save drafts or submit for review. Can log in, download, comment, request and vote (rules, Suspension level). |
| accountStatus.banned | Cannot log in; login returns 403 ACCOUNT_BANNED and open sessions are invalidated. |
| levels.public | No session required. If present, viewer fields are populated. |
| levels.user | Session for a verified account that is not banned. |
| levels.uploader | Like user, but with active status (a suspended account receives 403 ACCOUNT_SUSPENDED). |
| levels.staff | moderator or founder role. |
| levels.founder | founder role. |
| ownership | Temporary files, media and submissions belong to their creator. Any attempt to use or read another user's private resources returns 404, except for staff on /api/admin routes. |

## Operating rules

| Field | Definition |
|---|---|
| addonExpansionVersion.addon | An addon is a single listing with an immutable slug, unique throughout the catalog. The slug is generated from the name when the first submission is approved and does not change even if the name changes. |
| addonExpansionVersion.nameUniqueness | The normalized name (lowercase, without spaces or punctuation) is unique across all published addons and pending addon submissions, in every expansion. The same addon for another expansion is not a new listing: it is a new version of the same listing ('duplicates' rule). |
| addonExpansionVersion.aliases | An addon can have aliases (e.g. 'dbm' for Deadly Boss Mods). Staff manage them outside this API; check-name also checks aliases. |
| addonExpansionVersion.version | Each version belongs to one addon and one expansion (declared by the .toc, 'version' rule). The (addon, expansion, version number) combination is unique. |
| addonExpansionVersion.compatibility | Addon compatibility is the set of expansions with at least one published version. Each expansion has a 'latest version': the one with the most recent releasedAt in that expansion. |
| addonExpansionVersion.canonicalExpansion | An addon's canonical expansion is the expansion of its most recently published version. Listings without an expansion filter and the canonical URL /:lang/explore/{expansionId}/{categoryId}/{slug} use it. |
| addonExpansionVersion.listingExpansion | Each addon appears once in listings. With an expansion filter, the item shows the filtered expansion (if several, the one with the most recent version among them) and that expansion's latest version. Without a filter, it shows the canonical expansion. |
| addonExpansionVersion.download | Every list item with a download button includes download (#Download) with the specific versionId. The frontend must not construct download links itself. |
| addonExpansionVersion.category | categoryIds allows several general categories; categoryId is the first and forms part of the URL. All selected categories feed the filters. Classes, professions and roles are stored separately (classIds, professionIds, roles). Older addons without categoryIds retain categoryId as their sole category. |
| requestLifecycle.states.open | Requested, with nobody working on it. |
| requestLifecycle.states.volunteered | Someone saved a draft linked to the request (requestId), or staff linked it. |
| requestLifecycle.states.inQueue | A linked submission is awaiting review. |
| requestLifecycle.states.fulfilled | Linked to a published addon (request.addon). Final state. |
| requestLifecycle.states.closed | Closed by staff without fulfillment (duplicate, impossible, against the rules). Final state. |
| requestLifecycle.transitions.0 | create -&gt; open (POST /api/requests; the author automatically votes and follows). |
| requestLifecycle.transitions.1 | open -&gt; volunteered: a user saves a draft with requestId, or staff performs action=link with a draft submission. |
| requestLifecycle.transitions.2 | open \| volunteered -&gt; inQueue: a submission with requestId is sent for review, or staff performs action=link with a pending submission. |
| requestLifecycle.transitions.3 | inQueue -&gt; fulfilled: the linked submission is approved; request.addon points to the published addon. |
| requestLifecycle.transitions.4 | open \| volunteered \| inQueue -&gt; fulfilled: staff performs action=link with addonSlug (the addon was already in the catalog). |
| requestLifecycle.transitions.5 | inQueue -&gt; volunteered \| open: the linked submission is rejected; returns to volunteered if another linked draft remains, otherwise open. |
| requestLifecycle.transitions.6 | volunteered -&gt; open: the last linked draft expires or is discarded. |
| requestLifecycle.transitions.7 | open \| volunteered -&gt; closed: staff performs action=close. |
| requestLifecycle.transitions.8 | fulfilled and closed have no outgoing transitions in this version. |
| requestLifecycle.permissions | Create, vote and follow: user. link, ping and close: staff. Users cannot change the state directly; changes come from submissions or staff actions. |
| requestLifecycle.voting | Voting is allowed only in open, volunteered and inQueue. Votes freeze in fulfilled and closed: PUT vote returns 409 INVALID_STATE and counters do not change. |
| requestLifecycle.following | Following is independent of voting and works in any state. Followers receive email when the state changes (the server sends the notice; it is not an endpoint). |
| requestLifecycle.listing | GET /api/requests excludes closed by default; state=closed returns them. stateCounts includes all five states. |
| submissionLifecycle.states.draft | Draft editable by its owner. Hidden from staff. |
| submissionLifecycle.states.pending | Submitted for review. Nobody can edit it; it appears in the queue. |
| submissionLifecycle.states.approved | Published: created an addon (kind=addon) or a new version (kind=version). |
| submissionLifecycle.states.rejected | Rejected with a reason and rule. Cannot be edited: create another draft to correct it. |
| submissionLifecycle.kinds.addon | New listing (addonSlug null). The name must be available. |
| submissionLifecycle.kinds.version | New version of an existing addon (addonSlug provided). Name, category and listing come from the addon; name is ignored. |
| submissionLifecycle.draftValidation | Saving a draft validates only the format of supplied fields (types, lengths, enumerations, existing IDs). No field is required and confirmations can be false. |
| submissionLifecycle.submitValidation | Submitting for review requires all fields marked 'required on submit' in #Submission, rulesAccepted=true, testedConfirmed=true, a ZIP without danger warnings, an available name (kind=addon) and a version not already present for that addon and expansion. Failure returns 422 with error.fields. |
| submissionLifecycle.expiry | A draft expires after 30 days without changes (expiresAt). On expiry, it and its temporary files are deleted. pending, approved and rejected submissions do not expire. |
| submissionLifecycle.concurrency | Each submission has revision (integer). PATCH can send revision; a mismatch returns 409 REVISION_MISMATCH and the client must reload the draft. |
| temporaryFiles.zip | POST /api/uploads creates a temporary file owned by the user. It expires after 24 h if no draft or submission references it; while referenced by a draft, it lasts as long as that draft. On approval, it becomes the published version's file. |
| temporaryFiles.media | POST /api/media creates a temporary icon or screenshot with the same expiry rules. It is linked to the submission through iconMediaId and screenshotMediaIds; unlinking it (PATCH) restores 24 h expiry. |
| temporaryFiles.ownership | Only the owner can link their files. Another user's or expired ID in a draft returns 422 for that field (MEDIA_EXPIRED or UPLOAD_EXPIRED as the field message). |
| temporaryFiles.scan | The ZIP is scanned on upload (folders, .toc, executables). Results are included in #UploadFile; the queue information staff see comes from this scan. |
| reportLifecycle.states | open -&gt; closed through warn, takedown or dismiss; closed -&gt; open through reopen. |
| reportLifecycle.severity | Assigned by the server according to type: file=critical, credit=high, comment=medium, listing=low. The scanner creates file reports with reporter.id null and name 'scanner'. |
| reportLifecycle.effects.warn | Closes the report and adds a warning to the uploader (increases threat). |
| reportLifecycle.effects.takedown | Closes the report and removes the version or addon from the catalog. |
| reportLifecycle.effects.dismiss | Closes the report without effects. |
| reportLifecycle.effects.reopen | Reopens a closed report. Does not undo a takedown. |
| reportLifecycle.threat | The server calculates threat (0..100) from account warnings, takedowns and suspensions. The frontend displays thresholds (warning 25, takedown 50, suspension 75, ban 100). |

## Frontend content

| Field | Definition |
|---|---|
| description | Content remaining in the frontend (src/config, i18n or content/) without an endpoint. Editorial text, translations or styles. |
| items.0 | Home hero slides (homeHero.js) and upload CTA text. |
| items.1 | Expansion, severity, state, role, activity group and slot quality colors. |
| items.2 | Category icons (/assets/categorias/*.png) and their mapping to category IDs. |
| items.3 | Rules, examples, threat levels and notices (rulesArticles, rulesConsequences) and the rules checker (rulesChecks). |
| items.4 | Markdown guides and documentation (content/guides/, content/development/ and content/api/) served by DocsPage. |
| items.5 | About timeline and founder profiles. |
| items.6 | Contact role labels, topics, placeholders and hints. Recipient identities come from the API. |
| items.7 | Achievement and title definitions (title, text, requirement): the API returns only ID, points and state. |
| items.8 | Profile equipment slot names, assigned by position. |
| items.9 | Upload reminders and limits (upload.js) that do not depend on the server. Actual limits are enforced by the API and appear in this contract. |
| items.10 | Footer (siteMeta), languages and popular searches (popularAddonSearches). |

## Data types

### Error

| Field | Definition |
|---|---|
| ok | false |
| error.code | #ErrorCode |
| error.message | string (readable English text for logs; the frontend translates by code) |
| error.fields? | Record&lt;string, string[]&gt; (only on 422: field -&gt; error codes, e.g. {"email": ["INVALID_EMAIL"]}) |
| error.retryAfterSeconds? | integer (only on 429) |
| error.details? | Record&lt;string, string&gt; (data to continue the flow; currently only login with EMAIL_NOT_VERIFIED: verificationId and maskedEmail) |
| error.requestId | string |

### ErrorCode

'BAD_REQUEST' | 'UNAUTHENTICATED' | 'FORBIDDEN' | 'EMAIL_NOT_VERIFIED' | 'ACCOUNT_SUSPENDED' | 'ACCOUNT_BANNED' | 'INVALID_CREDENTIALS' | 'INVALID_CODE' | 'NOT_FOUND' | 'NAME_TAKEN' | 'VERSION_EXISTS' | 'INVALID_STATE' | 'REVISION_MISMATCH' | 'ALREADY_REPORTED' | 'VERIFICATION_EXPIRED' | 'UPLOAD_EXPIRED' | 'MEDIA_EXPIRED' | 'FILE_TOO_LARGE' | 'UNSUPPORTED_MEDIA_TYPE' | 'VALIDATION_FAILED' | 'RATE_LIMITED' | 'INTERNAL' | 'DRAFT_EXPIRED'

### Page

| Field | Definition |
|---|---|
| number | integer (&gt;= 1) |
| size | integer (&gt;= 1) |
| total | integer (&gt;= 0) |
| totalPages | integer (&gt;= 0) |

### Role

'founder' | 'moderator' | 'author' | 'player'

### AccountStatus

'active' | 'suspended' | 'banned'

### ExpansionId

string (ID from GET /api/taxonomy expansions; currently 'wotlk' | 'mop' | 'wod' | 'bfa' | 'sl' | 'df' | 'tww')

### CategoryId

string (ID from the taxonomy 'category' group; currently 'pve' | 'pvp' | 'dps' | 'healing' | 'tank' | 'gui' | 'map' | 'professions')

### ClassId

string (ID from the 'classes' group; e.g. 'death-knight', 'druid')

### ProfessionId

string (ID from the 'professions' group; e.g. 'alchemy', 'herbalism')

### RoleTag

'tank' | 'healer' | 'dps'

### RuleId

'clean' | 'free' | 'credit' | 'version' | 'duplicates' | 'honest' | 'privacy'

### RequestState

'open' | 'volunteered' | 'inQueue' | 'fulfilled' | 'closed'

### SubmissionStatus

'draft' | 'pending' | 'approved' | 'rejected'

### Range

'7d' | '30d' | '90d'

### UserRef

| Field | Definition |
|---|---|
| id | integer |
| name | string |
| color | string (hex #rrggbb) |

### SessionUser

| Field | Definition |
|---|---|
| id | integer |
| name | string |
| email | string |
| color | string (hex #rrggbb) |
| role | #Role |
| status | #AccountStatus |
| suspendedUntil | string&lt;date-time&gt; \| null |
| expansionId | #ExpansionId \| null |

### Download

| Field | Definition |
|---|---|
| versionId | integer |
| url | string&lt;url&gt; (/api/downloads/{versionId}) |
| fileName | string |
| size | integer (bytes) |

### AddonSummary

| Field | Definition |
|---|---|
| id | integer |
| slug | string |
| name | string |
| iconUrl | string&lt;url&gt; |
| categoryId | #CategoryId |
| categoryIds | #CategoryId[] |
| expansionId | #ExpansionId (expansion for this listing; see domain.addonExpansionVersion.listingExpansion) |
| version | string (latest version in expansionId) |
| summary | string (1..120) |
| rating | number (0..5, one decimal) \| null (null without ratings) |
| votes | integer (number of ratings) |
| downloads | integer (addon total, all versions) |
| author | #UserRef (uploader) |
| createdAt | string&lt;date-time&gt; (listing publication) |
| updatedAt | string&lt;date-time&gt; (latest version in expansionId) |
| download | #Download (latest version in expansionId) |

### AddonUpdate

| Field | Definition |
|---|---|
| ... | all fields from #AddonSummary |
| previousVersion | string \| null (previous version in the same expansion) |
| changelog | string (0..280, latest version notes) |

### AddonVersion

| Field | Definition |
|---|---|
| id | integer |
| number | string |
| title | string |
| notes | string |
| expansionId | #ExpansionId |
| clientPatch | string (e.g. '8.3.7') |
| size | integer (bytes) |
| downloads | integer |
| author | #UserRef |
| releasedAt | string&lt;date-time&gt; |
| isLatest | boolean (most recent for the addon) |
| isLatestForExpansion | boolean |
| download | #Download |

### Compatibility

| Field | Definition |
|---|---|
| expansionId | #ExpansionId |
| latestVersion | string |
| latestVersionId | integer |
| releasedAt | string&lt;date-time&gt; |
| versionCount | integer |

### AddonDetail

| Field | Definition |
|---|---|
| ... | all fields from #AddonSummary |
| canonicalExpansionId | #ExpansionId |
| description | string (Markdown, 0..20000) |
| features | string[] (0..12) |
| screenshots | string&lt;url&gt;[] (0..4) |
| classIds | #ClassId[] |
| professionIds | #ProfessionId[] |
| roles | #RoleTag[] |
| tags | string[] (0..10) |
| languages | string[] (uppercase ISO 639-1 codes, e.g. 'EN') |
| licence | string \| null |
| originalAuthor | string \| null |
| sourceUrl | string&lt;url&gt; \| null |
| compatibility | #Compatibility[] (taxonomy order) |
| versions | #AddonVersion[] (all, by releasedAt descending) |
| commentCount | integer |
| viewer.isFavorite | boolean |
| viewer.rating | integer (1..5) \| null (current user rating) |

### Comment

| Field | Definition |
|---|---|
| id | integer |
| user.id | integer |
| user.name | string |
| user.color | string (hex #rrggbb) |
| user.expansionId | #ExpansionId \| null |
| rating | integer (1..5) |
| text | string |
| createdAt | string&lt;date-time&gt; |
| addonId | integer |
| content | string (1..4000) |
| expansionId | #ExpansionId |
| updatedAt | string&lt;date-time&gt; |
| ownedByViewer | boolean |

### Pack

| Field | Definition |
|---|---|
| id | #RoleTag |
| name | string |
| description | string |
| availability.0.expansionId | #ExpansionId |
| availability.0.addons.0.slug | string |
| availability.0.addons.0.name | string |
| availability.0.size | integer (pack ZIP bytes) |
| availability.0.downloadUrl | string&lt;url&gt; (/api/packs/{packId}/download?expansion={expansionId}) |

### Request

| Field | Definition |
|---|---|
| id | integer |
| addonName | string (2..60) |
| expansionId | #ExpansionId |
| text | string (10..500) |
| state | #RequestState |
| votes | integer |
| followers | integer |
| replies | integer (0; no reply operation exists) |
| author | #UserRef |
| addon | { "slug": "string", "name": "string", "expansionId": "#ExpansionId", "categoryId": "#CategoryId" } \| null (only in fulfilled) |
| submissionId | integer \| null (linked submission in volunteered and inQueue; visible only to staff and the submission owner) |
| createdAt | string&lt;date-time&gt; |
| updatedAt | string&lt;date-time&gt; |
| viewer | { "voted": "boolean", "following": "boolean" } \| null (null without a session) |

### UploadFile

| Field | Definition |
|---|---|
| id | string (uuid) |
| fileName | string |
| size | integer (bytes) |
| sha256 | string (hex) |
| folders | string[] (addon folders at the ZIP root) |
| tocInterfaces | integer[] (## Interface values from .toc files) |
| detectedExpansionId | #ExpansionId \| null (inferred from ## Interface) |
| status | 'clean' \| 'warning' \| 'blocked' (blocked if any danger warning exists) |
| warnings | #ScanWarning[] |
| expiresAt | string&lt;date-time&gt; \| null (null when referenced by a submission that does not expire) |

### ScanWarning

| Field | Definition |
|---|---|
| code | 'EXECUTABLE_FOUND' \| 'NESTED_FOLDER' \| 'MISSING_TOC' \| 'OUTDATED_TOC' \| 'SAVED_VARIABLES' \| 'INTERFACE_MISMATCH' |
| severity | 'warning' \| 'danger' (only EXECUTABLE_FOUND is danger) |
| path | string \| null (path inside the ZIP) |
| message | string |

### Media

| Field | Definition |
|---|---|
| id | integer |
| kind | 'icon' \| 'screenshot' |
| url | string&lt;url&gt; |
| width | integer |
| height | integer |
| size | integer (bytes) |
| expiresAt | string&lt;date-time&gt; \| null |

### Submission

| Field | Definition |
|---|---|
| id | integer |
| status | #SubmissionStatus |
| kind | 'addon' \| 'version' (inferred from addonSlug) |
| revision | integer |
| addonSlug | string \| null (required on submit if kind=version) |
| requestId | integer \| null |
| upload | #UploadFile \| null (required on submit) |
| name | string (3..60) \| null (required on submit if kind=addon) |
| version | string (1..20, ^[0-9A-Za-z._-]+$) \| null (required on submit) |
| categoryId | #CategoryId \| null (required on submit if kind=addon) |
| categoryIds | #CategoryId[] (0..8; at least one on submit if kind=addon) |
| expansionId | #ExpansionId \| null (required on submit) |
| classIds | #ClassId[] (0..13) |
| professionIds | #ProfessionId[] (0..15) |
| roles | #RoleTag[] (0..3) |
| summary | string (1..120) \| null (required on submit if kind=addon) |
| description | string (Markdown, 0..20000) \| null |
| tags | string[] (0..10, each 2..24, lowercase) |
| originalAuthor | string (1..60) \| null |
| sourceUrl | string&lt;url&gt; (http/https, 1..300) \| null |
| icon | #Media \| null |
| screenshots | #Media[] (0..4) |
| rulesAccepted | boolean (true on submit) |
| testedConfirmed | boolean (true on submit) |
| createdAt | string&lt;date-time&gt; |
| updatedAt | string&lt;date-time&gt; |
| submittedAt | string&lt;date-time&gt; \| null |
| expiresAt | string&lt;date-time&gt; \| null (drafts only) |
| decision | { "status": "'approved' \| 'rejected'", "ruleId": "#RuleId \| null", "reason": "string \| null", "decidedAt": "string&lt;date-time&gt;", "addonSlug": "string \| null" } \| null |
| estimatedReviewHours | integer \| null (only in pending) |

### QueueItem

| Field | Definition |
|---|---|
| id | integer (submission ID) |
| kind | 'addon' \| 'version' |
| name | string |
| version | string |
| expansionId | #ExpansionId |
| iconUrl | string&lt;url&gt; \| null |
| author.id | integer |
| author.name | string |
| author.color | string |
| author.approvedUploads | integer |
| size | integer (bytes) |
| folders | integer |
| note | string \| null (free text from the author that staff must read; currently taken from the description) |
| flag | { "severity": "'warning' \| 'danger'", "code": "#ScanWarning.code", "message": "string" } \| null (most severe scan warning) |
| submittedAt | string&lt;date-time&gt; |

### ReportTarget

| Field | Definition |
|---|---|
| kind | 'addon' \| 'version' \| 'comment' |
| id | integer |
| label | string (e.g. 'AutoLoot Pro 2.1') |
| addonSlug | string \| null (to link the listing) |
| expansionId | #ExpansionId \| null |
| categoryId | #CategoryId \| null |

### Report

| Field | Definition |
|---|---|
| id | integer |
| severity | 'critical' \| 'high' \| 'medium' \| 'low' |
| type | 'file' \| 'credit' \| 'listing' \| 'comment' |
| target | #ReportTarget |
| text | string |
| reporter | { "id": "integer \| null (null = scanner)", "name": "string" } |
| otherReporters | integer |
| autoQuarantined | boolean |
| uploader | #UserRef |
| ruleId | #RuleId \| null |
| closed | boolean |
| resolution | 'warn' \| 'takedown' \| 'dismiss' \| null |
| createdAt | string&lt;date-time&gt; |
| closedAt | string&lt;date-time&gt; \| null |

### AdminAccount

| Field | Definition |
|---|---|
| id | integer |
| name | string |
| email | string |
| color | string (hex #rrggbb) |
| role | #Role |
| status | #AccountStatus |
| suspendedUntil | string&lt;date-time&gt; \| null |
| uploads | integer (approved submissions) |
| downloads | integer (downloads of their addons) |
| reports | integer (reports received) |
| threat | integer (0..100) |
| createdAt | string&lt;date-time&gt; |
| lastSeenAt | string&lt;date-time&gt; \| null |
| online | boolean |

### ActivityKind

'upload' | 'approve' | 'reject' | 'comment' | 'account' | 'request' | 'report' | 'delete' | 'ban' | 'suspend' | 'quarantine' | 'roleChange'

### ActivityEvent

| Field | Definition |
|---|---|
| id | integer |
| kind | #ActivityKind |
| actor | { "id": "integer \| null (null = system)", "name": "string", "role": "#Role \| 'system'", "color": "string" } |
| target | { "kind": "'addon' \| 'version' \| 'submission' \| 'comment' \| 'request' \| 'report' \| 'account' \| 'rule'", "id": "integer \| null", "label": "string" } |
| detail | string \| null (free text, e.g. reason) |
| context | Record&lt;string, string \| number&gt; (data to build the sentence: reviewHours, reportId, votes, size, folders, rating, reportedBy...) |
| createdAt | string&lt;date-time&gt; |

### AchievementState

| Field | Definition |
|---|---|
| id | string (definition ID in the frontend) |
| points | integer |
| feat | boolean |
| completedAt | string&lt;date-time&gt; \| null |
| progress | { "current": "number", "max": "number" } \| null (null if already complete) |

### Expansion

| Field | Definition |
|---|---|
| id | #ExpansionId |
| abbr | string |
| name | string |
| patch | string |
| addonCount | integer |

## Routes

### GET /api/setup/status

#### Request

| Field | Definition |
|---|---|
| auth | setup |
| notes | See [site installation](installation.en.md) for setup; file downloads return binary content. |

#### Response

| Field | Definition |
|---|---|
| 200.description | Response from InstallService::status |

### GET /api/setup/requirements

#### Request

| Field | Definition |
|---|---|
| auth | setup |
| notes | See [site installation](installation.en.md) for setup; file downloads return binary content. |

#### Response

| Field | Definition |
|---|---|
| 200.description | Response from InstallService::requirements |

### POST /api/setup/database

#### Request

| Field | Definition |
|---|---|
| auth | setup |
| notes | See [site installation](installation.en.md) for setup; file downloads return binary content. |

#### Response

| Field | Definition |
|---|---|
| 200.description | Response from InstallService::database |

### POST /api/setup/install

#### Request

| Field | Definition |
|---|---|
| auth | setup |
| notes | See [site installation](installation.en.md) for setup; file downloads return binary content. |

#### Response

| Field | Definition |
|---|---|
| 201.description | Response from InstallService::install |

### GET /api/default-icon.svg

#### Request

| Field | Definition |
|---|---|
| auth | public |
| notes | Additional API operation. |

#### Response

| Field | Definition |
|---|---|
| 200.description | Response from CatalogService::default_icon |

### POST /api/auth/invitations/accept

#### Request

| Field | Definition |
|---|---|
| auth | user |
| notes | Additional API operation. |

#### Response

| Field | Definition |
|---|---|
| 200.description | Response from AdminService::accept_invitation |

### GET /api/admin/activity/export

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| query.q? | string |
| query.group? | same as /api/admin/activity |
| query.who? | same as /api/admin/activity |
| query.range? | #Range (default '7d') |
| notes | 'Export CSV' button. Columns: id, createdAt, kind, actorId, actorName, actorRole, targetKind, targetId, targetLabel, detail. |

#### Response

| Field | Definition |
|---|---|
| 200 | text/csv; charset=utf-8. Content-Disposition: attachment; filename="rog-activity-{range}-{YYYYMMDD}.csv" |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### POST /api/auth/verify/resend

#### Request

| Field | Definition |
|---|---|
| auth | public |
| contentType | application/json |
| body.verificationId | string (uuid) |
| body.email? | string (valid email; if supplied, changes the unverified account's email before resending) |
| notes | Covers the verification form's 'Resend' and 'Change email' buttons. Invalidates the previous code. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.maskedEmail | string |
| 200.expiresAt | string&lt;date-time&gt; |
| 200.resendAvailableAt | string&lt;date-time&gt; |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE (the account is already verified) |
| errors.422 | VALIDATION_FAILED: fields.email (format or EMAIL_TAKEN) |
| errors.429 | RATE_LIMITED (before resendAvailableAt) |

### POST /api/admin/invitations

#### Request

| Field | Definition |
|---|---|
| auth | founder |
| contentType | application/json |
| body.email | string (valid email) |
| body.role | 'moderator' |
| notes | 'Invite moderator' button. If the email already has a verified account, the role is assigned when accepting through the link; otherwise, the link leads to /register with invitationToken. Expires after 7 days. |

#### Response

| Field | Definition |
|---|---|
| 201.ok | true |
| 201.invitationId | integer |
| 201.expiresAt | string&lt;date-time&gt; |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.409 | INVALID_STATE (already staff or an active invitation exists) |
| errors.422 | VALIDATION_FAILED |

### GET /api/addons/check-name

#### Request

| Field | Definition |
|---|---|
| auth | user |
| query.name | string (1..60) |
| query.expansion? | #ExpansionId |
| query.version? | string (1..20) |
| notes | Live upload feedback (debounced). Compares the normalized name with names, aliases and slugs of published addons and pending addon submissions. With expansion and version, also indicates whether that version already exists for the matched addon. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.available | boolean |
| 200.existing | { "slug": "string", "name": "string", "expansionId": "#ExpansionId (canonical)", "categoryId": "#CategoryId", "match": "'name' \| 'alias' \| 'slug'", "pending": "boolean (true if a pending submission reserves the name without a public listing)" } \| null |
| 200.versionExists | boolean \| null (null if expansion and version were not supplied or there is no published existing match) |
| errors.401 | UNAUTHENTICATED |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/submissions

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| query.q? | string (1..80; addon or author name) |
| query.kind? | 'addon' \| 'version' |
| query.flagged? | 'flagged' \| 'clean' |
| query.sort? | 'oldest' \| 'newest' \| 'flagged' \| 'size' (default 'oldest') |
| query.range? | #Range (default '7d'; affects only decided) |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 20; overview uses 5) |
| notes | Review queue: pending submissions only. counts ignores q, kind and flagged. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items | #QueueItem[] |
| 200.page | #Page |
| 200.counts.pending | integer |
| 200.counts.flagged | integer |
| 200.counts.oldestWaitHours | integer \| null (null when the queue is empty) |
| 200.decided.count | integer |
| 200.decided.averageHours | integer \| null |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/dashboard

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| query.range? | #Range (default '7d') |
| notes | The five overview figures. The frontend calculates changes from value and previous. Overview panels reuse submissions, reports, requests, activity and stats with a small pageSize. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.kpis.downloads.value | integer |
| 200.kpis.downloads.previous | integer |
| 200.kpis.uploads.value | integer (submissions approved during the period) |
| 200.kpis.uploads.previous | integer |
| 200.kpis.pendingReview.value | integer |
| 200.kpis.pendingReview.previous | integer (pending at the start of the period) |
| 200.kpis.pendingReview.oldestWaitHours | integer |
| 200.kpis.openReports.value | integer |
| 200.kpis.openReports.previous | integer |
| 200.kpis.openReports.critical | integer |
| 200.kpis.activePlayers.value | integer (accounts with a session during the period) |
| 200.kpis.activePlayers.previous | integer |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/accounts

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| query.q? | string (1..80; name or email) |
| query.role? | #Role |
| query.status? | #AccountStatus |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 10) |
| notes | roleCounts is the full census and ignores filters. The account log comes from GET /api/admin/activity?actorId={id}&amp;limit=5. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items | #AdminAccount[] (lastSeenAt descending) |
| 200.page | #Page |
| 200.roleCounts | Record&lt;#Role, integer&gt; |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/activity

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| query.q? | string (1..80; actor or target) |
| query.group? | 'uploads' \| 'comments' \| 'signUps' \| 'requests' \| 'reports' \| 'removals' |
| query.who? | 'all' \| 'staff' \| 'users' (default 'all'; staff = founder, moderator and system) |
| query.actorId? | integer (account log) |
| query.range? | #Range (default '7d') |
| query.before? | string (cursor) |
| query.limit? | integer (1..100, default 50; overview uses 5) |
| notes | Groups: uploads = upload and approve; comments = comment; signUps = account; requests = request; reports = report; removals = delete, reject, ban, suspend and quarantine; roleChange appears only without a group filter. groupCounts and topActors cover the entire period and ignore group, q and the cursor. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items | #ActivityEvent[] (createdAt descending) |
| 200.nextCursor | string \| null |
| 200.groupCounts | Record&lt;'uploads' \| 'comments' \| 'signUps' \| 'requests' \| 'reports' \| 'removals', integer&gt; |
| 200.topActors.0.actor | { "id": "integer \| null", "name": "string", "role": "#Role \| 'system'", "color": "string" } |
| 200.topActors.0.count | integer |
| 200.topActors.0.kindCounts | Record&lt;#ActivityKind, integer&gt; |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### POST /api/auth/register

#### Request

| Field | Definition |
|---|---|
| auth | public |
| contentType | application/json |
| body.username | string (3..24, ^[A-Za-z0-9_^]+$, unique case-insensitively) |
| body.email | string (valid email, 3..254, unique) |
| body.password | string (8..128) |
| body.expansionId | #ExpansionId \| null (optional in the form; for recommendations) |
| body.termsAccepted | boolean (must be true) |
| body.invitationToken? | string (from a moderator invitation; assigns the role on verification) |
| notes | Creates the unverified account and emails a 6-character [A-Z0-9] code valid for 15 min. Accepts 'expansion' as an alias for expansionId. |

#### Response

| Field | Definition |
|---|---|
| 201.ok | true |
| 201.verificationId | string (uuid) |
| 201.maskedEmail | string (e.g. 'a***@gmail.com') |
| 201.expiresAt | string&lt;date-time&gt; |
| 201.resendAvailableAt | string&lt;date-time&gt; |
| errors.409 | NAME_TAKEN (username) \| INVALID_STATE (a session already exists) |
| errors.422 | VALIDATION_FAILED: fields.username \| email \| password \| expansionId \| termsAccepted \| invitationToken (EMAIL_TAKEN appears in fields.email) |
| errors.429 | RATE_LIMITED |

### GET /api/admin/reports

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| query.q? | string (1..80; target, reporter or text) |
| query.severity? | 'critical' \| 'high' \| 'medium' \| 'low' |
| query.type? | 'file' \| 'credit' \| 'listing' \| 'comment' |
| query.state? | 'open' \| 'closed' (when omitted: both) |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 20; overview uses 5) |
| notes | Order: open first, then severity and createdAt descending. severityCounts counts open reports and ignores filters. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items | #Report[] |
| 200.page | #Page |
| 200.severityCounts | Record&lt;'critical' \| 'high' \| 'medium' \| 'low', integer&gt; |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/counts

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| notes | Dashboard sidebar counters, visible in all sections. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.queue | integer (pending submissions) |
| 200.reports | integer (open reports) |
| 200.accounts | integer (verified accounts) |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |

### GET /api/auth/session

#### Request

| Field | Definition |
|---|---|
| auth | public |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.user | #SessionUser \| null (null without a session) |

### POST /api/auth/logout

#### Request

| Field | Definition |
|---|---|
| auth | public |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |

### POST /api/auth/verify

#### Request

| Field | Definition |
|---|---|
| auth | public |
| contentType | application/json |
| body.verificationId | string (uuid) |
| body.code | string (6, ^[A-Z0-9]{6}$; the server converts to uppercase) |
| notes | A correct code verifies the account, creates the session (remember=false) and returns the user. Five failed attempts invalidate the code. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.user | #SessionUser |
| errors.404 | NOT_FOUND (unknown verificationId) |
| errors.410 | VERIFICATION_EXPIRED (expired code or attempts exhausted) |
| errors.422 | INVALID_CODE, with fields.code |
| errors.429 | RATE_LIMITED |

### POST /api/submissions

#### Request

| Field | Definition |
|---|---|
| auth | uploader |
| contentType | application/json |
| body.uploadId? | string (uuid, owned by the user and unexpired) |
| body.addonSlug? | string \| null |
| body.requestId? | integer \| null (request in open or volunteered) |
| body.name? | string \| null |
| body.version? | string \| null |
| body.categoryId? | #CategoryId \| null |
| body.categoryIds? | #CategoryId[] (0..8; if provided, the first determines categoryId) |
| body.expansionId? | #ExpansionId \| null |
| body.classIds? | #ClassId[] |
| body.professionIds? | #ProfessionId[] |
| body.roles? | #RoleTag[] |
| body.summary? | string \| null |
| body.description? | string \| null |
| body.tags? | string[] |
| body.originalAuthor? | string \| null |
| body.sourceUrl? | string \| null |
| body.iconMediaId? | integer \| null |
| body.screenshotMediaIds? | integer[] (0..4, in order) |
| body.rulesAccepted? | boolean |
| body.testedConfirmed? | boolean |
| notes | Creates a draft ('Save draft' without a previous ID). Validates only format (domain.submissionLifecycle.draftValidation). With requestId, the request moves to volunteered. |

#### Response

| Field | Definition |
|---|---|
| 201.ok | true |
| 201.submission | #Submission |
| errors.401 | UNAUTHENTICATED |
| errors.403 | ACCOUNT_SUSPENDED |
| errors.404 | NOT_FOUND (addonSlug or requestId) |
| errors.409 | INVALID_STATE (the request is inQueue, fulfilled or closed) |
| errors.410 | UPLOAD_EXPIRED \| MEDIA_EXPIRED |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/stats

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| query.range? | #Range (default '7d') |
| query.tz? | string (IANA zone, default 'UTC'; for days and hours) |
| notes | Download chart, most downloaded items, peak activity time, client migration and records. The frontend calculates percentages, peak, quiet hour and weekend increase from primeTime. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.downloads.from | string&lt;date&gt; |
| 200.downloads.current | integer[] (one figure per day in the period) |
| 200.downloads.previous | integer[] (same length, previous period) |
| 200.periodTotal | integer |
| 200.topAddons.0.slug | string |
| 200.topAddons.0.name | string |
| 200.topAddons.0.downloads | integer |
| 200.primeTime | integer[][] (7 x 24; downloads by weekday, Monday = 0, and hour) |
| 200.migration.0.from | #ExpansionId |
| 200.migration.0.to | #ExpansionId |
| 200.migration.0.value | integer (users who downloaded from from first, then from to during the period) |
| 200.records.0.id | 'fastest' \| 'streak' \| 'comeback' \| 'nightOwl' \| 'loyal' \| 'heaviest' |
| 200.records.0.holder.label | string |
| 200.records.0.holder.addonSlug | string \| null |
| 200.records.0.value | number |
| 200.records.0.unit | 'hours' \| 'days' \| 'percent' \| 'minuteOfDay' \| 'bytes' |
| 200.records.0.active | boolean (still current: gold diamond) |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### GET /api/submissions

#### Request

| Field | Definition |
|---|---|
| auth | user |
| query.status? | #SubmissionStatus |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 10) |
| notes | For recovering drafts and checking submission outcomes. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items | #Submission[] (updatedAt descending) |
| 200.page | #Page |
| errors.401 | UNAUTHENTICATED |
| errors.422 | VALIDATION_FAILED |

### POST /api/auth/login

#### Request

| Field | Definition |
|---|---|
| auth | public |
| contentType | application/json |
| body.identifier | string (1..254, username or email) |
| body.password | string (1..128) |
| body.remember | boolean |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.user | #SessionUser |
| errors.401 | INVALID_CREDENTIALS (same error if the account does not exist) |
| errors.403 | EMAIL_NOT_VERIFIED: error.details includes verificationId and maskedEmail for navigating to /verify, and the server resends a code \| ACCOUNT_BANNED |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED (5 failures in 15 min per identifier and IP) |

### GET /api/admin/live

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| query.since? | string&lt;date-time&gt; (only subsequent feed) |
| notes | Live Statistics pulse. The frontend polls every 10 s while the pause button is active and sends the newest event's createdAt in since. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.online | integer |
| 200.perMinute | integer |
| 200.today | integer |
| 200.sameTimeLastWeek | integer (for the rate) |
| 200.pulse | integer[] (40; downloads per minute, oldest first) |
| 200.feed.0.id | integer |
| 200.feed.0.user | string |
| 200.feed.0.city | string \| null |
| 200.feed.0.addon.slug | string |
| 200.feed.0.addon.name | string |
| 200.feed.0.addon.iconUrl | string&lt;url&gt; |
| 200.feed.0.expansionId | #ExpansionId |
| 200.feed.0.createdAt | string&lt;date-time&gt; |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |

### GET /api/site/stats

#### Request

| Field | Definition |
|---|---|
| auth | public |
| notes | Global figures for AccountIntro (login, registration, verification), the About header and average upload review time. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.addons | integer (published addons) |
| 200.downloads | integer (total downloads) |
| 200.players | integer (verified accounts) |
| 200.averageReviewHours | integer (average over the last week) |

### POST /api/requests

#### Request

| Field | Definition |
|---|---|
| auth | user |
| contentType | application/json |
| body.addonName | string (2..60) |
| body.expansionId | #ExpansionId |
| body.text | string (10..500) |
| notes | Home form (name, expansion and details fields). The author automatically votes and follows. |

#### Response

| Field | Definition |
|---|---|
| 201.ok | true |
| 201.request | #Request |
| errors.401 | UNAUTHENTICATED |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED (5 requests per day per account) |

### GET /api/requests

#### Request

| Field | Definition |
|---|---|
| auth | public |
| query.q? | string (1..80; addon or author name) |
| query.state? | #RequestState (when omitted: all except closed) |
| query.expansion? | #ExpansionId |
| query.sort? | 'votes' \| 'newest' \| 'oldest' (default 'votes'; oldest = oldest first) |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 20) |
| notes | Home (sort=newest, pageSize=4), dashboard overview (sort=votes, pageSize=5) and dashboard requests section. stateCounts and expansionCounts ignore state and expansion filters but respect q. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items | #Request[] |
| 200.page | #Page |
| 200.stateCounts | Record&lt;#RequestState, integer&gt; |
| 200.expansionCounts | Record&lt;#ExpansionId, integer&gt; (only expansions with a request; feeds the dashboard filter) |
| errors.422 | VALIDATION_FAILED |

### GET /api/taxonomy

#### Request

| Field | Definition |
|---|---|
| auth | public |
| notes | Expansions, category/classes/professions/role groups and upload limits in a single response. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.expansions.0.id | #ExpansionId |
| 200.expansions.0.abbr | string |
| 200.expansions.0.name | string |
| 200.expansions.0.patch | string (reference client, e.g. '3.3.5a') |
| 200.expansions.0.addonCount | integer (addons with a version in that expansion) |
| 200.groups.0.id | 'category' \| 'classes' \| 'professions' \| 'role' |
| 200.groups.0.items.0.id | string |
| 200.groups.0.items.0.name | string |
| 200.groups.0.items.0.addonCount | integer |
| 200.limits.summaryMax | integer |
| 200.limits.screenshotsMax | integer |
| 200.limits.addonZipMaxBytes | integer |
| 200.limits.imageMaxBytes | integer |

### POST /api/contact

#### Request

| Field | Definition |
|---|---|
| auth | public |
| contentType | multipart/form-data |
| body.recipientId | string (current ID from GET /api/contact: account:{id}, group:moderation or mailbox:{key}) |
| body.topicId | 'broken' \| 'rejected' \| 'takedown' \| 'account' \| 'report' \| 'other' |
| body.name | string (2..60) |
| body.email | string (valid email; required without a session, defaults to the account email with a session) |
| body.subject | string (3..120) |
| body.message | string (20..5000) |
| body.attachment? | image/png or image/jpeg (up to 2 MB) |

#### Response

| Field | Definition |
|---|---|
| 201.ok | true |
| 201.ticketId | integer |
| 201.estimatedReplyHours | integer |
| errors.413 | FILE_TOO_LARGE |
| errors.415 | UNSUPPORTED_MEDIA_TYPE |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED (3 messages per hour per IP) |

### POST /api/reports

#### Request

| Field | Definition |
|---|---|
| auth | user |
| contentType | application/json |
| body.target.kind | 'addon' \| 'version' \| 'comment' |
| body.target.id | integer |
| body.type | 'file' \| 'credit' \| 'listing' \| 'comment' |
| body.text | string (10..1000) |
| notes | 'Report' button on the addon page. If another user already has an open report of the same type for the same target, joins that report (otherReporters) rather than creating another. |

#### Response

| Field | Definition |
|---|---|
| 201.ok | true |
| 201.reportId | integer |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND (target) |
| errors.409 | ALREADY_REPORTED (the same user already has an open report for that target) |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED |

### POST /api/uploads

#### Request

| Field | Definition |
|---|---|
| auth | uploader |
| contentType | multipart/form-data |
| body.file | .zip file (application/zip or application/x-zip-compressed, 1 B..50 MB) |
| notes | Upload step 1. The frontend displays progress through XMLHttpRequest.upload.onprogress. The server scans the ZIP before responding. The file stays temporary: see domain.temporaryFiles. |

#### Response

| Field | Definition |
|---|---|
| 201.ok | true |
| 201.upload | #UploadFile |
| errors.401 | UNAUTHENTICATED |
| errors.403 | ACCOUNT_SUSPENDED |
| errors.413 | FILE_TOO_LARGE |
| errors.415 | UNSUPPORTED_MEDIA_TYPE (not a ZIP or encrypted) |
| errors.422 | VALIDATION_FAILED (empty or corrupt ZIP) |
| errors.429 | RATE_LIMITED |

### GET /api/contact

#### Request

| Field | Definition |
|---|---|
| auth | public |
| notes | Lists individual verified, non-banned founders, one shared Moderators card and configured mailboxes. The group is available with a founder alone and sends to every verified, non-banned moderator and founder at submission time. One ticket has a separate notification per group member. Addresses stay private; the group title uses frontend i18n. Removed destinations are rejected. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.queue | integer (unanswered messages) |
| 200.recipients.0.id | string (account:{id}, group:moderation or mailbox:{key}) |
| 200.recipients.0.name | string \| null (null for the localized moderation group title) |
| 200.recipients.0.kind | 'founder' \| 'moderation' \| 'mailbox' |
| 200.recipients.0.username | string \| null (null for the group and mailboxes) |
| 200.recipients.0.color | string (avatar color or default accent) |
| 200.recipients.0.description | string (optional configured public description; empty for staff accounts) |
| 200.recipients.0.status | 'reviewing' \| 'normal' |
| 200.recipients.0.replyHours.min | integer |
| 200.recipients.0.replyHours.max | integer |
| 200.recipients.0.estimatedReplyHours | integer (default 24; configurable for mailboxes) |
| 200.recipients.0.availabilityPercent | integer (0..100; calculated from open tickets) |
| 200.recipients.0.load | integer (0..100; fills the estimated response bar) |
| 200.week.received | integer |
| 200.week.answered | integer |
| 200.week.averageReplyHours | integer |

### GET /api/addons

#### Request

| Field | Definition |
|---|---|
| auth | public |
| query.q? | string (1..80; searches name, aliases, summary and tags) |
| query.expansion? | string (comma-separated #ExpansionId IDs) |
| query.category? | string (comma-separated #CategoryId IDs) |
| query.classes? | string (comma-separated #ClassId IDs) |
| query.professions? | string (comma-separated #ProfessionId IDs) |
| query.role? | string (comma-separated #RoleTag IDs) |
| query.rating? | 0 \| 3 \| 4 \| 4.5 (minimum rating; 0 = all) |
| query.author? | string (1..24; case-insensitive partial match against uploader name) |
| query.recent? | '1' (only addons with a new version in the last 30 days) |
| query.sort? | 'downloads' \| 'rating' \| 'recent' \| 'updated' \| 'az' (default 'downloads'; recent = createdAt, updated = updatedAt) |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..48, default 12; Explore uses 6) |
| notes | Within a group, IDs combine with OR; between groups, with AND. Each facet counter applies other filters but not its own group's, so selecting one option does not hide the others. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items | #AddonSummary[] |
| 200.page | #Page |
| 200.facets.expansion | Record&lt;#ExpansionId, integer&gt; |
| 200.facets.category | Record&lt;#CategoryId, integer&gt; |
| 200.facets.classes | Record&lt;#ClassId, integer&gt; |
| 200.facets.professions | Record&lt;#ProfessionId, integer&gt; |
| 200.facets.role | Record&lt;#RoleTag, integer&gt; |
| errors.422 | VALIDATION_FAILED (sort, rating, pageSize or an unknown filter ID) |

### GET /api/health

#### Request

| Field | Definition |
|---|---|
| auth | public |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.message | string |
| 200.php | string |

### POST /api/media

#### Request

| Field | Definition |
|---|---|
| auth | uploader |
| contentType | multipart/form-data |
| body.kind | 'icon' \| 'screenshot' |
| body.file | image/png or image/jpeg (icon: exactly 256x256, up to 2 MB; screenshot: 640x360..3840x2160, up to 2 MB) |
| notes | Upload step 3. Returns a temporary ID linked to the submission through PATCH (iconMediaId, screenshotMediaIds). |

#### Response

| Field | Definition |
|---|---|
| 201.ok | true |
| 201.media | #Media |
| errors.401 | UNAUTHENTICATED |
| errors.403 | ACCOUNT_SUSPENDED |
| errors.413 | FILE_TOO_LARGE |
| errors.415 | UNSUPPORTED_MEDIA_TYPE |
| errors.422 | VALIDATION_FAILED (dimensions) |

### GET /api/about

#### Request

| Field | Definition |
|---|---|
| auth | public |
| notes | Only changing About data. The three header figures come from /api/site/stats; the timeline and founders are in the frontend. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.support.month | string (YYYY-MM) |
| 200.support.raised | number |
| 200.support.goal | number |
| 200.support.currency | string (ISO 4217) |
| 200.support.contributors | integer |
| 200.hallOfFame.0.user | #UserRef |
| 200.hallOfFame.0.uploads | integer |
| 200.hallOfFame.0.since | integer (year) |
| 200.achievements | #AchievementState[] |

### GET /api/home

#### Request

| Field | Definition |
|---|---|
| auth | public |
| notes | Home-specific data. Expansions and categories come from /api/taxonomy and requests from /api/requests?sort=newest&amp;pageSize=4. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.latest | #AddonSummary[] (6, published addons by createdAt descending) |
| 200.updated | #AddonUpdate[] (6, latest new versions of existing addons) |
| 200.packs | #Pack[] |

### POST /api/admin/requests/{requestId}/notify-authors

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| notes | Additional API operation. |

#### Response

| Field | Definition |
|---|---|
| 202.description | Response from RequestService::notify_authors |

### POST /api/admin/submissions/{submissionId}/decision

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| path.submissionId | integer |
| contentType | application/json |
| body.decision | 'approve' \| 'reject' |
| body.ruleId | #RuleId \| null (required for reject) |
| body.reason | string (0..1000) \| null |
| notes | approve publishes the listing or version (and changes the uploader to author if previously player); reject discards it. Both email the author and change the linked request according to domain.requestLifecycle. Only for pending submissions: retrying afterward returns 409. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.submission | #Submission |
| 200.addon | #AddonSummary \| null (only for approve) |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE \| NAME_TAKEN \| VERSION_EXISTS |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/submissions/{submissionId}/file

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| path.submissionId | integer |
| notes | Queue 'ZIP' button. Also serves quarantined files. |

#### Response

| Field | Definition |
|---|---|
| 200 | application/zip. Content-Disposition: attachment; filename="submission-{submissionId}.zip" |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND |

### POST /api/admin/accounts/{accountId}/message

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| path.accountId | integer |
| contentType | application/json |
| body.subject | string (3..120) |
| body.body | string (1..5000) |
| notes | Listing 'Message' button. Delivered by email; the site has no inbox. |

#### Response

| Field | Definition |
|---|---|
| 201.ok | true |
| 201.messageId | integer |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED |

### POST /api/admin/requests/{requestId}/action

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| path.requestId | integer |
| contentType | application/json |
| body.action | 'link' \| 'ping' \| 'close' |
| body.submissionId? | integer (link: draft or pending submission) |
| body.addonSlug? | string (link: already published addon) |
| body.reason? | string (0..500) (close) |
| notes | The dashboard listing uses GET /api/requests. link requires exactly one of submissionId or addonSlug and follows domain.requestLifecycle transitions. ping emails authors with addons in that expansion without changing state (once every 24 h per request). close only from open or volunteered. 'Open addon' is not an action: it links to request.addon. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.request | #Request |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND (request, submission or addon) |
| errors.409 | INVALID_STATE |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED (repeated ping) |

### POST /api/submissions/{submissionId}/submit

#### Request

| Field | Definition |
|---|---|
| auth | uploader |
| path.submissionId | integer |
| contentType | application/json |
| body.revision? | integer |
| notes | 'Submit for review' button. If the form has unsaved changes, the frontend performs PATCH first. Applies domain.submissionLifecycle.submitValidation and moves the submission to pending; with requestId, the request moves to inQueue. Retrying a submission already pending returns 409. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.submission | #Submission (status pending, estimatedReviewHours) |
| errors.401 | UNAUTHENTICATED |
| errors.403 | ACCOUNT_SUSPENDED |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE \| REVISION_MISMATCH \| NAME_TAKEN \| VERSION_EXISTS |
| errors.410 | UPLOAD_EXPIRED \| MEDIA_EXPIRED |
| errors.422 | VALIDATION_FAILED (required fields, false confirmations or blocked ZIP) |

### GET /api/admin/submissions/{submissionId}

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| path.submissionId | integer |
| notes | Queue 'Inspect' button. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.submission | #Submission |
| 200.author | #AdminAccount |
| 200.addon | #AddonSummary \| null (existing addon if kind=version) |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND (missing or draft) |

### POST /api/admin/reports/{reportId}/action

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| path.reportId | integer |
| contentType | application/json |
| body.action | 'warn' \| 'takedown' \| 'dismiss' \| 'reopen' |
| body.note | string (0..1000) \| null |
| notes | warn, takedown and dismiss only for open reports; reopen only for closed reports. Effects in domain.reportLifecycle.effects. 'View' is not an action: it links to target. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.report | #Report |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE |
| errors.422 | VALIDATION_FAILED |

### PATCH /api/admin/accounts/{accountId}

#### Request

| Field | Definition |
|---|---|
| auth | staff |
| path.accountId | integer |
| contentType | application/json |
| body.role? | 'founder' \| 'moderator' \| 'player' |
| body.status? | #AccountStatus |
| body.suspendDays? | integer (1..365, default 30; only with status suspended) |
| body.reason? | string (0..500) |
| notes | Only a founder changes role; 'player' removes staff status and the server converts it to author if the account has published addons. A moderator can suspend and reactivate player and author accounts; banning or modifying other staff requires founder. Nobody changes their own account. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.account | #AdminAccount |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND |
| errors.422 | VALIDATION_FAILED |

### PATCH /api/submissions/{submissionId}

#### Request

| Field | Definition |
|---|---|
| auth | uploader |
| path.submissionId | integer |
| contentType | application/json |
| body.revision? | integer (last value received) |
| body.... | any field from the POST /api/submissions body; absent fields stay unchanged and null clears them |
| notes | Only in draft status. Renews expiresAt. Changing uploadId or images unlinks previous files, which return to 24 h expiry. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.submission | #Submission |
| errors.401 | UNAUTHENTICATED |
| errors.403 | ACCOUNT_SUSPENDED |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE (not a draft) \| REVISION_MISMATCH |
| errors.410 | UPLOAD_EXPIRED \| MEDIA_EXPIRED |
| errors.422 | VALIDATION_FAILED |

### PUT /api/requests/{requestId}/follow

#### Request

| Field | Definition |
|---|---|
| auth | user |
| path.requestId | integer |
| contentType | application/json |
| body.following | boolean (true = follow, false = unfollow) |
| notes | Idempotent. Allowed in any state. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.followers | integer |
| 200.following | boolean |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND |
| errors.422 | VALIDATION_FAILED |

### GET /api/download-files/{versionId}

#### Request

| Field | Definition |
|---|---|
| auth | public |
| notes | Additional API operation. |

#### Response

| Field | Definition |
|---|---|
| 200.description | Response from DownloadService::signed |

### GET /api/submissions/{submissionId}

#### Request

| Field | Definition |
|---|---|
| auth | user |
| path.submissionId | integer |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.submission | #Submission |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND (missing, belongs to another user or expired draft) |

### PUT /api/requests/{requestId}/vote

#### Request

| Field | Definition |
|---|---|
| auth | user |
| path.requestId | integer |
| contentType | application/json |
| body.voted | boolean (true = vote, false = remove vote) |
| notes | Idempotent: repeating the same value returns 200 without changing the counter. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.votes | integer |
| 200.voted | boolean |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE (fulfilled or closed) |
| errors.422 | VALIDATION_FAILED |

### GET /api/users/{username}/journal

#### Request

| Field | Definition |
|---|---|
| auth | public |
| path.username | string \| 'me' |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 20) |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items.0.id | integer |
| 200.items.0.type | 'shipped' \| 'updated' \| 'replied' \| 'titleEarned' \| 'reviewed' \| 'reported' \| 'realmFirst' |
| 200.items.0.target.kind | 'addon' \| 'request' \| 'title' \| 'report' |
| 200.items.0.target.id | string |
| 200.items.0.target.label | string |
| 200.items.0.target.addonSlug | string \| null |
| 200.items.0.detail | string \| null |
| 200.items.0.note | string \| null |
| 200.items.0.createdAt | string&lt;date-time&gt; |
| 200.page | #Page |
| errors.404 | NOT_FOUND |

### GET /api/users/{username}/reviews

#### Request

| Field | Definition |
|---|---|
| auth | public |
| path.username | string \| 'me' |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 10) |
| notes | Rated comments the user left on other users' addons. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items.0.id | integer (comment ID) |
| 200.items.0.addon.slug | string |
| 200.items.0.addon.name | string |
| 200.items.0.addon.expansionId | #ExpansionId |
| 200.items.0.addon.categoryId | #CategoryId |
| 200.items.0.rating | integer (1..5) |
| 200.items.0.text | string |
| 200.items.0.helpful | integer |
| 200.items.0.createdAt | string&lt;date-time&gt; |
| 200.page | #Page |
| errors.404 | NOT_FOUND |

### PUT /api/addons/{slug}/my-comment

#### Request

| Field | Definition |
|---|---|
| auth | user |
| path.slug | string |
| body.content | string (1..4000) |
| body.rating | integer (1..5) |
| body.expansionId | #ExpansionId |
| notes | One review per user and addon. Creates with 201 or updates the existing one with 200. The expansion must have a published version of the addon. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.comment | #Comment |
| 200.addonRating.rating | number \| null |
| 200.addonRating.votes | integer |
| 200.rating | number \| null |
| 200.ratingCount | integer |
| 201.ok | true |
| 201.comment | #Comment |
| 201.addonRating.rating | number \| null |
| 201.addonRating.votes | integer |
| 201.rating | number \| null |
| 201.ratingCount | integer |
| errors.401 | UNAUTHENTICATED |
| errors.409 | INVALID_STATE |
| errors.422 | VALIDATION_FAILED |

### GET /api/packs/{packId}/download

#### Request

| Field | Definition |
|---|---|
| auth | public |
| path.packId | #RoleTag |
| query.expansion | #ExpansionId |
| notes | ZIP with the latest version of each pack addon for that expansion. Adds one download to every included version. |

#### Response

| Field | Definition |
|---|---|
| 200 | application/zip. Content-Disposition: attachment; filename="rog-{packId}-kit-{expansion}.zip" |
| errors.404 | NOT_FOUND (pack missing or unavailable for that expansion) |
| errors.422 | VALIDATION_FAILED |

### GET /api/users/{username}/addons

#### Request

| Field | Definition |
|---|---|
| auth | public |
| path.username | string \| 'me' |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..48, default 24) |
| notes | 'Addons' tab (bag). An item is equipped if its ID appears in loadout. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items | #AddonSummary[] (updatedAt descending) |
| 200.page | #Page |
| errors.404 | NOT_FOUND |

### PUT /api/users/{username}/follow

#### Request

| Field | Definition |
|---|---|
| auth | user |
| path.username | string |
| contentType | application/json |
| body.following | boolean |
| notes | Profile 'Follow' button. Idempotent. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.followers | integer |
| 200.following | boolean |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND |
| errors.422 | VALIDATION_FAILED (following yourself) |

### GET /api/addons/{slug}/comments

#### Request

| Field | Definition |
|---|---|
| auth | public |
| path.slug | string |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 10) |
| notes | Lists current reviews: one per user and addon. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.items | #Comment[] (createdAt descending) |
| 200.page | #Page |
| errors.404 | NOT_FOUND |

### PUT /api/addons/{slug}/favorite

#### Request

| Field | Definition |
|---|---|
| auth | user |
| path.slug | string |
| contentType | application/json |
| body.favorited | boolean |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.favorited | boolean |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND |
| errors.422 | VALIDATION_FAILED |

### GET /api/downloads/{versionId}

#### Request

| Field | Definition |
|---|---|
| auth | public |
| path.versionId | integer |
| notes | Single download entry point for published versions: cards, rows, addon header and version list. Counts one download per version and IP every 24 h. |

#### Response

| Field | Definition |
|---|---|
| 302 | Location pointing to the ZIP (signed URL, valid for 5 min). Content-Disposition: attachment; filename="{slug}-{version}-{expansionId}.zip" |
| errors.404 | NOT_FOUND (version missing or withdrawn) |
| errors.429 | RATE_LIMITED |

### GET /api/profiles/{username}

#### Request

| Field | Definition |
|---|---|
| auth | public |
| path.username | string (username or 'me', which requires a session) |
| notes | Profile header and main tab. Growing lists (addons, journal, reviews) use their own paginated endpoints. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.user.id | integer |
| 200.user.name | string |
| 200.user.color | string (hex #rrggbb) |
| 200.user.role | #Role |
| 200.user.titleId | string \| null (active title; its text is in the frontend) |
| 200.user.level | integer |
| 200.user.realm | string \| null |
| 200.user.joinedAt | string&lt;date-time&gt; |
| 200.user.online | boolean |
| 200.user.badge | { "achievementId": "string", "expansionId": "#ExpansionId \| null" } \| null |
| 200.user.spec | { "role": "#RoleTag", "name": "string (e.g. 'Restoration')" } \| null |
| 200.user.skill.value | integer |
| 200.user.skill.max | integer |
| 200.user.reputation.value | integer |
| 200.user.reputation.max | integer |
| 200.user.reputation.standing | 'neutral' \| 'friendly' \| 'honored' \| 'revered' \| 'exalted' |
| 200.stats.addons | integer |
| 200.stats.addonsThisYear | integer |
| 200.stats.downloads | integer |
| 200.stats.downloads30d | integer |
| 200.stats.averageRating | number \| null |
| 200.stats.reviewsReceived | integer |
| 200.stats.followers | integer |
| 200.stats.followers30d | integer |
| 200.stats.versions | integer |
| 200.stats.lastVersionAt | string&lt;date-time&gt; \| null (also feeds 'Latest upload') |
| 200.stats.replies | integer |
| 200.counts.addons | integer |
| 200.counts.journal | integer |
| 200.counts.reviews | integer |
| 200.loadout | #AddonSummary[] (0..8; their most downloaded addons, in slot order) |
| 200.addonsByExpansion | Record&lt;#ExpansionId, integer&gt; (talent tree) |
| 200.titles.0.id | string |
| 200.titles.0.earnedAt | string&lt;date-time&gt; \| null |
| 200.achievements | #AchievementState[] |
| 200.lastWhisper | { "text": "string", "author": "#UserRef", "addonSlug": "string", "createdAt": "string&lt;date-time&gt;" } \| null (latest comment received on one of their addons) |
| 200.heat.from | string&lt;date&gt; (Monday four weeks ago) |
| 200.heat.counts | integer[] (28; actions per day; the frontend calculates level 0..3) |
| 200.viewer | { "isSelf": "boolean", "following": "boolean" } \| null |
| errors.401 | UNAUTHENTICATED (username = me without a session) |
| errors.404 | NOT_FOUND (missing or banned) |

### GET /api/files/{fileId}

#### Request

| Field | Definition |
|---|---|
| auth | public |
| notes | See [site installation](installation.en.md) for setup; file downloads return binary content. |

#### Response

| Field | Definition |
|---|---|
| 200.description | Response from FileService::media |

### GET /api/addons/{slug}

#### Request

| Field | Definition |
|---|---|
| auth | public |
| path.slug | string |
| query.expansion? | #ExpansionId (from the URL; returns the canonical expansion if the addon has no version in it) |
| notes | If returned expansionId or categoryId differ from the URL, the frontend redirects to the canonical URL as it does today. |

#### Response

| Field | Definition |
|---|---|
| 200.ok | true |
| 200.addon | #AddonDetail |
| errors.404 | NOT_FOUND (slug missing or addon withdrawn) |
