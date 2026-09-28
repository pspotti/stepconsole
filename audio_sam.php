<?php
require __DIR__ . '/includes/functions.php';
$pageTitle = 'Gestione Audio SAM';
$activeKey = 'audio_sam';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="panel">
  <h2>Gestione Audio SAM</h2>
  <p class="panel-desc">
    Attiva/disattiva l'audio di sistema del PC SAM (<?= h(sam_config()['host']) ?>), eseguendo
    <code>nircmd.exe mutesysvolume</code> da remoto via WMI (Impacket <code>wmiexec.py</code>) —
    stesso comando finora lanciato a mano da riga di comando.
  </p>
  <div class="action-row">
    <?php foreach (sam_commands() as $cmd): ?>
    <button class="btn <?= h($cmd['style']) ?>" data-action="sam" data-command="<?= h($cmd['key']) ?>">
      <span class="spinner"></span><span class="btn-label"><?= h($cmd['label']) ?></span>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel">
  <h2>Schedulazioni</h2>
  <p class="panel-desc">
    Programma l'invio automatico di un comando, come sopra: una tantum a una data/ora
    scelta, oppure ogni giorno alla stessa ora. L'invio effettivo è fatto dal cron
    <code>scripts/run_scheduler.php</code> sul server: se non è stato installato, i
    comandi restano in coda senza essere spediti (vedi README).
  </p>

  <form data-schedule-form data-schedule-type="sam" class="form-grid">
    <div class="field">
      <label for="sam-sch-command">Comando</label>
      <select id="sam-sch-command" name="command">
        <?php foreach (sam_commands() as $cmd): ?>
          <option value="<?= h($cmd['key']) ?>"><?= h($cmd['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sam-sch-repeat">Ripetizione</label>
      <select id="sam-sch-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="sam-sch-date">Data</label>
      <input id="sam-sch-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="sam-sch-time">Ora</label>
      <input id="sam-sch-time" type="time" name="time" required>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma</span>
    </button>
  </form>

  <div class="schedule-list" data-schedule-list data-schedule-type="sam">
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
