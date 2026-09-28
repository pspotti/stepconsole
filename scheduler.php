<?php
require __DIR__ . '/includes/functions.php';
$pageTitle = 'Schedulazioni';
$activeKey = 'scheduler';
require __DIR__ . '/includes/layout_top.php';

// Sezioni/setup di WallConfig: letti dalla sua API (servizio separato), non
// duplicati qui — se il servizio non risponde, la sezione sotto lo segnala
// invece di rompere il resto della pagina (banner/BrightSign restano
// utilizzabili comunque).
$wcData     = wallconfig_api_get('/api/sections');
$wcOk       = is_array($wcData) && !empty($wcData['ok']);
$wcSections = $wcOk ? ($wcData['sections'] ?? []) : [];
?>

<div class="panel">
  <h2>Schedulazioni</h2>
  <p class="panel-desc">
    Tutte le schedulazioni della console in un unico posto, divise per sezione: banner
    Future Trends, player BrightSign, audio Yamaha, audio Jobs (Soundcraft), audio SAM,
    Scenari Schermo Windoor (matrice HDMI TESmart) e Scenari Schermi STEP (cambi di stato
    dei monitor). Ogni sezione mostra la propria coda e permette di aggiungere,
    sospendere/riattivare o eliminare un comando — stessi pannelli già presenti nelle
    rispettive pagine (<a href="station.php?station=square">Future Trends</a>,
    <a href="brightsign.php">BrightSign</a>, <a href="audio.php">Audio Yamaha</a>,
    <a href="audio_jobs.php">Audio Jobs</a>, <a href="audio_sam.php">Audio SAM</a>,
    <a href="tesmart_test.php">Scenari Schermo Windoor</a>), qui solo riuniti. Uno scenario
    programmato dalla schedulazione aggregata di <a href="audio_master.php">Scenari
    Audio</a> compare qui come tre job separati (uno per sezione Jobs/SAM/Yamaha), non
    come voce a sé.
  </p>
</div>

<div class="panel">
  <h2>Banner Future Trends</h2>
  <p class="panel-desc">Accende/spegne il banner sui totem del percorso Future Trends.</p>

  <form data-schedule-form data-schedule-type="ftbanner" class="form-grid">
    <div class="field">
      <label for="sch-ft-repeat">Ripetizione</label>
      <select id="sch-ft-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="sch-ft-date">Data</label>
      <input id="sch-ft-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="sch-ft-time">Ora</label>
      <input id="sch-ft-time" type="time" name="time" required>
    </div>
    <div class="field">
      <label for="sch-ft-mode">Comando</label>
      <select id="sch-ft-mode" name="mode">
        <?php foreach (station('square')['bannerCommands'] as $cmd): ?>
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

<div class="panel">
  <h2>BrightSign</h2>
  <p class="panel-desc">Comandi di riproduzione UDP a un player BrightSign.</p>

  <form data-schedule-form data-schedule-type="brightsign" class="form-grid">
    <div class="field">
      <label for="sch-bs-ip">Dispositivo</label>
      <select id="sch-bs-ip" name="ip">
        <?php foreach (brightsign_devices() as $dev): ?>
          <option value="<?= h($dev['ip']) ?>"><?= h($dev['label']) ?> — <?= h($dev['ip']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sch-bs-command">Comando</label>
      <select id="sch-bs-command" name="command">
        <?php foreach (brightsign_commands() as $cmd): ?>
          <option value="<?= h($cmd['key']) ?>"><?= h($cmd['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sch-bs-repeat">Ripetizione</label>
      <select id="sch-bs-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="sch-bs-date">Data</label>
      <input id="sch-bs-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="sch-bs-time">Ora</label>
      <input id="sch-bs-time" type="time" name="time" required>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma</span>
    </button>
  </form>

  <div class="schedule-list" data-schedule-list data-schedule-type="brightsign">
    <p class="log-empty">Nessun comando programmato.</p>
  </div>
</div>

<div class="panel">
  <h2>Audio Yamaha</h2>
  <p class="panel-desc">
    Richiamo scenari (Museo/Evento/Muto) sul processore audio
    (<?= h(yamaha_host()) ?>:<?= h((string) yamaha_port()) ?>).
  </p>

  <form data-schedule-form data-schedule-type="yamaha" class="form-grid">
    <div class="field">
      <label for="sch-ya-scene">Scenario</label>
      <select id="sch-ya-scene" name="scene">
        <?php foreach (yamaha_scenes() as $scene): ?>
          <option value="<?= h($scene['key']) ?>"><?= h($scene['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sch-ya-repeat">Ripetizione</label>
      <select id="sch-ya-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="sch-ya-date">Data</label>
      <input id="sch-ya-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="sch-ya-time">Ora</label>
      <input id="sch-ya-time" type="time" name="time" required>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma</span>
    </button>
  </form>

  <div class="schedule-list" data-schedule-list data-schedule-type="yamaha">
    <p class="log-empty">Nessun comando programmato.</p>
  </div>
</div>

<div class="panel">
  <h2>Audio Jobs (Soundcraft)</h2>
  <p class="panel-desc">
    Richiamo snapshot nello show <strong><?= h(soundcraft_show()) ?></strong> sul mixer
    Soundcraft Ui24R (<?= h(soundcraft_host()) ?>).
  </p>

  <form data-schedule-form data-schedule-type="soundcraft_snapshot" class="form-grid">
    <div class="field">
      <label for="sch-sc-snapshot">Snapshot</label>
      <select id="sch-sc-snapshot" name="snapshot">
        <?php foreach (soundcraft_snapshots() as $snapshot): ?>
          <option value="<?= h($snapshot['key']) ?>"><?= h($snapshot['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sch-sc-repeat">Ripetizione</label>
      <select id="sch-sc-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="sch-sc-date">Data</label>
      <input id="sch-sc-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="sch-sc-time">Ora</label>
      <input id="sch-sc-time" type="time" name="time" required>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma</span>
    </button>
  </form>

  <div class="schedule-list" data-schedule-list data-schedule-type="soundcraft_snapshot">
    <p class="log-empty">Nessun comando programmato.</p>
  </div>
</div>

<div class="panel">
  <h2>Audio SAM</h2>
  <p class="panel-desc">
    Mute/unmute del volume di sistema sul PC SAM (<?= h(sam_config()['host']) ?>) via WMI.
  </p>

  <form data-schedule-form data-schedule-type="sam" class="form-grid">
    <div class="field">
      <label for="sch-sam-command">Comando</label>
      <select id="sch-sam-command" name="command">
        <?php foreach (sam_commands() as $cmd): ?>
          <option value="<?= h($cmd['key']) ?>"><?= h($cmd['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sch-sam-repeat">Ripetizione</label>
      <select id="sch-sam-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="sch-sam-date">Data</label>
      <input id="sch-sam-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="sch-sam-time">Ora</label>
      <input id="sch-sam-time" type="time" name="time" required>
    </div>
    <button type="submit" class="btn btn-accent btn-block">
      <span class="spinner"></span><span class="btn-label">Programma</span>
    </button>
  </form>

  <div class="schedule-list" data-schedule-list data-schedule-type="sam">
    <p class="log-empty">Nessun comando programmato.</p>
  </div>
</div>

<div class="panel">
  <h2>Scenari Schermo Windoor</h2>
  <p class="panel-desc">
    Instrada la stessa sorgente su tutte le uscite della matrice HDMI TESmart 8x8
    (<?= h(tesmart_host()) ?>:<?= h((string) tesmart_port()) ?>).
  </p>

  <form data-schedule-form data-schedule-type="tesmart" class="form-grid">
    <div class="field">
      <label for="sch-ts-input">Scenario</label>
      <select id="sch-ts-input" name="input">
        <?php foreach (tesmart_scenarios() as $n => $label): ?>
          <option value="<?= (int) $n ?>"><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="sch-ts-repeat">Ripetizione</label>
      <select id="sch-ts-repeat" name="repeat">
        <option value="once">Una volta</option>
        <option value="daily">Ogni giorno</option>
      </select>
    </div>
    <div class="field" data-sch-date-field>
      <label for="sch-ts-date">Data</label>
      <input id="sch-ts-date" type="date" name="date">
    </div>
    <div class="field">
      <label for="sch-ts-time">Ora</label>
      <input id="sch-ts-time" type="time" name="time" required>
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
  <h2>Scenari Schermi STEP</h2>
  <p class="panel-desc">
    Cambi di stato dei monitor. Coda gestita dal servizio WallConfig, separato da questa
    console (<a href="<?= h(wallconfig_base_url()) ?>">apri Scenari Schermi STEP</a>) — qui
    sotto la stessa coda, gestibile senza uscire dalla console.
  </p>

  <?php if (!$wcOk): ?>
    <p class="log-empty">
      Scenari Schermi STEP non è raggiungibile in questo momento (servizio spento o rete
      diversa): impossibile caricare sezioni/setup per programmare un nuovo comando.
    </p>
  <?php else: ?>
    <form data-schedule-form data-schedule-type="wallconfig"
          data-schedule-api="<?= h(wallconfig_base_url()) ?>/api/schedule" class="form-grid">
      <div class="field">
        <label for="sch-wc-section">Sezione</label>
        <select id="sch-wc-section">
          <?php foreach ($wcSections as $name => $setups): ?>
            <option value="<?= h($name) ?>"><?= h($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="sch-wc-setup">Setup</label>
        <select name="setup" id="sch-wc-setup">
          <?php foreach ($wcSections as $name => $setups): ?>
            <?php foreach ($setups as $setupName): ?>
              <option value="<?= h($name) ?>::<?= h($setupName) ?>" data-section="<?= h($name) ?>"><?= h($setupName) ?></option>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="sch-wc-repeat">Ripetizione</label>
        <select id="sch-wc-repeat" name="repeat">
          <option value="once">Una volta</option>
          <option value="daily">Ogni giorno</option>
        </select>
      </div>
      <div class="field" data-sch-date-field>
        <label for="sch-wc-date">Data</label>
        <input id="sch-wc-date" type="date" name="date">
      </div>
      <div class="field">
        <label for="sch-wc-time">Ora</label>
        <input id="sch-wc-time" type="time" name="time" required>
      </div>
      <button type="submit" class="btn btn-accent btn-block">
        <span class="spinner"></span><span class="btn-label">Programma</span>
      </button>
    </form>

    <div class="schedule-list" data-schedule-list data-schedule-type="wallconfig">
      <p class="log-empty">Nessun comando programmato.</p>
    </div>

    <script>
      // Filtra il select "Setup" in base al select "Sezione" scelto, come lo
      // stesso meccanismo già usato dentro WallConfig (wallconfig/templates/
      // index.html): qui è un caso isolato (l'unica coppia sezione/setup di
      // tutta questa pagina), non vale la pena farlo condiviso in scheduler.js.
      (function () {
        const sectionSel = document.getElementById('sch-wc-section');
        const setupSel = document.getElementById('sch-wc-setup');
        if (!sectionSel || !setupSel) return;
        const allSetupOptions = Array.from(setupSel.options);

        function filterSetups() {
          const chosen = sectionSel.value;
          const matching = allSetupOptions.filter(opt => opt.dataset.section === chosen);
          const previousValue = setupSel.value;
          setupSel.replaceChildren(...matching);
          const stillValid = matching.some(opt => opt.value === previousValue);
          setupSel.value = stillValid ? previousValue : (matching[0] ? matching[0].value : '');
        }
        sectionSel.addEventListener('change', filterSetups);
        filterSetups();
      })();
    </script>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
