// =============================================================================
// INSTALLATION STEPS
// =============================================================================

// Five ordered steps; i18n supplies each step's text under steps.<id>.

export const setupSteps = ['requirements', 'database', 'site', 'founder', 'install'];

// =============================================================================
// REQUIREMENT DISPLAY ORDER
// =============================================================================

// Render checks from GET /api/setup/requirements in this order; optional checks only warn.

export const requirementIds = [
  'php',
  'pdo_database',
  'intl',
  'zip',
  'mbstring',
  'fileinfo',
  'openssl',
  'config_writable',
  'storage_writable',
  'memory_limit',
  'upload_max_filesize',
  'post_max_size',
];

export const sslModes = ['prefer', 'require', 'verify-ca', 'verify-full', 'disable'];
export const databaseEngines = [{ id: 'pgsql', name: 'PostgreSQL 15+', port: '5432' }, { id: 'mysql', name: 'MySQL 8.4+', port: '3306' }, { id: 'sqlite', name: 'SQLite 3.38+', port: '' }];

// =============================================================================
// SITE LANGUAGES
// =============================================================================

// Languages accepted by the API as the site's default.

export const siteLanguages = [
  { code: 'en', name: 'English' },
  { code: 'es', name: 'Español' },
  { code: 'pt', name: 'Português' },
  { code: 'de', name: 'Deutsch' },
  { code: 'fr', name: 'Français' },
  { code: 'it', name: 'Italiano' },
  { code: 'ru', name: 'Русский' },
  { code: 'pl', name: 'Polski' },
  { code: 'ko', name: '한국어' },
  { code: 'zh', name: '中文' },
];

export const tokenLength = { min: 32, max: 256 };
export const founderPasswordLength = { min: 12, max: 128 };

/**
 * Suggest timezones while leaving validation to the server's supported list.
 */
export function timezones() {
  try {
    return Intl.supportedValuesOf('timeZone');
  } catch {
    return ['UTC', 'Europe/Madrid', 'America/New_York', 'America/Mexico_City', 'America/Bogota'];
  }
}

export function initialValues(language) {
  return {
    db: {
      driver: 'pgsql',
      path: '',
      sslCa: '',
      host: '127.0.0.1',
      port: '5432',
      name: 'rog_addons',
      username: 'rog',
      password: '',
      sslMode: 'prefer',
      createIfMissing: false,
    },
    site: {
      name: 'RoG Addons',
      url: window.location.origin,
      language: siteLanguages.some((item) => item.code === language) ? language : 'en',
      timezone: 'UTC',
      storagePath: '',
    },
    mail: { transport: 'spool', from: '', host: '', port: '587', username: '', password: '' },
    admin: { username: '', email: '', password: '', passwordConfirmation: '' },
  };
}
