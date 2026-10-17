# Documentación del proyecto

[English index](README.en.md)

Esta documentación describe el estado final del proyecto, su instalación, uso y funcionamiento.

Las guías del juego y la documentación técnica se reúnen aquí, ordenadas por
tema. Cada documento conserva su idioma: `.en.md` para inglés y `.es.md` para
español.

## Guías del juego

| Guía | Español | English |
| --- | --- | --- |
| Instalar y actualizar addons | [Leer](guides/install.es.md) | [Read](guides/install.en.md) |
| Packs por función | [Leer](guides/role-packs.es.md) | [Read](guides/role-packs.en.md) |

## Desarrollo

| Documento | Español | English |
| --- | --- | --- |
| Preparar y ejecutar el proyecto | — | [Read](development/setup.en.md) |
| Estilos compartidos | [Leer](development/styles.es.md) | [Read](development/styles.en.md) |
| Integración del frontend | [Leer](development/frontend-integration.es.md) | [Read](development/frontend-integration.en.md) |
| Plantillas de correo | — | [Read](development/email-templates.en.md) |

## API

| Documento | Español | English |
| --- | --- | --- |
| Introducción | [Leer](api/overview.es.md) | [Read](api/overview.en.md) |
| Instalación del sitio | [Leer](api/installation.es.md) | [Read](api/installation.en.md) |
| Bases de datos | [Leer](api/databases.es.md) | [Read](api/databases.en.md) |
| Configuración operativa | [Leer](api/settings.es.md) | [Read](api/settings.en.md) |
| Referencia de la API | [Leer](api/reference.es.md) | [Read](api/reference.en.md) |
| Rutas canónicas | [Leer](api/routes.es.md) | [Read](api/routes.en.md) |
| Diccionario de columnas | [Leer](api/schema.es.md) | [Read](api/schema.en.md) |
| Decisiones de diseño | [Leer](api/decisions.es.md) | [Read](api/decisions.en.md) |
| Ejecutar las pruebas | [Leer](api/tests.es.md) | [Read](api/tests.en.md) |
| Alcance de la validación | [Leer](api/validation.es.md) | [Read](api/validation.en.md) |

## Organización de los documentos

La web carga los documentos de `guides/`, `development/` y `api/` y los agrupa
por esas mismas áreas. Si no existe una traducción, muestra la versión en inglés
o el idioma disponible del documento. Los enlaces entre archivos Markdown
abren su página correspondiente en la web.

Los archivos se agrupan por tema y tienen nombres que describen su contenido.
Cada documento usa un título principal, apartados breves, listas para los pasos
y bloques de código con el lenguaje indicado. Los enlaces entre documentos usan
rutas relativas y este índice reúne sus entradas.

Las rutas de código parten de la raíz del proyecto salvo que la guía indique
otra carpeta. Los comandos de la API se ejecutan desde `api/`.
