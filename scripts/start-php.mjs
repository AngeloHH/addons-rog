/**
 * Addons ROG - Development API server
 *
 * - Start PHP with the shared runtime settings and the local frontend origin.
 */

import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { inspectPhp } from './php-runtime.mjs';

// =============================================================================
// PHP REQUIREMENTS
// =============================================================================

let runtime;
try { runtime = inspectPhp(); }
catch (error) { console.error(error.message); process.exit(1); }

if (!runtime) {
  console.error('PHP was not found. Run npm run setup.');
  process.exit(1);
}

if (runtime.configured.versionId < 80200 || runtime.missing.length) {
  console.error(`PHP ${runtime.configured.version} is missing: ${[
    ...(runtime.configured.versionId < 80200 ? ['PHP 8.2+'] : []),
    ...runtime.missing,
  ].join(', ')}. Run npm run setup.`);
  process.exit(1);
}

// =============================================================================
// LOCAL SERVER
// =============================================================================

const publicRoot = fileURLToPath(new URL('../api/public/', import.meta.url));
const router = fileURLToPath(new URL('../api/public/router.php', import.meta.url));
const args = [...runtime.args, '-S', '127.0.0.1:8000', '-t', publicRoot, router];

const child = spawn(runtime.path, args, {
  env: { ...process.env, ROG_ORIGIN: process.env.ROG_ORIGIN || 'http://127.0.0.1:5173' },
  stdio: 'inherit',
  shell: false,
  windowsHide: true,
});

child.on('error', (error) => {
  console.error(error.message);
  process.exitCode = 1;
});

child.on('exit', (code) => {
  process.exit(code ?? 0);
});
