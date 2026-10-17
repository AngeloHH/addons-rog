/**
 * Addons ROG - Test addon generator launcher
 *
 * - Run the PHP fixture generator with the project's local PHP extensions.
 */

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { inspectPhp } from './php-runtime.mjs';

try {
  const php = inspectPhp();
  if (!php || php.missing.length) throw new Error('Run npm run setup:check to prepare PHP first.');
  const script = fileURLToPath(new URL('../api/tests/fixtures/generate-addons.php', import.meta.url));
  const result = spawnSync(php.path, [...php.args, script, ...process.argv.slice(2)], {
    stdio: 'inherit', windowsHide: true,
  });
  if (result.error) throw result.error;
  process.exitCode = result.status ?? 1;
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}
