<?php
/**
 * Matrice HDMI TESmart 8x8, comandata via TCP (porta 5000, nessun login)
 * con il protocollo ASCII "MT00...NT" documentato da TESmart e usato dal
 * modulo Companion open source bitfocus/companion-module-tesmart-hdmimatrix
 * (vedi send_tesmart_command in includes/functions.php).
 *
 * 'labels' sono le etichette mostrate in tesmart_test.php (pagina "Scenari
 * Schermo Windoor"): rinominale con i nomi reali di sorgenti/schermi
 * collegati quando cambiano, senza toccare altro codice.
 *
 * 'scenarios' elenca le chiavi di labels.inputs usate come "scenario" (i
 * pulsanti Museo/Resolume Arena che instradano lo stesso ingresso su tutte
 * le uscite, in tesmart_test.php, e la relativa schedulazione): per
 * aggiungere/togliere uno scenario basta modificare questa lista, gli altri
 * ingressi restano comunque selezionabili nella mappatura per singola
 * uscita più sotto.
 */

return [
    'host' => '192.168.11.252',
    'port' => 5000,
    'labels' => [
        'inputs' => [
            1 => 'Museo', 2 => 'Resolume Arena', 3 => 'Ingresso 3', 4 => 'Ingresso 4',
            5 => 'Ingresso 5', 6 => 'Ingresso 6', 7 => 'Ingresso 7', 8 => 'Ingresso 8',
        ],
        'outputs' => [
            1 => 'Uscita 1', 2 => 'Uscita 2', 3 => 'Uscita 3', 4 => 'Uscita 4',
            5 => 'Uscita 5', 6 => 'Uscita 6', 7 => 'Uscita 7', 8 => 'Uscita 8',
        ],
    ],
    'scenarios' => [1, 2],
];
