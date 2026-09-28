<?php
require __DIR__ . '/includes/functions.php';
$pageTitle = 'Sblocco generico';
$activeKey = 'recovery';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="panel">
  <h2>Console sblocco di emergenza</h2>
  <p class="panel-desc">
    Da usare quando una visita resta bloccata su uno step: inserisci il Visit ID
    reale della visita (non quello demo della barra in alto) e scegli la stazione da sbloccare.
  </p>

  <form data-action="recovery">
    <div class="form-grid">
      <div class="field">
        <label for="rv-visit">Visit ID</label>
        <input id="rv-visit" type="text" name="visitId" placeholder="00000000-0000-0000-0000-000000000000" required>
      </div>
      <div class="field">
        <label for="rv-station">Stazione da sbloccare</label>
        <select id="rv-station" name="station" required>
          <option value="">Seleziona…</option>
          <?php foreach (stations() as $optKey => $optStation): if (!empty($optStation['noVisitControls'])) continue; ?>
            <option value="<?= h($optKey) ?>"><?= h(strtoupper($optStation['label'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="rv-eng">Sub English</label>
        <select id="rv-eng" name="subEng">
          <option value="1" selected>Attivi</option>
          <option value="">Non attivi</option>
        </select>
      </div>
      <div class="field">
        <label for="rv-ita">Sub Italiano</label>
        <select id="rv-ita" name="subIta">
          <option value="1" selected>Attivi</option>
          <option value="">Non attivi</option>
        </select>
      </div>
      <div class="field">
        <label for="rv-type">Tipo visita</label>
        <select id="rv-type" name="visitType">
          <option value="Regular">Regular</option>
          <option value="Young">Young</option>
          <option value="Kids">Kids</option>
        </select>
      </div>
    </div>
    <button type="submit" class="btn btn-accent">
      <span class="spinner"></span><span class="btn-label">Sblocca</span>
    </button>
  </form>
</div>

<div class="panel log-panel">
  <h2>Ultimi comandi</h2>
  <div data-log>
    <p class="log-empty">Nessun comando inviato in questa sessione.</p>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
