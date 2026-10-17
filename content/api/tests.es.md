# Ejecutar las pruebas de la API

Desde api/, con PHP 8.2+:

    php tests/run.php sqlite
    php tests/run.php mysql
    php tests/run.php pgsql

Sin argumento se utiliza PostgreSQL. Los ejecutores de la API no necesitan Node.js ni npm.
Las pruebas del frontend, en scripts/tests/, siguen utilizando las herramientas de JavaScript.

## Preparación

PHP debe tener pdo_sqlite, pdo_mysql o pdo_pgsql, según el motor elegido, además de
intl, zip, mbstring, openssl y fileinfo. Necesita poder crear procesos con proc_open
y abrir puertos locales.

MySQL requiere los binarios de MySQL 8.4+ y PostgreSQL los de PostgreSQL 15+.
Ejemplo PowerShell:

    $env:MYSQL_BIN = 'C:\ruta\mysql\bin'
    $env:PG_BIN = 'C:\ruta\postgresql\bin'
    php tests/run.php mysql
    php tests/run.php pgsql

En Linux/macOS, por ejemplo:

    PG_BIN=/ruta/postgresql/bin php tests/run.php pgsql
    MYSQL_BIN=/ruta/mysql/bin php tests/run.php mysql

PostgreSQL debe ejecutarse con un usuario normal: initdb no admite root.

Por defecto, los procesos hijos utilizan el mismo ejecutable de PHP. PHP_BIN permite elegir
otro y PHP_EXTENSION_DIR permite cargar explícitamente las extensiones desde un directorio.
Sin esa variable, las extensiones deben estar activadas en el php.ini habitual.

La ejecución rechaza config/config.local.php. Utilizar una copia limpia de la API para que
una configuración local no sustituya el destino de prueba. Las pruebas individuales exigen
ROG_TEST_MODE=disposable y usuario rog_test; el ejecutor configura ambas condiciones.

## Qué se ejecuta

run.php es la entrada y support/TestRunner.php prepara y cierra los procesos. Cada ejecución crea
un directorio temporal nuevo, puertos libres y credenciales aleatorias. SQLite utiliza un
archivo nuevo; MySQL y PostgreSQL utilizan servidores privados limitados a 127.0.0.1.

Se comprueban el esquema inicial, la instalación web desde una copia privada, la recuperación
ante interrupciones, los modelos, las rutas HTTP, las validaciones, los permisos, CSRF,
Unicode, JSON, archivos y publicación. También se prueban cuatro escenarios concurrentes:
ediciones, votos, descargas y reseñas. Al final se ejecutan la limpieza y el trabajador de correo.

Los servidores se detienen al terminar, incluso si una comprobación falla, y se eliminan
los archivos temporales usados para inicializar las credenciales. Los registros y las bases
detenidas quedan en el directorio indicado al final. ROG_TEST_WORKDIR permite elegir el
directorio padre; cada ejecución crea su propio subdirectorio.

Los errores deliberados de restricciones e instalación son parte de las pruebas. En esta suite,
el correo se escribe en una cola local: no se envían mensajes externos. Su alcance es funcional
y de concurrencia; no mide la capacidad máxima de un alojamiento.

## Comprobación SMTP con Ethereal

El 17 de octubre de 2026 se realizó una comprobación adicional con una cuenta temporal de
Ethereal y una copia aislada de la API. El Mailer PHP existente envió cinco mensajes por SMTP
con STARTTLS y verificación del certificado; los mensajes se descargaron desde la bandeja web
por HTTPS. El código recibido activó una cuenta mediante la API. También se comprobaron la
notificación de aprobación, la entrega de contacto a un fundador y un moderador, y la
conservación de acentos y símbolos en asunto y contenido.

Una contraseña SMTP incorrecta dejó el mensaje pendiente con un intento fallido. Tras corregirla
y adelantar su disponibilidad únicamente en la base aislada, el reintento se entregó. Una nueva
ejecución del worker no volvió a enviar los mensajes completados. La instalación de desarrollo
permaneció intacta. Las credenciales y los mensajes capturados quedaron en la carpeta privada
de esa comprobación, excluida de Git y de la publicación.

Ethereal captura los mensajes: esta prueba verifica el transporte SMTP y los flujos de la API,
pero no certifica la llegada a bandejas reales ni la configuración del proveedor de producción.

## Archivos auxiliares y resultados

La estructura distingue las áreas de la API de la infraestructura de pruebas:

    tests/
      run.php                     Entrada para SQLite, MySQL y PostgreSQL
      http/
        run.php                   Orden de los escenarios HTTP
        authentication/           Registro, sesiones, permisos, CSRF e invitaciones
        catalog/                  Listados públicos y taxonomía
        submissions/              Borradores, publicación, versiones y rechazo
        comments/                 Creación y edición de reseñas
        downloads/                Descargas firmadas y paquetes
        requests/                 Votos, seguimiento y moderación de solicitudes
        reports/                  Advertencias, reapertura y retirada
        profiles/                 Seguimiento y privacidad de perfiles
        contact/                  Mensajes y adjuntos
        admin/                    Cuentas, panel y estadísticas
        files/                    Validación de ZIP e imágenes
        routing/                  Rutas, métodos y compatibilidad
        response-formats.php      Tipos y estructura de respuestas
      database/                   Esquemas, modelos y portabilidad SQL
      installation/               Instalador web y recuperación ante fallos
      concurrency/                Ediciones, votos, descargas y reseñas simultáneas
      notifications/              Entrega de correo mediante el trabajador
      validation/                 Fechas y contraseñas
      fixtures/                   Datos y preparación del frontend
      support/                    Ejecutor, cliente HTTP, aserciones y workers

Los escenarios tienen nombres como publish-addon.php, signed-files-and-packs.php y
roles-and-csrf.php. http/run.php declara el orden de ejecución: comparten una base
desechable y algunos utilizan los usuarios o addons creados por escenarios anteriores.
Por eso se ejecutan mediante run.php, en lugar de abrir cada archivo por separado.

La salida identifica cada escenario y cuántas comprobaciones ha superado. Los workers
de concurrencia y arranque se ejecutan como procesos PHP independientes.
fixtures/frontend.php prepara los datos de las pruebas del frontend.

fixtures/routes.json contiene el inventario esperado de rutas y
fixtures/response-schemas.json los tipos de las respuestas. La documentación para lectura
está en [referencia de la API](reference.es.md).

Los informes JSON se guardan en la carpeta results/ del directorio temporal de cada ejecución.
El ejecutor imprime su ruta al terminar. Incluyen test-results-<motor>.json,
installer-results-<motor>.json, concurrency-results-<motor>.json e
initial-schema-results-<motor>.json. Los informes no se escriben en content/ ni en el código fuente.
