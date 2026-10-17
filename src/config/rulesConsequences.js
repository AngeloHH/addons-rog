// =============================================================================
// THREAT LEVELS
// =============================================================================

// Order levels by increasing severity; keep meter values, colors, and translation keys together.

export const rulesThreat = [
  {
    id: 'warning',
    percent: 25,
    color: '#8fc98a',
    copyKey: 'rules.threatLevels.warning',
    levelKey: 'admin.threats.warning',
  },
  {
    id: 'takedown',
    percent: 50,
    color: '#c8a24a',
    copyKey: 'rules.threatLevels.takedown',
    levelKey: 'admin.threats.takedown',
  },
  {
    id: 'suspension',
    percent: 75,
    color: '#e39a6a',
    copyKey: 'rules.threatLevels.suspension',
    levelKey: 'admin.threats.suspension',
  },
  {
    id: 'ban',
    percent: 100,
    color: '#d95a5a',
    copyKey: 'rules.threatLevels.ban',
    levelKey: 'admin.threats.ban',
  },
];

// =============================================================================
// RULE NOTICES
// =============================================================================

// Resolve notice copy through i18n; the view supplies navigation targets for inline links.

export const rulesNotices = [
  { id: 'appeal', copyKey: 'rules.notices.appeal' },
  { id: 'author', copyKey: 'rules.notices.author' },
  { id: 'changes', copyKey: 'rules.notices.changes' },
];
