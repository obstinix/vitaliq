/*
 * Rule tester: instant client-side verdict, then the authoritative server
 * verdict from POST /api/qa/evaluate. A mismatch between the two engines is
 * shown explicitly, since it would itself be a defect.
 */
const { appUrl, checkRule } = window.Vitaliq;

const form = document.querySelector('[data-qa-form]');
const ruleSelect = document.querySelector('[data-qa-rule]');
const valueInput = document.querySelector('[data-qa-value]');
const verdict = document.querySelector('[data-qa-verdict]');
const metaEl = document.getElementById('qa-rule-meta');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
const meta = metaEl ? JSON.parse(metaEl.textContent || '{}') : {};

function visible(value) {
  return '"' + value.replace(/[\p{Cc}\p{Cf}\p{Z}]/gu, (ch) => {
    if (ch === ' ') return ' ';
    if (ch === '\t') return '\\t';
    if (ch === '\n') return '\\n';
    if (ch === '\r') return '\\r';
    return '\\u{' + ch.codePointAt(0).toString(16).toUpperCase().padStart(4, '0') + '}';
  }) + '"';
}

function row(dl, term, value, mono = false) {
  const dt = document.createElement('dt');
  dt.textContent = term;
  const dd = document.createElement('dd');
  if (mono) {
    const span = document.createElement('span');
    span.className = 'input-echo';
    span.textContent = value;
    dd.append(span);
  } else {
    dd.textContent = value;
  }
  dl.append(dt, dd);
}

function render(state, heading, rows) {
  verdict.dataset.state = state;
  const head = document.createElement('p');
  head.className = 'verdict__head';
  head.textContent = heading;
  const dl = document.createElement('dl');
  dl.className = 'details';
  rows.forEach((r) => row(dl, ...r));
  verdict.replaceChildren(head, dl);
}

function updateRuleInfo() {
  const m = meta[ruleSelect.value];
  if (!m) return;
  document.querySelector('[data-qa-label]').textContent = m.label;
  document.querySelector('[data-qa-description]').textContent = m.description;
  document.querySelector('[data-qa-pattern]').textContent = m.pattern;
  document.querySelector('[data-qa-limits]').textContent = `${m.min}–${m.max}`;
  document.querySelector('[data-qa-format]').textContent = 'Expected: ' + m.format;
  document.querySelectorAll('[data-qa-cases]').forEach((el) => { el.hidden = el.dataset.qaCases !== ruleSelect.value; });
  const params = new URLSearchParams(window.location.search);
  params.set('rule', ruleSelect.value);
  window.history.replaceState(null, '', '?' + params.toString());
}

let seq = 0;
let timer = null;

async function evaluate() {
  const rule = ruleSelect.value;
  const value = valueInput.value;
  const mySeq = ++seq;
  const client = rule === 'required'
    ? (/[\p{L}\p{M}\p{N}\p{P}\p{S}]/u.test(value) ? null : 'Required field is empty.')
    : (value === '' ? `${meta[rule]?.label || 'Value'} is required.` : checkRule(rule, value));

  render(client ? 'rejected' : 'accepted', (client ? 'Rejected' : 'Accepted') + ' (browser check)', [
    ['Input', `${visible(value)} (${Array.from(value).length} characters)`, true],
    ['Message', client || 'None'],
    ['Server', 'Checking…'],
  ]);

  try {
    const res = await fetch(appUrl('/api/qa/evaluate'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ rule, value }),
    });
    const data = await res.json();
    if (mySeq !== seq) return;
    if (!res.ok) {
      render('rejected', 'Not evaluated', [['Reason', data.error || 'The server refused this input.']]);
      return;
    }
    const agree = (client === null) === data.accepted;
    render(data.accepted ? 'accepted' : 'rejected', data.accepted ? 'Accepted' : 'Rejected', [
      ['Input', `${visible(value)} (${data.input_length} characters)`, true],
      ['After normalising', visible(data.normalised), true],
      ['Pattern match', data.regex_match ? 'Yes' : 'No'],
      ['Message', data.message || 'None'],
      ['Browser and server', agree ? 'Agree' : 'DISAGREE: the browser check differs from the server. Record a defect.'],
      ['Server time', `${data.duration_ms} ms`],
    ]);
  } catch (_) {
    if (mySeq === seq) render(client ? 'rejected' : 'accepted', 'Browser check only', [['Server', 'Could not reach the server.']]);
  }
}

if (form && ruleSelect && valueInput && verdict) {
  form.addEventListener('submit', (e) => { e.preventDefault(); evaluate(); });
  ruleSelect.addEventListener('change', () => { updateRuleInfo(); if (valueInput.value !== '') evaluate(); });
  valueInput.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(evaluate, 250); });
  document.querySelectorAll('[data-qa-use]').forEach((b) => b.addEventListener('click', () => {
    valueInput.value = b.dataset.qaUse;
    evaluate();
    valueInput.focus();
  }));
}
