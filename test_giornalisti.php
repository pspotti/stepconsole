<?php
/**
 * === TEST GIORNALISTI (rimovibile) =====================================
 * Pagina di test per le anteprime stampa: pubblica a mano lo stesso
 * comando Redis Start che il percorso normale invia sulla sala Future
 * Trends (canale 'step04in'), con Visit ID inserito qui sotto —
 * indipendente dal contesto visita globale nella barra in alto.
 *
 * Per rimuovere questa funzione: cancella questo file, il case
 * 'journalist_test' in api.php e la voce di menu "Test giornalisti" in
 * includes/layout_top.php (blocchi marcati con lo stesso commento).
 * ======================================================================
 */
require __DIR__ . '/includes/functions.php';
$pageTitle = 'Test giornalisti';
$activeKey = 'test_giornalisti';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="panel">
  <h2>Test giornalisti — Future Trends (step04in)</h2>
  <p class="panel-desc">
    Pagina di test per le anteprime stampa. Invia:
    <code>redis-cli publish step04in
    "{"visitId":"&lt;inserito qui sotto&gt;","enSubs":true,"itSubs":true,"visitType":"Regular","command":"Start"}"</code>
    — stessa chiamata usata dal percorso normale, ma con Visit ID inserito
    a mano e non preso dal contesto visita in alto.
  </p>

  <form data-action="journalist_test">
    <div class="form-grid">
      <div class="field">
        <label for="jt-visit">Visit ID</label>
        <input id="jt-visit" type="text" name="visitId" placeholder="00000000-0000-0000-0000-000000000000" required>
      </div>
    </div>
    <button type="submit" class="btn btn-start">
      <span class="spinner"></span><span class="btn-label">Start</span>
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
