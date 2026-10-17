# Diccionario completo de columnas

Generado del esquema instalado en PostgreSQL en memoria. **15 tablas, 128 columnas.** Este inventario no incluye columnas de vistas. Las definiciones vigentes están en [los modelos](../../api/src/Models/) y los objetos SQL de PostgreSQL en [database/objects.sql](../../api/database/objects.sql).

Debajo de cada tabla se muestran ejemplos de sus columnas JSONB o se indica que no tiene ninguna.
**Cada bloque JSON representa el valor de una columna, no la fila completa ni una petición de
la API.** `object_json` es un dominio sobre `jsonb` que exige un objeto; la validación de sus
claves y tipos internos corresponde a los servicios de la API. Los datos, IDs, fechas,
direcciones y recursos son ficticios e ilustrativos. Los ejemplos no añaden columnas ni
cambian los permisos de edición.

## accounts — Identidad y cuenta (11)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `username` | text | No | — |
| `email` | text | No | — |
| `password_hash` | text | No | — |
| `verified_at` | timestamptz | Sí | — |
| `access_level` | smallint | No | DEFAULT 0; CHECK IN (0,1,2); 0 usuario, 1 moderador, 2 fundador |
| `banned_at` | timestamptz | Sí | — |
| `suspended_until` | timestamptz | Sí | — |
| `profile` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `created_at` | timestamptz | No | DEFAULT now() |
| `last_seen_at` | timestamptz | Sí | — |

`access_level` expresa permisos administrativos: 0 = usuario regular, 1 = moderador, 2 = fundador. No admite NULL. El rol público `author` se deriva de publicaciones disponibles y no eleva este nivel. Solo un fundador puede asignar o retirar privilegios mediante una operación autorizada; el registro siempre usa 0.

Las escrituras de preferencias del perfil se limitan a los campos admitidos por sus endpoints
autorizados. El servidor registra los términos aceptados y concede logros y títulos.
Las definiciones de premios se guardan en la configuración; los identificadores del ejemplo
son ficticios.

**Ejemplo de `profile` — Perfil con preferencias y premios históricos**

```json
{
  "color": "#5b8def",
  "expansionId": "wotlk",
  "realm": "Reino de ejemplo",
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

## auth_tokens — Sesiones, verificaciones e invitaciones (10)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `public_id` | uuid | No | DEFAULT gen_random_uuid() |
| `purpose` | text | No | — |
| `account_id` | bigint | Sí | FK → accounts.id |
| `email` | text | Sí | — |
| `secret_hash` | bytea | No | — |
| `expires_at` | timestamptz | No | — |
| `consumed_at` | timestamptz | Sí | — |
| `data` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `created_at` | timestamptz | No | DEFAULT now() |

Son tres filas alternativas según la columna purpose; no se guardan las tres variantes juntas ni se añade un contenedor session/verification/invitation. El servidor gestiona estos datos. El secreto se representa mediante secret_hash en su columna, nunca como texto dentro de data.

**Ejemplo de `data` — Sesión: purpose = session**

```json
{
  "remember": true,
  "csrfVersion": 1
}
```

**Ejemplo de `data` — Verificación: purpose = verification**

```json
{
  "failedAttempts": 0,
  "resendAvailableAt": "2026-10-09T12:01:00Z",
  "emailAtIssue": "lucia@example.invalid",
  "invitationId": null
}
```

**Ejemplo de `data` — Invitación: purpose = invitation**

```json
{
  "inviterId": 7,
  "message": "Invitación al equipo de moderación.",
  "acceptedBy": null
}
```

## assets — Metadatos de archivos (12)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `external_id` | uuid | No | DEFAULT gen_random_uuid() |
| `owner_id` | bigint | Sí | FK → accounts.id |
| `kind` | text | No | — |
| `storage_key` | text | No | — |
| `filename` | text | No | — |
| `mime_type` | text | No | — |
| `size_bytes` | bigint | No | — |
| `status` | text | No | — |
| `metadata` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `expires_at` | timestamptz | Sí | — |
| `created_at` | timestamptz | No | DEFAULT now() |

El servidor y el analizador de archivos generan estos metadatos. Cada fila representa un archivo: las dimensiones corresponden a imágenes y folders/tocInterfaces al ZIP. La decisión de disponibilidad se guarda en la columna status.

**Ejemplo de `metadata` — Archivo ZIP: kind = zip**

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

**Ejemplo de `metadata` — Captura de pantalla: kind = screenshot**

```json
{
  "width": 1920,
  "height": 1080,
  "warnings": [],
  "scannerVersion": "example-scanner-1.0",
  "scannedAt": "2026-10-09T12:06:00Z"
}
```

## addons — Ficha publicada (8)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `slug` | text | No | — |
| `owner_id` | bigint | No | FK → accounts.id |
| `name` | text | No | — |
| `category_id` | category_code (text) | No | — |
| `details` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `published_at` | timestamptz | No | DEFAULT now() |
| `withdrawn_at` | timestamptz | Sí | — |

Contenido de la ficha publicada. Se obtiene del envío aprobado y de las ediciones autorizadas que se implementen. description es el texto canónico de este ejemplo. Valoraciones, descargas y compatibilidades se calculan desde sus fuentes; no se guardan aquí como autoridad.

**Ejemplo de `details` — Ficha de un addon**

```json
{
  "summary": "Avisos de banda y seguimiento de habilidades.",
  "description": "## Raid Helper\nMuestra avisos configurables durante los encuentros.",
  "features": [
    "Avisos de habilidades",
    "Temporizadores configurables"
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
  "originalAuthor": "Autora de ejemplo",
  "sourceUrl": "https://example.invalid/raid-helper"
}
```

## requests — Solicitudes de la comunidad (9)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `author_id` | bigint | No | FK → accounts.id |
| `addon_name` | text | No | — |
| `expansion_id` | expansion_code (text) | No | — |
| `details` | text | No | — |
| `state` | text | No | DEFAULT 'open'::text |
| `fulfilled_addon_id` | bigint | Sí | FK → addons.id |
| `created_at` | timestamptz | No | DEFAULT now() |
| `updated_at` | timestamptz | No | DEFAULT now() |

Esta tabla no contiene columnas JSONB. details es texto libre (text), no un objeto JSON.

## submissions — Borradores, decisiones y versiones (16)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `owner_id` | bigint | No | FK → accounts.id |
| `kind` | text | No | — |
| `addon_id` | bigint | Sí | FK → addons.id |
| `request_id` | bigint | Sí | FK → requests.id |
| `status` | text | No | DEFAULT 'draft'::text |
| `revision` | integer | No | DEFAULT 1 |
| `expansion_id` | expansion_code (text) | Sí | — |
| `version_label` | text | Sí | — |
| `fields` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `created_at` | timestamptz | No | DEFAULT now() |
| `updated_at` | timestamptz | No | DEFAULT now() |
| `submitted_at` | timestamptz | Sí | — |
| `decided_at` | timestamptz | Sí | — |
| `reviewer_id` | bigint | Sí | FK → accounts.id |
| `withdrawn_at` | timestamptz | Sí | — |

El propietario puede editar los campos permitidos mientras el envío sea draft, con control de revision. La decisión la añade el servidor al resolverlo; está prohibida en draft/pending. Estado, expansión, versión y relaciones siguen en sus columnas. Los ejemplos son valores completos de fields para dos filas distintas; no incluyen los archivos, que se vinculan mediante asset_links.

**Ejemplo de `fields` — Borrador de un addon: kind = addon, status = draft**

```json
{
  "name": "Raid Helper",
  "categoryId": "pve",
  "summary": "Avisos de banda y seguimiento de habilidades.",
  "description": "## Raid Helper\nMuestra avisos configurables durante los encuentros.",
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
  "originalAuthor": "Autora de ejemplo",
  "sourceUrl": "https://example.invalid/raid-helper",
  "clientPatch": "3.3.5a",
  "title": "Primera publicación",
  "changelog": "Versión inicial con avisos configurables.",
  "rulesAccepted": false,
  "testedConfirmed": false
}
```

**Ejemplo de `fields` — Versión rechazada: kind = version, status = rejected**

```json
{
  "summary": "Actualización de los avisos de banda.",
  "clientPatch": "3.3.5a",
  "title": "Actualización de avisos",
  "changelog": "Añade avisos para nuevos encuentros.",
  "rulesAccepted": true,
  "testedConfirmed": true,
  "decision": {
    "ruleId": "credit",
    "reason": "Falta identificar al autor original de los recursos incluidos."
  }
}
```

## name_claims — Unicidad global de nombres (4)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `key` | text | No | PK |
| `addon_id` | bigint | Sí | FK → addons.id |
| `submission_id` | bigint | Sí | FK → submissions.id |
| `kinds` | text[] | No | — |

Esta tabla no contiene columnas JSONB. kinds es un array nativo de PostgreSQL (text[]), por ejemplo ARRAY['name', 'slug']; no es JSONB.

## comments — Comentarios y puntuaciones (9)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `addon_id` | bigint | No | FK → addons.id |
| `author_id` | bigint | No | FK → accounts.id |
| `body` | text | No | — |
| `rating` | smallint | Sí | — |
| `expansion_id` | expansion_code (text) | Sí | — |
| `created_at` | timestamptz | No | DEFAULT now() |
| `updated_at` | timestamptz | No | DEFAULT now() |
| `withdrawn_at` | timestamptz | Sí | — |

Esta tabla no contiene columnas JSONB. El comentario se guarda como texto en body y la puntuación como número en rating.

## interactions — Estados binarios por usuario y destino (7)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `actor_id` | bigint | No | FK → accounts.id |
| `kind` | text | No | — |
| `addon_id` | bigint | Sí | FK → addons.id |
| `target_user_id` | bigint | Sí | FK → accounts.id |
| `request_id` | bigint | Sí | FK → requests.id |
| `created_at` | timestamptz | No | DEFAULT now() |

Esta tabla no contiene columnas JSONB. Cada favorito, seguimiento o voto es una fila con su tipo y las claves de usuario/destino.

## reports — Casos de moderación (9)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `type` | text | No | — |
| `addon_id` | bigint | Sí | FK → addons.id |
| `submission_id` | bigint | Sí | FK → submissions.id |
| `comment_id` | bigint | Sí | FK → comments.id |
| `resolution` | text | Sí | — |
| `closed_at` | timestamptz | Sí | — |
| `details` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `created_at` | timestamptz | No | DEFAULT now() |

Metadatos de un caso de moderación. La resolución y su fecha están en resolution y closed_at; los textos de los denunciantes están en report_entries. El servidor o el personal autorizado establece estos datos. autoQuarantined no sustituye el estado real del archivo o la retirada del contenido.

**Ejemplo de `details` — Caso resuelto: resolution = warn**

```json
{
  "ruleId": "credit",
  "resolutionNote": "Se ha solicitado añadir los créditos del autor original.",
  "autoQuarantined": false
}
```

## report_entries — Denunciantes y textos (5)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `report_id` | bigint | No | FK → reports.id |
| `reporter_id` | bigint | Sí | FK → accounts.id |
| `body` | text | No | — |
| `created_at` | timestamptz | No | DEFAULT now() |

Esta tabla no contiene columnas JSONB. body guarda el texto aportado por cada denunciante y report_id identifica el caso.

## messages — Contacto y entrega de mensajes (10)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `kind` | text | No | — |
| `sender_id` | bigint | Sí | FK → accounts.id |
| `recipient_id` | bigint | Sí | FK → accounts.id |
| `parent_id` | bigint | Sí | FK → messages.id |
| `status` | text | No | — |
| `data` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |
| `created_at` | timestamptz | No | DEFAULT now() |
| `completed_at` | timestamptz | Sí | — |
| `available_at` | timestamptz | No | DEFAULT now() |

Cada variante corresponde a una fila distinta. El usuario aporta el contenido del contacto; el servidor resuelve destinatarios y gestiona intentos, deduplicación y entrega. Los adjuntos se vinculan mediante asset_links. No se deben exponer al usuario las claves internas de entrega.

**Ejemplo de `data` — Contacto recibido: kind = contact, status = open**

```json
{
  "recipientKey": "moderation",
  "topicId": "broken",
  "senderName": "Lucía",
  "senderEmail": "lucia@example.invalid",
  "subject": "Problema al instalar Raid Helper",
  "body": "Al instalar el addon aparece un error al abrir la ventana de configuración.",
  "estimatedReplyAt": "2026-10-10T12:00:00Z"
}
```

**Ejemplo de `data` — Notificación pendiente: kind = notification, status = queued**

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

## asset_links — Relaciones y orden de archivos (8)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `asset_id` | bigint | No | FK → assets.id |
| `submission_id` | bigint | Sí | FK → submissions.id |
| `addon_id` | bigint | Sí | FK → addons.id |
| `message_id` | bigint | Sí | FK → messages.id |
| `slot` | text | No | — |
| `position` | smallint | No | DEFAULT 0 |
| `data` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |

alt describe una imagen para accesibilidad y puede proceder de una edición autorizada del contenido. La referencia al archivo y su orden permanecen en asset_id y position. Un vínculo ZIP sin metadatos adicionales puede usar {}.

**Ejemplo de `data` — Texto alternativo de una captura: slot = screenshot**

```json
{
  "alt": "Ventana del addon con los temporizadores de habilidades activos."
}
```

## events — Historial temporal (7)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `id` | bigint | No | PK identity |
| `kind` | text | No | — |
| `actor_id` | bigint | Sí | FK → accounts.id |
| `submission_id` | bigint | Sí | FK → submissions.id |
| `client_key` | bytea | Sí | — |
| `occurred_at` | timestamptz | No | DEFAULT clock_timestamp() |
| `data` | object_json (jsonb) | No | DEFAULT '{}'::jsonb |

El servidor registra estos hechos; no son campos editables del perfil. Cada ejemplo corresponde a un evento distinto. El actor, la versión descargada y el momento están en actor_id, submission_id y occurred_at. Los IDs y valores históricos del JSON sirven para auditoría, no sustituyen claves foráneas de relaciones vigentes.

**Ejemplo de `data` — Descarga: kind = download**

```json
{
  "counted": true,
  "city": null,
  "packId": null
}
```

**Ejemplo de `data` — Cambio de estado de una cuenta registrado en auditoría**

```json
{
  "actorRole": "moderator",
  "targetKind": "account",
  "targetId": 42,
  "targetLabel": "lucia",
  "detail": "Suspensión temporal aplicada por moderación.",
  "before": {
    "suspendedUntil": null
  },
  "after": {
    "suspendedUntil": "2026-10-10T12:00:00Z"
  }
}
```

## settings — Configuración y snapshots operativos (3)

| Columna | Tipo | Admite NULL | Clave / valor inicial |
|---|---|---|---|
| `key` | text | No | PK |
| `value` | object_json (jsonb) | No | — |
| `updated_at` | timestamptz | No | DEFAULT now() |

Cada key identifica una fila con su propio objeto value; no se agrupan todas las configuraciones en una sola fila. Las administra el backend. Los slugs de packs deben validarse contra el catálogo; las cifras de apoyo proceden de una fuente externa o una carga administrativa. Todos los valores de estos ejemplos son ficticios.

**Ejemplo de `value` — Composición de packs: key = packDefinitions**

```json
{
  "packs": [
    {
      "id": "healer-starter",
      "name": "Pack inicial de sanación",
      "description": "Herramientas para empezar a sanar en banda.",
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

**Ejemplo de `value` — Resumen mensual de apoyo: key = support:2026-10**

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

## Unicidades que complementan las PK

- accounts: lower(username), lower(email).
- auth_tokens: public_id, hash de sesión, verificación no consumida por cuenta e invitación no consumida por email.
- assets: external_id y storage_key.
- addons: slug; nombre normalizado coordinado con name_claims en transacción.
- requests: nombre normalizado+expansión mientras esté activa.
- submissions: addon+expansión+versión mientras pending/approved, incluyendo aprobadas retiradas.
- interactions: actor+tipo+destino, con NULLS NOT DISTINCT.
- reports: tipo+destino para casos abiertos.
- report_entries: caso+reportante, incluyendo una sola entrada de scanner (NULL).
- messages: dedupeKey cuando existe.
- asset_links: padre+slot+posición; padre+asset.

La tabla comments puede conservar registros históricos. La API pública solo permite PUT para crear o actualizar la reseña propia: el bloqueo transaccional y la selección de la reseña existente evitan duplicados. La vista current_ratings mantiene una sola puntuación vigente.
