# Configuración operativa

Cada propiedad siguiente es una clave de settings. Guardar solo su objeto como archivo para bin/settings.php. Sustituir nombres, slugs, importes y buzones por los reales; estas son muestras.

Cada apartado corresponde a una clave de settings. Guarda su contenido JSON en un archivo y cárgalo desde api/:

    php bin/settings.php CLAVE archivo.json

## packDefinitions

```json
{
  "tank": {
    "name": "Tank kit",
    "description": "Addons elegidos para tanque",
    "membersByExpansion": {
      "wotlk": [
        "example-addon"
      ]
    }
  }
}
```

## contactRecipients

Los fundadores verificados y sin baneo tienen fichas individuales con su nombre de usuario y correo actuales.
La ficha común Moderadores envía a todos los moderadores y fundadores verificados y sin baneo (`access_level` 1 o 2).
Aparece aunque solo haya un fundador. Este grupo no necesita configurar ningún buzón.
Esta configuración añade buzones opcionales; solo se listan si tienen un correo válido.
Las claves usan 1–64 letras minúsculas, números, guiones bajos o guiones y empiezan por una letra.
`name` y `description` opcional son públicos; las direcciones de correo permanecen privadas.
`replyHours` admite 1–720 y usa 24 por defecto. La estimación de fundadores y moderadores también parte de 24 horas.
Utiliza los IDs de `GET /api/contact`: `account:{id}`, `group:moderation` o `mailbox:{key}`.
El envío al grupo crea un ticket y una notificación en cola independiente para cada cuenta elegible.

```json
{
  "moderation": {
    "name": "Moderación",
    "description": "Reportes de la comunidad y consultas de moderación.",
    "email": "replace@example.invalid",
    "replyHours": 24
  },
  "authors": {
    "name": "Autores",
    "email": "replace@example.invalid",
    "replyHours": 48
  }
}
```

## support

```json
{
  "month": "2026-10",
  "raised": 0,
  "goal": 0,
  "currency": "USD",
  "contributors": 0
}
```

## calculationRules

```json
{
  "pointsPerVersion": 100,
  "pointsPerComment": 5,
  "pointsPerLevel": 100,
  "threatPerAction": 25
}
```

## achievementDefinitions

```json
{}
```

## communityAchievements

```json
{
  "items": []
}
```

## taxonomy

```json
{
  "labels": {},
  "expansions": {
    "wotlk": {
      "name": "Wrath of the Lich King",
      "patch": "3.3.5a"
    }
  }
}
```

