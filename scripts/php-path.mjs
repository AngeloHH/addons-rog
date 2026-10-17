import { existsSync, readdirSync, statSync } from 'node:fs';
import { delimiter, join } from 'node:path';
import { platform } from 'node:process';

const executable = platform === 'win32' ? 'php.exe' : 'php';
const executables = platform === 'win32' ? [executable] : ['php', 'php85', 'php84', 'php83', 'php82'];

function isExecutableFile(path) {
  try {
    return statSync(path).isFile();
  } catch {
    return false;
  }
}

function findInPath() {
  for (const directory of process.env.PATH?.split(delimiter) ?? []) {
    for (const name of executables) {
      const candidate = join(directory, name);
      if (isExecutableFile(candidate)) return candidate;
    }
  }

  return null;
}

function walkForPhp(root, maxDepth = 4) {
  if (!root || !existsSync(root) || maxDepth < 0) return null;

  let entries = [];
  try {
    entries = readdirSync(root, { withFileTypes: true });
  } catch {
    return null;
  }

  for (const entry of entries) {
    const fullPath = join(root, entry.name);
    if (entry.isFile() && entry.name.toLowerCase() === executable) {
      return fullPath;
    }
  }

  for (const entry of entries) {
    if (!entry.isDirectory()) continue;
    const match = walkForPhp(join(root, entry.name), maxDepth - 1);
    if (match) return match;
  }

  return null;
}

function windowsCandidates() {
  return [
    process.env.LOCALAPPDATA && join(process.env.LOCALAPPDATA, 'Microsoft', 'WinGet', 'Packages'),
    process.env.ProgramFiles && join(process.env.ProgramFiles, 'PHP'),
    'C:\\php',
    'C:\\tools\\php',
  ].filter(Boolean);
}

export function resolvePhpPath() {
  if (process.env.PHP_BIN) {
    if (isExecutableFile(process.env.PHP_BIN)) return process.env.PHP_BIN;
    throw new Error(`PHP_BIN does not point to a PHP executable: ${process.env.PHP_BIN}`);
  }
  const fromPath = findInPath();
  if (fromPath) return fromPath;

  if (platform !== 'win32') return null;

  for (const root of windowsCandidates()) {
    const match = walkForPhp(root);
    if (match) return match;
  }

  return null;
}
