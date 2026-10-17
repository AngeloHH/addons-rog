# Bases de datos admitidas

La API utiliza los mismos 15 modelos con PostgreSQL 15+, MySQL 8.4+ o SQLite 3.38+. MariaDB no está incluido. PHP necesita `intl`, `mbstring`, `zip`, `fileinfo`, `openssl` y el controlador PDO del motor seleccionado. El asistente muestra los controladores disponibles; no exige instalar los tres.

## PostgreSQL

```json
{"database":{"driver":"pgsql","host":"127.0.0.1","port":5432,"name":"rog_addons","username":"rog","password":"REEMPLAZAR","sslMode":"prefer","createIfMissing":true}}
```

Usa UTF-8 y el esquema `rog_design`. Crear una base ausente necesita `CREATEDB`; crear sus objetos requiere los permisos de esquema correspondientes. La prueba de conexión no crea bases ni tablas. La estructura y el fundador se crean dentro de una transacción.

## MySQL

```json
{"database":{"driver":"mysql","host":"127.0.0.1","port":3306,"name":"rog_addons","username":"rog","password":"REEMPLAZAR","sslCa":"","createIfMissing":true}}
```

Requiere MySQL 8.4+, InnoDB y utf8mb4. La cuenta de instalación necesita crear tablas, índices, vistas, triggers y funciones, además de leer y escribir datos. Con binlog activo, el administrador debe permitir la creación de las funciones deterministas incluidas. El instalador no cambia opciones globales del servidor. El permiso de crear la base solo es necesario si todavía no existe.

`sslCa` admite la ruta de un certificado CA en el servidor PHP y activa TLS con verificación del certificado. Sin CA se utiliza la conexión predeterminada del controlador. Los modos `sslMode` de PostgreSQL no se aplican a MySQL. La configuración directa admite `ROG_DB_SSL_CA`.

MySQL confirma las operaciones de estructura inmediatamente. El asistente registra el avance del intento inicial y lo reanuda después de un fallo, sin borrar tablas. El identificador de instalación y el plan de creación deben coincidir; una base ajena o un plan cambiado se rechazan. El fundador y los ajustes se guardan juntos en una transacción. La recuperación se ha probado tanto después de la primera tabla como a mitad de la creación y antes/después de confirmar los datos.

Después de instalar se puede usar una cuenta con `SELECT`, `INSERT`, `UPDATE`, `DELETE` y `EXECUTE` sobre la base de la aplicación. Las conexiones normales no necesitan permisos de creación de tablas. Los triggers y vistas conservan el definidor que los creó.

## SQLite

```json
{"database":{"driver":"sqlite","path":"/srv/rog-private/rog.sqlite","createIfMissing":true}}
```

En Windows, por ejemplo: `D:/rog-private/rog.sqlite`. La ruta debe ser absoluta, terminar en `.sqlite`, `.sqlite3` o `.db`, estar fuera del directorio público y tener una carpeta padre existente con permiso de escritura. También se rechazan las carpetas protegidas de la API y los enlaces simbólicos del archivo. No necesita host, puerto, usuario ni contraseña.

La prueba de conexión nunca crea el archivo de destino. Si falta y se permite crearlo, solo la instalación lo crea. Las conexiones habituales rechazan un archivo ausente. La estructura y el fundador se crean dentro de una transacción. SQLite serializa las escrituras mediante `BEGIN IMMEDIATE`, con un tiempo de espera de 15 segundos; es apropiado para instalaciones pequeñas, pero su concurrencia de escritura es distinta a la de los servidores PostgreSQL/MySQL.

La aplicación registra funciones SQLite para Unicode, JSON y fechas en cada conexión. Utilizar `Db` y los comandos PHP del proyecto para escribir datos o crear el esquema; un cliente SQLite genérico no incorpora esas funciones.

## Configuración directa y comportamiento común

Ejemplos de `ROG_DSN`:

```text
pgsql:host=127.0.0.1;port=5432;dbname=rog_addons
mysql:host=127.0.0.1;port=3306;dbname=rog_addons;charset=utf8mb4
sqlite:/srv/rog-private/rog.sqlite
```

`php bin/schema.php create` crea exclusivamente una estructura vacía. Para instalaciones MySQL que deban recuperarse de interrupciones, usar el asistente web y conservar su diario privado. `php bin/schema.php export mysql` (o `pgsql`, `sqlite`) permite revisar el SQL generado sin conexión. El SQL SQLite exportado necesita las funciones registradas por la aplicación.

Las fechas se guardan en UTC. Los intervalos de estadísticas respetan la zona horaria y los cambios de horario. JSON conserva la distinción entre propiedades ausentes y valores nulos. Las claves binarias y listas se convierten al leer/escribir modelos. Las solicitudes conservan su nombre visible y aplican normalización Unicode al impedir duplicados; MySQL guarda una clave técnica calculada por `Db` porque no dispone de normalización NFKC nativa.

PostgreSQL mantiene la vista materializada del catálogo, que `bin/worker.php` refresca
después de procesar los mensajes pendientes. En MySQL y SQLite se usa una vista normal:
los totales se calculan al consultar.

No hay migraciones ni sincronización automática. Los modelos definen la estructura de las
instalaciones nuevas. Cada motor conserva sus propios datos; cambiar el DSN no los convierte
ni los transfiere.

## Verificación

Las pruebas crean destinos desechables, recorren el formato HTTP único, comprueban instalación, recuperación, modelos, Unicode, JSON, archivos, publicación, moderación y accesos concurrentes. No se conectan a producción ni envían correo externo. Los resultados por motor quedan en la carpeta results/ del directorio temporal indicado por el ejecutor. Las instrucciones están en [pruebas de la API](tests.es.md). No son una prueba de carga masiva ni validación del TLS de un proveedor remoto.
