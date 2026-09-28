<?php
require __DIR__ . '/includes/functions.php';
$pageTitle = 'Gestione Audio Jobs';
$activeKey = 'audio_jobs';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="panel">
  <h2>Gestione Audio Jobs</h2>
  <p class="panel-desc">
    Richiama uno snapshot dentro lo show <strong><?= h(soundcraft_show()) ?></strong> sul mixer
    Soundcraft Ui24R (<?= h(soundcraft_host()) ?>). Ogni pulsante invia via WebSocket lo stesso
    comando <code>LOADSNAPSHOT</code> usato dall'app ufficiale del mixer.
  </p>
  <div class="action-row">
    <?php foreach (soundcraft_snapshots() as $snapshot): ?>
    <button class="btn <?= h($snapshot['style']) ?>" data-action="soundcraft_snapshot" data-snapshot="<?= h($snapshot['key']) ?>">
      <span class="spinner"></span><span class="btn-label"><?= h($snapshot['label']) ?></span>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h2>Schedulazioni</h2>
  <p class="panel-desc">
    Programma il richiamo automatico di uno snapshot, come sopra: una tantum a una
    data/ora scelta, oppure ogni giorno alla stessa ora. L'invio effettivo è fatto dal
    cron <code>scripts/run_scheduler.php</code> sul server: se non è stato installato, i
    comandi restano in coda senza essere spediti (vedi README).
  </p>

  <form data-schedule-form data-schedule-type="soundcraft_snapshot" class="form-grid">
    <div class="field">
      <label for="sc-sch-snapshot">Snapshot</label>
      <select id="sc-sch-snapshot" name="snapshot">
        <?php foreach (soundcraft_snapshots() as $snapshot): ?>
          <option value="<?= h($snapshot['key']) ?>"><?= h($snapshot['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sc-sch-repeat">Ripetizione</label>
      <select id="sc-sch-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="sc-sch-date">Data</label>
      <input id="sc-sch-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="sc-sch-time">Ora</label>
      <input id="sc-sch-time" type="time" name="time" required>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma</span>
    </button>
  </form>

  <div class="schedule-list" data-schedule-list data-schedule-type="soundcraft_snapshot">
    <p class="log-empty">Nessun comando programmato.</p>
  </div>
</div>

<div class="panel log-panel">
  <h2>Ultimi comandi</h2>
  <div data-log>
    <p class="log-empty">Nessun comando inviato in questa sessione.</p>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
