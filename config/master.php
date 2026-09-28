<?php
/**
 * Scenari "master" del pannello "Scenari Audio": un solo pulsante che
 * combina un comando per ciascuno dei tre dispositivi audio già gestiti
 * separatamente (Jobs/Soundcraft, SAM, Yamaha). Ogni campo qui sotto è la
 * "key" del comando/snapshot/scenario nel rispettivo file di config
 * (config/soundcraft.php, config/sam.php, config/yamaha.php): run_master_scenario()
 * in includes/functions.php le risolve sempre contro quei file, così la
 * whitelist resta unica per dispositivo invece di duplicare nomi/valori qui.
 */

return [
    'scenarios' => [
        [
            'key'   => 'evento',
            'label' => 'Evento',
            'style' => 'btn-accent',
            'badge' => 'accent',
            'soundcraft_snapshot' => 'evento', // Jobs -> EVENTO
            'sam_command'         => 'mute',   // SAM -> Muto
            'yamaha_scene'        => 'evento', // Yamaha -> Evento
        ],
        [
            'key'   => 'muto',
            'label' => 'Muto',
            'style' => 'btn-stop',
            'badge' => 'stop',
            'soundcraft_snapshot' => 'museo',  // Jobs -> MUSEO_1006
            'sam_command'         => 'mute',   // SAM -> Muto
            'yamaha_scene'        => 'muto',   // Yamaha -> Muto
        ],
        [
            'key'   => 'museo',
            'label' => 'Museo',
            'style' => 'btn-start',
            'badge' => 'start',
            'soundcraft_snapshot' => 'museo',  // Jobs -> MUSEO_1006
            'sam_command'         => 'unmute', // SAM -> Attivo
            'yamaha_scene'        => 'museo',  // Yamaha -> Museo
        ],
    ],
];
