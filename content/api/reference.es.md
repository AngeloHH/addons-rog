# Referencia de la API

Documentación de las rutas, campos y reglas de la API de Addons RoG. La implementación está en los servicios PHP; esta referencia se mantiene junto con los cambios del código.

Los datos que utilizan las pruebas están en el [inventario de rutas](../../api/tests/fixtures/routes.json) y los [tipos de respuesta](../../api/tests/fixtures/response-schemas.json). No se leen desde esta documentación.

## Información general

- API: Addons RoG, versión 3.0.0.
- Prefijo de las rutas: `/api`.
- Las rutas no llevan `.php`. El backend PHP utiliza un controlador de entrada.
- En desarrollo, el proxy de Vite quita el prefijo `/api` antes de llegar a PHP; el router acepta ambas formas.
- Todas las respuestas JSON usan un único formato, con el campo `ok`.

## Convenciones

| Campo | Definición |
|---|---|
| typeNotation.string \| integer \| number \| boolean | Tipos JSON. integer no admite decimales. |
| typeNotation.string&lt;date-time&gt; | ISO 8601 en UTC con Z, p. ej. 2026-09-21T14:32:00Z. |
| typeNotation.string&lt;date&gt; | YYYY-MM-DD. |
| typeNotation.string&lt;url&gt; | URL absoluta o ruta que empieza por /. |
| typeNotation.'a' \| 'b' | Enumeracion cerrada: cualquier otro valor devuelve 422. |
| typeNotation.T \| null | La llave siempre esta presente; su valor puede ser null. |
| typeNotation.clave? | La llave es opcional: puede no enviarse o no devolverse. |
| typeNotation.T[] | Array de T. Los limites de tamano van entre parentesis. |
| typeNotation.#Nombre | Referencia a un esquema de 'schemas'. |
| typeNotation.(a..b) | Longitud (texto), tamano (array) o rango (numeros) inclusivo. |
| typeNotation.default | Valor que el servidor usa si el parametro no llega. |
| naming | camelCase en llaves y valores de enumeracion. Los ids de catalogo (expansiones, categorias, reglas, logros) son slugs en minusculas; los ids de entidades (usuarios, addons, versiones, solicitudes...) son integer. |
| numbers | Las cifras viajan en crudo: descargas como integer, tamanos en bytes, duraciones en horas o segundos, porcentajes 0..100. El formato (12.4k, 4.8 MB, 'hace 3 dias') lo pone el frontend. |
| presentation | La API no envia colores de expansion, severidad, estado o grupo ni textos traducibles de la interfaz: devuelve ids y el frontend los resuelve con src/config y i18n. La unica excepcion es el color de avatar del usuario, que es un dato de su cuenta. |
| requests.json | Los cuerpos JSON van con Content-Type: application/json; charset=utf-8. |
| requests.multipart | Las subidas de archivos van con multipart/form-data y se indican en request.contentType. |
| requests.unknownFields | Los campos desconocidos del cuerpo se ignoran. Un parametro de consulta con valor fuera de su enumeracion devuelve 422. |
| success.shape | { "ok": true, ...campos del endpoint } |
| success.statusCodes.200 | Lectura o modificacion correcta. |
| success.statusCodes.201 | Recurso creado. El cuerpo incluye el recurso. |
| success.binary | Las descargas responden 302 con Location hacia el archivo o 200 con el binario; en ambos casos llevan Content-Disposition: attachment; filename="...". |
| errors.shape | #Error |
| errors.statusCodes.400 | BAD_REQUEST: JSON mal formado o multipart ilegible. |
| errors.statusCodes.401 | UNAUTHENTICATED: hace falta sesion. |
| errors.statusCodes.403 | FORBIDDEN, EMAIL_NOT_VERIFIED, ACCOUNT_SUSPENDED o ACCOUNT_BANNED. |
| errors.statusCodes.404 | NOT_FOUND: el recurso no existe o el usuario no puede verlo (los recursos ajenos no se revelan). |
| errors.statusCodes.409 | Conflicto de estado: NAME_TAKEN, VERSION_EXISTS, INVALID_STATE, REVISION_MISMATCH, ALREADY_REPORTED. |
| errors.statusCodes.410 | GONE: el recurso temporal caduco (UPLOAD_EXPIRED, MEDIA_EXPIRED, VERIFICATION_EXPIRED). |
| errors.statusCodes.413 | FILE_TOO_LARGE. |
| errors.statusCodes.415 | UNSUPPORTED_MEDIA_TYPE. |
| errors.statusCodes.422 | VALIDATION_FAILED: error.fields lista los campos invalidos. |
| errors.statusCodes.429 | RATE_LIMITED: la cabecera Retry-After y error.retryAfterSeconds dicen cuando reintentar. |
| errors.statusCodes.500 | INTERNAL. |
| pagination.pageBased | query page (integer &gt;= 1, default 1) y pageSize (integer, limites por endpoint). La respuesta trae items y page: #Page. Una pagina mayor que totalPages devuelve items vacio, no error. |
| pagination.cursorBased | Solo en la actividad admin, que crece sin parar: query before (cursor opaco) y limit. La respuesta trae items y nextCursor (null al final). |
| idempotency | Los PUT de estado (voto, seguimiento, favorito) reciben el estado deseado y son idempotentes: repetir la misma peticion devuelve 200 con el mismo resultado y no cambia contadores. Los POST que crean recursos no son idempotentes; el frontend debe desactivar el boton mientras espera. |
| i18n | La API no traduce. Los textos que escriben los usuarios (descripciones, comentarios, solicitudes) se devuelven tal cual se escribieron. |

## Autenticación y permisos

| Campo | Definición |
|---|---|
| session.mechanism | Cookie de sesion rog_session: HttpOnly, Secure en produccion, SameSite=Lax, Path=/. El cuerpo nunca devuelve tokens. |
| session.creation | La crean POST /api/auth/login y POST /api/auth/verify (la verificacion inicia sesion). |
| session.lifetime | Con remember=true dura 30 dias y se renueva al usarse; con remember=false es de sesion del navegador y caduca tras 12 h sin actividad. |
| session.csrf | Las peticiones que modifican datos conservan la cookie de sesión y envían X-CSRF-Token. El servidor también verifica Origin. GET /api/auth/session proporciona el token. |
| session.discovery | El frontend llama a GET /api/auth/session al arrancar para saber quien es y pintar AuthBar, SiteHeader, subida y panel. |
| roles.player | Cuenta verificada. Descarga, comenta, valora, pide, vota, sigue y guarda favoritos. |
| roles.author | Player con al menos un addon publicado. Lo asigna el servidor automaticamente al aprobar su primer envio; no se asigna a mano. |
| roles.moderator | Revisa la cola, gestiona reportes, solicitudes y el estado de las cuentas player y author. |
| roles.founder | Todo lo del moderador, mas cambiar roles, invitar moderadores y banear. |
| accountStatus.active | Sin restricciones. |
| accountStatus.suspended | Durante suspendedUntil no puede subir archivos, guardar borradores ni enviar a revision. Puede iniciar sesion, descargar, comentar, pedir y votar (reglas, nivel Suspension). |
| accountStatus.banned | No puede iniciar sesion; login responde 403 ACCOUNT_BANNED y las sesiones abiertas se invalidan. |
| levels.public | No hace falta sesion. Si la hay, se rellenan los campos viewer. |
| levels.user | Sesion de una cuenta verificada que no esta baneada. |
| levels.uploader | Como user, pero con estado active (una cuenta suspendida recibe 403 ACCOUNT_SUSPENDED). |
| levels.staff | Rol moderator o founder. |
| levels.founder | Rol founder. |
| ownership | Los archivos temporales, la multimedia y los envios pertenecen a quien los crea. Cualquier intento de usar o leer recursos ajenos devuelve 404, salvo para staff en las rutas /api/admin. |

## Reglas de funcionamiento

| Campo | Definición |
|---|---|
| addonExpansionVersion.addon | Un addon es una ficha unica con slug inmutable, unico en todo el catalogo. El slug se genera del nombre al aprobar el primer envio y no cambia aunque cambie el nombre. |
| addonExpansionVersion.nameUniqueness | El nombre normalizado (minusculas, sin espacios ni signos) es unico entre todos los addons publicados y los envios pendientes de tipo addon, en cualquier expansion. Un mismo addon para otra expansion no es una ficha nueva: es una version nueva de la misma ficha (regla 'duplicates'). |
| addonExpansionVersion.aliases | Un addon puede tener alias (p. ej. 'dbm' para Deadly Boss Mods). El staff los gestiona fuera de esta API; check-name tambien comprueba alias. |
| addonExpansionVersion.version | Cada version pertenece a un addon y a una sola expansion (la que declara el .toc, regla 'version'). La combinacion (addon, expansion, numero de version) es unica. |
| addonExpansionVersion.compatibility | La compatibilidad de un addon es el conjunto de expansiones con al menos una version publicada. Para cada expansion hay una 'ultima version': la de releasedAt mas reciente en esa expansion. |
| addonExpansionVersion.canonicalExpansion | La expansion canonica de un addon es la de su version publicada mas reciente. La usan las listas sin filtro de expansion y la URL canonica /:lang/explore/{expansionId}/{categoryId}/{slug}. |
| addonExpansionVersion.listingExpansion | En las listas, cada addon aparece una sola vez. Con filtro de expansion, el item muestra la expansion filtrada (si hay varias, la de version mas reciente entre ellas) y la ultima version de esa expansion. Sin filtro, muestra la canonica. |
| addonExpansionVersion.download | Todo item de lista con boton de descarga lleva download (#Download) con el versionId concreto. El frontend no debe construir enlaces de descarga por su cuenta. |
| addonExpansionVersion.category | categoryIds permite varias categorias generales; categoryId es la primera y forma parte de la URL. Todas las categorias seleccionadas alimentan los filtros. Clases, profesiones y roles se guardan aparte (classIds, professionIds, roles). Los addons anteriores sin categoryIds conservan su categoryId como unica categoria. |
| requestLifecycle.states.open | Pedida y sin nadie trabajando en ella. |
| requestLifecycle.states.volunteered | Alguien guardo un borrador vinculado a la solicitud (requestId) o el staff lo vinculo. |
| requestLifecycle.states.inQueue | Hay un envio vinculado pendiente de revision. |
| requestLifecycle.states.fulfilled | Vinculada a un addon publicado (request.addon). Estado final. |
| requestLifecycle.states.closed | Cerrada por el staff sin cumplirse (duplicada, imposible, fuera de las reglas). Estado final. |
| requestLifecycle.transitions.0 | crear -&gt; open (POST /api/requests; el autor queda votando y siguiendo). |
| requestLifecycle.transitions.1 | open -&gt; volunteered: un usuario guarda un borrador con requestId, o el staff hace action=link con un envio en borrador. |
| requestLifecycle.transitions.2 | open \| volunteered -&gt; inQueue: se envia a revision un envio con requestId, o el staff hace action=link con un envio pendiente. |
| requestLifecycle.transitions.3 | inQueue -&gt; fulfilled: se aprueba el envio vinculado; request.addon apunta al addon publicado. |
| requestLifecycle.transitions.4 | open \| volunteered \| inQueue -&gt; fulfilled: el staff hace action=link con addonSlug (el addon ya estaba en el catalogo). |
| requestLifecycle.transitions.5 | inQueue -&gt; volunteered \| open: se rechaza el envio vinculado; vuelve a volunteered si queda otro borrador vinculado y a open si no. |
| requestLifecycle.transitions.6 | volunteered -&gt; open: caduca o se descarta el ultimo borrador vinculado. |
| requestLifecycle.transitions.7 | open \| volunteered -&gt; closed: el staff hace action=close. |
| requestLifecycle.transitions.8 | fulfilled y closed no tienen salida en esta version. |
| requestLifecycle.permissions | Crear, votar y seguir: user. link, ping y close: staff. Ningun usuario puede cambiar el estado directamente; los cambios salen de los envios o de las acciones del staff. |
| requestLifecycle.voting | Solo se vota en open, volunteered e inQueue. En fulfilled y closed el voto queda congelado: PUT vote responde 409 INVALID_STATE y los contadores no cambian. |
| requestLifecycle.following | Seguir es independiente del voto y funciona en cualquier estado. Quien sigue recibe aviso por email cuando cambia el estado (el aviso lo manda el servidor, no es un endpoint). |
| requestLifecycle.listing | GET /api/requests excluye closed por defecto; state=closed los devuelve. stateCounts incluye los cinco estados. |
| submissionLifecycle.states.draft | Borrador editable por su dueno. No lo ve el staff. |
| submissionLifecycle.states.pending | Enviado a revision. Nadie puede editarlo; aparece en la cola. |
| submissionLifecycle.states.approved | Publicado: creo un addon (kind=addon) o una version nueva (kind=version). |
| submissionLifecycle.states.rejected | Rechazado con motivo y regla. No se edita: para corregirlo se crea otro borrador. |
| submissionLifecycle.kinds.addon | Ficha nueva (addonSlug null). El nombre debe estar libre. |
| submissionLifecycle.kinds.version | Version nueva de un addon existente (addonSlug indicado). El nombre, categoria y ficha salen del addon; name se ignora. |
| submissionLifecycle.draftValidation | Guardar un borrador solo valida el formato de los campos enviados (tipos, longitudes, enumeraciones, ids existentes). Ningun campo es obligatorio y los checks pueden ser false. |
| submissionLifecycle.submitValidation | Enviar a revision exige todos los campos marcados 'required on submit' en #Submission, rulesAccepted=true, testedConfirmed=true, un ZIP sin avisos de severidad danger, nombre libre (kind=addon) y version inexistente para ese addon y expansion. Si falla, 422 con error.fields. |
| submissionLifecycle.expiry | Un borrador caduca a los 30 dias sin cambios (expiresAt). Al caducar se borran el y sus archivos temporales. Los envios pending, approved y rejected no caducan. |
| submissionLifecycle.concurrency | Cada envio tiene revision (integer). PATCH puede enviar revision; si no coincide, 409 REVISION_MISMATCH y el cliente debe recargar el borrador. |
| temporaryFiles.zip | POST /api/uploads crea un archivo temporal del usuario. Caduca a las 24 h si ningun borrador o envio lo referencia; mientras lo referencie un borrador vive lo que viva el borrador. Al aprobarse pasa a ser el archivo de la version publicada. |
| temporaryFiles.media | POST /api/media crea un icono o captura temporal con las mismas reglas de caducidad. Se vincula al envio por iconMediaId y screenshotMediaIds; al desvincularse (PATCH) vuelve a caducar a las 24 h. |
| temporaryFiles.ownership | Solo el dueno puede vincular sus archivos. Un id ajeno o caducado en un borrador devuelve 422 en ese campo (MEDIA_EXPIRED o UPLOAD_EXPIRED como mensaje del campo). |
| temporaryFiles.scan | El ZIP se analiza al subir (carpetas, .toc, ejecutables). El resultado viaja en #UploadFile; lo que el staff ve en la cola sale de ese analisis. |
| reportLifecycle.states | open -&gt; closed mediante warn, takedown o dismiss; closed -&gt; open mediante reopen. |
| reportLifecycle.severity | La asigna el servidor segun el tipo: file=critical, credit=high, comment=medium, listing=low. El escaner crea reportes file con reporter.id null y name 'scanner'. |
| reportLifecycle.effects.warn | Cierra el reporte y suma un aviso al subidor (sube threat). |
| reportLifecycle.effects.takedown | Cierra el reporte y retira la version o el addon del catalogo. |
| reportLifecycle.effects.dismiss | Cierra el reporte sin efectos. |
| reportLifecycle.effects.reopen | Reabre un reporte cerrado. No deshace un takedown. |
| reportLifecycle.threat | threat (0..100) lo calcula el servidor con los avisos, retiradas y suspensiones de la cuenta. Los tramos (warning 25, takedown 50, suspension 75, ban 100) los pinta el frontend. |

## Contenido del frontend

| Campo | Definición |
|---|---|
| description | Contenido que sigue en el frontend (src/config, i18n o content/) y no tiene endpoint. Son textos editoriales, traducciones o estilos. |
| items.0 | Slides del hero de la portada (homeHero.js) y textos del CTA de subida. |
| items.1 | Colores de expansion, severidad, estado, rol, grupo de actividad y calidad de ranura. |
| items.2 | Iconos de categorias (/assets/categorias/*.png) y su asignacion a cada id de categoria. |
| items.3 | Reglas, ejemplos, niveles de amenaza y avisos (rulesArticles, rulesConsequences) y el checker de reglas (rulesChecks). |
| items.4 | Guías y documentación en Markdown (content/guides/, content/development/ y content/api/) servidas por DocsPage. |
| items.5 | Linea de tiempo y fichas de fundadores de About. |
| items.6 | Etiquetas de roles, temas, placeholders y pistas de contacto. Las identidades de los destinatarios proceden de la API. |
| items.7 | Definiciones de logros y titulos (titulo, texto, requisito): la API solo devuelve el id, los puntos y el estado. |
| items.8 | Nombres de las ranuras del equipo del perfil, asignados por posicion. |
| items.9 | Recordatorios y limites de la subida (upload.js) que no dependen del servidor. Los limites reales los impone la API y figuran en este contrato. |
| items.10 | Footer (siteMeta), idiomas y busquedas populares (popularAddonSearches). |

## Tipos de datos

### Error

| Campo | Definición |
|---|---|
| ok | false |
| error.code | #ErrorCode |
| error.message | string (texto legible en ingles para logs; el frontend traduce por code) |
| error.fields? | Record&lt;string, string[]&gt; (solo en 422: campo -&gt; codigos de error, p. ej. {"email": ["INVALID_EMAIL"]}) |
| error.retryAfterSeconds? | integer (solo en 429) |
| error.details? | Record&lt;string, string&gt; (datos para continuar el flujo; hoy solo login con EMAIL_NOT_VERIFIED: verificationId y maskedEmail) |
| error.requestId | string |

### ErrorCode

'BAD_REQUEST' | 'UNAUTHENTICATED' | 'FORBIDDEN' | 'EMAIL_NOT_VERIFIED' | 'ACCOUNT_SUSPENDED' | 'ACCOUNT_BANNED' | 'INVALID_CREDENTIALS' | 'INVALID_CODE' | 'NOT_FOUND' | 'NAME_TAKEN' | 'VERSION_EXISTS' | 'INVALID_STATE' | 'REVISION_MISMATCH' | 'ALREADY_REPORTED' | 'VERIFICATION_EXPIRED' | 'UPLOAD_EXPIRED' | 'MEDIA_EXPIRED' | 'FILE_TOO_LARGE' | 'UNSUPPORTED_MEDIA_TYPE' | 'VALIDATION_FAILED' | 'RATE_LIMITED' | 'INTERNAL' | 'DRAFT_EXPIRED'

### Page

| Campo | Definición |
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

string (id de GET /api/taxonomy expansions; hoy 'wotlk' | 'mop' | 'wod' | 'bfa' | 'sl' | 'df' | 'tww')

### CategoryId

string (id del grupo 'category' de la taxonomia; hoy 'pve' | 'pvp' | 'dps' | 'healing' | 'tank' | 'gui' | 'map' | 'professions')

### ClassId

string (id del grupo 'classes'; p. ej. 'death-knight', 'druid')

### ProfessionId

string (id del grupo 'professions'; p. ej. 'alchemy', 'herbalism')

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

| Campo | Definición |
|---|---|
| id | integer |
| name | string |
| color | string (hex #rrggbb) |

### SessionUser

| Campo | Definición |
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

| Campo | Definición |
|---|---|
| versionId | integer |
| url | string&lt;url&gt; (/api/downloads/{versionId}) |
| fileName | string |
| size | integer (bytes) |

### AddonSummary

| Campo | Definición |
|---|---|
| id | integer |
| slug | string |
| name | string |
| iconUrl | string&lt;url&gt; |
| categoryId | #CategoryId |
| categoryIds | #CategoryId[] |
| expansionId | #ExpansionId (expansion de este listado; ver domain.addonExpansionVersion.listingExpansion) |
| version | string (ultima version en expansionId) |
| summary | string (1..120) |
| rating | number (0..5, un decimal) \| null (null sin valoraciones) |
| votes | integer (numero de valoraciones) |
| downloads | integer (total del addon, todas las versiones) |
| author | #UserRef (quien lo subio) |
| createdAt | string&lt;date-time&gt; (publicacion de la ficha) |
| updatedAt | string&lt;date-time&gt; (ultima version en expansionId) |
| download | #Download (ultima version en expansionId) |

### AddonUpdate

| Campo | Definición |
|---|---|
| ... | todos los campos de #AddonSummary |
| previousVersion | string \| null (version anterior en la misma expansion) |
| changelog | string (0..280, notas de la ultima version) |

### AddonVersion

| Campo | Definición |
|---|---|
| id | integer |
| number | string |
| title | string |
| notes | string |
| expansionId | #ExpansionId |
| clientPatch | string (p. ej. '8.3.7') |
| size | integer (bytes) |
| downloads | integer |
| author | #UserRef |
| releasedAt | string&lt;date-time&gt; |
| isLatest | boolean (la mas reciente del addon) |
| isLatestForExpansion | boolean |
| download | #Download |

### Compatibility

| Campo | Definición |
|---|---|
| expansionId | #ExpansionId |
| latestVersion | string |
| latestVersionId | integer |
| releasedAt | string&lt;date-time&gt; |
| versionCount | integer |

### AddonDetail

| Campo | Definición |
|---|---|
| ... | todos los campos de #AddonSummary |
| canonicalExpansionId | #ExpansionId |
| description | string (Markdown, 0..20000) |
| features | string[] (0..12) |
| screenshots | string&lt;url&gt;[] (0..4) |
| classIds | #ClassId[] |
| professionIds | #ProfessionId[] |
| roles | #RoleTag[] |
| tags | string[] (0..10) |
| languages | string[] (codigos ISO 639-1 en mayusculas, p. ej. 'EN') |
| licence | string \| null |
| originalAuthor | string \| null |
| sourceUrl | string&lt;url&gt; \| null |
| compatibility | #Compatibility[] (orden de la taxonomia) |
| versions | #AddonVersion[] (todas, por releasedAt descendente) |
| commentCount | integer |
| viewer.isFavorite | boolean |
| viewer.rating | integer (1..5) \| null (su valoracion vigente) |

### Comment

| Campo | Definición |
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

| Campo | Definición |
|---|---|
| id | #RoleTag |
| name | string |
| description | string |
| availability.0.expansionId | #ExpansionId |
| availability.0.addons.0.slug | string |
| availability.0.addons.0.name | string |
| availability.0.size | integer (bytes del ZIP del pack) |
| availability.0.downloadUrl | string&lt;url&gt; (/api/packs/{packId}/download?expansion={expansionId}) |

### Request

| Campo | Definición |
|---|---|
| id | integer |
| addonName | string (2..60) |
| expansionId | #ExpansionId |
| text | string (10..500) |
| state | #RequestState |
| votes | integer |
| followers | integer |
| replies | integer (0; no hay operacion para responder) |
| author | #UserRef |
| addon | { "slug": "string", "name": "string", "expansionId": "#ExpansionId", "categoryId": "#CategoryId" } \| null (solo en fulfilled) |
| submissionId | integer \| null (envio vinculado en volunteered e inQueue; solo visible para el staff y el dueno del envio) |
| createdAt | string&lt;date-time&gt; |
| updatedAt | string&lt;date-time&gt; |
| viewer | { "voted": "boolean", "following": "boolean" } \| null (null sin sesion) |

### UploadFile

| Campo | Definición |
|---|---|
| id | string (uuid) |
| fileName | string |
| size | integer (bytes) |
| sha256 | string (hex) |
| folders | string[] (carpetas de addon en la raiz del ZIP) |
| tocInterfaces | integer[] (valores ## Interface de los .toc) |
| detectedExpansionId | #ExpansionId \| null (deducida de ## Interface) |
| status | 'clean' \| 'warning' \| 'blocked' (blocked si hay algun aviso danger) |
| warnings | #ScanWarning[] |
| expiresAt | string&lt;date-time&gt; \| null (null cuando lo referencia un envio que no caduca) |

### ScanWarning

| Campo | Definición |
|---|---|
| code | 'EXECUTABLE_FOUND' \| 'NESTED_FOLDER' \| 'MISSING_TOC' \| 'OUTDATED_TOC' \| 'SAVED_VARIABLES' \| 'INTERFACE_MISMATCH' |
| severity | 'warning' \| 'danger' (solo EXECUTABLE_FOUND es danger) |
| path | string \| null (ruta dentro del ZIP) |
| message | string |

### Media

| Campo | Definición |
|---|---|
| id | integer |
| kind | 'icon' \| 'screenshot' |
| url | string&lt;url&gt; |
| width | integer |
| height | integer |
| size | integer (bytes) |
| expiresAt | string&lt;date-time&gt; \| null |

### Submission

| Campo | Definición |
|---|---|
| id | integer |
| status | #SubmissionStatus |
| kind | 'addon' \| 'version' (deducido de addonSlug) |
| revision | integer |
| addonSlug | string \| null (required on submit si kind=version) |
| requestId | integer \| null |
| upload | #UploadFile \| null (required on submit) |
| name | string (3..60) \| null (required on submit si kind=addon) |
| version | string (1..20, ^[0-9A-Za-z._-]+$) \| null (required on submit) |
| categoryId | #CategoryId \| null (required on submit si kind=addon) |
| categoryIds | #CategoryId[] (0..8; al menos una al enviar si kind=addon) |
| expansionId | #ExpansionId \| null (required on submit) |
| classIds | #ClassId[] (0..13) |
| professionIds | #ProfessionId[] (0..15) |
| roles | #RoleTag[] (0..3) |
| summary | string (1..120) \| null (required on submit si kind=addon) |
| description | string (Markdown, 0..20000) \| null |
| tags | string[] (0..10, cada una 2..24, minusculas) |
| originalAuthor | string (1..60) \| null |
| sourceUrl | string&lt;url&gt; (http/https, 1..300) \| null |
| icon | #Media \| null |
| screenshots | #Media[] (0..4) |
| rulesAccepted | boolean (true on submit) |
| testedConfirmed | boolean (true on submit) |
| createdAt | string&lt;date-time&gt; |
| updatedAt | string&lt;date-time&gt; |
| submittedAt | string&lt;date-time&gt; \| null |
| expiresAt | string&lt;date-time&gt; \| null (solo borradores) |
| decision | { "status": "'approved' \| 'rejected'", "ruleId": "#RuleId \| null", "reason": "string \| null", "decidedAt": "string&lt;date-time&gt;", "addonSlug": "string \| null" } \| null |
| estimatedReviewHours | integer \| null (solo en pending) |

### QueueItem

| Campo | Definición |
|---|---|
| id | integer (id del envio) |
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
| note | string \| null (texto libre del autor que el staff debe leer; hoy sale de la descripcion) |
| flag | { "severity": "'warning' \| 'danger'", "code": "#ScanWarning.code", "message": "string" } \| null (el aviso mas grave del analisis) |
| submittedAt | string&lt;date-time&gt; |

### ReportTarget

| Campo | Definición |
|---|---|
| kind | 'addon' \| 'version' \| 'comment' |
| id | integer |
| label | string (p. ej. 'AutoLoot Pro 2.1') |
| addonSlug | string \| null (para enlazar la ficha) |
| expansionId | #ExpansionId \| null |
| categoryId | #CategoryId \| null |

### Report

| Campo | Definición |
|---|---|
| id | integer |
| severity | 'critical' \| 'high' \| 'medium' \| 'low' |
| type | 'file' \| 'credit' \| 'listing' \| 'comment' |
| target | #ReportTarget |
| text | string |
| reporter | { "id": "integer \| null (null = escaner)", "name": "string" } |
| otherReporters | integer |
| autoQuarantined | boolean |
| uploader | #UserRef |
| ruleId | #RuleId \| null |
| closed | boolean |
| resolution | 'warn' \| 'takedown' \| 'dismiss' \| null |
| createdAt | string&lt;date-time&gt; |
| closedAt | string&lt;date-time&gt; \| null |

### AdminAccount

| Campo | Definición |
|---|---|
| id | integer |
| name | string |
| email | string |
| color | string (hex #rrggbb) |
| role | #Role |
| status | #AccountStatus |
| suspendedUntil | string&lt;date-time&gt; \| null |
| uploads | integer (envios aprobados) |
| downloads | integer (descargas de sus addons) |
| reports | integer (reportes recibidos) |
| threat | integer (0..100) |
| createdAt | string&lt;date-time&gt; |
| lastSeenAt | string&lt;date-time&gt; \| null |
| online | boolean |

### ActivityKind

'upload' | 'approve' | 'reject' | 'comment' | 'account' | 'request' | 'report' | 'delete' | 'ban' | 'suspend' | 'quarantine' | 'roleChange'

### ActivityEvent

| Campo | Definición |
|---|---|
| id | integer |
| kind | #ActivityKind |
| actor | { "id": "integer \| null (null = sistema)", "name": "string", "role": "#Role \| 'system'", "color": "string" } |
| target | { "kind": "'addon' \| 'version' \| 'submission' \| 'comment' \| 'request' \| 'report' \| 'account' \| 'rule'", "id": "integer \| null", "label": "string" } |
| detail | string \| null (texto libre, p. ej. motivo) |
| context | Record&lt;string, string \| number&gt; (datos para montar la frase: reviewHours, reportId, votes, size, folders, rating, reportedBy...) |
| createdAt | string&lt;date-time&gt; |

### AchievementState

| Campo | Definición |
|---|---|
| id | string (id de la definicion en el frontend) |
| points | integer |
| feat | boolean |
| completedAt | string&lt;date-time&gt; \| null |
| progress | { "current": "number", "max": "number" } \| null (null si ya esta completo) |

### Expansion

| Campo | Definición |
|---|---|
| id | #ExpansionId |
| abbr | string |
| name | string |
| patch | string |
| addonCount | integer |

## Rutas

### GET /api/setup/status

#### Solicitud

| Campo | Definición |
|---|---|
| auth | setup |
| notes | Ver [instalación del sitio](installation.es.md) para setup; las descargas de archivos devuelven contenido binario. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.description | Respuesta de InstallService::status |

### GET /api/setup/requirements

#### Solicitud

| Campo | Definición |
|---|---|
| auth | setup |
| notes | Ver [instalación del sitio](installation.es.md) para setup; las descargas de archivos devuelven contenido binario. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.description | Respuesta de InstallService::requirements |

### POST /api/setup/database

#### Solicitud

| Campo | Definición |
|---|---|
| auth | setup |
| notes | Ver [instalación del sitio](installation.es.md) para setup; las descargas de archivos devuelven contenido binario. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.description | Respuesta de InstallService::database |

### POST /api/setup/install

#### Solicitud

| Campo | Definición |
|---|---|
| auth | setup |
| notes | Ver [instalación del sitio](installation.es.md) para setup; las descargas de archivos devuelven contenido binario. |

#### Respuesta

| Campo | Definición |
|---|---|
| 201.description | Respuesta de InstallService::install |

### GET /api/default-icon.svg

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| notes | Operación complementaria de la API. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.description | Respuesta de CatalogService::default_icon |

### POST /api/auth/invitations/accept

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| notes | Operación complementaria de la API. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.description | Respuesta de AdminService::accept_invitation |

### GET /api/admin/activity/export

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| query.q? | string |
| query.group? | igual que /api/admin/activity |
| query.who? | igual que /api/admin/activity |
| query.range? | #Range (default '7d') |
| notes | Boton 'Exportar CSV'. Columnas: id, createdAt, kind, actorId, actorName, actorRole, targetKind, targetId, targetLabel, detail. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200 | text/csv; charset=utf-8. Content-Disposition: attachment; filename="rog-activity-{range}-{YYYYMMDD}.csv" |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### POST /api/auth/verify/resend

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| contentType | application/json |
| body.verificationId | string (uuid) |
| body.email? | string (email valido; si llega, cambia el email de la cuenta sin verificar antes de reenviar) |
| notes | Cubre los botones 'Reenviar' y 'Cambiar email' del formulario de verificacion. Invalida el codigo anterior. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.maskedEmail | string |
| 200.expiresAt | string&lt;date-time&gt; |
| 200.resendAvailableAt | string&lt;date-time&gt; |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE (la cuenta ya esta verificada) |
| errors.422 | VALIDATION_FAILED: fields.email (formato o EMAIL_TAKEN) |
| errors.429 | RATE_LIMITED (antes de resendAvailableAt) |

### POST /api/admin/invitations

#### Solicitud

| Campo | Definición |
|---|---|
| auth | founder |
| contentType | application/json |
| body.email | string (email valido) |
| body.role | 'moderator' |
| notes | Boton 'Invitar moderador'. Si el email ya tiene cuenta verificada, se le asigna el rol al aceptar desde el enlace; si no, el enlace lleva a /register con invitationToken. Caduca a los 7 dias. |

#### Respuesta

| Campo | Definición |
|---|---|
| 201.ok | true |
| 201.invitationId | integer |
| 201.expiresAt | string&lt;date-time&gt; |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.409 | INVALID_STATE (ya es staff o hay una invitacion vigente) |
| errors.422 | VALIDATION_FAILED |

### GET /api/addons/check-name

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| query.name | string (1..60) |
| query.expansion? | #ExpansionId |
| query.version? | string (1..20) |
| notes | Aviso en vivo de la subida (con debounce). Compara el nombre normalizado con nombres, alias y slugs de addons publicados y envios pending de tipo addon. Con expansion y version, dice ademas si esa version ya existe en el addon encontrado. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.available | boolean |
| 200.existing | { "slug": "string", "name": "string", "expansionId": "#ExpansionId (canonica)", "categoryId": "#CategoryId", "match": "'name' \| 'alias' \| 'slug'", "pending": "boolean (true si el nombre lo reserva un envio pending, sin ficha publica)" } \| null |
| 200.versionExists | boolean \| null (null si no se enviaron expansion y version o no hay existing publicado) |
| errors.401 | UNAUTHENTICATED |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/submissions

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| query.q? | string (1..80; nombre del addon o del autor) |
| query.kind? | 'addon' \| 'version' |
| query.flagged? | 'flagged' \| 'clean' |
| query.sort? | 'oldest' \| 'newest' \| 'flagged' \| 'size' (default 'oldest') |
| query.range? | #Range (default '7d'; solo afecta a decided) |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 20; el resumen usa 5) |
| notes | Cola de revision: solo envios pending. counts ignora q, kind y flagged. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.items | #QueueItem[] |
| 200.page | #Page |
| 200.counts.pending | integer |
| 200.counts.flagged | integer |
| 200.counts.oldestWaitHours | integer \| null (null con la cola vacia) |
| 200.decided.count | integer |
| 200.decided.averageHours | integer \| null |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/dashboard

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| query.range? | #Range (default '7d') |
| notes | Las cinco cifras del resumen. La variacion la calcula el frontend con value y previous. Los paneles del resumen reutilizan submissions, reports, requests, activity y stats con pageSize pequeno. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.kpis.downloads.value | integer |
| 200.kpis.downloads.previous | integer |
| 200.kpis.uploads.value | integer (envios aprobados en el periodo) |
| 200.kpis.uploads.previous | integer |
| 200.kpis.pendingReview.value | integer |
| 200.kpis.pendingReview.previous | integer (pendientes al empezar el periodo) |
| 200.kpis.pendingReview.oldestWaitHours | integer |
| 200.kpis.openReports.value | integer |
| 200.kpis.openReports.previous | integer |
| 200.kpis.openReports.critical | integer |
| 200.kpis.activePlayers.value | integer (cuentas con sesion en el periodo) |
| 200.kpis.activePlayers.previous | integer |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/accounts

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| query.q? | string (1..80; nombre o email) |
| query.role? | #Role |
| query.status? | #AccountStatus |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 10) |
| notes | roleCounts es el censo completo e ignora los filtros. El registro de la ficha sale de GET /api/admin/activity?actorId={id}&amp;limit=5. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.items | #AdminAccount[] (lastSeenAt descendente) |
| 200.page | #Page |
| 200.roleCounts | Record&lt;#Role, integer&gt; |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/activity

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| query.q? | string (1..80; actor u objetivo) |
| query.group? | 'uploads' \| 'comments' \| 'signUps' \| 'requests' \| 'reports' \| 'removals' |
| query.who? | 'all' \| 'staff' \| 'users' (default 'all'; staff = founder, moderator y sistema) |
| query.actorId? | integer (registro de una cuenta) |
| query.range? | #Range (default '7d') |
| query.before? | string (cursor) |
| query.limit? | integer (1..100, default 50; el resumen usa 5) |
| notes | Grupos: uploads = upload y approve; comments = comment; signUps = account; requests = request; reports = report; removals = delete, reject, ban, suspend y quarantine; roleChange solo sale sin filtro de grupo. groupCounts y topActors cubren el periodo entero e ignoran group, q y el cursor. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.items | #ActivityEvent[] (createdAt descendente) |
| 200.nextCursor | string \| null |
| 200.groupCounts | Record&lt;'uploads' \| 'comments' \| 'signUps' \| 'requests' \| 'reports' \| 'removals', integer&gt; |
| 200.topActors.0.actor | { "id": "integer \| null", "name": "string", "role": "#Role \| 'system'", "color": "string" } |
| 200.topActors.0.count | integer |
| 200.topActors.0.kindCounts | Record&lt;#ActivityKind, integer&gt; |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### POST /api/auth/register

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| contentType | application/json |
| body.username | string (3..24, ^[A-Za-z0-9_^]+$, unico sin distinguir mayusculas) |
| body.email | string (email valido, 3..254, unico) |
| body.password | string (8..128) |
| body.expansionId | #ExpansionId \| null (opcional en el formulario; para recomendaciones) |
| body.termsAccepted | boolean (debe ser true) |
| body.invitationToken? | string (de una invitacion de moderador; asigna el rol al verificar) |
| notes | Crea la cuenta sin verificar y envia por email un codigo de 6 caracteres [A-Z0-9], valido 15 min. Acepta 'expansion' como alias de expansionId. |

#### Respuesta

| Campo | Definición |
|---|---|
| 201.ok | true |
| 201.verificationId | string (uuid) |
| 201.maskedEmail | string (p. ej. 'a***@gmail.com') |
| 201.expiresAt | string&lt;date-time&gt; |
| 201.resendAvailableAt | string&lt;date-time&gt; |
| errors.409 | NAME_TAKEN (username) \| INVALID_STATE (ya hay sesion) |
| errors.422 | VALIDATION_FAILED: fields.username \| email \| password \| expansionId \| termsAccepted \| invitationToken (EMAIL_TAKEN va en fields.email) |
| errors.429 | RATE_LIMITED |

### GET /api/admin/reports

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| query.q? | string (1..80; objetivo, reportante o texto) |
| query.severity? | 'critical' \| 'high' \| 'medium' \| 'low' |
| query.type? | 'file' \| 'credit' \| 'listing' \| 'comment' |
| query.state? | 'open' \| 'closed' (sin el: ambos) |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 20; el resumen usa 5) |
| notes | Orden: primero abiertos, luego por severidad y por createdAt descendente. severityCounts cuenta reportes abiertos e ignora los filtros. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.items | #Report[] |
| 200.page | #Page |
| 200.severityCounts | Record&lt;'critical' \| 'high' \| 'medium' \| 'low', integer&gt; |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/counts

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| notes | Contadores de la barra lateral del panel, visibles en todos los apartados. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.queue | integer (envios pending) |
| 200.reports | integer (reportes abiertos) |
| 200.accounts | integer (cuentas verificadas) |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |

### GET /api/auth/session

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.user | #SessionUser \| null (null sin sesion) |

### POST /api/auth/logout

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |

### POST /api/auth/verify

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| contentType | application/json |
| body.verificationId | string (uuid) |
| body.code | string (6, ^[A-Z0-9]{6}$; el servidor pasa a mayusculas) |
| notes | Si el codigo es correcto verifica la cuenta, crea la sesion (remember=false) y devuelve el usuario. Cinco intentos fallidos invalidan el codigo. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.user | #SessionUser |
| errors.404 | NOT_FOUND (verificationId desconocido) |
| errors.410 | VERIFICATION_EXPIRED (codigo caducado o agotados los intentos) |
| errors.422 | INVALID_CODE, con fields.code |
| errors.429 | RATE_LIMITED |

### POST /api/submissions

#### Solicitud

| Campo | Definición |
|---|---|
| auth | uploader |
| contentType | application/json |
| body.uploadId? | string (uuid, propio y vigente) |
| body.addonSlug? | string \| null |
| body.requestId? | integer \| null (solicitud en open o volunteered) |
| body.name? | string \| null |
| body.version? | string \| null |
| body.categoryId? | #CategoryId \| null |
| body.categoryIds? | #CategoryId[] (0..8; si se proporciona, la primera determina categoryId) |
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
| body.screenshotMediaIds? | integer[] (0..4, en orden) |
| body.rulesAccepted? | boolean |
| body.testedConfirmed? | boolean |
| notes | Crea un borrador ('Guardar borrador' sin id previo). Solo valida formato (domain.submissionLifecycle.draftValidation). Con requestId la solicitud pasa a volunteered. |

#### Respuesta

| Campo | Definición |
|---|---|
| 201.ok | true |
| 201.submission | #Submission |
| errors.401 | UNAUTHENTICATED |
| errors.403 | ACCOUNT_SUSPENDED |
| errors.404 | NOT_FOUND (addonSlug o requestId) |
| errors.409 | INVALID_STATE (la solicitud esta en inQueue, fulfilled o closed) |
| errors.410 | UPLOAD_EXPIRED \| MEDIA_EXPIRED |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/stats

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| query.range? | #Range (default '7d') |
| query.tz? | string (zona IANA, default 'UTC'; para dias y horas) |
| notes | Grafica de descargas, lo mas descargado, horario de maxima actividad, migracion entre clientes y records. Porcentajes, pico, hora tranquila y subida de fin de semana los calcula el frontend a partir de primeTime. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.downloads.from | string&lt;date&gt; |
| 200.downloads.current | integer[] (una cifra por dia del periodo) |
| 200.downloads.previous | integer[] (mismo largo, periodo anterior) |
| 200.periodTotal | integer |
| 200.topAddons.0.slug | string |
| 200.topAddons.0.name | string |
| 200.topAddons.0.downloads | integer |
| 200.primeTime | integer[][] (7 x 24; descargas por dia de la semana, lunes = 0, y hora) |
| 200.migration.0.from | #ExpansionId |
| 200.migration.0.to | #ExpansionId |
| 200.migration.0.value | integer (usuarios que descargaron primero de from y despues de to en el periodo) |
| 200.records.0.id | 'fastest' \| 'streak' \| 'comeback' \| 'nightOwl' \| 'loyal' \| 'heaviest' |
| 200.records.0.holder.label | string |
| 200.records.0.holder.addonSlug | string \| null |
| 200.records.0.value | number |
| 200.records.0.unit | 'hours' \| 'days' \| 'percent' \| 'minuteOfDay' \| 'bytes' |
| 200.records.0.active | boolean (sigue vigente: rombo dorado) |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.422 | VALIDATION_FAILED |

### GET /api/submissions

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| query.status? | #SubmissionStatus |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 10) |
| notes | Para recuperar borradores y ver en que quedaron los envios. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.items | #Submission[] (updatedAt descendente) |
| 200.page | #Page |
| errors.401 | UNAUTHENTICATED |
| errors.422 | VALIDATION_FAILED |

### POST /api/auth/login

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| contentType | application/json |
| body.identifier | string (1..254, username o email) |
| body.password | string (1..128) |
| body.remember | boolean |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.user | #SessionUser |
| errors.401 | INVALID_CREDENTIALS (mismo error si no existe la cuenta) |
| errors.403 | EMAIL_NOT_VERIFIED: error.details trae verificationId y maskedEmail para ir a /verify, y el servidor reenvia un codigo \| ACCOUNT_BANNED |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED (5 fallos en 15 min por identificador e IP) |

### GET /api/admin/live

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| query.since? | string&lt;date-time&gt; (solo feed posterior) |
| notes | Pulso en directo de Estadisticas. El frontend consulta cada 10 s mientras el boton de pausa este activo y pasa en since el createdAt del evento mas reciente. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.online | integer |
| 200.perMinute | integer |
| 200.today | integer |
| 200.sameTimeLastWeek | integer (para el ritmo) |
| 200.pulse | integer[] (40; descargas por minuto, la mas antigua primero) |
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

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| notes | Cifras globales de AccountIntro (login, registro, verificacion), la cabecera de About y el tiempo medio de revision de la subida. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.addons | integer (addons publicados) |
| 200.downloads | integer (descargas totales) |
| 200.players | integer (cuentas verificadas) |
| 200.averageReviewHours | integer (media de la ultima semana) |

### POST /api/requests

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| contentType | application/json |
| body.addonName | string (2..60) |
| body.expansionId | #ExpansionId |
| body.text | string (10..500) |
| notes | Formulario de la portada (campos name, expansion y details). El autor queda con voto y seguimiento. |

#### Respuesta

| Campo | Definición |
|---|---|
| 201.ok | true |
| 201.request | #Request |
| errors.401 | UNAUTHENTICATED |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED (5 solicitudes al dia por cuenta) |

### GET /api/requests

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| query.q? | string (1..80; nombre del addon o del autor) |
| query.state? | #RequestState (sin el: todas menos closed) |
| query.expansion? | #ExpansionId |
| query.sort? | 'votes' \| 'newest' \| 'oldest' (default 'votes'; oldest = mas antiguas primero) |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 20) |
| notes | Portada (sort=newest, pageSize=4), resumen del panel (sort=votes, pageSize=5) y apartado de solicitudes del panel. stateCounts y expansionCounts ignoran los filtros state y expansion pero respetan q. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.items | #Request[] |
| 200.page | #Page |
| 200.stateCounts | Record&lt;#RequestState, integer&gt; |
| 200.expansionCounts | Record&lt;#ExpansionId, integer&gt; (solo expansiones con alguna solicitud; alimenta el filtro del panel) |
| errors.422 | VALIDATION_FAILED |

### GET /api/taxonomy

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| notes | Expansiones, grupos category/classes/professions/role y límites de subida en una sola respuesta. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.expansions.0.id | #ExpansionId |
| 200.expansions.0.abbr | string |
| 200.expansions.0.name | string |
| 200.expansions.0.patch | string (cliente de referencia, p. ej. '3.3.5a') |
| 200.expansions.0.addonCount | integer (addons con alguna version en esa expansion) |
| 200.groups.0.id | 'category' \| 'classes' \| 'professions' \| 'role' |
| 200.groups.0.items.0.id | string |
| 200.groups.0.items.0.name | string |
| 200.groups.0.items.0.addonCount | integer |
| 200.limits.summaryMax | integer |
| 200.limits.screenshotsMax | integer |
| 200.limits.addonZipMaxBytes | integer |
| 200.limits.imageMaxBytes | integer |

### POST /api/contact

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| contentType | multipart/form-data |
| body.recipientId | string (ID vigente de GET /api/contact: account:{id}, group:moderation o mailbox:{key}) |
| body.topicId | 'broken' \| 'rejected' \| 'takedown' \| 'account' \| 'report' \| 'other' |
| body.name | string (2..60) |
| body.email | string (email valido; obligatorio sin sesion, con sesion por defecto el de la cuenta) |
| body.subject | string (3..120) |
| body.message | string (20..5000) |
| body.attachment? | image/png o image/jpeg (hasta 2 MB) |

#### Respuesta

| Campo | Definición |
|---|---|
| 201.ok | true |
| 201.ticketId | integer |
| 201.estimatedReplyHours | integer |
| errors.413 | FILE_TOO_LARGE |
| errors.415 | UNSUPPORTED_MEDIA_TYPE |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED (3 mensajes por hora e IP) |

### POST /api/reports

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| contentType | application/json |
| body.target.kind | 'addon' \| 'version' \| 'comment' |
| body.target.id | integer |
| body.type | 'file' \| 'credit' \| 'listing' \| 'comment' |
| body.text | string (10..1000) |
| notes | Boton 'Reportar' de la ficha del addon. Si otro usuario ya tiene abierto un reporte del mismo tipo sobre el mismo objetivo, se suma a ese (otherReporters) en lugar de crear otro. |

#### Respuesta

| Campo | Definición |
|---|---|
| 201.ok | true |
| 201.reportId | integer |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND (objetivo) |
| errors.409 | ALREADY_REPORTED (el mismo usuario ya tiene un reporte abierto sobre ese objetivo) |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED |

### POST /api/uploads

#### Solicitud

| Campo | Definición |
|---|---|
| auth | uploader |
| contentType | multipart/form-data |
| body.file | archivo .zip (application/zip o application/x-zip-compressed, 1 B..50 MB) |
| notes | Paso 1 de la subida. El frontend muestra el progreso con XMLHttpRequest.upload.onprogress. El servidor analiza el ZIP antes de responder. El archivo queda temporal: ver domain.temporaryFiles. |

#### Respuesta

| Campo | Definición |
|---|---|
| 201.ok | true |
| 201.upload | #UploadFile |
| errors.401 | UNAUTHENTICATED |
| errors.403 | ACCOUNT_SUSPENDED |
| errors.413 | FILE_TOO_LARGE |
| errors.415 | UNSUPPORTED_MEDIA_TYPE (no es ZIP o esta cifrado) |
| errors.422 | VALIDATION_FAILED (ZIP vacio o corrupto) |
| errors.429 | RATE_LIMITED |

### GET /api/contact

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| notes | Lista fundadores individuales verificados y sin baneo, una ficha común Moderadores y los buzones configurados. El grupo aparece aunque solo haya un fundador y envía a todos los moderadores y fundadores verificados y sin baneo al enviar. Un ticket tiene una notificación independiente por miembro. Los correos son privados; el nombre del grupo usa i18n del frontend. Los destinos retirados se rechazan. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.queue | integer (mensajes sin responder) |
| 200.recipients.0.id | string (account:{id}, group:moderation o mailbox:{key}) |
| 200.recipients.0.name | string \| null (null para el nombre traducible del grupo de moderación) |
| 200.recipients.0.kind | 'founder' \| 'moderation' \| 'mailbox' |
| 200.recipients.0.username | string \| null (null para el grupo y los buzones) |
| 200.recipients.0.color | string (color del avatar o acento por defecto) |
| 200.recipients.0.description | string (descripción pública opcional del buzón; vacía para cuentas del equipo) |
| 200.recipients.0.status | 'reviewing' \| 'normal' |
| 200.recipients.0.replyHours.min | integer |
| 200.recipients.0.replyHours.max | integer |
| 200.recipients.0.estimatedReplyHours | integer (24 por defecto; configurable en buzones) |
| 200.recipients.0.availabilityPercent | integer (0..100; calculado desde los tickets abiertos) |
| 200.recipients.0.load | integer (0..100; llena la barra de respuesta estimada) |
| 200.week.received | integer |
| 200.week.answered | integer |
| 200.week.averageReplyHours | integer |

### GET /api/addons

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| query.q? | string (1..80; busca en nombre, alias, resumen y etiquetas) |
| query.expansion? | string (ids #ExpansionId separados por coma) |
| query.category? | string (ids #CategoryId separados por coma) |
| query.classes? | string (ids #ClassId separados por coma) |
| query.professions? | string (ids #ProfessionId separados por coma) |
| query.role? | string (ids #RoleTag separados por coma) |
| query.rating? | 0 \| 3 \| 4 \| 4.5 (valoracion minima; 0 = todas) |
| query.author? | string (1..24; coincidencia parcial con el nombre del subidor, sin distinguir mayusculas) |
| query.recent? | '1' (solo addons con version nueva en los ultimos 30 dias) |
| query.sort? | 'downloads' \| 'rating' \| 'recent' \| 'updated' \| 'az' (default 'downloads'; recent = createdAt, updated = updatedAt) |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..48, default 12; Explore usa 6) |
| notes | Dentro de un grupo los ids se combinan con OR y entre grupos con AND. Cada contador de facets aplica el resto de filtros pero no el de su propio grupo, para que al marcar una opcion no desaparezcan las demas. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.items | #AddonSummary[] |
| 200.page | #Page |
| 200.facets.expansion | Record&lt;#ExpansionId, integer&gt; |
| 200.facets.category | Record&lt;#CategoryId, integer&gt; |
| 200.facets.classes | Record&lt;#ClassId, integer&gt; |
| 200.facets.professions | Record&lt;#ProfessionId, integer&gt; |
| 200.facets.role | Record&lt;#RoleTag, integer&gt; |
| errors.422 | VALIDATION_FAILED (sort, rating, pageSize o un id de filtro desconocido) |

### GET /api/health

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.message | string |
| 200.php | string |

### POST /api/media

#### Solicitud

| Campo | Definición |
|---|---|
| auth | uploader |
| contentType | multipart/form-data |
| body.kind | 'icon' \| 'screenshot' |
| body.file | image/png o image/jpeg (icono: exactamente 256x256, hasta 2 MB; captura: 640x360..3840x2160, hasta 2 MB) |
| notes | Paso 3 de la subida. Devuelve un id temporal que se vincula al envio con PATCH (iconMediaId, screenshotMediaIds). |

#### Respuesta

| Campo | Definición |
|---|---|
| 201.ok | true |
| 201.media | #Media |
| errors.401 | UNAUTHENTICATED |
| errors.403 | ACCOUNT_SUSPENDED |
| errors.413 | FILE_TOO_LARGE |
| errors.415 | UNSUPPORTED_MEDIA_TYPE |
| errors.422 | VALIDATION_FAILED (dimensiones) |

### GET /api/about

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| notes | Solo lo que cambia en About. Las tres cifras de la cabecera salen de /api/site/stats; la linea de tiempo y los fundadores estan en el frontend. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.support.month | string (YYYY-MM) |
| 200.support.raised | number |
| 200.support.goal | number |
| 200.support.currency | string (ISO 4217) |
| 200.support.contributors | integer |
| 200.hallOfFame.0.user | #UserRef |
| 200.hallOfFame.0.uploads | integer |
| 200.hallOfFame.0.since | integer (ano) |
| 200.achievements | #AchievementState[] |

### GET /api/home

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| notes | Datos propios de la portada. Las expansiones y categorias salen de /api/taxonomy y las solicitudes de /api/requests?sort=newest&amp;pageSize=4. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.latest | #AddonSummary[] (6, addons publicados por createdAt descendente) |
| 200.updated | #AddonUpdate[] (6, ultimas versiones nuevas de addons ya existentes) |
| 200.packs | #Pack[] |

### POST /api/admin/requests/{requestId}/notify-authors

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| notes | Operación complementaria de la API. |

#### Respuesta

| Campo | Definición |
|---|---|
| 202.description | Respuesta de RequestService::notify_authors |

### POST /api/admin/submissions/{submissionId}/decision

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| path.submissionId | integer |
| contentType | application/json |
| body.decision | 'approve' \| 'reject' |
| body.ruleId | #RuleId \| null (obligatorio si reject) |
| body.reason | string (0..1000) \| null |
| notes | approve publica la ficha o la version (y convierte al autor en author si era player); reject la descarta. Ambas avisan al autor por email y cambian la solicitud vinculada segun domain.requestLifecycle. Solo sobre envios pending: reintentar despues devuelve 409. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.submission | #Submission |
| 200.addon | #AddonSummary \| null (solo si approve) |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE \| NAME_TAKEN \| VERSION_EXISTS |
| errors.422 | VALIDATION_FAILED |

### GET /api/admin/submissions/{submissionId}/file

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| path.submissionId | integer |
| notes | Boton 'ZIP' de la cola. Tambien sirve archivos en cuarentena. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200 | application/zip. Content-Disposition: attachment; filename="submission-{submissionId}.zip" |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND |

### POST /api/admin/accounts/{accountId}/message

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| path.accountId | integer |
| contentType | application/json |
| body.subject | string (3..120) |
| body.body | string (1..5000) |
| notes | Boton 'Mensaje' de la ficha. Se entrega por email; no hay bandeja de entrada en el sitio. |

#### Respuesta

| Campo | Definición |
|---|---|
| 201.ok | true |
| 201.messageId | integer |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED |

### POST /api/admin/requests/{requestId}/action

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| path.requestId | integer |
| contentType | application/json |
| body.action | 'link' \| 'ping' \| 'close' |
| body.submissionId? | integer (link: envio draft o pending) |
| body.addonSlug? | string (link: addon ya publicado) |
| body.reason? | string (0..500) (close) |
| notes | El listado del panel usa GET /api/requests. link exige exactamente uno de submissionId o addonSlug y sigue las transiciones de domain.requestLifecycle. ping avisa por email a los autores que tienen addons en esa expansion y no cambia el estado (una vez cada 24 h por solicitud). close solo desde open o volunteered. 'Abrir addon' no es una accion: enlaza a request.addon. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.request | #Request |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND (solicitud, envio o addon) |
| errors.409 | INVALID_STATE |
| errors.422 | VALIDATION_FAILED |
| errors.429 | RATE_LIMITED (ping repetido) |

### POST /api/submissions/{submissionId}/submit

#### Solicitud

| Campo | Definición |
|---|---|
| auth | uploader |
| path.submissionId | integer |
| contentType | application/json |
| body.revision? | integer |
| notes | Boton 'Enviar a revision'. Si el formulario tiene cambios sin guardar, el frontend hace antes el PATCH. Aplica domain.submissionLifecycle.submitValidation y pasa el envio a pending; si tiene requestId, la solicitud pasa a inQueue. Reintentar sobre un envio ya pending devuelve 409. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.submission | #Submission (status pending, estimatedReviewHours) |
| errors.401 | UNAUTHENTICATED |
| errors.403 | ACCOUNT_SUSPENDED |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE \| REVISION_MISMATCH \| NAME_TAKEN \| VERSION_EXISTS |
| errors.410 | UPLOAD_EXPIRED \| MEDIA_EXPIRED |
| errors.422 | VALIDATION_FAILED (campos obligatorios, checks en false o ZIP blocked) |

### GET /api/admin/submissions/{submissionId}

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| path.submissionId | integer |
| notes | Boton 'Inspeccionar' de la cola. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.submission | #Submission |
| 200.author | #AdminAccount |
| 200.addon | #AddonSummary \| null (addon existente si kind=version) |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND (inexistente o borrador) |

### POST /api/admin/reports/{reportId}/action

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| path.reportId | integer |
| contentType | application/json |
| body.action | 'warn' \| 'takedown' \| 'dismiss' \| 'reopen' |
| body.note | string (0..1000) \| null |
| notes | warn, takedown y dismiss solo sobre abiertos; reopen solo sobre cerrados. Efectos en domain.reportLifecycle.effects. 'Ver' no es una accion: enlaza a target. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.report | #Report |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE |
| errors.422 | VALIDATION_FAILED |

### PATCH /api/admin/accounts/{accountId}

#### Solicitud

| Campo | Definición |
|---|---|
| auth | staff |
| path.accountId | integer |
| contentType | application/json |
| body.role? | 'founder' \| 'moderator' \| 'player' |
| body.status? | #AccountStatus |
| body.suspendDays? | integer (1..365, default 30; solo con status suspended) |
| body.reason? | string (0..500) |
| notes | role solo lo cambia un founder; 'player' quita el rol de staff y el servidor lo convierte en author si tiene addons publicados. Un moderador puede suspender y reactivar cuentas player y author; banear y tocar a otro staff es solo de founder. Nadie se cambia a si mismo. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.account | #AdminAccount |
| errors.401 | UNAUTHENTICATED |
| errors.403 | FORBIDDEN |
| errors.404 | NOT_FOUND |
| errors.422 | VALIDATION_FAILED |

### PATCH /api/submissions/{submissionId}

#### Solicitud

| Campo | Definición |
|---|---|
| auth | uploader |
| path.submissionId | integer |
| contentType | application/json |
| body.revision? | integer (la ultima recibida) |
| body.... | cualquier campo del cuerpo de POST /api/submissions; los ausentes no cambian y null los vacia |
| notes | Solo en status draft. Renueva expiresAt. Cambiar uploadId o las imagenes desvincula los archivos anteriores, que vuelven a caducar a las 24 h. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.submission | #Submission |
| errors.401 | UNAUTHENTICATED |
| errors.403 | ACCOUNT_SUSPENDED |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE (no es borrador) \| REVISION_MISMATCH |
| errors.410 | UPLOAD_EXPIRED \| MEDIA_EXPIRED |
| errors.422 | VALIDATION_FAILED |

### PUT /api/requests/{requestId}/follow

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| path.requestId | integer |
| contentType | application/json |
| body.following | boolean (true = seguir, false = dejar de seguir) |
| notes | Idempotente. Se permite en cualquier estado. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.followers | integer |
| 200.following | boolean |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND |
| errors.422 | VALIDATION_FAILED |

### GET /api/download-files/{versionId}

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| notes | Operación complementaria de la API. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.description | Respuesta de DownloadService::signed |

### GET /api/submissions/{submissionId}

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| path.submissionId | integer |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.submission | #Submission |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND (inexistente, ajeno o borrador caducado) |

### PUT /api/requests/{requestId}/vote

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| path.requestId | integer |
| contentType | application/json |
| body.voted | boolean (true = votar, false = quitar el voto) |
| notes | Idempotente: repetir el mismo valor devuelve 200 sin cambiar el contador. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.votes | integer |
| 200.voted | boolean |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND |
| errors.409 | INVALID_STATE (fulfilled o closed) |
| errors.422 | VALIDATION_FAILED |

### GET /api/users/{username}/journal

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| path.username | string \| 'me' |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 20) |

#### Respuesta

| Campo | Definición |
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

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| path.username | string \| 'me' |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 10) |
| notes | Comentarios con valoracion que el usuario dejo en addons ajenos. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.items.0.id | integer (id del comentario) |
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

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| path.slug | string |
| body.content | string (1..4000) |
| body.rating | integer (1..5) |
| body.expansionId | #ExpansionId |
| notes | Una reseña por usuario y addon. Crea con 201 o actualiza la existente con 200. La expansión debe tener una versión publicada del addon. |

#### Respuesta

| Campo | Definición |
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

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| path.packId | #RoleTag |
| query.expansion | #ExpansionId |
| notes | ZIP con la ultima version, en esa expansion, de cada addon del pack. Suma una descarga a cada version incluida. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200 | application/zip. Content-Disposition: attachment; filename="rog-{packId}-kit-{expansion}.zip" |
| errors.404 | NOT_FOUND (pack inexistente o no disponible para esa expansion) |
| errors.422 | VALIDATION_FAILED |

### GET /api/users/{username}/addons

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| path.username | string \| 'me' |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..48, default 24) |
| notes | Pestana 'Addons' (bolsa). Un item esta en el equipo si su id aparece en loadout. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.items | #AddonSummary[] (updatedAt descendente) |
| 200.page | #Page |
| errors.404 | NOT_FOUND |

### PUT /api/users/{username}/follow

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| path.username | string |
| contentType | application/json |
| body.following | boolean |
| notes | Boton 'Seguir' del perfil. Idempotente. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.followers | integer |
| 200.following | boolean |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND |
| errors.422 | VALIDATION_FAILED (seguirse a si mismo) |

### GET /api/addons/{slug}/comments

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| path.slug | string |
| query.page? | integer (&gt;= 1, default 1) |
| query.pageSize? | integer (1..50, default 10) |
| notes | Lista las reseñas vigentes: una por usuario y addon. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.items | #Comment[] (createdAt descendente) |
| 200.page | #Page |
| errors.404 | NOT_FOUND |

### PUT /api/addons/{slug}/favorite

#### Solicitud

| Campo | Definición |
|---|---|
| auth | user |
| path.slug | string |
| contentType | application/json |
| body.favorited | boolean |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.favorited | boolean |
| errors.401 | UNAUTHENTICATED |
| errors.404 | NOT_FOUND |
| errors.422 | VALIDATION_FAILED |

### GET /api/downloads/{versionId}

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| path.versionId | integer |
| notes | Unico punto de descarga de versiones publicadas: tarjetas, filas, cabecera del addon y lista de versiones. Suma una descarga por version e IP cada 24 h. |

#### Respuesta

| Campo | Definición |
|---|---|
| 302 | Location hacia el ZIP (URL firmada, valida 5 min). Content-Disposition: attachment; filename="{slug}-{version}-{expansionId}.zip" |
| errors.404 | NOT_FOUND (version inexistente o retirada) |
| errors.429 | RATE_LIMITED |

### GET /api/profiles/{username}

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| path.username | string (nombre de usuario o 'me', que exige sesion) |
| notes | Cabecera y pestana principal del perfil. Las listas que crecen (addons, diario, resenas) van por sus propios endpoints paginados. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.user.id | integer |
| 200.user.name | string |
| 200.user.color | string (hex #rrggbb) |
| 200.user.role | #Role |
| 200.user.titleId | string \| null (titulo activo; su texto esta en el frontend) |
| 200.user.level | integer |
| 200.user.realm | string \| null |
| 200.user.joinedAt | string&lt;date-time&gt; |
| 200.user.online | boolean |
| 200.user.badge | { "achievementId": "string", "expansionId": "#ExpansionId \| null" } \| null |
| 200.user.spec | { "role": "#RoleTag", "name": "string (p. ej. 'Restoration')" } \| null |
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
| 200.stats.lastVersionAt | string&lt;date-time&gt; \| null (tambien alimenta 'Ultima subida') |
| 200.stats.replies | integer |
| 200.counts.addons | integer |
| 200.counts.journal | integer |
| 200.counts.reviews | integer |
| 200.loadout | #AddonSummary[] (0..8; sus addons mas descargados, en orden de ranura) |
| 200.addonsByExpansion | Record&lt;#ExpansionId, integer&gt; (arbol de talentos) |
| 200.titles.0.id | string |
| 200.titles.0.earnedAt | string&lt;date-time&gt; \| null |
| 200.achievements | #AchievementState[] |
| 200.lastWhisper | { "text": "string", "author": "#UserRef", "addonSlug": "string", "createdAt": "string&lt;date-time&gt;" } \| null (ultimo comentario recibido en uno de sus addons) |
| 200.heat.from | string&lt;date&gt; (lunes de hace cuatro semanas) |
| 200.heat.counts | integer[] (28; acciones por dia; el nivel 0..3 lo calcula el frontend) |
| 200.viewer | { "isSelf": "boolean", "following": "boolean" } \| null |
| errors.401 | UNAUTHENTICATED (username = me sin sesion) |
| errors.404 | NOT_FOUND (inexistente o baneado) |

### GET /api/files/{fileId}

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| notes | Ver [instalación del sitio](installation.es.md) para setup; las descargas de archivos devuelven contenido binario. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.description | Respuesta de FileService::media |

### GET /api/addons/{slug}

#### Solicitud

| Campo | Definición |
|---|---|
| auth | public |
| path.slug | string |
| query.expansion? | #ExpansionId (la de la URL; si el addon no tiene version en ella se devuelve la canonica) |
| notes | Si el expansionId o el categoryId devueltos no coinciden con la URL, el frontend redirige a la URL canonica, como hace hoy. |

#### Respuesta

| Campo | Definición |
|---|---|
| 200.ok | true |
| 200.addon | #AddonDetail |
| errors.404 | NOT_FOUND (slug inexistente o addon retirado) |
