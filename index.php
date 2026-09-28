<?php
require __DIR__ . '/includes/functions.php';
$pageTitle = 'Panoramica';
$activeKey = '';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="panel">
  <h2>Regia della mostra</h2>
  <p class="panel-desc">
    Imposta una sola volta il Visit ID e le preferenze visita nella barra in alto:
    verranno riusati automaticamente da ogni comando Start/Stop di questa sessione,
    su qualunque stazione. Ogni card qui sotto corrisponde a una sala del percorso.
  </p>
</div>

<div class="panel">
  <h2>Banner Future Trends</h2>
  <p class="panel-desc">Accende/spegne il banner sui totem del percorso Future Trends (visibile anche dalle pagine Gate e Future Trends).</p>
  <div class="action-row">
    <button class="btn btn-stop" data-action="ftbanner" data-mode="off">
      <span class="spinner"></span><span class="btn-label">Spegni Banner Future Trends</span>
    </button>
    <button class="btn btn-start" data-action="ftbanner" data-mode="on">
      <span class="spinner"></span><span class="btn-label">Riattiva Banner Future Trends</span>
    </button>
  </div>
</div>

<div class="card-grid">
  <?php foreach (stations() as $key => $st): ?>
  <div class="card">
    <img src="images/<?= h($st['image']) ?>" alt="<?= h($st['label']) ?>" loading="lazy">
    <div class="card-body">
      <h3><?= h($st['label']) ?></h3>
      <div class="step-id"><?= h($st['stepid']) ?></div>
      <div class="card-actions">
        <?php if (empty($st['noVisitControls'])): ?>
          <button class="btn btn-start btn-sm" data-action="visit" data-station="<?= h($key) ?>" data-azione="Start">
            <span class="spinner"></span><span class="btn-label">Start</span>
          </button>
          <?php if (empty($st['noStop'])): ?>
          <button class="btn btn-stop btn-sm" data-action="visit" data-station="<?= h($key) ?>" data-azione="Reset">
            <span class="spinner"></span><span class="btn-label">Stop</span>
          </button>
          <?php endif; ?>
        <?php endif; ?>
        <a class="btn btn-neutral btn-sm" href="station.php?station=<?= h($key) ?>">Dettagli</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="panel log-panel">
  <h2>Ultimi comandi</h2>
  <div data-log>
    <p class="log-empty">Nessun comando inviato in questa sessione.</p>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
