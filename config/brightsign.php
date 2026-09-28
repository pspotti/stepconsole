<?php
/**
 * Dispositivi BrightSign gestibili dal pannello "Gestione BrightSign"
 * della dashboard. Ogni player riceve comandi di riproduzione (prev/play/
 * stop/next/first/last) via UDP sulla stessa porta di ascolto, con lo
 * stesso meccanismo già usato per il banner Future Trends (send_udp_message
 * in includes/functions.php).
 *
 * Placeholder: gli IP sotto sono segnaposto. Sostituiscili con gli
 * indirizzi reali dei player quando saranno assegnati/collegati in rete —
 * è l'unica modifica necessaria, la UI e l'API leggono sempre da qui.
 */

return [
    'port' => 5000,
    'devices' => [
        ['label' => 'Gate', 'ip' => '192.168.11.71'],
        ['label' => 'BrightSign 2 (da configurare)', 'ip' => '192.168.11.41'],
        ['label' => 'FT-Wall', 'ip' => '192.168.11.83'],
    ],
    /**
     * Comandi disponibili sia come pulsanti sia come voci schedulabili
     * (vedi brightsign.php e lo scheduler in api.php/scripts/run_scheduler.php):
     * un'unica lista, sullo stesso modello di 'bannerCommands' in
     * config/stations.php, così aggiungere/togliere un comando qui lo fa
     * comparire automaticamente ovunque senza doverlo tenere allineato a mano.
     */
    'commands' => [
        ['key' => 'prev', 'label' => 'Precedente', 'style' => 'btn-neutral', 'badge' => 'accent'],
        ['key' => 'play', 'label' => 'Play',        'style' => 'btn-start',   'badge' => 'start'],
        ['key' => 'stop', 'label' => 'Stop',         'style' => 'btn-stop',    'badge' => 'stop'],
        ['key' => 'next', 'label' => 'Successivo',  'style' => 'btn-neutral', 'badge' => 'accent'],
    ],
];
