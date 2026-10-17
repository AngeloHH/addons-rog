# Integración del frontend con la API

Las pantallas usan la API única bajo `/api`. El navegador no conoce el motor de base de datos: SQLite, MySQL y PostgreSQL siguen siendo responsabilidad del backend.

Contacto muestra fundadores individuales, la ficha común Moderadores y los buzones configurados.
La ficha común aparece aunque solo haya un fundador y notifica a todos los moderadores y fundadores verificados y sin baneo.
El ID seleccionado viene de `GET /api/contact`; el servidor resuelve nombres y direcciones de entrega.
La lista pública no incluye correos. Si no hay destinatarios disponibles, el envío queda deshabilitado.

## Funciones conectadas

| Pantalla | Datos y acciones |
| --- | --- |
| Sesión y cuentas | Inicio y cierre de sesión, registro, verificación, reenvío y cambio de correo, aceptación de invitaciones. |
| Portada y catálogo | Addons, categorías, expansiones, búsquedas, filtros, orden, páginas y descarga de packs. |
| Ficha de addon | Descripción, capturas, versiones, descargas, favoritos, reseñas y reportes. |
| Subidas | ZIP y análisis del servidor, imágenes, taxonomía, guardar y reabrir borradores, nuevas versiones y envío a revisión. |
| Solicitudes | Creación, votos, seguimiento, estados, paginación y enlaces a addons o subidas. |
| Perfiles | Estadísticas, addons, logros, actividad, reseñas y seguimiento. |
| Contacto | Destinatarios y cola reales, envío con adjunto y confirmación mediante ticket. |
| Administración | Contadores, revisión y decisiones, reportes, solicitudes, cuentas, mensajes, invitaciones, actividad, estadísticas y CSV. |
| Página 404 | Sugerencias obtenidas del catálogo publicado. |

`InstallationGate` comprueba `/api/setup/status` antes de montar la sesión y las páginas. Si `installed` es `false`, redirige a `/{idioma}/setup`. Los fallos de red permiten reintentar; no se interpretan como una instalación pendiente. Al salir del instalador se vuelve a comprobar el estado.

`src/api/client.js` centraliza sesión, CSRF, JSON, archivos y errores. No repite automáticamente operaciones de escritura. `SessionContext` mantiene la identidad compartida; `useApi` cancela lecturas obsoletas y separa los datos al cambiar de usuario. Los estados vacíos y errores son visibles y las lecturas permiten reintentar.

Los textos editoriales, colores, ilustraciones y definiciones de logros permanecen en
el frontend. Las listas y cifras de actividad proceden del servidor. Las cuentas utilizan
el registro e inicio de sesión propios de la web; la ayuda con contraseñas se gestiona
mediante contacto.

## Verificación

```sh
npm run test:client
npm run test:build
npm run test:installation
npm run test:frontend
npm run build
```

Las pruebas del cliente y del paquete usan Node y no necesitan servidor. `npm install` o
`npm run setup` instala la biblioteca de Playwright y descarga Chromium como dependencias
de desarrollo. No hace falta un directorio de paquetes externo ni tener Edge instalado.
Las pruebas del navegador solo acceden a sus servidores locales. La prueba completa requiere
PHP 8.2+ con `pdo_sqlite`, `intl`, `zip`, `mbstring`, `openssl` y `fileinfo`. Utiliza la misma
detección de PHP, extensiones locales y límites de subida que el desarrollo. `PHP_BIN` permite
elegir otro ejecutable; `npm run setup:check` comprueba sus requisitos.

Las reglas y respuestas del backend se comprueban en `api/tests/`. Las pruebas del navegador comprueban formularios, controles, navegación y resultados visibles; las llamadas directas a la API solo preparan datos auxiliares. La persistencia de los campos del borrador y sus cuatro capturas se cubre en `http/submissions/draft-fields.php`; los filtros y contadores de categorías secundarias, en `http/catalog/secondary-categories.php`.

La prueba completa copia los archivos de ejecución de la API y sus datos de prueba a un
directorio temporal, excluyendo la configuración local, los marcadores de instalación,
los archivos almacenados y las bases de datos. Puede ejecutarse con la web instalada.
Levanta PHP y Vite en puertos temporales, crea un esquema SQLite aislado y ejecuta acciones
reales desde Chromium. Las variables de correo y base de datos del proyecto se excluyen
de la configuración de prueba. Los archivos, cuentas y correos en cola pertenecen solamente
al entorno temporal; no se ejecuta ningún trabajador de correo. Los servidores temporales
se cierran al terminar. Las capturas y registros se conservan en el directorio temporal
que muestra la prueba.

El informe frontend-test-results.json se guarda en el directorio temporal de la ejecución, junto con sus registros. Esta suite de navegador verifica SQLite; no afirma haber repetido estos recorridos de navegador sobre MySQL o PostgreSQL. Los servicios de esos motores conservan sus pruebas independientes existentes.

El build genera el frontend y copia la API a `dist/api`. Vite puede advertir del tamaño del paquete JavaScript principal; la advertencia no impide compilar.

## Addons sintéticos para pruebas manuales

```sh
npm run fixtures:addons
npm run fixtures:addons -- .test-data/otra-generacion 20261017
```

El generador PHP crea 30 addons distintos y 90 ZIP: seis addons con cada cantidad de versiones,
desde una hasta cinco. Reparte los addons entre los siete clientes admitidos y las categorías
del catálogo. Cada ZIP contiene una carpeta estable por addon, su TOC, archivos Lua válidos y
texto aleatorio reproducible con la semilla indicada. Todos pasan el escáner antes de terminar.
Son datos sintéticos para probar la web; no son addons de juego reales.

Cada addon incluye un icono de 256 × 256 y cuatro capturas de 640 × 360 para el formulario.
`manifest.json` relaciona nombres, categorías, clientes, versiones, rutas y hashes SHA-256.
La carpeta de salida debe ser nueva: el comando no sobrescribe archivos existentes ni cambia
la base de datos. Sin argumentos, crea una carpeta fechada dentro de `.test-data/`.

`.test-data/` está excluida de Git y del paquete publicado. El generador permanece en
`api/tests/fixtures/generate-addons.php`, también fuera del paquete de producción.
