/*
 * Vitaliq front-end behaviour. Progressive enhancement only: every form and
 * link works without JavaScript, and the server re-validates everything.
 * No HTML strings are built from data; dynamic text uses textContent.
 */

import { compile, normalise, check } from './validation.js';

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

const base = ($('meta[name="app-base"]')?.content || '/').replace(/\/$/, '');
const csrfToken = () => $('meta[name="csrf-token"]')?.content || '';
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
export const appUrl = (path) => base + path;

/* ---------------------------------------------------------------- widths */
function applyWidths(root = document) {
  $$('[data-pct]', root).forEach((el) => {
    const pct = Math.max(0, Math.min(100, parseFloat(el.dataset.pct) || 0));
    el.style.width = pct + '%';
  });
}

/* ------------------------------------------------------------ navigation */
function initNav() {
  const toggle = $('[data-nav-toggle]');
  const nav = toggle && document.getElementById(toggle.getAttribute('aria-controls'));
  if (!toggle || !nav) return;
  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('is-open', open);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav.classList.contains('is-open')) {
      nav.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
    }
  });
}

/* -------------------------------------------------------------- popovers */
function initPopover(wrapper, toggleSel, onOpen) {
  const button = $(toggleSel, wrapper);
  const panel = button && document.getElementById(button.getAttribute('aria-controls'));
  if (!button || !panel) return;
  const close = (restoreFocus) => {
    panel.hidden = true;
    button.setAttribute('aria-expanded', 'false');
    if (restoreFocus) button.focus();
  };
  button.addEventListener('click', () => {
    const open = panel.hidden;
    $$('.popover').forEach((p) => { if (p !== panel) p.hidden = true; });
    $$('[aria-controls]').forEach((b) => { if (b !== button && b.closest('.menu-wrap')) b.setAttribute('aria-expanded', 'false'); });
    panel.hidden = !open;
    button.setAttribute('aria-expanded', String(open));
    if (open) {
      onOpen?.();
      $('a, button', panel)?.focus();
    }
  });
  document.addEventListener('click', (e) => {
    if (!panel.hidden && !wrapper.contains(e.target)) close(false);
  });
  wrapper.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !panel.hidden) close(true);
  });
}

/* ------------------------------------------------- notifications (polling) */
function initBell() {
  const wrap = $('[data-bell]');
  if (!wrap) return;
  const countEl = $('[data-bell-count]', wrap);
  const labelEl = $('[data-bell-label]', wrap);
  const list = $('[data-bell-list]', wrap);
  const live = $('[data-live]');
  const interval = Math.max(10, parseInt(document.body.dataset.poll || '30', 10)) * 1000;
  let lastUnread = parseInt(countEl?.textContent || '0', 10) || 0;
  let timer = null;
  let stopped = false;

  const render = (data) => {
    const unread = data.unread || 0;
    if (countEl) {
      countEl.textContent = String(unread);
      countEl.hidden = unread === 0;
    }
    if (labelEl) labelEl.textContent = `Notifications, ${unread} unread`;
    if (unread > lastUnread && live) {
      live.textContent = `You have ${unread} unread notification${unread === 1 ? '' : 's'}.`;
    }
    lastUnread = unread;
    if (!list) return;
    list.replaceChildren();
    if (!data.items || data.items.length === 0) {
      const li = document.createElement('li');
      const p = document.createElement('p');
      p.className = 'note-body';
      p.style.padding = '1rem';
      p.textContent = 'No notifications yet.';
      li.append(p);
      list.append(li);
      return;
    }
    data.items.forEach((item) => {
      const li = document.createElement('li');
      if (!item.read) li.className = 'unread';
      const a = document.createElement('a');
      a.href = item.url;
      const title = document.createElement('span');
      title.className = 'note-title';
      title.textContent = item.title + (item.read ? '' : ' ');
      if (!item.read) {
        const sr = document.createElement('span');
        sr.className = 'visually-hidden';
        sr.textContent = '(unread)';
        title.append(sr);
      }
      const body = document.createElement('span');
      body.className = 'note-body';
      body.textContent = item.body;
      const time = document.createElement('time');
      time.className = 'note-time';
      time.dateTime = item.created_at;
      time.textContent = item.created_label;
      a.append(title, body, time);
      li.append(a);
      list.append(li);
    });
  };

  const poll = async () => {
    if (stopped) return;
    try {
      const res = await fetch(appUrl('/api/notifications'), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        cache: 'no-store',
      });
      if (res.status === 401) { stopped = true; return; }
      if (res.ok) render(await res.json());
    } catch (_) {
      /* Network hiccup: keep the last known state and try again later. */
    }
  };

  const schedule = () => {
    clearInterval(timer);
    if (document.visibilityState === 'visible') timer = setInterval(poll, interval);
  };
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') poll();
    schedule();
  });
  schedule();
  initPopover(wrap, '[data-bell-toggle]', poll);

  const markAll = $('[data-mark-all]', wrap);
  markAll?.addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const res = await fetch(markAll.action, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrfToken() },
        credentials: 'same-origin',
      });
      if (res.ok) poll();
    } catch (_) {
      markAll.submit();
    }
  });
}

/* ----------------------------------------------------------- validation */
function loadRules() {
  const el = document.getElementById('vq-rules');
  if (!el) return {};
  try {
    return compile(JSON.parse(el.textContent || '{}'));
  } catch (_) {
    return {};
  }
}
const RULES = loadRules();
const VISIBLE = /[\p{L}\p{M}\p{N}\p{P}\p{S}]/u;

/** Mirrors Rules::check() on the server. Returns a message or null. */
export const checkRule = (key, raw) => check(RULES, key, raw);

function labelFor(field) {
  const label = field.id ? $(`label[for="${CSS.escape(field.id)}"]`) : null;
  return (label?.childNodes[0]?.textContent || field.name || 'This field').trim();
}

function setError(field, message) {
  const id = 'err-' + field.name.replace(/\[\]$/, '');
  let el = document.getElementById(id);
  const container = field.closest('.field') || field.parentElement;
  if (message) {
    if (!el) {
      el = document.createElement('p');
      el.className = 'field-error';
      el.id = id;
      container.append(el);
    }
    el.textContent = message;
    field.setAttribute('aria-invalid', 'true');
    const described = new Set((field.getAttribute('aria-describedby') || '').split(' ').filter(Boolean));
    described.add(id);
    field.setAttribute('aria-describedby', Array.from(described).join(' '));
  } else {
    if (el && el.dataset.client !== 'server') el.remove();
    field.removeAttribute('aria-invalid');
    const described = (field.getAttribute('aria-describedby') || '').split(' ').filter((d) => d && d !== id);
    if (described.length) field.setAttribute('aria-describedby', described.join(' '));
    else field.removeAttribute('aria-describedby');
  }
}

function validateField(field, onSubmit) {
  if (field.closest('[hidden]') || field.disabled) return null;
  const value = field.value || '';
  let message = null;
  if (field.dataset.rule) {
    if (!VISIBLE.test(value)) {
      message = field.required && onSubmit ? `${labelFor(field)} is required.` : null;
    } else {
      message = checkRule(field.dataset.rule, value);
    }
  } else if (field.dataset.match) {
    const other = field.form?.elements.namedItem(field.dataset.match);
    if (other && value !== other.value && (onSubmit || value !== '')) message = 'The two passwords do not match.';
  } else if (onSubmit && field.required) {
    if (field.type === 'checkbox' && !field.checked) message = 'Please confirm to continue.';
    else if (field.type !== 'checkbox' && field.type !== 'radio' && !VISIBLE.test(value)) message = `${labelFor(field)} is required.`;
  }
  setError(field, message);
  return message;
}

function initValidation() {
  $$('form[data-validate]').forEach((form) => {
    const fields = $$('input, select, textarea', form).filter((f) => f.type !== 'hidden' && f.name);
    fields.forEach((f) => {
      f.addEventListener('blur', () => { if (f.value !== '') validateField(f, false); });
      f.addEventListener('input', () => { if (f.getAttribute('aria-invalid') === 'true') validateField(f, false); });
    });
    form.addEventListener('submit', (e) => {
      let first = null;
      fields.forEach((f) => {
        if (f.type === 'radio') return;
        if (validateField(f, true) && !first) first = f;
      });
      // Required radio groups
      const groups = new Set(fields.filter((f) => f.type === 'radio' && f.required).map((f) => f.name));
      groups.forEach((name) => {
        const radios = fields.filter((f) => f.name === name);
        const group = radios[0].closest('.field');
        if (group && group.closest('[hidden]')) return;
        const ok = radios.some((r) => r.checked);
        const errId = 'err-' + name;
        let err = document.getElementById(errId);
        if (!ok) {
          if (!err) {
            err = document.createElement('p');
            err.className = 'field-error';
            err.id = errId;
            group?.append(err);
          }
          err.textContent = 'Choose an option.';
          if (!first) first = radios[0];
        } else if (err) {
          err.remove();
        }
      });
      if (first) {
        e.preventDefault();
        e.stopImmediatePropagation();
        first.focus();
        const live = $('[data-live]');
        if (live) live.textContent = 'Some details need attention before you can continue.';
      }
    });
  });
}

/* ------------------------------------------------ double-submit protection */
function initOnce() {
  $$('form[data-once]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (e.defaultPrevented) return;
      if (form.dataset.submitting === '1') {
        e.preventDefault();
        return;
      }
      form.dataset.submitting = '1';
      form.setAttribute('aria-busy', 'true');
      $$('button[type="submit"], button:not([type])', form).forEach((b) => {
        if (b.dataset.busyLabel) b.textContent = b.dataset.busyLabel;
        // Disable after the browser has captured the submitter's name/value.
        setTimeout(() => { b.disabled = true; }, 0);
      });
    });
  });
  // Pages restored from the back/forward cache should be usable again.
  window.addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    $$('form[data-once]').forEach((form) => {
      delete form.dataset.submitting;
      form.removeAttribute('aria-busy');
      $$('button', form).forEach((b) => { b.disabled = false; });
    });
  });
}

/* --------------------------------------------------- conditional fields */
function initConditional() {
  const conditions = {
    symptoms: () => $$('[data-symptom]').some((c) => c.checked) || $('#f-reason')?.value === 'symptoms',
    home: () => $('[data-collection-mode][value="home"]')?.checked === true,
    'role-phlebotomist': () => $('[data-role-select]')?.value === 'phlebotomist',
    'kind-regex': () => ($('[data-kind-select]')?.value || 'regex') === 'regex',
    'kind-manual': () => $('[data-kind-select]')?.value === 'manual',
  };
  const targets = $$('[data-show-when]');
  if (!targets.length) return;
  const update = () => targets.forEach((el) => {
    const test = conditions[el.dataset.showWhen];
    if (test) el.hidden = !test();
  });
  document.addEventListener('change', update);
  update();
}

/* ----------------------------------------------------------- utilities */
function initUtilities() {
  $$('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
  const summary = $('[data-error-summary]');
  if (summary) summary.focus();
}

/* ------------------------------------------------------------- motion */
function initMotion() {
  const root = document.documentElement;
  const gsap = window.gsap;
  if (!root.classList.contains('motion-ok')) return;
  if (!gsap || reduceMotion) {
    root.classList.remove('motion-ok');
    return;
  }
  root.classList.add('motion-started');
  const ST = window.ScrollTrigger;
  if (ST) gsap.registerPlugin(ST);
  gsap.defaults({ ease: 'power3.out' });

  const title = $('[data-hero-title]');
  if (title) gsap.fromTo(title, { opacity: 0, y: 24 }, { opacity: 1, y: 0, duration: 0.7 });

  const rail = $('[data-rail]');
  if (rail) {
    const fill = $('[data-rail-fill]', rail);
    const steps = $$('[data-rail-step]', rail);
    const tl = gsap.timeline({ delay: 0.15 });
    if (fill) tl.fromTo(fill, { scaleY: 0 }, { scaleY: 1, duration: 1.1, ease: 'power2.inOut' }, 0);
    tl.fromTo(steps.map((s) => s.querySelector('.rail__node')), { scale: 0.4, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.35, stagger: 0.16, ease: 'back.out(2)' }, 0.05);
    tl.fromTo(steps.map((s) => [s.querySelector('.rail__label'), s.querySelector('.rail__meta')]).flat(), { opacity: 0, x: -6 }, { opacity: 1, x: 0, duration: 0.35, stagger: 0.08 }, 0.15);
  }

  // Count-up for platform figures: ends on the exact server-rendered value.
  const fmt = new Intl.NumberFormat('en-US');
  $$('[data-count]').forEach((el) => {
    const target = parseInt(el.dataset.count, 10) || 0;
    if (target === 0) return;
    const state = { v: 0 };
    gsap.to(state, {
      v: target, duration: 1.2, delay: 0.3, ease: 'power2.out',
      onUpdate: () => { el.textContent = fmt.format(Math.round(state.v)); },
      onComplete: () => { el.textContent = fmt.format(target); },
    });
  });

  const reveals = $$('[data-reveal]');
  if (ST) {
    gsap.set(reveals, { y: 14 });
    ST.batch(reveals, {
      start: 'top 92%',
      once: true,
      onEnter: (batch) => gsap.to(batch, { opacity: 1, y: 0, duration: 0.6, stagger: 0.07 }),
    });
  } else {
    gsap.to(reveals, { opacity: 1, duration: 0.4, stagger: 0.04 });
  }
}

/* ---------------------------------------------------------------- boot */
// Shared with page scripts (qa-lab.js). Exposed on window rather than via
// import, because app.js is loaded with a cache-busting query string.
window.Vitaliq = Object.freeze({ appUrl, checkRule, normalise });

applyWidths();
initNav();
$$('[data-menu]').forEach((m) => initPopover(m, '[data-menu-toggle]'));
initBell();
initValidation();
initOnce();
initConditional();
initUtilities();
initMotion();
