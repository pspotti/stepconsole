/**
 * STEP Console v2 — frontend.
 *
 * Sostituisce il pattern "un <form> per pulsante che ricarica la pagina"
 * con chiamate fetch() verso api.php. Il contesto visita (Visit ID,
 * sottotitoli, tipo visita) viene impostato UNA volta e riusato da ogni
 * pulsante della sessione, invece di essere ripetuto come input hidden
 * identici in ogni singola pagina come nel sito originale.
 */

const DEFAULT_VISIT_ID = '4a60049d-de84-49de-af21-c09ebe77ede0';
const STORE_KEY = 'step-console-visit-context';

const VisitContext = {
  read() {
    try {
      const raw = localStorage.getItem(STORE_KEY);
      if (raw) return { ...this.defaults(), ...JSON.parse(raw) };
    } catch (e) { /* storage non disponibile: usa i default */ }
    return this.defaults();
  },
  defaults() {
    return { visitId: DEFAULT_VISIT_ID, subEng: true, subIta: true, visitType: 'Regular' };
  },
  write(ctx) {
    try { localStorage.setItem(STORE_KEY, JSON.stringify(ctx)); } catch (e) { /* ignore */ }
  },
};

async function callApi(action, payload) {
  const ctx = VisitContext.read();
  const body = {
    action,
    visitId: ctx.visitId,
    subEng: ctx.subEng,
    subIta: ctx.subIta,
    visitType: ctx.visitType,
    ...payload,
  };
  const res = await fetch('api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  let data;
  try { data = await res.json(); } catch (e) { data = { ok: false, message: 'Risposta non valida dal server.' }; }
  return data;
}

function toast(message, kind = 'ok') {
  let stack = document.querySelector('.toast-stack');
  if (!stack) {
    stack = document.createElement('div');
    stack.className = 'toast-stack';
    document.body.appendChild(stack);
  }
  const el = document.createElement('div');
  el.className = `toast ${kind}`;
  el.textContent = message;
  stack.appendChild(el);
  setTimeout(() => el.remove(), 4200);
}

function appendLog(entry) {
  const log = document.querySelector('[data-log]');
  if (!log) return;
  const empty = log.querySelector('.log-empty');
  if (empty) empty.remove();
  const pre = document.createElement('pre');
  const time = new Date().toLocaleTimeString('it-IT');
  pre.textContent = `[${time}] ${entry}`;
  log.prepend(pre);
  while (log.children.length > 12) log.removeChild(log.lastChild);
}

function setButtonLoading(btn, loading) {
  btn.disabled = loading;
  btn.classList.toggle('is-loading', loading);
}

/** Collega ogni pulsante [data-action] alla relativa chiamata API. */
function bindActionButtons() {
  document.querySelectorAll('[data-action]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const action = btn.dataset.action;
      const confirmMsg = btn.dataset.confirm;
      if (confirmMsg && !window.confirm(confirmMsg)) return;

      const payload = {};
      if (btn.dataset.station) payload.station = btn.dataset.station;
      if (btn.dataset.azione) payload.azione = btn.dataset.azione;
      if (btn.dataset.comando) payload.comando = btn.dataset.comando;
      if (btn.dataset.target) payload.target = Number(btn.dataset.target);
      if (btn.dataset.exec) payload.exec = Number(btn.dataset.exec);
      if (btn.dataset.mode) payload.mode = btn.dataset.mode;
      if (btn.dataset.command) payload.command = btn.dataset.command;
      if (btn.dataset.scene) payload.scene = btn.dataset.scene;
      if (btn.dataset.snapshot) payload.snapshot = btn.dataset.snapshot;
      if (btn.dataset.scenario) payload.scenario = btn.dataset.scenario;
      if (btn.dataset.sub) payload.sub = btn.dataset.sub;
      if (btn.dataset.input) payload.input = Number(btn.dataset.input);
      if (btn.dataset.output) payload.output = Number(btn.dataset.output);
      if (btn.dataset.ipSelect) {
        const select = document.getElementById(btn.dataset.ipSelect);
        if (select) payload.ip = select.value;
      }
      if (btn.dataset.inputSelect) {
        const select = document.getElementById(btn.dataset.inputSelect);
        if (select) payload.input = Number(select.value);
      }

      setButtonLoading(btn, true);
      try {
        const data = await callApi(action, payload);
        toast(data.message || (data.ok ? 'Fatto.' : 'Errore.'), data.ok ? 'ok' : 'err');
        if (data.output) appendLog(data.output.trim());
      } catch (e) {
        toast('Richiesta non riuscita: ' + e.message, 'err');
      } finally {
        setButtonLoading(btn, false);
      }
    });
  });
}

/** Form generici con validazione lato client (recovery console / SAM / Gigante). */
function bindActionForms() {
  document.querySelectorAll('form[data-action]').forEach((form) => {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = form.querySelector('button[type="submit"]');
      const action = form.dataset.action;
      const payload = Object.fromEntries(new FormData(form).entries());

      if (btn) setButtonLoading(btn, true);
      try {
        const data = await callApi(action, payload);
        toast(data.message || (data.ok ? 'Fatto.' : 'Errore.'), data.ok ? 'ok' : 'err');
        if (data.output) appendLog(data.output.trim());
      } catch (e) {
        toast('Richiesta non riuscita: ' + e.message, 'err');
      } finally {
        if (btn) setButtonLoading(btn, false);
      }
    });
  });
}

function initVisitContextBar() {
  const bar = document.querySelector('[data-visit-context]');
  if (!bar) return;
  const ctx = VisitContext.read();

  const idInput = bar.querySelector('[name="visitId"]');
  const engInput = bar.querySelector('[name="subEng"]');
  const itaInput = bar.querySelector('[name="subIta"]');
  const typeSelect = bar.querySelector('[name="visitType"]');
  const resetBtn = bar.querySelector('[data-reset-visit]');

  idInput.value = ctx.visitId;
  engInput.checked = !!ctx.subEng;
  itaInput.checked = !!ctx.subIta;
  typeSelect.value = ctx.visitType;

  const persist = () => {
    VisitContext.write({
      visitId: idInput.value.trim() || DEFAULT_VISIT_ID,
      subEng: engInput.checked,
      subIta: itaInput.checked,
      visitType: typeSelect.value,
    });
  };

  [idInput, engInput, itaInput, typeSelect].forEach((el) => el.addEventListener('change', persist));
  idInput.addEventListener('blur', persist);

  resetBtn?.addEventListener('click', () => {
    VisitContext.write(VisitContext.defaults());
    idInput.value = DEFAULT_VISIT_ID;
    engInput.checked = true;
    itaInput.checked = true;
    typeSelect.value = 'Regular';
    toast('Contesto visita ripristinato ai valori predefiniti.', 'ok');
  });
}

function initSidebarToggle() {
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const sidebar = document.querySelector('.sidebar');
  if (!toggle || !sidebar) return;
  toggle.addEventListener('click', () => sidebar.classList.toggle('is-open'));
  document.addEventListener('click', (ev) => {
    if (!sidebar.classList.contains('is-open')) return;
    if (sidebar.contains(ev.target) || toggle.contains(ev.target)) return;
    sidebar.classList.remove('is-open');
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initVisitContextBar();
  initSidebarToggle();
  bindActionButtons();
  bindActionForms();
});
