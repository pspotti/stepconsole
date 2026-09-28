<?php
/**
 * Processore audio Yamaha gestibile dal pannello "Gestione Audio Yamaha".
 * Ogni scenario richiama una scena salvata sul dispositivo (ssrecall <n>)
 * via TCP, con lo stesso meccanismo già usato manualmente da riga di
 * comando (send_yamaha_scene in includes/functions.php).
 */

return [
    'host' => '192.168.11.162',
    'port' => 49280,
    'scenes' => [
        ['key' => 'museo',  'label' => 'Museo',  'style' => 'btn-start',  'badge' => 'start',  'recall' => 1],
        ['key' => 'evento', 'label' => 'Evento', 'style' => 'btn-accent', 'badge' => 'accent', 'recall' => 2],
        ['key' => 'muto',   'label' => 'Muto',   'style' => 'btn-stop',   'badge' => 'stop',   'recall' => 3],
    ],
];
