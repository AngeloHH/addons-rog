# Decisiones de compatibilidad y persistencia

La API utiliza una sola representación y un único conjunto de rutas. La especificación vigente es [referencia de la API](reference.es.md); las rutas seleccionadas se describen en [rutas canónicas](routes.es.md).

| Tema | Implementación |
|---|---|
| Envoltorio | `ok: true` y datos en campos superiores; `ok: false` y `error` para fallos. Los errores incluyen un identificador de petición. |
| Autorización | `access_level SMALLINT`: 0 regular, 1 moderador, 2 fundador. `author` se deriva de publicaciones y no concede permisos de staff. Cambiar roles, banear o modificar otro staff requiere los permisos correspondientes. |
| CSRF | Cookie de sesión más token y Origin en todas las escrituras, incluido acceso y registro. `/auth/session` proporciona el token anónimo. |
| Borradores | Caducan a los 30 días. Los archivos vinculados heredan esa duración; los huérfanos duran 24 horas. |
| Versión | Nombre global reservado solo al enviar. La combinación addon/expansión/versión es única entre pendientes y aprobados. El slug no cambia. |
| Edición | Los borradores admiten `revision` para rechazar una copia obsoleta del formulario. La capa de modelos comprueba versiones de fila con `xmin` en PostgreSQL y `_row_version` en MySQL y SQLite. |
| Comentarios | PUT `/addons/{slug}/my-comment` crea o actualiza la reseña del usuario, con puntuación obligatoria. El listado usa las valoraciones vigentes. Si una reseña antigua no declara expansión, se utiliza la del perfil o la última publicada. |
| Descargas | GET `/downloads/{versionId}` emite un enlace firmado válido 5 minutos. Se deduplica por versión y HMAC de IP durante 24 horas móviles. Se registra una entrega emitida, no bytes recibidos completamente por el usuario. |
| Packs | Miembros explícitos por expansión, última versión disponible, cacheados por IDs exactos. Una colisión de rutas dentro de los ZIP impide construir el pack. No se seleccionan addons de un rol de forma arbitraria. |
| Solicitudes | Votar/seguir son estados deseados idempotentes. Votos congelados en estados finales. Cambios de estado y notificaciones comparten transacción. Los enlaces requieren envío o addon compatible; no se acepta un estado arbitrario sin su causa. |
| Reportes | Un caso abierto por tipo y objetivo, una entrada por denunciante. Retirar afecta al objetivo; reabrir no lo restaura. Un escaneo posterior de contenido publicado puede ponerlo en cuarentena y abrir un reporte automático. Los ZIP bloqueados antes de enviar permanecen privados e impiden el envío. |
| Invitaciones | Se verifica correo antes de conceder nivel 1. Se añade POST `/auth/invitations/accept` para una cuenta ya verificada: el enlace de invitación por sí solo no modifica permisos. |
| Mensajes | Un mensaje externo queda `queued` hasta que el trabajador lo entrega al transporte. `sendEmailCopy=false` guarda el mensaje del staff; la API no ofrece una bandeja pública de lectura. |
| Contacto | Se guarda siempre el ticket. Buzones configurables; un adjunto PNG/JPEG de hasta 2 MiB, almacenado como archivo privado. |
| IDs y valores desconocidos | IDs numéricos de entidades y UUID de ZIP temporales y enlaces públicos de archivos. Icono neutro cuando no hay imagen. No se inventan geolocalización, licencia, autor original, donaciones ni logros. |
| Configuración editorial | Taxonomía y nombres de referencia configurables. Los parches de cliente son referencias, no una consulta a versiones actualizadas. Features, idiomas, licencias y logros se pueden cargar desde modelos/configuración; no tienen endpoint de edición. |

## Datos calculados

Descargas, rating, votos, seguidores, compatibilidad, rol público, estado efectivo de cuenta, contadores y series se calculan a partir de hechos persistidos. `catalog_metrics` es una copia calculada para evitar recorrer todo el historial en cada listado. No reemplaza las tablas fuente.

Presencia guarda eventos limitados a uno por minuto y cuenta online a los usuarios vistos en los últimos cinco minutos. Las series administrativas agrupan descargas contadas; migración compara expansiones consecutivas de cuentas identificadas. Ciudad es nullable porque no se integró un proveedor de geolocalización.

Las fórmulas no definidas por la especificación están explícitas en el código: puntos = 100 por versión publicada + 5 por comentario; nivel = 1 + puntos/100 entero; reputación basada en seguidores. `calculationRules` permite ajustar los puntos y el peso de acciones para threat. Los títulos y fechas de logros se conservan en el perfil; sus definiciones se cargan en settings. Sin definiciones, se devuelven listas vacías.

Récords calculados cuando existen hechos suficientes: revisión más rápida, ZIP más pesado, racha de días con descargas, cantidad de días activos, última descarga antes de las 06:00 y mayor crecimiento de descargas entre dos períodos de 30 días. Un récord sin datos no aparece. Las reglas son decisiones iniciales de producto, no fórmulas deducidas de la presentación del frontend.

`replies/replyCount` de solicitudes y `helpful` de reseñas valen 0: la API no define
escrituras para esas capacidades ni simula actividad.

## JSONB y edición

El usuario edita los campos permitidos por cada servicio: por ejemplo descripción, etiquetas y confirmaciones del borrador. Estos terminan en `submissions.fields`. La aprobación copia la ficha publicada a `addons.details`. No existe un endpoint que permita reemplazar libremente un JSONB entero ni establecer `decision`, `access_level`, resultados del escáner o datos de auditoría desde el navegador.

JSONB es adecuado para metadatos de archivos, campos variables de una ficha, snapshots de eventos, configuración pequeña y payload cifrado de correo. Propiedad, permisos, estado, caducidad, versión, destino de reportes y vínculos de archivos usan columnas y FK porque necesitan integridad y consultas transaccionales.

Las sesiones, códigos, invitaciones, borradores, reportes, votos, seguidores, comentarios, colas y eventos requieren persistencia. Un JSON de configuración del frontend o una caché no cubre esos ciclos. Los archivos binarios se guardan fuera de la base de datos y fuera del directorio público.

## Validación y consultas

La validación utiliza PHP y bases PostgreSQL, MySQL y SQLite reales en entornos locales
desechables. Las comprobaciones SMTP con Ethereal verifican el transporte y los flujos
de correo de la API; su alcance se describe en [pruebas de correo](tests.es.md).
El escáner comprueba estructuras, extensiones de ejecutables, tamaños y rutas de los ZIP.
La aprobación del contenido corresponde a los moderadores; el escáner no es un antivirus.

Las consultas usan parámetros y los identificadores de modelos se validan. La capa propia
no incluye un lenguaje de consultas general, carga automática de relaciones ni actualización
destructiva automática del esquema. Las conexiones normales utilizan la estructura existente.

## Inicialización desde los modelos

Los modelos son la fuente de tablas y columnas. El setup y `php bin/schema.php create` crean la estructura inicial sobre un esquema vacío. PostgreSQL y SQLite crean el esquema en una transacción; MySQL conserva un progreso verificable porque su DDL confirma cambios por sentencia. La base nombrada en el DSN solo puede crearse durante esta operación explícita, si se permite y la cuenta tiene los permisos necesarios.

`Db` solo conecta durante el funcionamiento normal. No consulta versiones de los modelos, no añade columnas y no modifica tablas existentes. La cuenta web puede usar permisos limitados después de la instalación.

No se mantiene un historial de migraciones ni se generan instantáneas. La recuperación de una instalación interrumpida se conserva como parte del setup: utiliza su diario y la marca de instalación en settings para terminar sin duplicar al fundador.
