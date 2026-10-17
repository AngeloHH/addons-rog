const expansions = [
  {
    id: 'wotlk',
    abbr: 'LK',
    name: 'Wrath of the Lich King',
    patch: '3.3.5a',
    colorDark: '#12283a',
    colorAccent: '#7fb7d9',
  },
  {
    id: 'mop',
    abbr: 'MoP',
    name: 'Mists of Pandaria',
    patch: '5.4.8',
    colorDark: '#17301a',
    colorAccent: '#8fc98a',
  },
  {
    id: 'wod',
    abbr: 'WoD',
    name: 'Warlords of Draenor',
    patch: '6.2.4',
    colorDark: '#3a1c10',
    colorAccent: '#d98b5a',
  },
  {
    id: 'bfa',
    abbr: 'BfA',
    name: 'Battle for Azeroth',
    patch: '8.3.7',
    colorDark: '#3a2a10',
    colorAccent: '#c8a24a',
  },
  {
    id: 'sl',
    abbr: 'SL',
    name: 'Shadowlands',
    patch: '9.2.7',
    colorDark: '#1c1a34',
    colorAccent: '#9fa6e8',
  },
  {
    id: 'df',
    abbr: 'DF',
    name: 'Dragonflight',
    patch: '10.2.7',
    colorDark: '#2e1a12',
    colorAccent: '#e39a6a',
  },
  {
    id: 'tww',
    abbr: 'TWW',
    name: 'The War Within',
    patch: '11.0.7',
    colorDark: '#1a2a2a',
    colorAccent: '#7fd0c8',
  },
];

export function findExpansion(expansion) {
  return expansions.find((item) => item.id === String(expansion).toLowerCase());
}

export function expansionAccent(expansion) {
  return findExpansion(expansion)?.colorAccent ?? '#c8a24a';
}
