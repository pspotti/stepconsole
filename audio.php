<?php
require __DIR__ . '/includes/functions.php';
$pageTitle = 'Gestione Audio Yamaha';
$activeKey = 'audio_yamaha';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="panel">
  <h2>Gestione Audio Yamaha</h2>
  <p class="panel-desc">
    Richiama uno scenario preimpostato sul processore audio Yamaha
    (<?= h(yamaha_host()) ?>:<?= h((string) yamaha_port()) ?>). Ogni pulsante invia via TCP
    la stessa sequenza <code>devstatus runmode</code> / <code>ssrecall</code> usata finora da riga
    di comando.
  </p>
  <div class="action-row">
    <?php foreach (yamaha_scenes() as $scene): ?>
    <button class="btn <?= h($scene['style']) ?>" data-action="yamaha" data-scene="<?= h($scene['key']) ?>">
      <span class="spinner"></span><span class="btn-label"><?= h($scene['label']) ?></span>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h2>Schedulazioni</h2>
  <p class="panel-desc">
    Programma il richiamo automatico di uno scenario, come sopra: una tantum a una
    data/ora scelta, oppure ogni giorno alla stessa ora (es. Museo all'apertura, Muto
    alla chiusura). L'invio effettivo è fatto dal cron <code>scripts/run_scheduler.php</code>
    sul server: se non è stato installato, i comandi restano in coda senza essere
    spediti (vedi README).
  </p>

  <form data-schedule-form data-schedule-type="yamaha" class="form-grid">
    <div class="field">
      <label for="ya-sch-scene">Scenario</label>
      <select id="ya-sch-scene" name="scene">
        <?php foreach (yamaha_scenes() as $scene): ?>
          <option value="<?= h($scene['key']) ?>"><?= h($scene['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="ya-sch-repeat">Ripetizione</label>
      <select id="ya-sch-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="ya-sch-date">Data</label>
      <input id="ya-sch-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="ya-sch-time">Ora</label>
      <input id="ya-sch-time" type="time" name="time" required>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma</span>
    </button>
  </form>

  <div class="schedule-list" data-schedule-list data-schedule-type="yamaha">
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
