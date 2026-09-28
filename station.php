<?php
require __DIR__ . '/includes/functions.php';

$key = $_GET['station'] ?? '';
$st  = station($key);
if (!$st) {
    http_response_code(404);
    $pageTitle = 'Stazione non trovata';
    $activeKey = '';
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="panel"><h2>Stazione non trovata</h2><p class="panel-desc">Controlla il link nella barra laterale.</p></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$pageTitle = $st['label'];
$activeKey = $key;
require __DIR__ . '/includes/layout_top.php';
?>

<div class="hero">
  <img src="images/<?= h($st['image']) ?>" alt="<?= h($st['label']) ?>">
  <div>
    <h1><?= h($st['label']) ?></h1>
    <p><?= h($st['description'] ?? 'Stazione ' . $st['stepid'] . ' del percorso mostra.') ?></p>
  </div>
</div>

<?php if (empty($st['noVisitControls'])): ?>
<div class="panel">
  <h2>Avvio / arresto</h2>
  <p class="panel-desc">Usa il Visit ID e le preferenze impostate nella barra in alto.</p>
  <div class="action-row">
    <button class="btn btn-start" data-action="visit" data-station="<?= h($key) ?>" data-azione="Start">
      <span class="spinner"></span><span class="btn-label">Start</span>
    </button>
    <?php if (empty($st['noStop'])): ?>
    <button class="btn btn-stop" data-action="visit" data-station="<?= h($key) ?>" data-azione="Reset"
            data-confirm="Confermi lo stop di <?= h($st['label']) ?>?">
      <span class="spinner"></span><span class="btn-label">Stop</span>
    </button>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($st['subactions'])): ?>
<div class="panel">
  <h2>Sotto-azioni</h2>
  <p class="panel-desc">Comandi mirati (job / traccia audio) inviati alla stessa stazione.</p>
  <div class="sub-grid">
    <?php foreach ($st['subactions'] as $sub): ?>
      <button class="btn btn-start btn-sm btn-block" data-action="substep" data-station="<?= h($key) ?>"
              data-comando="<?= h($sub['comando']) ?>" data-target="<?= (int) $sub['target'] ?>">
        <span class="spinner"></span><span class="btn-label"><?= h($sub['label']) ?></span>
      </button>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($st['projector'])): ?>
<div class="panel">
  <h2>Player video</h2>
  <p class="panel-desc">
    Comandi diretti inviati dal server al player (<?= h($st['projector']['host']) ?>),
    senza aprire una nuova scheda del browser come nella console originale.
  </p>
  <div class="action-row">
    <?php foreach ($st['projector']['links'] as $link): ?>
      <button class="btn btn-neutral" data-action="projector" data-exec="<?= (int) $link['exec'] ?>">
        <span class="spinner"></span><span class="btn-label"><?= h($link['label']) ?></span>
      </button>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($st['ftBanner'])): ?>
<div class="panel">
  <h2>Banner Future Trends</h2>
  <p class="panel-desc">Accende/spegne il banner sui totem del percorso Future Trends.</p>
  <div class="action-row">
    <?php foreach ($st['bannerCommands'] as $cmd): ?>
    <button class="btn <?= h($cmd['style']) ?>" data-action="ftbanner" data-station="<?= h($key) ?>"
            data-mode="<?= h($cmd['key']) ?>">
      <span class="spinner"></span><span class="btn-label"><?= h($cmd['label']) ?></span>
    </button>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($st['ftScheduler'])): ?>
<div class="panel">
  <h2>Scheduler banner Future Trends</h2>
  <p class="panel-desc">
    Programma l'invio automatico del comando banner qui sopra: una tantum a una data/ora
    scelta, oppure ogni giorno alla stessa ora (es. spegnimento a chiusura, riaccensione
    ad apertura). L'invio effettivo è fatto dal cron <code>scripts/run_scheduler.php</code>
    sul server: se non è stato installato, i comandi restano in coda senza essere spediti
    (vedi README).
  </p>

  <form data-schedule-form data-schedule-type="ftbanner" class="form-grid">
    <div class="field">
      <label for="sch-repeat">Ripetizione</label>
      <select id="sch-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="sch-date">Data</label>
      <input id="sch-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="sch-time">Ora</label>
      <input id="sch-time" type="time" name="time" required>
    </div>
    <div class="field">
      <label for="sch-mode">Comando</label>
      <select id="sch-mode" name="mode">
        <?php foreach ($st['bannerCommands'] as $cmd): ?>
        <option value="<?= h($cmd['key']) ?>"><?= h($cmd['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma</span>
    </button>
  </form>

  <div class="schedule-list" data-schedule-list data-schedule-type="ftbanner">
    <p class="log-empty">Nessun comando programmato.</p>
  </div>
</div>
<?php endif; ?>

<div class="panel log-panel">
  <h2>Ultimi comandi</h2>
  <div data-log>
    <p class="log-empty">Nessun comando inviato in questa sessione.</p>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
