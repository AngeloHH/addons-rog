import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, isAbsolute, join, resolve } from 'node:path';
import { platform } from 'node:process';
import { resolvePhpPath } from './php-path.mjs';

export const databaseExtensions = ['pdo_pgsql', 'pdo_mysql', 'pdo_sqlite'];
export const requiredExtensions = ['intl', 'zip', 'mbstring', 'fileinfo', 'openssl'];
export const phpSettings = [
  'memory_limit=256M',
  'upload_max_filesize=50M',
  'post_max_size=60M',
];

const probeCode = `echo json_encode([
  'version' => PHP_VERSION,
  'versionId' => PHP_VERSION_ID,
  'extensions' => array_map('strtolower', get_loaded_extensions()),
  'extensionDir' => ini_get('extension_dir'),
  'memoryLimit' => ini_get('memory_limit'),
  'uploadLimit' => ini_get('upload_max_filesize'),
  'postLimit' => ini_get('post_max_size'),
], JSON_THROW_ON_ERROR);`;

function runProbe(phpPath, args = []) {
  const result = spawnSync(phpPath, [...args, '-r', probeCode], { encoding: 'utf8', windowsHide: true });
  if (result.error || result.status !== 0) {
    const detail = result.error?.message || result.stderr?.trim() || `exit code ${result.status}`;
    throw new Error(`PHP could not start (${detail}).`);
  }
  try { return JSON.parse(result.stdout); }
  catch { throw new Error('PHP did not return readable runtime information.'); }
}

export function missingExtensions(probe) {
  const loaded = new Set(probe.extensions);
  return [...requiredExtensions.filter((name) => !loaded.has(name)), ...(databaseExtensions.some((name) => loaded.has(name)) ? [] : ['PDO database driver'])];
}

export function extensionDirectory(phpPath, configuredDir) {
  if (platform === 'win32') return join(dirname(phpPath), 'ext');
  return isAbsolute(configuredDir) ? configuredDir : resolve(dirname(phpPath), configuredDir || 'ext');
}

export function localExtensionArgs(phpPath, probe) {
  const directory = extensionDirectory(phpPath, probe.extensionDir);
  const suffix = platform === 'win32' ? '.dll' : '.so';
  const prefix = platform === 'win32' ? 'php_' : '';
  const missing = [...requiredExtensions, ...databaseExtensions].filter((name) => !probe.extensions.includes(name));
  const loadable = missing.filter((name) => existsSync(join(directory, `${prefix}${name}${suffix}`)));
  return loadable.length
    ? ['-d', `extension_dir=${directory}`, ...loadable.flatMap((name) => ['-d', `extension=${name}`])]
    : [];
}

export function inspectPhp(phpPath = resolvePhpPath()) {
  if (!phpPath) return null;
  const base = runProbe(phpPath);
  const extensionArgs = localExtensionArgs(phpPath, base);
  const args = [...extensionArgs, ...phpSettings.flatMap((setting) => ['-d', setting])];
  const configured = runProbe(phpPath, args);
  return { path: phpPath, base, configured, args, missing: missingExtensions(configured) };
}
