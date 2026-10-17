// =============================================================================
// ADMIN NAVIGATION
// =============================================================================

// badge shows highlighted attention counts; count shows plain totals supplied by the view.

export const adminNav = [
  { id: 'dashboard', group: 'manage', icon: 'dashboard', labelKey: 'admin.dashboard' },
  { id: 'queue', group: 'manage', icon: 'inbox', badge: 'queue', labelKey: 'admin.reviewQueue' },
  { id: 'reports', group: 'manage', icon: 'flag', badge: 'reports', labelKey: 'admin.reports' },
  { id: 'requests', group: 'manage', icon: 'hand', labelKey: 'admin.requests' },
  { id: 'stats', group: 'manage', icon: 'chart', live: true, labelKey: 'admin.stats' },
  { id: 'accounts', group: 'people', icon: 'users', count: 'accounts', labelKey: 'admin.accounts' },
  { id: 'activity', group: 'people', icon: 'pulse', live: true, labelKey: 'admin.activity' },
];

export const adminRanges = [
  { value: '7d', labelKey: 'admin.periods.7d' },
  { value: '30d', labelKey: 'admin.periods.30d' },
  { value: '90d', labelKey: 'admin.periods.90d' },
];

// =============================================================================
// HEADER STATISTIC LABELS
// =============================================================================

// Keep translation keys here; the dashboard supplies current values through the API.

export const adminKpis = [
  { id: 'downloads', labelKey: 'admin.downloads' },
  { id: 'uploads', labelKey: 'admin.uploadsColumn' },
  { id: 'queue', labelKey: 'admin.pendingReview' },
  { id: 'reports', labelKey: 'admin.openReports' },
  { id: 'users', labelKey: 'admin.activePlayers' },
];

// =============================================================================
// ACTIVITY GROUPS
// =============================================================================

// Map each event kind to the group used by counters and filters.

export const adminActivityGroups = [
  { id: 'uploads', color: '#8fc98a', kinds: ['Upload', 'Approve'] },
  { id: 'comments', color: '#9fa6e8', kinds: ['Comment'] },
  { id: 'signUps', color: '#7fb7d9', kinds: ['Account'] },
  { id: 'requests', color: '#c8a24a', kinds: ['Request'] },
  { id: 'reports', color: '#e39a6a', kinds: ['Report'] },
  { id: 'removals', color: '#d95a5a', kinds: ['Delete', 'Reject', 'Ban', 'Quarantine'] },
];

// =============================================================================
// ACCOUNT ROLE SUMMARY
// =============================================================================

// Role labels and colors for the directory summary; counts come from the API.

export const adminRoles = [
  { id: 'founders', role: 'Founder', color: '#c8a24a' },
  { id: 'moderators', role: 'Moderator', color: '#9fa6e8' },
  { id: 'authors', role: 'Author', color: '#8fc98a' },
  { id: 'players', role: 'Player', color: '#8f7b54' },
];
