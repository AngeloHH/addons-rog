// =============================================================================
// SITE TIMELINE
// =============================================================================

// Colors highlight milestones; copyKey resolves each event's title, badge, and description.

export const aboutTimeline = [
  { id: 'folder', year: '2020', accent: '#7fb7d9', dot: '#3a2a10', copyKey: 'about.timeline.folder' },
  { id: 'launch', year: '2020', accent: '#7fb7d9', dot: '#e2bd63', copyKey: 'about.timeline.launch' },
  { id: 'expansions', year: '2021', accent: '#8fc98a', dot: '#3a2a10', copyKey: 'about.timeline.expansions' },
  { id: 'uploads', year: '2022', accent: '#c8a24a', dot: '#e2bd63', copyKey: 'about.timeline.uploads' },
  { id: 'requests', year: '2023', accent: '#c8a24a', dot: '#3a2a10', copyKey: 'about.timeline.requests' },
  {
    id: 'independence',
    year: '2024',
    accent: '#e39a6a',
    dot: '#3a2a10',
    copyKey: 'about.timeline.independence',
  },
  { id: 'redesign', year: '2026', accent: '#7fd0c8', dot: '#e2bd63', copyKey: 'about.timeline.redesign' },
];

// =============================================================================
// FOUNDER PROFILES
// =============================================================================

// Profile card metadata and bar values; names remain data and copyKey resolves translated text.

export const aboutFounders = [
  {
    id: 'angelo',
    name: 'AngeloHH',
    initial: 'A',
    colorDark: '#1c150a',
    colorAccent: '#c8a24a',
    bars: [
      { id: 'server', value: 96 },
      { id: 'backend', value: 88 },
      { id: 'patience', value: 74 },
    ],
    copyKey: 'about.founderProfiles.angelo',
  },
  {
    id: 'gaara',
    name: 'KZKG^Gaara',
    initial: 'K',
    colorDark: '#12283a',
    colorAccent: '#7fb7d9',
    bars: [
      { id: 'review', value: 99 },
      { id: 'catalogue', value: 92 },
      { id: 'diplomacy', value: 81 },
    ],
    copyKey: 'about.founderProfiles.gaara',
  },
];

// =============================================================================
// SITE ACHIEVEMENTS
// =============================================================================

// Achievement IDs, points, and translation keys; the API supplies completion and progress.

export const aboutAchievements = [
  { id: 'first-zip', points: 10, copyKey: 'about.achievements.first-zip' },
  { id: 'dalaran', points: 25, copyKey: 'about.achievements.dalaran' },
  { id: 'five', points: 25, copyKey: 'about.achievements.five' },
  { id: 'adventurers', points: 20, copyKey: 'about.achievements.adventurers' },
  { id: 'zero-errors', points: 15, copyKey: 'about.achievements.zero-errors' },
  { id: 'no-banners', points: 30, copyKey: 'about.achievements.no-banners' },
  { id: 'million', points: 50, copyKey: 'about.achievements.million' },
  { id: 'funded', points: 40, copyKey: 'about.achievements.funded' },
];
