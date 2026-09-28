<?php
require __DIR__ . '/includes/functions.php';
$pageTitle = 'Scenari Schermo Windoor';
$activeKey = 'tesmart_test';
require __DIR__ . '/includes/layout_top.php';

$labels    = tesmart_labels();
$inputs    = $labels['inputs'] ?? [];
$outputs   = $labels['outputs'] ?? [];
$scenarios = tesmart_scenarios();
?>

<div class="panel">
  <h2>Scenari Schermo Windoor</h2>
  <p class="panel-desc">
    Instrada subito la stessa sorgente su tutte le uscite dello schermo Windoor,
    via la matrice HDMI TESmart 8x8 (<code><?= h(tesmart_host()) ?>:<?= h((string) tesmart_port()) ?></code>).
  </p>
  <div class="action-row">
    <?php foreach ($scenarios as $n => $label): ?>
    <button class="btn btn-accent" data-action="tesmart" data-sub="all" data-input="<?= (int) $n ?>">
      <span class="spinner"></span><span class="btn-label">Tutte &rarr; <?= h($label) ?></span>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h2>Schedulazione scenari</h2>
  <p class="panel-desc">
    Programma il richiamo automatico di uno scenario, come sopra: una tantum a una
    data/ora scelta, oppure ogni giorno alla stessa ora. L'invio effettivo è fatto dal
    cron <code>scripts/run_scheduler.php</code> sul server: se non è stato installato, i
    comandi restano in coda senza essere spediti (vedi README).
  </p>

  <form data-schedule-form data-schedule-type="tesmart" class="form-grid">
    <div class="field">
      <label for="ts-sch-input">Scenario</label>
      <select id="ts-sch-input" name="input">
        <?php foreach ($scenarios as $n => $label): ?>
          <option value="<?= (int) $n ?>"><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="ts-sch-repeat">Ripetizione</label>
      <select id="ts-sch-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="ts-sch-date">Data</label>
      <input id="ts-sch-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="ts-sch-time">Ora</label>
      <input id="ts-sch-time" type="time" name="time" required>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma</span>
    </button>
  </form>

  <div class="schedule-list" data-schedule-list data-schedule-type="tesmart">
    <p class="log-empty">Nessun comando programmato.</p>
  </div>
</div>

<div class="panel">
  <details>
    <summary>Mappature singole e diagnostica matrice</summary>

    <div class="action-row">
      <button class="btn btn-neutral" id="tesmart-status-btn">
        <span class="spinner"></span><span class="btn-label">Leggi stato attuale</span>
      </button>
      <button class="btn btn-dark" data-action="tesmart" data-sub="mirror">
        <span class="spinner"></span><span class="btn-label">Specchio 1:1 (ingresso N &rarr; uscita N)</span>
      </button>
    </div>

    <p class="panel-desc">
      Per ogni uscita, scegli l'ingresso da instradare e conferma con "Applica".
      La colonna "Attuale" si aggiorna solo premendo "Leggi stato attuale" sopra.
    </p>

    <?php foreach ($outputs as $outN => $outLabel): ?>
    <div class="matrix-row">
      <div class="matrix-row-label"><?= h($outLabel) ?></div>
      <div class="matrix-row-current">Attuale: <strong id="tesmart-cur-<?= (int) $outN ?>">?</strong></div>
      <div class="matrix-row-controls">
        <select id="tesmart-in-<?= (int) $outN ?>">
          <?php foreach ($inputs as $inN => $inLabel): ?>
            <option value="<?= (int) $inN ?>"><?= h($inLabel) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-start btn-sm" data-action="tesmart" data-sub="switch"
          data-output="<?= (int) $outN ?>" data-input-select="tesmart-in-<?= (int) $outN ?>">
          <span class="spinner"></span><span class="btn-label">Applica</span>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </details>
</div>

<div class="panel log-panel">
  <h2>Ultimi comandi</h2>
  <div data-log>
    <p class="log-empty">Nessun comando inviato in questa sessione.</p>
  </div>
</div>

<script>
  // Pulsante "Leggi stato attuale": a differenza dei pulsanti [data-action]
  // generici (assets/js/app.js) qui la risposta non è solo un messaggio da
  // mostrare in toast, ma una mappa uscita->ingresso che aggiorna la colonna
  // "Attuale" e i menu a tendina della tabella sopra. Letta anche al
  // caricamento della pagina, per partire con lo stato reale della matrice.
  (function () {
    const btn = document.getElementById('tesmart-status-btn');
    if (!btn) return;

    async function refreshStatus(showToast) {
      setButtonLoading(btn, true);
      try {
        const data = await callApi('tesmart', { sub: 'status' });
        if (data.map) {
          Object.entries(data.map).forEach(([output, input]) => {
            const cur = document.getElementById('tesmart-cur-' + output);
            if (cur) cur.textContent = 'Ingresso ' + input;
            const select = document.getElementById('tesmart-in-' + output);
            if (select) select.value = String(input);
          });
        }
        if (showToast) toast(data.message || (data.ok ? 'Fatto.' : 'Errore.'), data.ok ? 'ok' : 'err');
        if (data.output) appendLog(data.output.trim());
      } catch (e) {
        if (showToast) toast('Richiesta non riuscita: ' + e.message, 'err');
      } finally {
        setButtonLoading(btn, false);
      }
    }

    btn.addEventListener('click', () => refreshStatus(true));
    refreshStatus(false);
  })();
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
