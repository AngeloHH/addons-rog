# Project documentation

[Índice en español](README.md)

This documentation describes the project's final state, its installation, use and operation.

Game guides and technical documentation are collected here, organized by
topic. Each document keeps its language: `.en.md` for English and `.es.md` for
Spanish. Every document published on the website has an English version.

## Game guides

| Guide | English | Español |
| --- | --- | --- |
| Installing and updating addons | [Read](guides/install.en.md) | [Leer](guides/install.es.md) |
| Role packs | [Read](guides/role-packs.en.md) | [Leer](guides/role-packs.es.md) |

## Development

| Document | English | Español |
| --- | --- | --- |
| Project setup and development | [Read](development/setup.en.md) | — |
| Shared styles | [Read](development/styles.en.md) | [Leer](development/styles.es.md) |
| Frontend integration | [Read](development/frontend-integration.en.md) | [Leer](development/frontend-integration.es.md) |
| Email templates | [Read](development/email-templates.en.md) | — |

## API

| Document | English | Español |
| --- | --- | --- |
| Overview | [Read](api/overview.en.md) | [Leer](api/overview.es.md) |
| Site installation | [Read](api/installation.en.md) | [Leer](api/installation.es.md) |
| Databases | [Read](api/databases.en.md) | [Leer](api/databases.es.md) |
| Operational settings | [Read](api/settings.en.md) | [Leer](api/settings.es.md) |
| API reference | [Read](api/reference.en.md) | [Leer](api/reference.es.md) |
| Canonical routes | [Read](api/routes.en.md) | [Leer](api/routes.es.md) |
| Column dictionary | [Read](api/schema.en.md) | [Leer](api/schema.es.md) |
| Design decisions | [Read](api/decisions.en.md) | [Leer](api/decisions.es.md) |
| Running tests | [Read](api/tests.en.md) | [Leer](api/tests.es.md) |
| Validation scope | [Read](api/validation.en.md) | [Leer](api/validation.es.md) |

## Document organization

The website loads documents from `guides/`, `development/` and `api/` and groups
them by those areas. If a translation is missing, it displays the English version
or the document's available language. Links between Markdown files open their
corresponding website pages.

Files are grouped by topic and named after their content. Each document uses one
main heading, brief sections, lists for steps and code blocks with their language
specified. Links between documents use relative paths, and this index collects
their entries.

Code paths start at the project root unless a guide specifies another directory.
API commands run from `api/`.
