# Complete column dictionary

Generated from the schema installed in an in-memory PostgreSQL environment. **15 tables, 128 columns.** This inventory does not include view columns. Current definitions are in [the models](../../api/src/Models/) and PostgreSQL SQL objects in [database/objects.sql](../../api/database/objects.sql).

Examples of each table's JSONB columns appear below it, or a note states that it has none.
**Each JSON block represents one column's value, not the entire row or an API request.**
`object_json` is a domain over `jsonb` that requires an object; internal key and type validation
belongs to the API services. Data, IDs, dates, addresses and resources are fictional examples.
The examples do not add columns or change editing permissions.

## accounts — Identity and account (11)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `username` | text | No | — |
| `email` | text | No | — |
| `password_hash` | text | No | — |
| `verified_at` | timestamptz | Yes | — |
| `access_level` | smallint | No | DEFAULT 0; CHECK IN (0,1,2); 0 user, 1 moderator, 2 founder |
| `banned_at` | timestamptz | Yes | — |
| `suspended_until` | timestamptz | Yes | — |
| `profile` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `created_at` | timestamptz | No | DEFAULT now() |
| `last_seen_at` | timestamptz | Yes | — |

`access_level` expresses administrative permissions: 0 = regular user, 1 = moderator, 2 = founder. It does not accept NULL. The public `author` role is derived from available publications and does not raise this level. Only a founder can grant or remove privileges through an authorized operation; registration always uses 0.

Profile preference writes are limited to fields accepted by their authorized endpoints.
The server records accepted terms and awards achievements and titles. Award definitions
are stored in configuration; identifiers in the example are fictional.

**Example of `profile` — Profile with preferences and historical awards**

```json
{
  "color": "#5b8def",
  "expansionId": "wotlk",
  "realm": "Example Realm",
  "spec": {
    "role": "healer",
    "name": "Restoration"
  },
  "titleId": "first-upload",
  "badge": {
    "achievementId": "first-approved-addon",
    "expansionId": "wotlk"
  },
  "terms": {
    "version": "2026-10",
    "acceptedAt": "2026-10-01T10:00:00Z"
  },
  "achievements": {
    "first-approved-addon": {
      "completedAt": "2026-10-02T15:00:00Z",
      "points": 10,
      "feat": false
    }
  },
  "titles": {
    "first-upload": {
      "earnedAt": "2026-10-02T15:00:00Z"
    }
  }
}
```

## auth_tokens — Sessions, verifications and invitations (10)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `public_id` | uuid | No | DEFAULT gen_random_uuid() |
| `purpose` | text | No | — |
| `account_id` | bigint | Yes | FK → accounts.id |
| `email` | text | Yes | — |
| `secret_hash` | bytea | No | — |
| `expires_at` | timestamptz | No | — |
| `consumed_at` | timestamptz | Yes | — |
| `data` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `created_at` | timestamptz | No | DEFAULT now() |

These are three alternative rows depending on the purpose column; the three variants are not stored together and no session/verification/invitation container is added. The server manages this data. The secret is represented by secret_hash in its own column, never as text inside data.

**Example of `data` — Session: purpose = session**

```json
{
  "remember": true,
  "csrfVersion": 1
}
```

**Example of `data` — Verification: purpose = verification**

```json
{
  "failedAttempts": 0,
  "resendAvailableAt": "2026-10-09T12:01:00Z",
  "emailAtIssue": "lucia@example.invalid",
  "invitationId": null
}
```

**Example of `data` — Invitation: purpose = invitation**

```json
{
  "inviterId": 7,
  "message": "Invitation to the moderation team.",
  "acceptedBy": null
}
```

## assets — File metadata (12)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `external_id` | uuid | No | DEFAULT gen_random_uuid() |
| `owner_id` | bigint | Yes | FK → accounts.id |
| `kind` | text | No | — |
| `storage_key` | text | No | — |
| `filename` | text | No | — |
| `mime_type` | text | No | — |
| `size_bytes` | bigint | No | — |
| `status` | text | No | — |
| `metadata` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `expires_at` | timestamptz | Yes | — |
| `created_at` | timestamptz | No | DEFAULT now() |

The server and file scanner generate this metadata. Each row represents one file: dimensions apply to images and folders/tocInterfaces to the ZIP. Availability is stored in the status column.

**Example of `metadata` — ZIP file: kind = zip**

```json
{
  "sha256": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
  "folders": [
    "RaidHelper"
  ],
  "tocInterfaces": [
    30300
  ],
  "detectedExpansionId": "wotlk",
  "warnings": [],
  "scannerVersion": "example-scanner-1.0",
  "scannedAt": "2026-10-09T12:05:00Z"
}
```

**Example of `metadata` — Screenshot: kind = screenshot**

```json
{
  "width": 1920,
  "height": 1080,
  "warnings": [],
  "scannerVersion": "example-scanner-1.0",
  "scannedAt": "2026-10-09T12:06:00Z"
}
```

## addons — Published listing (8)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `slug` | text | No | — |
| `owner_id` | bigint | No | FK → accounts.id |
| `name` | text | No | — |
| `category_id` | category_code (text) | No | — |
| `details` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `published_at` | timestamptz | No | DEFAULT now() |
| `withdrawn_at` | timestamptz | Yes | — |

Published listing content. Obtained from the approved submission and any authorized edits that are implemented. description is the canonical text in this example. Ratings, downloads and compatibility are calculated from their sources; they are not stored here as authoritative data.

**Example of `details` — Addon listing**

```json
{
  "summary": "Raid alerts and ability tracking.",
  "description": "## Raid Helper\nDisplays configurable alerts during encounters.",
  "features": [
    "Ability alerts",
    "Configurable timers"
  ],
  "classIds": [
    "druid"
  ],
  "professionIds": [],
  "roles": [
    "healer",
    "dps"
  ],
  "tags": [
    "raid",
    "alerts"
  ],
  "languages": [
    "ES",
    "EN"
  ],
  "licence": "MIT",
  "originalAuthor": "Example Author",
  "sourceUrl": "https://example.invalid/raid-helper"
}
```

## requests — Community requests (9)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `author_id` | bigint | No | FK → accounts.id |
| `addon_name` | text | No | — |
| `expansion_id` | expansion_code (text) | No | — |
| `details` | text | No | — |
| `state` | text | No | DEFAULT 'open'::text |
| `fulfilled_addon_id` | bigint | Yes | FK → addons.id |
| `created_at` | timestamptz | No | DEFAULT now() |
| `updated_at` | timestamptz | No | DEFAULT now() |

This table has no JSONB columns. details is free text (text), not a JSON object.

## submissions — Drafts, decisions and versions (16)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `owner_id` | bigint | No | FK → accounts.id |
| `kind` | text | No | — |
| `addon_id` | bigint | Yes | FK → addons.id |
| `request_id` | bigint | Yes | FK → requests.id |
| `status` | text | No | DEFAULT 'draft'::text |
| `revision` | integer | No | DEFAULT 1 |
| `expansion_id` | expansion_code (text) | Yes | — |
| `version_label` | text | Yes | — |
| `fields` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `created_at` | timestamptz | No | DEFAULT now() |
| `updated_at` | timestamptz | No | DEFAULT now() |
| `submitted_at` | timestamptz | Yes | — |
| `decided_at` | timestamptz | Yes | — |
| `reviewer_id` | bigint | Yes | FK → accounts.id |
| `withdrawn_at` | timestamptz | Yes | — |

The owner can edit allowed fields while the submission is draft, with revision control. The server adds the decision when resolving it; it is forbidden in draft/pending. State, expansion, version and relationships remain in their columns. The examples are complete fields values for two different rows; they do not include files, which are linked through asset_links.

**Example of `fields` — Addon draft: kind = addon, status = draft**

```json
{
  "name": "Raid Helper",
  "categoryId": "pve",
  "summary": "Raid alerts and ability tracking.",
  "description": "## Raid Helper\nDisplays configurable alerts during encounters.",
  "classIds": [
    "druid"
  ],
  "professionIds": [],
  "roles": [
    "healer",
    "dps"
  ],
  "tags": [
    "raid",
    "alerts"
  ],
  "originalAuthor": "Example Author",
  "sourceUrl": "https://example.invalid/raid-helper",
  "clientPatch": "3.3.5a",
  "title": "First release",
  "changelog": "Initial version with configurable alerts.",
  "rulesAccepted": false,
  "testedConfirmed": false
}
```

**Example of `fields` — Rejected version: kind = version, status = rejected**

```json
{
  "summary": "Updated raid alerts.",
  "clientPatch": "3.3.5a",
  "title": "Alert update",
  "changelog": "Adds alerts for new encounters.",
  "rulesAccepted": true,
  "testedConfirmed": true,
  "decision": {
    "ruleId": "credit",
    "reason": "The original author of the included resources must be identified."
  }
}
```

## name_claims — Global name uniqueness (4)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `key` | text | No | PK |
| `addon_id` | bigint | Yes | FK → addons.id |
| `submission_id` | bigint | Yes | FK → submissions.id |
| `kinds` | text[] | No | — |

This table has no JSONB columns. kinds is a native PostgreSQL array (text[]), for example ARRAY['name', 'slug']; it is not JSONB.

## comments — Comments and ratings (9)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `addon_id` | bigint | No | FK → addons.id |
| `author_id` | bigint | No | FK → accounts.id |
| `body` | text | No | — |
| `rating` | smallint | Yes | — |
| `expansion_id` | expansion_code (text) | Yes | — |
| `created_at` | timestamptz | No | DEFAULT now() |
| `updated_at` | timestamptz | No | DEFAULT now() |
| `withdrawn_at` | timestamptz | Yes | — |

This table has no JSONB columns. The comment is stored as text in body and the rating as a number in rating.

## interactions — Binary states per user and target (7)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `actor_id` | bigint | No | FK → accounts.id |
| `kind` | text | No | — |
| `addon_id` | bigint | Yes | FK → addons.id |
| `target_user_id` | bigint | Yes | FK → accounts.id |
| `request_id` | bigint | Yes | FK → requests.id |
| `created_at` | timestamptz | No | DEFAULT now() |

This table has no JSONB columns. Each favorite, follow or vote is a row with its type and user/target keys.

## reports — Moderation cases (9)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `type` | text | No | — |
| `addon_id` | bigint | Yes | FK → addons.id |
| `submission_id` | bigint | Yes | FK → submissions.id |
| `comment_id` | bigint | Yes | FK → comments.id |
| `resolution` | text | Yes | — |
| `closed_at` | timestamptz | Yes | — |
| `details` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `created_at` | timestamptz | No | DEFAULT now() |

Metadata for a moderation case. Resolution and its date are in resolution and closed_at; reporters' text is in report_entries. The server or authorized staff sets this data. autoQuarantined does not replace the actual file state or content takedown.

**Example of `details` — Resolved case: resolution = warn**

```json
{
  "ruleId": "credit",
  "resolutionNote": "Credits for the original author have been requested.",
  "autoQuarantined": false
}
```

## report_entries — Reporters and text (5)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `report_id` | bigint | No | FK → reports.id |
| `reporter_id` | bigint | Yes | FK → accounts.id |
| `body` | text | No | — |
| `created_at` | timestamptz | No | DEFAULT now() |

This table has no JSONB columns. body stores the text provided by each reporter and report_id identifies the case.

## messages — Contact and message delivery (10)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `kind` | text | No | — |
| `sender_id` | bigint | Yes | FK → accounts.id |
| `recipient_id` | bigint | Yes | FK → accounts.id |
| `parent_id` | bigint | Yes | FK → messages.id |
| `status` | text | No | — |
| `data` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `created_at` | timestamptz | No | DEFAULT now() |
| `completed_at` | timestamptz | Yes | — |
| `available_at` | timestamptz | No | DEFAULT now() |

Each variant corresponds to a separate row. The user provides contact content; the server resolves recipients and manages attempts, deduplication and delivery. Attachments are linked through asset_links. Internal delivery keys must not be exposed to the user.

**Example of `data` — Received contact: kind = contact, status = open**

```json
{
  "recipientKey": "moderation",
  "topicId": "broken",
  "senderName": "Lucía",
  "senderEmail": "lucia@example.invalid",
  "subject": "Problem installing Raid Helper",
  "body": "After installing the addon, an error appears when opening its settings window.",
  "estimatedReplyAt": "2026-10-10T12:00:00Z"
}
```

**Example of `data` — Pending notification: kind = notification, status = queued**

```json
{
  "toEmail": "lucia@example.invalid",
  "template": "submission-approved",
  "context": {
    "submissionId": 501,
    "addonName": "Raid Helper"
  },
  "attempts": 0,
  "lastError": null,
  "providerMessageId": null,
  "dedupeKey": "submission-approved:501:account:42"
}
```

## asset_links — File relationships and order (8)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `asset_id` | bigint | No | FK → assets.id |
| `submission_id` | bigint | Yes | FK → submissions.id |
| `addon_id` | bigint | Yes | FK → addons.id |
| `message_id` | bigint | Yes | FK → messages.id |
| `slot` | text | No | — |
| `position` | smallint | No | DEFAULT 0 |
| `data` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |

alt describes an image for accessibility and can come from an authorized content edit. The file reference and its order remain in asset_id and position. A ZIP link without extra metadata can use {}.

**Example of `data` — Screenshot alternative text: slot = screenshot**

```json
{
  "alt": "Addon window with active ability timers."
}
```

## events — Time-based history (7)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `kind` | text | No | — |
| `actor_id` | bigint | Yes | FK → accounts.id |
| `submission_id` | bigint | Yes | FK → submissions.id |
| `client_key` | bytea | Yes | — |
| `occurred_at` | timestamptz | No | DEFAULT clock_timestamp() |
| `data` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |

The server records these facts; they are not editable profile fields. Each example corresponds to a different event. The actor, downloaded version and time are in actor_id, submission_id and occurred_at. Historical JSON IDs and values serve auditing; they do not replace foreign keys for current relationships.

**Example of `data` — Download: kind = download**

```json
{
  "counted": true,
  "city": null,
  "packId": null
}
```

**Example of `data` — Account state change recorded in the audit log**

```json
{
  "actorRole": "moderator",
  "targetKind": "account",
  "targetId": 42,
  "targetLabel": "lucia",
  "detail": "Temporary suspension applied by moderation.",
  "before": {
    "suspendedUntil": null
  },
  "after": {
    "suspendedUntil": "2026-10-10T12:00:00Z"
  }
}
```

## settings — Configuration and operational snapshots (3)

| Column | Type | Nullable | Key / default value |
|---|---|---|---|
| `key` | text | No | PK |
| `value` | object_json (jsonb) | No | — |
| `updated_at` | timestamptz | No | DEFAULT now() |

Each key identifies a row with its own value object; configurations are not grouped into one row. The backend manages them. Pack slugs must be validated against the catalog; support figures come from an external source or administrative import. All values in these examples are fictional.

**Example of `value` — Pack composition: key = packDefinitions**

```json
{
  "packs": [
    {
      "id": "healer-starter",
      "name": "Healer starter pack",
      "description": "Tools to start healing in raids.",
      "imageUrl": "/assets/packs/healer-starter.webp",
      "membersByExpansion": {
        "wotlk": [
          "raid-helper",
          "healing-frames"
        ]
      }
    }
  ]
}
```

**Example of `value` — Monthly support summary: key = support:2026-10**

```json
{
  "month": "2026-10",
  "raised": 125.5,
  "goal": 500,
  "currency": "EUR",
  "contributors": 7,
  "source": "manual-import",
  "asOf": "2026-10-09T12:00:00Z"
}
```

## Unique constraints in addition to primary keys

- accounts: lower(username), lower(email).
- auth_tokens: public_id, session hash, unconsumed verification per account and unconsumed invitation per email.
- assets: external_id and storage_key.
- addons: slug; normalized name coordinated with name_claims in a transaction.
- requests: normalized name+expansion while active.
- submissions: addon+expansion+version while pending/approved, including withdrawn approved submissions.
- interactions: actor+type+target, with NULLS NOT DISTINCT.
- reports: type+target for open cases.
- report_entries: case+reporter, including a single scanner entry (NULL).
- messages: dedupeKey when present.
- asset_links: parent+slot+position; parent+asset.

The comments table can retain historical records. The public API allows only PUT to create or update the user's own review: transactional locking and selection of the existing review prevent duplicates. The current_ratings view keeps a single current rating.
