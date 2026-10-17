# Servicio de instalación

El servicio está disponible antes de configurar la base de datos y la clave de la aplicación. Funciona con PHP/PDO y los modelos propios. Crea la estructura inicial directamente desde los modelos. Después de instalar, las conexiones normales no crean ni actualizan tablas. No se ejecutan migraciones.

## Activar el instalador

### Preparar el paquete de publicación

Ejecutar `npm run build` y publicar el contenido de `dist/`. El paquete de la API incluye el código
de ejecución, los archivos del esquema, los comandos de consola, la configuración base/de ejemplo
y una carpeta de almacenamiento vacía. Excluye la configuración privada, los bloqueos de instalación,
las claves y los diarios temporales del setup, los archivos almacenados, las bases SQLite y las
pruebas de la API. La instalación local permanece intacta.

El paquete queda preparado para una instalación nueva. En el servidor de destino, definir `ROG_ORIGIN`,
generar una nueva clave de instalación y elegir una base vacía como se explica abajo. Un `ROG_DSN`
preconfigurado sigue desactivando el instalador. Publicar sobre una instalación existente no borra
su configuración ni sus datos; conservar `api/config` y `api/storage` del servidor al actualizar.

### Configuración del servidor

Con PHP y el motor elegido preparados, definir `ROG_ORIGIN` con el origen desde el que se llamará al instalador, por ejemplo `https://addons.example.com`. Debe coincidir exactamente con el encabezado `Origin`, sin ruta ni barra final. La pantalla y la API deben servirse desde ese origen; no se habilita CORS entre sitios distintos. Con `npm run dev`, el servidor PHP usa `http://127.0.0.1:5173` de forma predeterminada, salvo que se haya definido `ROG_ORIGIN` explícitamente. Abrir esa dirección exacta, no `localhost`. El valor de respaldo de la API fuera del arranque de desarrollo sigue siendo `http://127.0.0.1:8080`.

Desde `api/`, en una copia aún no configurada, ejecutar:

```sh
php bin/setup-token.php
```

Devuelve una clave temporal de una hora. Guardar el valor `token` para enviarlo en `X-Setup-Token`. El archivo privado conserva únicamente su hash. Alternativamente se puede definir `ROG_SETUP_TOKEN` con una clave aleatoria de entre 32 y 256 caracteres; esta modalidad no caduca por tiempo, pero también queda bloqueada al completar la instalación. Si se usa esa variable, no es necesario ejecutar el comando. Retirarla después del uso.

La clave temporal permite instalar sin que un visitante pueda adjudicarse la primera cuenta. No sustituye la contraseña del fundador ni se convierte en la clave secreta de la aplicación. No ponerla en una URL, un repositorio o un registro de peticiones.

## Rutas

| Método y ruta | Autorización | Resultado |
|---|---|---|
| `GET /api/setup/status` | Pública | Indica si está configurado, disponible o pendiente de recuperación; no devuelve credenciales. |
| `GET /api/setup/requirements` | `X-Setup-Token` | Versiones y extensiones reales, permisos y límites efectivos de PHP. |
| `POST /api/setup/database` | `X-Setup-Token` y `Origin` | Prueba la conexión y comprueba la base, sus permisos y el esquema sin crear nada. |
| `POST /api/setup/install` | `X-Setup-Token` y `Origin` | Crea la estructura y el fundador, guarda la configuración y bloquea el instalador. |

Enviar los POST con `Content-Type: application/json`. Estas rutas usan la clave de instalación, sin sesión ni token CSRF de la aplicación, porque todavía no existen. Devuelven el mismo formato único de la API: `ok: true` junto con los datos o `ok: false` junto con `error`. No se devuelve ni almacena en logs el contenido de las credenciales.

## Probar la conexión

```json
{
  "database": {
    "driver": "pgsql",
    "host": "127.0.0.1",
    "port": 5432,
    "name": "rog_addons",
    "username": "rog",
    "password": "REEMPLAZAR",
    "sslMode": "prefer",
    "createIfMissing": true
  }
}
```

Admite PostgreSQL 15+ con UTF-8, MySQL 8.4+ con utf8mb4 y SQLite 3.38+. El ejemplo anterior corresponde a PostgreSQL. Consulta [configuración por motor](databases.es.md) para MySQL y SQLite. Los nombres de base deben empezar por una letra minúscula o guion bajo y contener letras minúsculas, números o guiones bajos, con un máximo de 63 caracteres. `postgres`, `template0`, `template1`, `mysql`, `sys`, `performance_schema` e `information_schema` no son destinos de instalación. El host admite nombres DNS e IP; no se recibe un DSN libre ni SQL del navegador. Los modos SSL admitidos son `disable`, `prefer`, `require`, `verify-ca` y `verify-full`; utilizar el modo apropiado del alojamiento.

En PostgreSQL, si la base no existe, la comprobación consulta la base de mantenimiento `postgres`. `createIfMissing: true` permite crearla **solo al instalar**, con una cuenta que tenga `CREATEDB`. Si el alojamiento restringe esa operación, crear una base vacía en su panel y enviar `false`. Una base existente necesita permisos de creación de esquema. El usuario de PostgreSQL debe existir.

Ejemplo de respuesta a una base ausente que se puede crear:

```json
{
  "ok": true,
  "connected": true,
  "databaseExists": false,
  "canCreateDatabase": true,
  "schemaEmpty": true,
  "serverVersion": "17.5",
  "ready": true,
  "reason": null
}
```

Una conexión válida puede devolver `ready: false`, por ejemplo con `reason: "DATABASE_NOT_EMPTY"`. El instalador exige un destino vacío: el esquema `rog_design` en PostgreSQL, la base elegida en MySQL o el archivo elegido en SQLite. No adopta una instalación existente.

## Instalar

Enviar al endpoint de instalación la misma configuración de base, añadiendo:

```json
{
  "database": {
    "driver": "pgsql",
    "host": "127.0.0.1",
    "port": 5432,
    "name": "rog_addons",
    "username": "rog",
    "password": "REEMPLAZAR",
    "createIfMissing": true
  },
  "site": {
    "url": "https://addons.example.com",
    "name": "RoG Addons",
    "language": "es",
    "timezone": "UTC"
  },
  "admin": {
    "username": "Angelo",
    "email": "angelo@example.com",
    "password": "REEMPLAZAR_POR_UNA_CONTRASENA_PROPIA",
    "passwordConfirmation": "REEMPLAZAR_POR_UNA_CONTRASENA_PROPIA"
  },
  "mail": { "transport": "spool" }
}
```

`site.url` es un origen HTTP(S), sin ruta, parámetros ni credenciales. Usar HTTPS en producción. El fundador requiere un nombre de 3–24 caracteres, email válido y contraseña coincidente de 12–128 caracteres. Se guarda verificado, con contraseña cifrada mediante hash y `access_level = 2`, por lo que puede iniciar sesión inmediatamente sin esperar correo.

`site.storagePath` es opcional: por defecto se usa `api/storage`. Si se especifica, debe ser una ruta absoluta privada, separada del directorio público y del código/configuración. Las rutas con `..` o directorios protegidos se rechazan. La carpeta se crea al instalar, no durante la prueba de conexión.

El servicio genera una clave secreta aleatoria, crea las tablas desde los modelos y escribe
`config/config.local.php` con las claves que consume esta API. No reemplaza `config/config.php`.
El nombre, idioma y zona horaria del sitio se guardan en `settings.site`; la persistencia
continúa en UTC.

Para SMTP se admiten `transport: "smtp"`, `from`, `host`, `port`, `username` y `password`. Se guarda la configuración para el transporte STARTTLS existente; instalar no envía correo de prueba ni demuestra que el proveedor SMTP lo acepte. `spool` permite completar toda la instalación sin correo externo.

La respuesta final usa HTTP 201 y contiene `installed`, `locked`, `resumed`, `founderId`, `siteUrl`, `mailTransport` y una lista de los pasos realmente completados. La operación es síncrona: no se inventan porcentajes ni se marca un paso completo mediante temporizadores.

## Bloqueo y recuperación

- Un bloqueo de archivo impide dos instalaciones simultáneas de la misma copia. Un bloqueo de base de datos coordina además la creación inicial y la cuenta.
- En PostgreSQL y SQLite, tablas, fundador y metadatos se crean en una transacción. Un fallo antes del commit revierte esas operaciones. Si se creó una base nueva, puede quedar vacía: `CREATE DATABASE` no forma parte de esa transacción.
- La configuración se prepara en un archivo privado completo antes del commit y se publica mediante renombrado. Un diario privado permite terminar si el proceso se interrumpe después de confirmar los datos.
- MySQL confirma cada cambio de estructura inmediatamente. Durante el setup se guarda un marcador temporal en `settings.initial_schema`, ligado al identificador privado de instalación y al SQL inicial. Un comentario de propiedad en la primera tabla protege también el fallo entre su creación y el registro del avance. Solo ese intento puede reanudarse; no se eliminan tablas ni se convierte una base ajena. El marcador se retira al guardar el fundador. No es un historial de migraciones ni se consulta durante el uso habitual. Si el código del esquema cambia con una instalación pendiente, se rechaza continuar con un plan distinto.
- Ante `INSTALL_FAILED` o `INSTALL_FINALIZE_FAILED`, corregir la causa del servidor y reenviar `POST /api/setup/install` con `{}` y la clave temporal válida. Se reutilizan los datos originales; no enviar una configuración distinta como mecanismo de cambio. Si la clave caducó, el administrador del servidor puede generar otra para reanudar.
- Una instalación completa responde `SETUP_LOCKED` a los endpoints operativos. La configuración existente, un DSN ya configurado o el marcador `config/install.lock` impiden reinstalar. Borrar solo `config.local.php` no elimina el bloqueo.

No borrar el diario pendiente para forzar una reinstalación. Sus rutas y nombres son fijos y privados; no se aceptan desde la petición. Tras terminar, se eliminan el diario y la clave temporal de archivo. Proteger también esos archivos mediante permisos del sistema y mantener `public/` como raíz web.

## Pantalla de instalación

La pantalla de instalación está integrada en React en `/:lang/setup`. Sus botones llaman
a estas rutas y muestran las respuestas reales. La pantalla ofrece PostgreSQL, MySQL y
SQLite con los campos correspondientes. El servicio no admite prefijos de tabla, cambios
de límite de subida, registro por invitación, desactivar moderación o insertar demos.
Los campos desconocidos se rechazan.

Validación reproducible: [pruebas del instalador](tests.es.md). Los informes por motor se guardan en el directorio temporal de cada ejecución.
