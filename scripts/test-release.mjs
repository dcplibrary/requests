import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { analyzeCommits } from '@semantic-release/commit-analyzer';
import { generateNotes } from '@semantic-release/release-notes-generator';

const cwd = fileURLToPath(new URL('../', import.meta.url));
const { plugins } = JSON.parse(readFileSync(new URL('../.releaserc.json', import.meta.url), 'utf8'));
const analyzerOptions = plugins.find(([name]) => name === '@semantic-release/commit-analyzer')[1];
const notesOptions = plugins.find(([name]) => name === '@semantic-release/release-notes-generator')[1];
const logger = { log() {} };

const cases = [
  ['fix!: secure patron login and correct page referral', 'major'],
  ['fix(auth)!: change patron authentication', 'major'],
  ['feat!: replace request API', 'major'],
  ['chore!: drop an unsupported runtime', 'major'],
  ['fix: change login\n\nBREAKING CHANGE: patrons must authenticate again', 'major'],
  ['feat: add a request option', 'minor'],
  ['fix: correct a referral', 'patch'],
  ['perf: speed up requests', 'patch'],
  ['refactor: simplify request handling', 'patch'],
  ['docs: clarify setup', 'patch'],
  ['chore: update tooling', null],
  ['test: add coverage', null],
  ['ci: update workflow', null],
  ['BREAKING CHANGE: standalone subject is not a footer', null],
];

for (const [message, expected] of cases) {
  test(`${message.split('\n')[0]} → ${expected ?? 'no release'}`, async () => {
    const release = await analyzeCommits(analyzerOptions, {
      cwd,
      commits: [{ hash: 'a841c2a', message }],
      logger,
    });
    assert.equal(release, expected);
  });
}

test('release notes include breaking fix headers', async () => {
  const notes = await generateNotes(notesOptions, {
    cwd,
    commits: [{ hash: 'a841c2a', message: cases[0][0] }],
    logger,
    options: { repositoryUrl: 'https://github.com/dcplibrary/requests' },
    lastRelease: { version: '4.31.7', gitTag: 'v4.31.7' },
    nextRelease: { version: '5.0.0', gitTag: 'v5.0.0' },
  });
  assert.match(notes, /BREAKING CHANGES/);
  assert.match(notes, /secure patron login and correct page referral/);
});
