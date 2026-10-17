// =============================================================================
// RULE CHECKLIST
// =============================================================================

// expected is the compliant answer; critical blocks uploading; copyKey resolves questions and feedback.

export const rulesChecks = [
  { id: 'clean', critical: true, expected: true, copyKey: 'rules.checks.clean' },
  { id: 'free', critical: true, expected: true, copyKey: 'rules.checks.free' },
  { id: 'author', expected: true, copyKey: 'rules.checks.author' },
  { id: 'tested', expected: true, copyKey: 'rules.checks.tested' },
  { id: 'duplicate', expected: true, copyKey: 'rules.checks.duplicate' },
];
