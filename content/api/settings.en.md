# Operational settings

Each property below is a settings key. Save only its object as a file for bin/settings.php. Replace names, slugs, amounts and mailboxes with real values; these are samples.

Each section corresponds to a settings key. Save its JSON content in a file and load it from api/:

    php bin/settings.php KEY file.json

## packDefinitions

```json
{
  "tank": {
    "name": "Tank kit",
    "description": "Selected addons for tanks",
    "membersByExpansion": {
      "wotlk": [
        "example-addon"
      ]
    }
  }
}
```

## contactRecipients

Verified, non-banned founders have individual cards using their current username and account email.
The shared Moderators card sends to every verified, non-banned moderator and founder (`access_level` 1 or 2).
It is available even when the only eligible account is a founder. No mailbox setting is needed for this group.
This setting adds optional mailboxes; only entries with a valid email are listed.
Keys use 1–64 lowercase letters, numbers, underscores or hyphens, starting with a letter.
`name` and optional `description` are public; email addresses stay private.
`replyHours` accepts 1–720 and defaults to 24. Staff estimates default to 24 hours.
Use IDs from `GET /api/contact`: `account:{id}`, `group:moderation` or `mailbox:{key}`.
Group delivery creates one ticket and a separate queued notification for each eligible account.

```json
{
  "moderation": {
    "name": "Moderation",
    "description": "Community reports and moderation questions.",
    "email": "replace@example.invalid",
    "replyHours": 24
  },
  "authors": {
    "name": "Authors",
    "email": "replace@example.invalid",
    "replyHours": 48
  }
}
```

## support

```json
{
  "month": "2026-10",
  "raised": 0,
  "goal": 0,
  "currency": "USD",
  "contributors": 0
}
```

## calculationRules

```json
{
  "pointsPerVersion": 100,
  "pointsPerComment": 5,
  "pointsPerLevel": 100,
  "threatPerAction": 25
}
```

## achievementDefinitions

```json
{}
```

## communityAchievements

```json
{
  "items": []
}
```

## taxonomy

```json
{
  "labels": {},
  "expansions": {
    "wotlk": {
      "name": "Wrath of the Lich King",
      "patch": "3.3.5a"
    }
  }
}
```

