# Cómo instalar addons

## En 30 segundos

Un addon es una carpeta con archivos `.lua`, `.xml` y un `.toc`. Instalarlo es
copiar esa carpeta en el sitio correcto y activarla en la pantalla de personajes.

1. **Descarga el ZIP** — elige la versión de tu cliente.
2. **Descomprime en AddOns** — la carpeta va directa, no anidada.
3. **Actívalo en el juego** — botón AddOns en la selección de personaje.

## Encuentra la carpeta AddOns

Todo addon vive en `Interface/AddOns`, dentro de la carpeta de tu cliente. Busca
la fila de tu sistema y tu cliente:

| Sistema | Cliente | Ruta |
| --- | --- | --- |
| Windows | Clásico (hasta WoD) | `C:\Program Files (x86)\World of Warcraft\Interface\AddOns` |
| Windows | Moderno (desde BfA) | `C:\Program Files (x86)\World of Warcraft\_retail_\Interface\AddOns` |
| macOS | Clásico (hasta WoD) | `/Applications/World of Warcraft/Interface/AddOns` |
| macOS | Moderno (desde BfA) | `/Applications/World of Warcraft/_retail_/Interface/AddOns` |

Si `Interface` o `AddOns` no existen, créalas tú con ese nombre exacto. El juego
las reconoce igualmente.

> **Nota** En un servidor privado la carpeta se llama como la dejara el
> lanzador, muchas veces sin `_retail_`. Busca `Wow.exe`: `Interface` está al
> lado.

## Copia el addon

1. **Descomprime el ZIP** — clic derecho › *Extraer aquí*. Te salen una o varias
   carpetas, por ejemplo `DBM-Core` y `DBM-GUI`.
2. **Muévelas a AddOns** — arrastra *todas* las carpetas a `Interface/AddOns`.
   Tienen que quedar al mismo nivel que las demás, nunca dentro de otra carpeta.

```text Correcto
AddOns/
├── DBM-Core/
│   └── DBM-Core.toc
├── DBM-GUI/
└── HealBot/
```

```text Incorrecto
AddOns/
└── DBM-9.2.3-BfA/
    ├── DBM-Core/
    └── DBM-GUI/
```

3. **Comprueba el .toc** — cada carpeta tiene que llevar un archivo con su mismo
   nombre y extensión `.toc`. Sin él, el juego no ve el addon.

> **Aviso** Si tenías el juego abierto, ciérralo del todo —o al menos vuelve a la
> pantalla de personajes— antes de copiar. Los addons se cargan al entrar con un
> personaje.

## Activa el addon en el juego

1. **Abre el juego y ve a la selección de personaje** — el botón `AddOns`
   aparece abajo a la izquierda. Si no sale, no hay ninguna carpeta válida en
   `AddOns`: revisa el paso anterior.
2. **Marca los addons que quieras** — actívalos para todos los personajes o solo
   para el actual con el desplegable de arriba.
3. **Marca "Load out of date AddOns"** — es la casilla de arriba a la izquierda.
   Muchos addons de aquí salen como desactualizados solo porque su `.toc`
   declara otra subversión.

> **Nota** Dentro del juego, `/reload` recarga la interfaz sin salir. Úsalo
> después de cambiar los ajustes de un addon.

## Actualizar o quitar un addon

**Actualizar.** Borra las carpetas antiguas del addon y copia las nuevas. Tus
ajustes están a salvo: viven en `WTF/Account/…/SavedVariables`, no en `AddOns`.

**Desinstalar.** Quita la carpeta de `AddOns`. Para borrar también sus ajustes,
elimina el `.lua` con su nombre en `SavedVariables`.

## Problemas frecuentes

### El botón AddOns no aparece en la selección de personaje

No hay ninguna carpeta con un `.toc` válido en `Interface/AddOns`. La causa más
habitual: las carpetas acabaron dentro de otra con el nombre del ZIP. Súbelas al
nivel de `AddOns`.

### El addon sale en gris como "out of date"

Su `.toc` declara otra subversión del cliente. Marca "Load out of date AddOns"
arriba a la izquierda de la ventana de AddOns; el 95% de las veces funciona sin
más.

### Errores de Lua al entrar

Casi siempre es una compilación para otra expansión. Comprueba en la página del
addon que la etiqueta de expansión coincide con tu cliente y descarga la
compilación correcta desde el historial de versiones.

### Instalé una versión nueva y ahora las barras están duplicadas

Restos de la versión anterior. Borra todas las carpetas del addon en `AddOns` y
copia solo las del ZIP nuevo. Tus ajustes siguen en `WTF`.

### Quiero los mismos ajustes en otro personaje

Copia el `.lua` del addon de
`WTF/Account/TUCUENTA/Reino/Personaje/SavedVariables` al mismo sitio del otro
personaje, con el juego cerrado.

## Glosario rápido

| Término | Qué es |
| --- | --- |
| `.toc` | El índice del addon: nombre, versión de interfaz y lista de archivos que carga. |
| `Interface` | El número de cliente que declara el addon (p. ej. `30300` = 3.3.x, `80300` = 8.3.x). |
| `SavedVariables` | Tus ajustes de cada addon, guardados en `WTF/`. Sobreviven a las actualizaciones. |
| Error de Lua | Ventana roja al cargar. Casi siempre una compilación que no coincide con tu cliente. |
| `/reload` | Recarga la interfaz sin salir del juego. |
