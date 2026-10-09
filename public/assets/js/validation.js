/*
 * Pure validation helpers shared by the browser (app.js) and the Node
 * consistency test (tests/js/rules.test.mjs). They mirror
 * Vitaliq\Validation\Rules::normalise() and ::check() on the server.
 * No DOM access here.
 */

/** Add a compiled `regex` to each rule from the server's rule export. */
export function compile(rules) {
  Object.values(rules).forEach((r) => {
    try { r.regex = new RegExp(r.pattern, r.flags || 'u'); } catch (_) { r.regex = null; }
  });
  return rules;
}

export function normalise(rule, value) {
  value = value.replace(/\0/g, '');
  switch (rule.normalise) {
    case 'collapse': return value.replace(/\s+/gu, ' ').trim();
    case 'lower': return value.trim().toLowerCase();
    case 'upper': return value.trim().toUpperCase();
    case 'digits_space': return value.trim().replace(/ /g, '');
    case 'trim': return value.trim();
    default: return value;
  }
}

/** Format-level check, as Rules::check(). Returns a message or null. */
export function check(rules, key, raw) {
  const rule = rules[key];
  if (!rule || !rule.regex) return null;
  const value = normalise(rule, raw);
  const length = Array.from(value).length;
  if (length < rule.min) return `${rule.label} is too short.`;
  if (length > rule.max) return `${rule.label} is too long.`;
  if (!rule.regex.test(value)) return `${rule.label} is not in the expected format (${rule.format}).`;
  if (key === 'iso_date') {
    const [y, m, d] = value.split('-').map(Number);
    const dt = new Date(Date.UTC(y, m - 1, d));
    if (dt.getUTCFullYear() !== y || dt.getUTCMonth() !== m - 1 || dt.getUTCDate() !== d) return 'That date does not exist.';
  }
  return null;
}
