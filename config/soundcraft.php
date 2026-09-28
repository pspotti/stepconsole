<?php
/**
 * Mixer Soundcraft Ui gestibile dal pannello "Gestione Audio Jobs".
 * A differenza di BrightSign/Yamaha (comandi UDP/TCP one-shot), il firmware
 * Ui parla WebSocket con un framing Socket.IO 0.9 (`3:::<comando>`, vedi
 * send_soundcraft_command in includes/functions.php): ogni scenario
 * richiama uno snapshot salvato dentro uno show con `LOADSNAPSHOT^<show>^
 * <snapshot>`.
 */

return [
    'host' => '192.168.11.242',
    'port' => 80,
    'show' => 'NewJobs',
    'snapshots' => [
        ['key' => 'evento', 'label' => 'Evento', 'style' => 'btn-accent', 'badge' => 'accent', 'name' => 'EVENTO'],
        ['key' => 'museo',  'label' => 'Museo',  'style' => 'btn-start',  'badge' => 'start',  'name' => 'MUSEO_1006'],
    ],
];
