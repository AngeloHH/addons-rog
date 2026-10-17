# API unificada

La aplicación expone un único formato de API. Se conservaron las rutas elegidas: `/api/auth/session`, `/api/profiles/{username}` y las familias `/api/submissions`, `/api/admin/submissions`, `/api/uploads`, `/api/media` y `/api/downloads`.

## Respuestas y solicitudes

- Éxito JSON: `{"ok": true, ...datos}`. Error: `{"ok": false, "error": {"code": "...", "message": "...", "requestId": "..."}}`.
- Los IDs de entidades son números; los ZIP temporales y los enlaces públicos de archivos usan UUID.
- Los listados devuelven `items` y `page` con `number`, `size`, `total` y `totalPages`.
- La sesión y el token CSRF se obtienen mediante `GET /api/auth/session`. Las escrituras conservan las cookies y envían `X-CSRF-Token`; el navegador aporta `Origin`.
- La instalación utiliza su clave temporal propia.
- Las rutas no admiten el sufijo `.php`.

## Reseñas y taxonomía

`PUT /api/addons/{slug}/my-comment` recibe `content` (1–4000 caracteres), `rating` (1–5) y `expansionId` con una versión publicada del addon. Devuelve 201 al crear y 200 al actualizar. Reutiliza la reseña del mismo usuario y addon, incluso ante solicitudes simultáneas. `GET /api/addons/{slug}/comments` lista las reseñas vigentes; no se crean comentarios sin puntuación desde la API.

`GET /api/taxonomy` reúne `expansions`, los grupos `category`, `classes`, `professions`, `role` y los límites de subida en `limits`: `summaryMax`, `screenshotsMax`, `addonZipMaxBytes` e `imageMaxBytes`.

Los borradores usan los campos de `/api/submissions`: `uploadId`, `name`, `version`, `summary`, `description`, etc. `revision` permite detectar una edición obsoleta. Las confirmaciones `rulesAccepted` y `testedConfirmed` deben estar guardadas antes de enviar a revisión.

Las descargas de `/api/downloads/{versionId}` redirigen al enlace firmado de `/api/download-files/{versionId}`. Los archivos conservan sus controles de acceso y caducidad.

## Rutas complementarias

Se conservaron las operaciones que no tenían alternativa. Por ello, la ficha principal usa `/api/profiles/{username}`, mientras que sus listados y seguimiento siguen en `/api/users/{username}/addons`, `/journal`, `/reviews` y `/follow`.

Esta unificación no cambia tablas ni borra datos. La especificación completa de campos está en [referencia de la API](reference.es.md).

## Inventario completo

| Método | Ruta | Acceso |
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
