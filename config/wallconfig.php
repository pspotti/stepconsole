<?php
/**
 * URL base del servizio WallConfig (app Flask separata, porta propria:
 * vedi wallconfig/wallconfig.service). Unico posto dove questo indirizzo
 * è scritto lato PHP: usato per il link nel menu e per interrogare le
 * sue API (elenco sezioni/setup, coda schedulata) dalla pagina di
 * riepilogo "Schedulazioni" (scheduler.php).
 */

return [
    'base_url' => 'http://192.168.11.240:8000',
];
