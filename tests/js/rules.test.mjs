/*
 * Client/server validation consistency.
 *
 *   node --test tests/js/
 *
 * Runs every STQA catalogue input and every documented rule example through
 * the browser validation module (public/assets/js/validation.js) and compares
 * the verdict with PHP's Rules::check(), exported by bin/export-rules.php.
 * Requires `php` on PATH (or the PHP_BINARY environment variable).
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { compile, check } from '../../public/assets/js/validation.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const php = process.env.PHP_BINARY || 'php';
const exported = JSON.parse(execFileSync(php, [path.join(root, 'bin', 'export-rules.php')], { encoding: 'utf8' }));
const rules = compile(exported.rules);

test('every rule pattern compiles as a JavaScript Unicode regular expression', () => {
  for (const [key, rule] of Object.entries(rules)) {
    assert.ok(rule.regex instanceof RegExp, `${key} did not compile in JavaScript`);
  }
});

test('browser and server agree on every catalogue input and rule example', () => {
  const VISIBLE = /[\p{L}\p{M}\p{N}\p{P}\p{S}]/u;
  const disagreements = [];
  for (const item of exported.inputs) {
    const jsAccepts = item.rule === 'required'
      ? VISIBLE.test(item.input)
      : check(rules, item.rule, item.input) === null;
    const expectedJs = item.server_only_extra ? true : item.php_accepts;
    if (jsAccepts !== expectedJs) {
      disagreements.push(`${item.code} (${item.rule}) ${JSON.stringify(item.input)}: js=${jsAccepts} php=${item.php_accepts}`);
    }
  }
  assert.deepEqual(disagreements, [], 'Client and server validation disagree');
  assert.ok(exported.inputs.length > 100, 'Expected the full catalogue to be checked');
});

test('server-only email check never rejects something the regex documents as valid', () => {
  const extras = exported.inputs.filter((i) => i.server_only_extra);
  assert.deepEqual(extras.map((i) => i.code), [], 'Regex and FILTER_VALIDATE_EMAIL should not diverge on catalogue inputs');
});
