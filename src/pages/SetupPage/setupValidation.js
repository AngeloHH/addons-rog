import { founderPasswordLength } from './setupConfig.js';

// Validate installer inputs before sending them to InstallService.
// Return the first error code for the step, or null when the inputs pass.
const length = (value) => Array.from(value).length;
const isPort = (value) => /^\d+$/.test(String(value)) && Number(value) >= 1 && Number(value) <= 65535;
const isEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());

function isOrigin(value) {
  try {
    const url = new URL(value.trim());
    return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password && !url.search && !url.hash && url.pathname === '/';
  } catch {
    return false;
  }
}

function isStoragePath(value) {
  const path = value.trim().replace(/\\/g, '/');
  return /^(?:[A-Za-z]:\/|\/)/.test(path) && !path.startsWith('//') && !/(?:^|\/)\.{1,2}(?:\/|$)/.test(path);
}

export function validateStep(step, { db, site, mail, admin }) {
  if (step === 'database') {
    if (db.driver === 'sqlite') return isStoragePath(db.path) && /\.(sqlite|sqlite3|db)$/i.test(db.path.trim()) ? null : 'DB_PATH';
    if (!/^[A-Za-z0-9._:-]{1,253}$/.test(db.host.trim())) return 'DB_HOST';
    if (!isPort(db.port)) return 'DB_PORT';
    if (!/^[a-z_][a-z0-9_]{0,62}$/.test(db.name.trim()) || ['postgres', 'template0', 'template1', 'mysql', 'sys', 'performance_schema', 'information_schema'].includes(db.name.trim())) return 'DB_NAME';
    if (!db.username.trim() || length(db.username.trim()) > 63 || length(db.password) > 1024) return 'DB_ACCOUNT';
  }

  if (step === 'site') {
    if (!site.name.trim() || length(site.name.trim()) > 120) return 'SITE_NAME';
    if (!isOrigin(site.url)) return 'SITE_URL';
    if (!site.timezone.trim()) return 'SITE_TIMEZONE';
    if (site.storagePath.trim() && !isStoragePath(site.storagePath)) return 'SITE_STORAGE';
    if (mail.from.trim() && !isEmail(mail.from)) return 'MAIL_FROM';
    if (mail.transport === 'smtp' && (!/^[A-Za-z0-9.-]{1,253}$/.test(mail.host.trim()) || !isPort(mail.port) || !isEmail(mail.from))) return 'MAIL_SMTP';
  }

  if (step === 'founder') {
    if (length(admin.username.trim()) < 3 || length(admin.username.trim()) > 24) return 'ADMIN_USERNAME';
    if (!isEmail(admin.email)) return 'ADMIN_EMAIL';
    if (length(admin.password) < founderPasswordLength.min || length(admin.password) > founderPasswordLength.max) return 'ADMIN_PASSWORD';
    if (admin.password !== admin.passwordConfirmation) return 'ADMIN_MATCH';
  }

  return null;
}

/**
 * Estimate founder password strength on a scale from 0 to 4.
 */
export function passwordStrength(password) {
  if (length(password) < founderPasswordLength.min) return password ? 1 : 0;

  const kinds = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].filter((pattern) => pattern.test(password)).length;
  return Math.min(4, 1 + Math.floor(kinds / 1.5) + (length(password) >= 20 ? 1 : 0));
}
