<?php
require __DIR__ . '/includes/functions.php';
$pageTitle = 'Gestione BrightSign';
$activeKey = 'brightsign';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="panel">
  <h2>Gestione BrightSign</h2>
  <p class="panel-desc">
    Invia comandi di riproduzione UDP a un player BrightSign. Seleziona il dispositivo
    e poi il comando: gli stessi comandi restano validi qualunque dispositivo tu scelga.
  </p>
  <div class="form-grid">
    <div class="field">
      <label for="brightsign-ip">Dispositivo</label>
      <select id="brightsign-ip">
        <?php foreach (brightsign_devices() as $dev): ?>
          <option value="<?= h($dev['ip']) ?>"><?= h($dev['label']) ?> — <?= h($dev['ip']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="action-row">
    <?php foreach (brightsign_commands() as $cmd): ?>
    <button class="btn <?= h($cmd['style']) ?> btn-sm" data-action="brightsign"
            data-command="<?= h($cmd['key']) ?>" data-ip-select="brightsign-ip">
      <span class="spinner"></span><span class="btn-label"><?= h($cmd['label']) ?></span>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h2>Schedulazioni</h2>
  <p class="panel-desc">
    Programma l'invio automatico di un comando a un player, come sopra: una tantum a
    una data/ora scelta, oppure ogni giorno alla stessa ora (es. avvio a orario di
    apertura, stop a chiusura). L'invio effettivo è fatto dal cron
    <code>scripts/run_scheduler.php</code> sul server: se non è stato installato, i
    comandi restano in coda senza essere spediti (vedi README).
  </p>

  <form data-schedule-form data-schedule-type="brightsign" class="form-grid">
    <div class="field">
      <label for="bs-sch-ip">Dispositivo</label>
      <select id="bs-sch-ip" name="ip">
        <?php foreach (brightsign_devices() as $dev): ?>
          <option value="<?= h($dev['ip']) ?>"><?= h($dev['label']) ?> — <?= h($dev['ip']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="bs-sch-command">Comando</label>
      <select id="bs-sch-command" name="command">
        <?php foreach (brightsign_commands() as $cmd): ?>
          <option value="<?= h($cmd['key']) ?>"><?= h($cmd['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="bs-sch-repeat">Ripetizione</label>
      <select id="bs-sch-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="bs-sch-date">Data</label>
      <input id="bs-sch-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="bs-sch-time">Ora</label>
      <input id="bs-sch-time" type="time" name="time" required>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma</span>
    </button>
  </form>

  <div class="schedule-list" data-schedule-list data-schedule-type="brightsign">
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
