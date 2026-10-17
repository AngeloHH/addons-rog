import { adminRoles } from './admin.js';

// =============================================================================
// ACCOUNT STATUSES
// =============================================================================

// Supported account statuses and their display colors.

export const accountStatuses = [
  { id: 'Active', color: '#8fc98a' },
  { id: 'Suspended', color: '#e39a6a' },
  { id: 'Banned', color: '#d95a5a' },
];

// =============================================================================
// ACCOUNT ROLES
// =============================================================================

// Order roles by increasing authority for the select; reuse the directory colors.

export const accountRoles = [...adminRoles]
  .reverse()
  .map((item) => ({ id: item.role, color: item.color }));

// =============================================================================
// THREAT LEVELS
// =============================================================================

// Five named ranges with their corresponding display colors.

const threatLevels = [
  { upto: 0, id: 'clean', color: '#8fc98a' },
  { upto: 25, id: 'warning', color: '#8fc98a' },
  { upto: 50, id: 'takedown', color: '#c8a24a' },
  { upto: 75, id: 'suspension', color: '#e39a6a' },
  { upto: 100, id: 'ban', color: '#d95a5a' },
];

export function threatLevel(threat) {
  return threatLevels.find((level) => threat <= level.upto) ?? threatLevels.at(-1);
}

export function accountColor(list, id) {
  return list.find((item) => item.id === id)?.color;
}
