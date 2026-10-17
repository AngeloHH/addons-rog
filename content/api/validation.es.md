# Validación de la API

Las pruebas utilizan bases desechables, servidores locales y credenciales temporales.
Las instrucciones y la estructura de los escenarios están en [pruebas de la API](tests.es.md).

## Alcance

- Instalación inicial, requisitos, autorización, bloqueo y recuperación ante fallos.
- Rutas canónicas, sesiones, CSRF, permisos y tipos de respuesta.
- Modelos, JSON, Unicode y consultas sobre PostgreSQL, MySQL y SQLite.
- Subidas multipart, publicación de addons y versiones, moderación y archivos.
- Solicitudes, reseñas, perfiles, contacto y estadísticas.
- Concurrencia en ediciones, votos, descargas y reseñas.
- Limpieza, métricas y entrega de correo a archivos locales.

Las comprobaciones adicionales de SMTP con Ethereal se describen en
[pruebas de correo](tests.es.md).

El inventario esperado de rutas está en [routes.json](../../api/tests/fixtures/routes.json).
Los escenarios se identifican por su área de la API y nombre durante la ejecución.

## Informes

Cada ejecución genera sus informes JSON en results/ dentro de su directorio temporal.
El ejecutor imprime la ubicación al terminar. Los informes corresponden a esa ejecución;
no se conservan resultados históricos dentro de la documentación ni del código fuente.

Las pruebas del frontend guardan frontend-test-results.json en su propio directorio temporal.

## Alcance de los resultados

Los resultados describen los escenarios funcionales y concurrentes ejecutados en entornos
locales desechables; no establecen una capacidad máxima de usuarios simultáneos. Las pruebas
SMTP verifican los mensajes capturados por Ethereal. El análisis de ZIP comprueba estructura
y extensiones peligrosas; no sustituye un antivirus.
