<?php
/**
 * Apertura HTML condivisa. Variabili attese dalla pagina chiamante:
 *   $pageTitle   (string) titolo <title> / intestazione topbar
 *   $activeKey   (string) chiave di stations.php da evidenziare nel menu, o ''
 */
require_once __DIR__ . '/functions.php';
$pageTitle = $pageTitle ?? 'STEP Console';
$activeKey = $activeKey ?? '';
$all = stations();

// Voci di menu statiche (Dashboard, Emergenza, Dispositivi, Gestione audio,
// Schedulazioni, Test giornalisti): lette da config/sidebar.json, condiviso
// con la sidebar Flask di WallConfig (wallconfig/app.py +
// templates/index.html). Aggiungere/togliere/rinominare una voce lì la
// aggiorna su entrambe le sidebar: non serve più tenerle sincronizzate a
// mano. La sezione "Percorso mostra" (dinamica da config/stations.php)
// resta invece hardcoded qui sotto e duplicata a mano in WallConfig, come
// già commentato più avanti.
$sidebarSections = json_decode((string) file_get_contents(APP_ROOT . '/config/sidebar.json'), true)['sections'] ?? [];

/** Href/stato "active" di una voce di menu statica di config/sidebar.json. */
function sidebar_item_href(array $item): string
{
    return $item['href'] === '__WALLCONFIG__' ? wallconfig_base_url() : $item['href'];
}
function sidebar_item_is_active(array $item, string $activeKey): bool
{
    return $item['key'] === '' ? $activeKey === '' : $activeKey === $item['key'];
}
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> · STEP Console</title>
<link rel="stylesheet" href="<?= h(asset_url('assets/css/style.css')) ?>">
</head>
<body>
<div class="app">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <span class="dot"></span>
      <div>
        <strong>STEP</strong>
        <span>Console regia mostra</span>
      </div>
    </div>

    <?php foreach ($sidebarSections as $section): ?>
      <?php if ($section['title'] === 'Emergenza / recovery'): ?>
    <div class="sidebar-section">Percorso mostra</div>
    <nav>
      <ul>
        <?php foreach ($all as $navKey => $navStation): ?>
        <li><a href="station.php?station=<?= h($navKey) ?>" class="<?= $activeKey === $navKey ? 'active' : '' ?>">
          <span class="swatch"></span><?= h($navStation['label']) ?>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </nav>

      <?php endif; ?>
      <?php if (!empty($section['removable'])): ?>
    <!-- === TEST GIORNALISTI (rimovibile): per eliminare la funzione,
         cancella questo blocco, il file test_giornalisti.php e il case
         'journalist_test' in api.php. === -->
      <?php endif; ?>
    <div class="sidebar-section"><?= h($section['title']) ?></div>
    <nav>
      <ul>
        <?php foreach ($section['items'] as $item): ?>
        <li><a href="<?= h(sidebar_item_href($item)) ?>" class="<?= sidebar_item_is_active($item, $activeKey) ? 'active' : '' ?>"><span class="swatch"></span><?= h($item['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
      <?php if (!empty($section['removable'])): ?>
    <!-- === /TEST GIORNALISTI === -->
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="sidebar-footer">STEP Console v2</div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="menu-toggle" data-sidebar-toggle aria-label="Apri menu">&#9776;</button>
      <div class="page-heading"><?= h($pageTitle) ?></div>

      <form class="visit-context" data-visit-context onsubmit="return false;">
        <label>Visit ID
          <input type="text" name="visitId" size="24" title="UUID della visita, usato in tutte le azioni">
        </label>
        <label><input type="checkbox" name="subEng"> Sub ENG</label>
        <label><input type="checkbox" name="subIta"> Sub ITA</label>
        <label>Tipo
          <select name="visitType">
            <option value="Regular">Regular</option>
            <option value="Young">Young</option>
            <option value="Kids">Kids</option>
          </select>
        </label>
        <button type="button" class="pill-btn" data-reset-visit>Reset</button>
      </form>
    </header>

    <div class="content">
