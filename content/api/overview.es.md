# API ROG — PHP con PostgreSQL, MySQL y SQLite

Las rutas de código y los comandos de esta guía parten de `api/`, en la raíz del proyecto.

API única, con PHP/PDO y una capa propia de modelos y creación inicial del esquema.
No utiliza Composer, un framework ni un ORM externo. El código mantiene clases prácticas,
nombres `snake_case`, consultas preparadas y retornos tempranos.

**Estado:** 64 combinaciones de método y ruta, incluidas las operaciones de infraestructura y de instalación. Especificación única: [referencia de la API](reference.es.md). Inventario completo: [inventario de rutas](../../api/tests/fixtures/routes.json). Ver [API unificada](routes.es.md).

La entrega se ha ejecutado en bases PostgreSQL, MySQL y SQLite locales de prueba, con servidores detenidos al terminar. También se validó SMTP con mensajes capturados por Ethereal. No se ha conectado una base de producción ni entregado mensajes a buzones reales.

## Instalación

Requisitos: PHP 8.2+, `intl`, `mbstring`, `zip`, `fileinfo`, `openssl` y el controlador PDO del motor elegido: PostgreSQL 15+ (`pdo_pgsql`), MySQL 8.4+ (`pdo_mysql`) o SQLite 3.38+ (`pdo_sqlite`). MySQL debe usar utf8mb4; PostgreSQL, UTF-8. MariaDB no está incluido. Consulta [compatibilidad y diferencias](databases.es.md).

**Instalación mediante una pantalla:** ya existe el [servicio de instalación](installation.es.md). Comprueba requisitos y conexión, guarda la configuración, crea al fundador y se bloquea al terminar. Se activa con una clave temporal (`php bin/setup-token.php` o `ROG_SETUP_TOKEN`); utiliza los modelos y no requiere SQL manual. Las instrucciones de abajo son la alternativa de configuración directa, sin asistente. No mezclar ambas: una configuración ya existente bloquea el instalador web.

1. Copiar `config/config.example.php` a `config/config.local.php` o definir las variables de entorno de `config/config.php`. Los secretos locales están excluidos de Git.
2. Definir `ROG_DSN`, `ROG_DB_USER`, `ROG_DB_PASSWORD`, `ROG_SECRET` y `ROG_ORIGIN`. La clave debe ser aleatoria, tener al menos 32 caracteres y mantenerse estable: protege sesiones, CSRF, descargas firmadas y correo cifrado.
3. Ejecutar `php bin/schema.php create` una sola vez, con el esquema de la aplicación vacío. Crea tablas desde los modelos y rechaza una estructura existente. Si la base indicada en `dbname` no existe y `create_database` está activado, puede crearla con los permisos del motor elegido. SQLite crea únicamente el archivo privado indicado, en una carpeta existente con permiso de escritura. La cuenta de instalación necesita permiso para crear el esquema y sus objetos.
4. Crear el primer fundador con una contraseña suministrada en `ROG_FOUNDER_PASSWORD`, sin escribirla en el historial del comando:

```sh
php bin/create-founder.php Angelo angelo@example.com
```

Este comando requiere la estructura creada en el paso anterior. Las conexiones normales nunca crean bases, tablas ni columnas.

5. Configurar el servidor web para que su raíz pública sea **`public/`**. Con Apache 2.4 se incluye `.htaccess`. El almacenamiento, configuración, contratos y modelos quedan fuera de esa raíz. El `.htaccess` del directorio superior ofrece una alternativa si el alojamiento obliga a publicar `api/`, pero se recomienda `public/`.
6. Para desarrollo local:

```sh
php -S 127.0.0.1:8080 -t public public/router.php
```

Usar `ROG_ORIGIN=http://127.0.0.1:8080` y `ROG_SECURE_COOKIE=0` exclusivamente con HTTP local. En producción, HTTPS y cookies seguras. El servidor PHP de desarrollo sirve para pruebas; el despliegue necesita Apache/PHP-FPM o equivalente.

Para PostgreSQL o MySQL, el servidor debe estar funcionando y la cuenta debe existir. SQLite no necesita un servidor separado. Si el alojamiento no permite crear bases, crear la base vacía desde su panel: el instalador creará las tablas desde los modelos. La creación de la base durante la instalación admite un `dbname` explícito de hasta 63 caracteres, en minúsculas, con letras, números y guiones bajos; debe empezar por una letra o guion bajo. Las bases existentes se conectan con el DSN habitual.

Las variables de entorno se leen en `config/config.php`; un `config.local.php` existente tiene prioridad. `ROG_CREATE_DATABASE=0` impide crear una base ausente durante la instalación. No hay una opción de sincronización automática: las conexiones de la aplicación solo utilizan la estructura existente. Para separar la cuenta de instalación de una cuenta web limitada, preparar primero el esquema y conceder solo los permisos de ejecución necesarios. [permisos de PostgreSQL](../../api/database/grants.sql) corresponde exclusivamente a PostgreSQL; los permisos de MySQL se describen en [bases de datos](databases.es.md).


## Uso desde el frontend

- Las respuestas JSON usan `ok: true` junto con sus datos; los errores, `ok: false` y `error`.
- Antes de una escritura, consultar `GET /api/auth/session`, conservar las cookies y enviar el `csrfToken` en `X-CSRF-Token`. El navegador envía `Origin`.
- Tras iniciar sesión, verificar la cuenta o cerrar sesión, actualizar el token CSRF antes de otra escritura.
- Los IDs de entidades son números; los IDs temporales de ZIP y los enlaces públicos de archivos usan UUID.
- Las URLs públicas llevan `/api` y no admiten el sufijo `.php`. El router también acepta la ruta interna sin prefijo, como requiere el proxy de desarrollo.
- PATCH de borrador admite `revision`; ante un 409, recargar antes de reenviar cambios.
- Cada usuario crea o actualiza su reseña mediante `PUT /api/addons/{slug}/my-comment`. El catálogo y los límites de subida se consultan en `GET /api/taxonomy`.

Las respuestas públicas usan selecciones explícitas. **No serializar directamente un modelo `Account`:** el objeto de persistencia contiene datos internos como el hash de contraseña.

## Modelos propios

```php
$config = require 'bootstrap.php';
$db = new Rog\Database\Db($config);

$users = new Rog\Models\Account($db);
$user = $users->get(42);
if ($user !== null) {
    $user->profile->realm = 'Example Realm';
    $user->save();
}

$settings = new Rog\Models\Setting($db);
$setting = $settings->create([
    'key' => 'example',
    'value' => (object) ['enabled' => true],
]);
$setting->value->enabled = false;
$setting->save();
```

Cada modelo declara **una sola vez** tabla, columnas, tipos y restricciones. Esa declaración produce el SQL y guía lectura, conversión y escritura de objetos. La clase base proporciona `get`, `filter`, `create`, `save`, `delete` y `to_array`. Guarda solo columnas modificadas, detecta cambios dentro de JSONB y rechaza columnas desconocidas. Un objeto cargado antes de otra escritura falla con `Conflict` al guardar o borrar, usando `xmin` en PostgreSQL o una versión de fila mantenida por triggers en MySQL y SQLite. Los servicios además bloquean las filas que participan en transiciones de negocio.

Las columnas se declaran en `COLUMNS` y las clases de modelo se registran en
`database/models.php`. Esas definiciones crean la estructura de las instalaciones nuevas.
Las conexiones normales utilizan las tablas existentes y no aplican cambios de esquema.

`php bin/schema.php export pgsql|mysql|sqlite` imprime el SQL inicial del motor indicado sin conectarse. Sin argumento, usa el motor del DSN o PostgreSQL si todavía no hay configuración. `php bin/schema.php create` lo aplica explícitamente a un esquema vacío. PostgreSQL y SQLite crean la estructura dentro de una transacción. MySQL confirma sus operaciones de estructura inmediatamente: el instalador web registra el avance y reanuda únicamente su propio intento, sin borrar tablas. Las vistas, funciones e índices de PostgreSQL están en `database/domains.sql` y `database/objects.sql`; `PortableSchema` genera sus equivalentes para MySQL y SQLite.

No hay migrador, comparación automática de versiones ni historial de cambios. Una tabla técnica de historial que exista en una instalación anterior queda sin uso; la aplicación no la elimina ni consulta. Los antiguos modos `diff`, `make` y `snapshot` ya no están disponibles.

Los servicios definen los campos editables, los permisos y los endpoints. Los JSONB editables
se reciben mediante campos permitidos; el navegador nunca accede directamente a la base de datos.

## Operación y datos configurables

```sh
php bin/worker.php 100
php bin/cleanup.php
php bin/rescan.php 123
php bin/settings.php support config/support.json
```

- `worker`: procesa la cola durable. Por defecto guarda correo de prueba en `storage/mail/`. Para entrega real: `ROG_MAIL_TRANSPORT=smtp`, host/puerto, usuario, contraseña y remitente. El transporte implementa STARTTLS con verificación de certificado. Se probaron el modo local y SMTP contra Ethereal; ver [pruebas de correo](tests.es.md).
- `cleanup`: caduca borradores de 30 días, invalida tokens y elimina archivos huérfanos caducados y registros de limitación antiguos. Puede ejecutarse cada hora. Conserva envíos decididos, historial y archivos todavía referenciados.
- `rescan`: vuelve a analizar un ZIP; si un archivo publicado resulta bloqueado, queda fuera de descargas y se crea un reporte de escáner, un evento y un aviso. Los ZIP recién subidos ya se analizan antes de responder. El análisis es estructural y de extensiones peligrosas; no es un motor antivirus.
- `settings`: carga un objeto JSON para configuración operativa. Véanse [configuración operativa](settings.es.md) y el diccionario.

El mismo `worker` refresca las métricas del catálogo en PostgreSQL después de procesar
la cola, incluso cuando no hay mensajes pendientes. MySQL y SQLite las calculan al consultar.
Las escrituras de valoraciones responden con su resultado exacto; los listados de PostgreSQL
reflejan las métricas del último refresco.

Los fundadores verificados y sin baneo tienen fichas de contacto individuales. La ficha común
Moderadores envía un ticket a todos los moderadores y fundadores verificados y sin baneo
mediante notificaciones independientes. Aparece aunque solo haya un fundador.
`contactRecipients` añade buzones opcionales; el equipo y el grupo automático se obtienen de
las cuentas. El servidor rechaza los destinos que ya no estén disponibles. `packDefinitions`
define los packs mediante slugs publicados y `support` contiene los datos de apoyo al proyecto.
Sin packs configurados, la lista es vacía. Los importes recaudados, miembros de packs y logros
editoriales proceden de la configuración y los datos guardados.

Los adjuntos de contacto se conservan y se indican en la notificación; su lectura por staff está disponible mediante la ruta protegida de archivos. La cola representa entrega al transporte, no confirmación de lectura. Un fallo después de entregar por SMTP y antes de confirmar en la base de datos puede causar un reintento: la semántica es **al menos una entrega**, con clave de deduplicación y Message-ID estable.

## Estructura

```text
public/              Entrada HTTP y configuración web
src/Http/            Petición, respuesta, validación y router
src/Database/        PDO, modelos y creación inicial del esquema
src/Models/          Las 15 tablas funcionales
src/Services/        Reglas, consultas y adaptadores de los endpoints
src/Support/         Contraseñas y SMTP nativo
database/            Registro de modelos y objetos SQL iniciales
tests/fixtures/      Rutas y tipos de respuesta esperados por las pruebas
bin/                 Herramientas de mantenimiento
tests/               Pruebas HTTP, modelos, formatos y concurrencia
../content/api/      Referencia, instalación, decisiones y pruebas
storage/             Archivos privados y correo de prueba; excluidos de Git
```

Hay 15 tablas funcionales y una vista de métricas, materializada solamente en PostgreSQL. Las relaciones y estados importantes tienen columnas y claves foráneas; JSONB conserva metadatos y contenido flexible. [Diccionario con ejemplos JSON](schema.es.md).

## Comprobaciones

Las pruebas de los tres motores generan sus informes en el directorio temporal de cada ejecución. El ejecutor muestra la ruta al terminar. Consulta [pruebas de la API](tests.es.md) para reproducir las comprobaciones de rutas, sesiones, archivos, publicación, moderación, modelos y concurrencia.

Las pruebas del instalador comprueban sus rutas desde una copia sin configurar, autorización, conexión sin escrituras, creación del fundador, bloqueo y recuperación ante fallos. Sus informes comparten el directorio temporal de resultados.

Para reproducir en una base **nueva y aislada**, ver [pruebas de la API](tests.es.md). El ejecutor crea un clúster local, comprueba la creación explícita desde los modelos y la ausencia de cambios estructurales durante conexiones normales, lo detiene al acabar y usa correo local. No reutiliza tus credenciales ni una base existente.

Las pruebas comprueban el comportamiento y los casos de contención de la API en entornos
locales desechables. Sus resultados describen esos escenarios y no establecen una capacidad
máxima de usuarios o peticiones simultáneas.
