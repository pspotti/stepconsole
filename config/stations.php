<?php
/**
 * Sorgente unica di verità per tutte le stazioni della mostra.
 *
 * Ogni voce alimenta contemporaneamente: la sidebar di navigazione,
 * la card della dashboard, la pagina di dettaglio (station.php) e la
 * whitelist server-side usata da api.php per validare le richieste.
 * Aggiungere/rimuovere una stazione richiede una sola modifica qui.
 */

/**
 * Comandi del pannello "Banner Future Trends", condivisi da bottoni e
 * scheduler: un'unica lista, così aggiungere/togliere un comando qui
 * lo fa comparire automaticamente sia come pulsante sia come voce
 * schedulabile (vedi station.php e la action 'ftbanner' in api.php),
 * senza doverli tenere allineati a mano in più punti.
 */
$ftBannerTotems = ['192.168.11.30', '192.168.11.31', '192.168.11.32', '192.168.11.33'];
$ftBannerBase = [
    [
        'key'     => 'off',
        'label'   => 'Nero',
        'style'   => 'btn-dark',
        'badge'   => 'dark',
        'targets' => $ftBannerTotems,
        'port'    => 5000,
        'message' => 'black',
    ],
    [
        'key'     => 'on',
        'label'   => 'Default',
        'style'   => 'btn-start',
        'badge'   => 'start',
        'targets' => $ftBannerTotems,
        'port'    => 5000,
        'message' => 'reset',
    ],
];

return [

    'gate' => [
        'label'      => 'Gate',
        'stepid'     => 'step01in',
        'image'      => '01_Gate chiuso attesa.png',
        'group'      => 'Percorso',
        'description'=> 'Ingresso e accoglienza visitatori.',
    ],
    'gallery' => [
        'label'      => 'Gallery',
        'stepid'     => 'step02in',
        'image'      => '02_Gallery 2.png',
        'group'      => 'Percorso',
    ],
    'theater' => [
        'label'      => 'Theater',
        'stepid'     => 'step03in',
        'image'      => '03_Windoor frontale_persone.png',
        'group'      => 'Percorso',
        'noStop'     => true, // nessun pulsante Stop per questa sala
    ],
    'square' => [
        'label'      => 'Future Trends',
        'stepid'     => 'step04in',
        'image'      => '04_Square laterale.png',
        'group'      => 'Percorso',
        'ftBanner'   => true, // banner sui totem del percorso Future Trends
        // mostra il pannello scheduler (solo qui, non su Gate/dashboard):
        // programma l'invio futuro dello stesso comando UDP di 'ftbanner'.
        'ftScheduler' => true,
        // Start/Stop di questa sala non passano da GestStep.sh/Redis: vanno
        // via UDP diretto al player della sala.
        'udpCommand' => [
            'port'    => 5000,
            'targets' => ['192.168.11.30'],
            'start'   => 'play',
            'stop'    => 'idle',
        ],
        // Stessa lista di 'gate' più il comando "Evento", specifico di questa
        // sala (inviato a tutti i totem del percorso, come "Default").
        'bannerCommands' => array_merge($ftBannerBase, [
            [
                'key'     => 'evento',
                'label'   => 'Evento',
                'style'   => 'btn-accent',
                'badge'   => 'accent',
                'targets' => $ftBannerTotems,
                'port'    => 5000,
                'message' => 'evento',
            ],
        ]),
    ],
    'tuseifuturo' => [
        'label'      => 'Tu Sei Futuro',
        'stepid'     => 'step05in',
        'image'      => '05_TUSEIFUTURO.png',
        'group'      => 'Percorso',
    ],
    'nextdoor' => [
        'label'      => 'Next Door',
        'stepid'     => 'step06in',
        'image'      => '06_Next Door esterno.png',
        'group'      => 'Percorso',
    ],
    'gigante' => [
        'label'      => 'Gigante Bracco',
        'stepid'     => 'exhibit06',
        'image'      => 'bracco_thebeautyofimaging_comm.jpg',
        'group'      => 'Percorso',
        // niente pulsante Start/Stop standard: gestione dedicata
        'noVisitControls' => true,
        'subactions' => [
            ['comando' => 'PlayAudio', 'target' => 1, 'label' => 'Avvia con audio'],
        ],
        // comandi diretti al player video (proxati server-side da api.php)
        'projector' => [
            'host' => '192.168.11.230',
            'port' => 1234,
            'links' => [
                ['exec' => 10880, 'label' => 'Link diretto con audio'],
                ['exec' => 10955, 'label' => 'Loop senza audio'],
                ['exec' => -1,    'label' => 'Reset'],
            ],
        ],
    ],
    'magic' => [
        'label'      => 'Magic',
        'stepid'     => 'step09in',
        'image'      => '09_Magic.png',
        'group'      => 'Percorso',
    ],
    'wall' => [
        'label'      => 'Wall',
        'stepid'     => 'step10in',
        'image'      => '10_Wall.png',
        'group'      => 'Percorso',
        'subactions' => [
            ['comando' => 'PlayAudio', 'target' => 1, 'label' => 'Audio 1'],
            ['comando' => 'PlayAudio', 'target' => 2, 'label' => 'Audio 2'],
        ],
    ],

];
