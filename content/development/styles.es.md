# Estilos compartidos

`src/styles/main.scss` es la entrada global, importada una sola vez desde `src/main.jsx`.

Las rutas del siguiente listado parten de `src/styles/`.

## Estructura

- `_fonts.scss`: carga local de WoW Plexus. Las fuentes de Google siguen cargándose desde `index.html`.
- `base/_tokens.scss`: paleta, familias de fuentes, ancho de página y puntos de cambio de diseño.
- `base/_global.scss`: reglas base, opciones de select, estructura general, utilidades móviles y desplazamiento a anclas.
- `base/_layout.scss`: contenedores centrados y espacio de los avisos superiores.
- `base/_typography.scss`: títulos, etiquetas superiores y texto truncado.
- `base/_columns.scss`: columnas adaptables generadas para las cuadrículas.
- `components/_surfaces.scss`: panel sencillo, marco dorado, cabecera y acabado oscuro de los controles.
- `components/_buttons.scss`: botón dorado completo y sus acabados normal/hover.
- `components/_forms.scss`: etiquetas y campos compartidos.
- `components/_account.scss`: estructura global de las pantallas de cuenta.
- `components/_feedback.scss`: avisos, estados vacíos, paginación y acciones de la API.

Los archivos `.module.scss` conservan la distribución y las variantes propias de cada componente. Importan solo variables y mixins: nunca `main`, `fonts`, `global`, `account` o `feedback`, porque estos últimos generan CSS global.

## Reutilizar un patrón

Desde un módulo de componente:

```scss
@use '../../styles/base/tokens';
@use '../../styles/components/surfaces';
@use '../../styles/components/buttons';

.panel {
  @include surfaces.panel;
  padding: 18px;
  color: tokens.$text-secondary;
}

.submit {
  @include buttons.gold;
  min-height: 44px;
  padding: 8px 20px;
}
```

## Distribución de cada pantalla

Los mixins de aspecto no imponen dimensiones. Cada pantalla conserva su tamaño y separación. Reutiliza el patrón existente antes de copiar declaraciones; los valores exclusivos de una pantalla pueden quedarse en su módulo.
