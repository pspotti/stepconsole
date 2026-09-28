/**
 * Pannelli "Comandi programmati": banner Future Trends (station.php?station=
 * square), BrightSign (brightsign.php) e il riepilogo unico di tutti e tre i
 * tipi, incluso WallConfig (scheduler.php). Ogni pannello è una coppia di
 * elementi [data-schedule-form]/[data-schedule-list] con lo stesso
 * data-schedule-type (es. "ftbanner", "brightsign", "wallconfig") a legarli
 * fra loro, e filtra così la coda lato server — possono convivere più
 * pannelli di tipo diverso sulla stessa pagina, ognuno vede solo i propri
 * job. Il form porta anche un data-schedule-api opzionale con l'URL a cui
 * parlare (default: api.php, la console PHP stessa): per il pannello
 * WallConfig punta invece al servizio Flask separato, così questa pagina
 * può gestirne la coda senza passare da un proxy PHP. Su una pagina senza
 * questi elementi non fa nulla. Riusa solo toast()/setButtonLoading() da
 * app.js — non callApi(), che inietta il contesto visita e punta sempre e
 * solo ad api.php, inutile/scorretto per un endpoint diverso.
 */
(function () {
  function statusText(job) {
    if (job.missed) return 'scaduto (non inviato)';
    if (!job.enabled && job.repeat === 'once' && job.lastRunAt) return 'inviato';
    if (!job.enabled) return 'sospeso';
    if (job.repeat === 'daily' && job.lastRunDate) return `ultimo invio ${job.lastRunDate}`;
    return 'in attesa';
  }

  /** POST JSON {action, ...payload} all'endpoint del pannello (api.php o l'API di WallConfig). */
  async function scheduleApi(apiUrl, action, payload) {
    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, ...payload }),
    });
    try {
      return await res.json();
    } catch (e) {
      return { ok: false, message: 'Risposta non valida dal server.' };
    }
  }

  function renderJobs(list, jobs, type, apiUrl) {
    list.innerHTML = '';
    if (!jobs.length) {
      list.innerHTML = '<p class="log-empty">Nessun comando programmato.</p>';
      return;
    }

    jobs.forEach((job) => {
      const when = job.repeat === 'once'
        ? `${job.date} alle ${job.time}`
        : `ogni giorno alle ${job.time}`;

      const row = document.createElement('div');
      row.className = 'schedule-row' + (job.enabled ? '' : ' is-disabled');
      row.innerHTML = `
        <div class="schedule-row-info">
          <span class="badge badge-${job.badge || 'accent'}">${job.label}</span>
          <span>${when}</span>
          <span class="schedule-row-status">${statusText(job)}</span>
        </div>
      `;

      const actions = document.createElement('div');
      actions.className = 'schedule-row-actions';

      if (!job.missed) {
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'btn btn-neutral btn-sm';
        toggleBtn.textContent = job.enabled ? 'Sospendi' : 'Riattiva';
        toggleBtn.addEventListener('click', async () => {
          toggleBtn.disabled = true;
          const data = await scheduleApi(apiUrl, 'schedule_toggle', { id: job.id, enabled: !job.enabled });
          toast(data.message || (data.ok ? 'Fatto.' : 'Errore.'), data.ok ? 'ok' : 'err');
          if (data.ok) loadJobs(list, type, apiUrl); else toggleBtn.disabled = false;
        });
        actions.appendChild(toggleBtn);
      }

      const delBtn = document.createElement('button');
      delBtn.type = 'button';
      delBtn.className = 'btn btn-stop btn-sm';
      delBtn.textContent = 'Elimina';
      delBtn.addEventListener('click', async () => {
        if (!window.confirm('Eliminare questo comando programmato?')) return;
        delBtn.disabled = true;
        const data = await scheduleApi(apiUrl, 'schedule_delete', { id: job.id });
        toast(data.message || (data.ok ? 'Fatto.' : 'Errore.'), data.ok ? 'ok' : 'err');
        if (data.ok) loadJobs(list, type, apiUrl); else delBtn.disabled = false;
      });
      actions.appendChild(delBtn);

      row.appendChild(actions);
      list.appendChild(row);
    });
  }

  async function loadJobs(list, type, apiUrl) {
    try {
      const data = await scheduleApi(apiUrl, 'schedule_list', { type });
      if (!data.ok) throw new Error(data.message || 'errore');
      renderJobs(list, data.jobs || [], type, apiUrl);
    } catch (e) {
      list.innerHTML = '<p class="log-empty">Impossibile caricare i comandi programmati.</p>';
    }
  }

  function initScheduleForm(form, list, type, apiUrl) {
    const repeatSelect = form.querySelector('[name="repeat"]');
    const dateField = form.querySelector('[data-sch-date-field]');
    const dateInput = form.querySelector('[name="date"]');

    function syncDateField() {
      const isOnce = repeatSelect.value === 'once';
      dateField.style.display = isOnce ? '' : 'none';
      dateInput.required = isOnce;
      dateInput.disabled = !isOnce;
      if (isOnce && !dateInput.value) {
        dateInput.value = new Date().toISOString().slice(0, 10);
      }
    }
    repeatSelect.addEventListener('change', syncDateField);
    syncDateField();

    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const btn = form.querySelector('button[type="submit"]');
      const payload = { type, ...Object.fromEntries(new FormData(form).entries()) };

      setButtonLoading(btn, true);
      try {
        const data = await scheduleApi(apiUrl, 'schedule_add', payload);
        toast(data.message || (data.ok ? 'Fatto.' : 'Errore.'), data.ok ? 'ok' : 'err');
        if (data.ok) {
          form.reset();
          syncDateField();
          loadJobs(list, type, apiUrl);
        }
      } catch (e) {
        toast('Richiesta non riuscita: ' + e.message, 'err');
      } finally {
        setButtonLoading(btn, false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-schedule-form]').forEach((form) => {
      const type = form.dataset.scheduleType;
      if (!type) return; // pannello senza tipo dichiarato: non sappiamo quale coda mostrargli
      const list = document.querySelector(`[data-schedule-list][data-schedule-type="${type}"]`);
      if (!list) return;
      const apiUrl = form.dataset.scheduleApi || 'api.php';
      initScheduleForm(form, list, type, apiUrl);
      loadJobs(list, type, apiUrl);
    });
  });
})();
