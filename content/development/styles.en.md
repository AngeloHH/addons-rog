# Shared styles

`src/styles/main.scss` is the global entry point, imported once from `src/main.jsx`.

The paths below are relative to `src/styles/`.

## Structure

- `_fonts.scss`: loads the local WoW Plexus font. Google fonts are still loaded from `index.html`.
- `base/_tokens.scss`: palette, font families, page width and layout breakpoints.
- `base/_global.scss`: base rules, select options, general structure, mobile utilities and anchor scrolling.
- `base/_layout.scss`: centered containers and spacing for notices at the top of the page.
- `base/_typography.scss`: headings, eyebrow labels and truncated text.
- `base/_columns.scss`: responsive columns generated for grids.
- `components/_surfaces.scss`: plain panel, gold frame, header and dark control finishes.
- `components/_buttons.scss`: complete gold button and its normal/hover finishes.
- `components/_forms.scss`: shared labels and fields.
- `components/_account.scss`: global structure for account screens.
- `components/_feedback.scss`: notices, empty states, pagination and API actions.

The `.module.scss` files keep each component's layout and variants. They import only variables and mixins: never `main`, `fonts`, `global`, `account` or `feedback`, since those generate global CSS.

## Reusing a pattern

From a component module:

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

## Screen layout

Appearance mixins do not impose dimensions. Each screen keeps its own sizing and spacing. Reuse an existing pattern before copying declarations; values specific to one screen can stay in its module.
