import { spawnSync } from 'node:child_process';
import { platform } from 'node:process';
import { inspectPhp } from './php-runtime.mjs';

const checkOnly = process.argv.includes('--check');

function available(command) {
  const result = spawnSync(command, ['--version'], { stdio: 'ignore', windowsHide: true });
  return !result.error;
}

function run(command, args, { elevated = false } = {}) {
  const needsSudo = elevated && platform !== 'win32' && process.getuid?.() !== 0;
  const executable = needsSudo ? 'sudo' : command;
  const commandArgs = needsSudo ? [command, ...args] : args;
  console.log(`Running ${command} ${args.join(' ')}`);
  const result = spawnSync(executable, commandArgs, { stdio: 'inherit', windowsHide: true });
  if (result.error || result.status !== 0) {
    throw new Error(`${command} failed${result.error ? `: ${result.error.message}` : ` (exit ${result.status})`}.`);
  }
}

function manager() {
  if (platform === 'win32') return available('winget') ? 'winget' : null;
  if (platform === 'darwin') return available('brew') ? 'brew' : null;
  return ['apt-get', 'dnf', 'pacman', 'apk'].find(available) || null;
}

function installPhp(packages, currentVersion) {
  switch (packages) {
    case 'winget':
      if (currentVersion) throw new Error('The installed Windows PHP is incomplete or older than 8.2. Update that PHP distribution, or point PHP_BIN to a complete PHP 8.2+ executable.');
      run('winget', ['install', '--id', 'PHP.PHP.8.4', '--exact', '--source', 'winget', '--accept-package-agreements', '--accept-source-agreements']);
      break;
    case 'brew':
      run('brew', ['install', 'php']);
      break;
    case 'apt-get':
      run('apt-get', ['update'], { elevated: true });
      run('apt-get', ['install', '-y', 'php-cli', 'php-pgsql', 'php-mysql', 'php-sqlite3', 'php-intl', 'php-zip', 'php-mbstring', 'php-common'], { elevated: true });
      break;
    case 'dnf':
      run('dnf', ['install', '-y', 'php-cli', 'php-pgsql', 'php-mysqlnd', 'php-pdo', 'php-intl', 'php-zip', 'php-mbstring', 'php-common'], { elevated: true });
      break;
    case 'pacman':
      run('pacman', ['-S', '--needed', '--noconfirm', 'php', 'php-pgsql', 'php-sqlite'], { elevated: true });
      break;
    case 'apk': {
      const version = currentVersion ? currentVersion.replace(/^([0-9]+)\.([0-9]+).*$/, '$1$2') : '84';
      const prefix = `php${version}`;
      run('apk', ['add', prefix, `${prefix}-pdo_pgsql`, `${prefix}-pdo_mysql`, `${prefix}-pdo_sqlite`, `${prefix}-intl`, `${prefix}-zip`, `${prefix}-mbstring`, `${prefix}-fileinfo`, `${prefix}-openssl`], { elevated: true });
      break;
    }
    default:
      throw new Error('No supported PHP package manager found. Install PHP 8.2+ with a PDO database driver, intl, zip, mbstring, fileinfo and openssl, then run npm run setup again.');
  }
}

function printRuntime(runtime) {
  if (!runtime) { console.log('PHP: not found'); return; }
  console.log(`PHP: ${runtime.configured.version} (${runtime.path})`);
  console.log(`Extensions: ${runtime.missing.length ? `missing ${runtime.missing.join(', ')}` : 'all required extensions loaded'}`);
  console.log(`Limits: memory ${runtime.configured.memoryLimit}, upload ${runtime.configured.uploadLimit}, POST ${runtime.configured.postLimit}`);
}

function verify(runtime) {
  if (!runtime) throw new Error('PHP was not found after installation. Reopen the terminal or install PHP 8.2+ manually.');
  if (runtime.configured.versionId < 80200) throw new Error(`PHP ${runtime.configured.version} is too old. PHP 8.2+ is required.`);
  if (runtime.missing.length) throw new Error(`PHP still lacks ${runtime.missing.join(', ')}. Install those extensions for this PHP executable and rerun setup.`);
}

function installNpmDependencies() {
  console.log('Installing npm dependencies…');
  if (process.env.npm_execpath) {
    run(process.execPath, [process.env.npm_execpath, 'install']);
  } else {
    const npm = platform === 'win32' ? 'npm.cmd' : 'npm';
    const result = spawnSync(npm, ['install'], { stdio: 'inherit', shell: platform === 'win32' });
    if (result.error || result.status !== 0) throw new Error('npm install failed.');
  }
}

try {
  let runtime = inspectPhp();
  printRuntime(runtime);
  if (checkOnly) {
    verify(runtime);
    console.log('PHP setup is ready.');
  } else {
    if (!runtime || runtime.configured.versionId < 80200 || runtime.missing.length) {
      const packageManager = manager();
      if (platform === 'win32' && runtime?.missing.length) {
        // Windows PHP distributions ship extension DLLs; inspectPhp loads them
        // locally with -d, so a remaining missing module needs another PHP build.
        verify(runtime);
      }
      installPhp(packageManager, runtime?.configured.version);
      runtime = inspectPhp();
      printRuntime(runtime);
    }
    verify(runtime);
    installNpmDependencies();
    console.log('Setup complete. Run npm run dev.');
  }
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}
