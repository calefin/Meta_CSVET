#!/usr/bin/env node
import fs from 'fs';
import path from 'path';

function usage() {
  console.log('Usage: node scripts/update-version.mjs <new-version>');
  console.log('Or: node scripts/update-version.mjs bump <major|minor|patch>');
}

function readVersion(file) {
  try { return fs.readFileSync(file, 'utf8').trim().split('\n')[0]; } catch (e) { return null; }
}

function writeVersion(file, v) { fs.writeFileSync(file, v + '\n'); }

function semverParts(v) { return v.split('.').map(n => parseInt(n,10)); }

async function main() {
  const args = process.argv.slice(2);
  if (args.length === 0) { usage(); process.exit(1); }

  const repoRoot = path.resolve(process.cwd());
  const versionFile = path.join(repoRoot, 'VERSION');
  const historyFile = path.join(repoRoot, 'memories', 'repo', 'version_history.md');

  let current = readVersion(versionFile) || '0.0.0';
  let next = current;

  if (args[0] === 'bump') {
    const which = args[1] || 'patch';
    const p = semverParts(current);
    if (which === 'major') { p[0] = p[0] + 1; p[1] = 0; p[2] = 0; }
    else if (which === 'minor') { p[1] = (p[1] || 0) + 1; p[2] = 0; }
    else { p[2] = (p[2] || 0) + 1; }
    next = p.join('.');
  } else if (/^\d+\.\d+\.\d+$/.test(args[0])) {
    next = args[0];
  } else {
    usage(); process.exit(1);
  }

  writeVersion(versionFile, next);

  // update package.json version
  const pkgFile = path.join(repoRoot, 'package.json');
  try {
    const pkg = JSON.parse(fs.readFileSync(pkgFile, 'utf8'));
    pkg.version = next;
    fs.writeFileSync(pkgFile, JSON.stringify(pkg, null, 2) + '\n', 'utf8');
    console.log(`Updated package.json: version -> ${next}`);
  } catch (e) {
    console.error('Warning: could not update package.json:', e.message);
  }

  // append to version history
  const now = new Date().toISOString().split('T')[0];
  const entry = `- ${next} — ${now} — updated via scripts/update-version.mjs\n`;
  try {
    fs.appendFileSync(historyFile, entry);
  } catch (e) {
    console.error('Warning: could not write version history:', e.message);
  }

  console.log(`Updated version: ${current} -> ${next}`);
  console.log('Remember to commit:');
  console.log(`  git add VERSION package.json ${path.relative(repoRoot, historyFile)}`);
}

main().catch(e => { console.error(e); process.exit(1); });
