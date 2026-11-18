import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const root = resolve(__dirname, '..');

const packageJsonPath = resolve(root, 'package.json');
const versionPath = resolve(root, 'VERSION');

const packageJsonRaw = await readFile(packageJsonPath, 'utf8');
const packageJson = JSON.parse(packageJsonRaw);
const versionFileRaw = await readFile(versionPath, 'utf8');
const versionFile = versionFileRaw.trim();

if (packageJson.version !== versionFile) {
  console.error(`Version mismatch: package.json=${packageJson.version} != VERSION=${versionFile}`);
  process.exit(1);
}

console.log(`Version check passed: ${packageJson.version}`);
