<?php
require __DIR__ . '/includes/functions.php';
$pageTitle = 'Scenari Audio';
$activeKey = 'audio_master';
require __DIR__ . '/includes/layout_top.php';

$soundcraftByKey = array_column(soundcraft_snapshots(), null, 'key');
$samByKey        = array_column(sam_commands(), null, 'key');
$yamahaByKey     = array_column(yamaha_scenes(), null, 'key');
?>

<div class="panel">
  <h2>Scenari Audio</h2>
  <p class="panel-desc">
    Un solo pulsante per comandare insieme i tre dispositivi audio (Jobs sul mixer
    Soundcraft, SAM, processore Yamaha). Se un dispositivo non risponde gli altri due
    vengono comunque comandati: l'esito di ciascuno compare nel log qui sotto.
  </p>
  <div class="action-row">
    <?php foreach (master_scenarios() as $scenario): ?>
    <button class="btn <?= h($scenario['style']) ?>" data-action="audio_master" data-scenario="<?= h($scenario['key']) ?>">
      <span class="spinner"></span><span class="btn-label"><?= h($scenario['label']) ?></span>
    </button>
    <?php endforeach; ?>
  </div>
  <ul class="master-scenario-list">
    <?php foreach (master_scenarios() as $scenario): ?>
    <li>
      <strong><?= h($scenario['label']) ?></strong>:
      Jobs → <?= h($soundcraftByKey[$scenario['soundcraft_snapshot']]['label'] ?? '?') ?>,
      SAM → <?= h($samByKey[$scenario['sam_command']]['label'] ?? '?') ?>,
      Yamaha → <?= h($yamahaByKey[$scenario['yamaha_scene']]['label'] ?? '?') ?>
    </li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="panel">
  <h2>Schedulazione aggregata</h2>
  <p class="panel-desc">
    Programma uno scenario a una data/ora futura: crea automaticamente le tre
    schedulazioni singole (Jobs, SAM, Yamaha) con la stessa data/ora/ripetizione. Una
    volta create, compaiono — e restano gestibili una per una (sospendi/elimina) — nelle
    pagine dei singoli dispositivi (<a href="audio_jobs.php">Audio Jobs</a>,
    <a href="audio_sam.php">Audio SAM</a>, <a href="audio.php">Audio Yamaha</a>) e nel
    <a href="scheduler.php">riepilogo Schedulazioni</a>: questa pagina non tiene una coda
    propria.
  </p>

  <form data-master-schedule-form class="form-grid">
    <div class="field">
      <label for="ms-scenario">Scenario</label>
      <select id="ms-scenario" name="scenario">
        <?php foreach (master_scenarios() as $scenario): ?>
          <option value="<?= h($scenario['key']) ?>"><?= h($scenario['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="ms-repeat">Ripetizione</label>
      <select id="ms-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="ms-date">Data</label>
      <input id="ms-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="ms-time">Ora</label>
      <input id="ms-time" type="time" name="time" required>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma su Jobs, SAM e Yamaha</span>
    </button>
  </form>
</div>

<div class="panel log-panel">
  <h2>Ultimi comandi</h2>
  <div data-log>
    <p class="log-empty">Nessun comando inviato in questa sessione.</p>
  </div>
</div>

<script>
  // Form dedicato: a differenza degli altri pannelli "Schedulazioni" (vedi
  // assets/js/scheduler.js) questo non gestisce una propria coda di job —
  // manda action 'schedule_add_master', che ne crea tre di tipo diverso
  // (soundcraft_snapshot/sam/yamaha) già visibili nelle rispettive pagine.
  // Riusa solo toast()/setButtonLoading() da app.js, come scheduler.js.
  (function () {
    const form = document.querySelector('[data-master-schedule-form]');
    if (!form) return;

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
      const payload = Object.fromEntries(new FormData(form).entries());

      setButtonLoading(btn, true);
      try {
        const res = await fetch('api.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'schedule_add_master', ...payload }),
        });
        let data;
        try { data = await res.json(); } catch (e) { data = { ok: false, message: 'Risposta non valida dal server.' }; }
        toast(data.message || (data.ok ? 'Fatto.' : 'Errore.'), data.ok ? 'ok' : 'err');
        if (data.ok) {
          form.reset();
          syncDateField();
        }
      } catch (e) {
        toast('Richiesta non riuscita: ' + e.message, 'err');
      } finally {
        setButtonLoading(btn, false);
      }
    });
  })();
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
