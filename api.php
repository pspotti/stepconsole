<?php
/**
 * Endpoint API unico: JSON in, JSON out.
 *
 * Sostituisce RecoveryVisit.php, RecoverySubVisit.php, RecoveryGigante.php,
 * RecoverySAM.php, FT_Black.php e FT_Reset.php: un solo punto di ingresso,
 * un solo formato di risposta, una sola validazione. Il frontend (assets/js/app.js)
 * ci parla via fetch() invece di inviare ogni pulsante come <form> a un file diverso.
 *
 * Ogni azione risolve la stazione dal suo "key" interno (non dallo STEPID
 * grezzo), così un client non può mai far pubblicare un canale Redis
 * arbitrario: lo STEPID/target/comando ammessi vengono sempre letti da
 * config/stations.php lato server.
 */

require __DIR__ . '/includes/functions.php';

header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metodo non consentito.'], 405);
}

$body   = json_body();
$action = $body['action'] ?? '';

/** Contesto visita comune a tutte le azioni "visit"/"substep". */
function read_visit_context(array $body): array
{
    $visitId = trim((string) ($body['visitId'] ?? ''));
    if ($visitId === '' || !is_valid_visit_id($visitId)) {
        $visitId = default_visit_id();
    }
    return [
        'visitId'    => $visitId,
        'subEng'     => !empty($body['subEng']) ? 'true' : 'false',
        'subIta'     => !empty($body['subIta']) ? 'true' : 'false',
        'visitType'  => in_array($body['visitType'] ?? '', ['Regular', 'Young', 'Kids'], true)
            ? $body['visitType'] : 'Regular',
    ];
}

try {
    switch ($action) {

        // Start / Reset di una stazione -----------------------------------
        case 'visit': {
            $key = (string) ($body['station'] ?? '');
            $st  = station($key);
            if (!$st) {
                json_response(['ok' => false, 'message' => 'Stazione sconosciuta.'], 422);
            }
            $azione = ($body['azione'] ?? '') === 'Reset' ? 'Reset' : 'Start';

            // Stazioni con comando diretto via UDP (es. Future Trends) invece
            // del solito Start/Reset su Redis via GestStep.sh.
            if (!empty($st['udpCommand'])) {
                $udp     = $st['udpCommand'];
                $message = $azione === 'Reset' ? $udp['stop'] : $udp['start'];
                $sent    = send_udp_message($udp['targets'], (int) $udp['port'], $message);

                json_response([
                    'ok' => true,
                    'message' => ($azione === 'Start' ? 'Avvio' : 'Stop') . ' inviato a ' . $st['label'] . '.',
                    'output' => json_encode($sent),
                ]);
            }

            $ctx = read_visit_context($body);

            $output = run_station_script('GestStep.sh', [
                $st['stepid'], $ctx['visitId'], $ctx['subEng'], $ctx['subIta'], $ctx['visitType'], $azione,
            ]);

            json_response([
                'ok' => true,
                'message' => ($azione === 'Start' ? 'Avvio' : 'Stop') . ' inviato a ' . $st['label'] . '.',
                'output' => $output,
            ]);
        }

        // Sotto-comando (job / traccia audio) di una stazione -------------
        case 'substep': {
            $key = (string) ($body['station'] ?? '');
            $st  = station($key);
            if (!$st || empty($st['subactions'])) {
                json_response(['ok' => false, 'message' => 'Stazione senza sotto-azioni.'], 422);
            }
            $target  = (int) ($body['target'] ?? -1);
            $comando = (string) ($body['comando'] ?? '');

            $match = null;
            foreach ($st['subactions'] as $sub) {
                if ($sub['target'] === $target && $sub['comando'] === $comando) {
                    $match = $sub;
                    break;
                }
            }
            if (!$match) {
                json_response(['ok' => false, 'message' => 'Sotto-azione non riconosciuta.'], 422);
            }
            $ctx = read_visit_context($body);

            $output = run_station_script('GestSubStep.sh', [
                $st['stepid'], $ctx['visitId'], $ctx['subEng'], $ctx['subIta'], $ctx['visitType'],
                $match['comando'], $match['target'],
            ]);

            json_response([
                'ok' => true,
                'message' => $match['label'] . ' inviato a ' . $st['label'] . '.',
                'output' => $output,
            ]);
        }

        // Sblocco generico da console di emergenza -------------------------
        case 'recovery': {
            $key = (string) ($body['station'] ?? '');
            $st  = station($key);
            if (!$st) {
                json_response(['ok' => false, 'message' => 'Stazione sconosciuta.'], 422);
            }
            $visitId = trim((string) ($body['visitId'] ?? ''));
            if ($visitId === '' || !is_valid_visit_id($visitId)) {
                json_response(['ok' => false, 'message' => 'Visit ID non valido (formato UUID richiesto).'], 422);
            }
            $ctx = read_visit_context(array_merge($body, ['visitId' => $visitId]));

            $output = run_station_script('GestStep.sh', [
                $st['stepid'], $ctx['visitId'], $ctx['subEng'], $ctx['subIta'], $ctx['visitType'], 'Start',
            ]);

            json_response([
                'ok' => true,
                'message' => 'Sblocco inviato per ' . $st['label'] . '.',
                'output' => $output,
            ]);
        }

        // Comandi diretti al player della sala Gigante -----------------------
        case 'projector': {
            $st = station('gigante');
            $exec = (int) ($body['exec'] ?? -9999);
            $allowed = array_column($st['projector']['links'], 'exec');
            if (!in_array($exec, $allowed, true)) {
                json_response(['ok' => false, 'message' => 'Comando non riconosciuto.'], 422);
            }
            $host = $st['projector']['host'];
            $port = $st['projector']['port'];
            $url  = "http://{$host}:{$port}/update?projectExec=" . urlencode((string) $exec);

            $ctxHttp = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
            $result  = @file_get_contents($url, false, $ctxHttp);

            json_response([
                'ok'      => $result !== false,
                'message' => $result !== false ? 'Comando inviato al player.' : 'Player non raggiungibile.',
                'output'  => (string) $result,
            ]);
        }

        // Banner Future Trends -------------------------------------------------
        // Un solo comando generico invece di un case per pulsante: la lista dei
        // comandi ammessi (Nero/Default/Evento...) viene da 'bannerCommands' in
        // config/stations.php, la stessa che genera i pulsanti in station.php e
        // le voci dello scheduler. Aggiungere un comando lì lo rende subito
        // disponibile qui, senza toccare altro codice.
        case 'ftbanner': {
            $key = (string) ($body['station'] ?? '');
            $st  = station($key);
            if (!$st || empty($st['bannerCommands'])) {
                json_response(['ok' => false, 'message' => 'Stazione senza comandi banner.'], 422);
            }
            $mode = (string) ($body['mode'] ?? '');
            $cmd  = null;
            foreach ($st['bannerCommands'] as $candidate) {
                if ($candidate['key'] === $mode) {
                    $cmd = $candidate;
                    break;
                }
            }
            if (!$cmd) {
                json_response(['ok' => false, 'message' => 'Comando banner non riconosciuto.'], 422);
            }
            $sent = send_udp_message($cmd['targets'], (int) $cmd['port'], $cmd['message']);

            json_response([
                'ok' => true,
                'message' => "{$cmd['label']} inviato" . (count($cmd['targets']) > 1 ? ' a tutti i totem.' : " a {$st['label']}."),
                'output' => json_encode($sent),
            ]);
        }

        // Gestione BrightSign ------------------------------------------------
        // Comandi di riproduzione (prev/play/stop/next/first/last) inviati via
        // UDP a uno dei player elencati in config/brightsign.php. L'IP arriva
        // dal client (menu a tendina) ma viene sempre validato contro la
        // whitelist server-side, come per ogni altra azione di questo file.
        case 'brightsign': {
            static $allowedCommands = ['prev', 'play', 'stop', 'next', 'first', 'last'];

            $command = (string) ($body['command'] ?? '');
            if (!in_array($command, $allowedCommands, true)) {
                json_response(['ok' => false, 'message' => 'Comando non riconosciuto.'], 422);
            }

            $ip = (string) ($body['ip'] ?? '');
            $devices = array_column(brightsign_devices(), 'label', 'ip');
            if (!array_key_exists($ip, $devices)) {
                json_response(['ok' => false, 'message' => 'Dispositivo BrightSign non riconosciuto.'], 422);
            }

            $sent = send_udp_message([$ip], brightsign_port(), $command);
            $ok   = $sent[$ip] ?? false;

            json_response([
                'ok' => $ok,
                'message' => $ok
                    ? "Comando '{$command}' inviato a {$devices[$ip]} ({$ip})."
                    : "Invio a {$ip} non riuscito.",
                'output' => json_encode($sent),
            ]);
        }

        // Gestione Audio Yamaha ------------------------------------------------
        // Richiama uno scenario (Museo/Evento/Muto) sul processore audio via
        // TCP. La scena scelta arriva dal client come "key" ma viene sempre
        // risolta contro config/yamaha.php lato server: il client non può
        // mai far spedire un numero ssrecall arbitrario.
        case 'yamaha': {
            $scenes = array_column(yamaha_scenes(), null, 'key');
            $key = (string) ($body['scene'] ?? '');
            if (!array_key_exists($key, $scenes)) {
                json_response(['ok' => false, 'message' => 'Scenario audio non riconosciuto.'], 422);
            }
            $scene = $scenes[$key];

            $result = send_yamaha_scene(yamaha_host(), yamaha_port(), (int) $scene['recall']);

            json_response([
                'ok' => $result['ok'],
                'message' => $result['ok']
                    ? "Scenario '{$scene['label']}' inviato al processore audio Yamaha."
                    : 'Invio al processore audio Yamaha non riuscito: ' . $result['output'],
                'output' => $result['output'],
            ]);
        }

        // Gestione Audio Jobs (mixer Soundcraft Ui) ---------------------------
        // Richiama uno snapshot (Evento/Museo) dentro lo show configurato sul
        // mixer, via WebSocket (vedi send_soundcraft_snapshot). Lo snapshot
        // scelto arriva dal client come "key" ma viene sempre risolto contro
        // config/soundcraft.php lato server, stesso schema di 'yamaha'.
        case 'soundcraft_snapshot': {
            $snapshots = array_column(soundcraft_snapshots(), null, 'key');
            $key = (string) ($body['snapshot'] ?? '');
            if (!array_key_exists($key, $snapshots)) {
                json_response(['ok' => false, 'message' => 'Snapshot non riconosciuto.'], 422);
            }
            $snapshot = $snapshots[$key];

            $result = send_soundcraft_snapshot(soundcraft_host(), soundcraft_port(), soundcraft_show(), $snapshot['name']);

            json_response([
                'ok' => $result['ok'],
                'message' => $result['ok']
                    ? "Snapshot '{$snapshot['label']}' richiamato sul mixer Soundcraft."
                    : 'Invio al mixer Soundcraft non riuscito: ' . $result['output'],
                'output' => $result['output'],
            ]);
        }

        // Gestione Audio SAM (PC comandato via WMI) ---------------------------
        // Mute/unmute del volume di sistema sul PC SAM, eseguendo nircmd.exe da
        // remoto via WMI (run_sam_command). Il comando scelto arriva dal client
        // come "key" ma viene sempre risolto contro config/sam.php lato server.
        case 'sam': {
            $commands = array_column(sam_commands(), null, 'key');
            $key = (string) ($body['command'] ?? '');
            if (!array_key_exists($key, $commands)) {
                json_response(['ok' => false, 'message' => 'Comando Audio SAM non riconosciuto.'], 422);
            }
            $cmd = $commands[$key];

            $output = run_sam_command((int) $cmd['mute']);

            json_response([
                'ok' => true,
                'message' => "{$cmd['label']} inviato al PC SAM.",
                'output' => $output,
            ]);
        }

        // Scenari Audio (master: Jobs + SAM + Yamaha in un solo pulsante) -----
        // Esegue subito i tre comandi dello scenario scelto (vedi
        // run_master_scenario). Prosegue anche se un dispositivo fallisce:
        // 'ok' è true solo se sono andati tutti a buon fine, ma il messaggio/
        // output riportano sempre l'esito di ciascuno.
        case 'audio_master': {
            $scenarios = array_column(master_scenarios(), null, 'key');
            $key = (string) ($body['scenario'] ?? '');
            if (!array_key_exists($key, $scenarios)) {
                json_response(['ok' => false, 'message' => 'Scenario non riconosciuto.'], 422);
            }
            $scenario = $scenarios[$key];

            $results = run_master_scenario($scenario);
            $allOk   = !in_array(false, array_column($results, 'ok'), true);

            $summary = array_map(
                fn ($r) => ($r['ok'] ? '✓' : '✗') . ' ' . $r['label'],
                array_values($results)
            );
            $outputLines = array_map(
                fn ($r) => $r['label'] . ': ' . trim($r['output']),
                array_values($results)
            );

            json_response([
                'ok' => $allOk,
                'message' => "Scenario '{$scenario['label']}': " . implode(' · ', $summary),
                'output' => implode("\n", $outputLines),
            ]);
        }

        // Schedulazione aggregata di uno scenario master: non crea un job di
        // tipo a sé, ma imposta le TRE schedulazioni singole già esistenti
        // (Jobs/soundcraft_snapshot, SAM, Yamaha) con la stessa data/ora/
        // ripetizione, riusando gli stessi campi/whitelist di 'schedule_add'.
        // Così ogni job resta visibile e gestibile (sospendi/elimina) nella
        // pagina del proprio dispositivo e nel riepilogo Schedulazioni,
        // senza bisogno di un quarto tipo capito solo dal cron.
        case 'schedule_add_master': {
            $scenarios = array_column(master_scenarios(), null, 'key');
            $key = (string) ($body['scenario'] ?? '');
            if (!array_key_exists($key, $scenarios)) {
                json_response(['ok' => false, 'message' => 'Scenario non riconosciuto.'], 422);
            }
            $scenario = $scenarios[$key];

            [$repeat, $date, $time] = validate_schedule_timing($body);

            $newJobs = [
                new_schedule_job('soundcraft_snapshot', $repeat, $date, $time) + ['snapshot' => $scenario['soundcraft_snapshot']],
                new_schedule_job('sam', $repeat, $date, $time) + ['command' => $scenario['sam_command']],
                new_schedule_job('yamaha', $repeat, $date, $time) + ['scene' => $scenario['yamaha_scene']],
            ];

            with_schedule_lock(function (array $jobs) use ($newJobs) {
                return array_merge($jobs, $newJobs);
            });

            json_response([
                'ok' => true,
                'message' => "Scenario '{$scenario['label']}' programmato su Jobs, SAM e Yamaha.",
                'jobs' => $newJobs,
            ]);
        }

        // Schedulazioni (banner Future Trends, BrightSign) -----------------
        // Programma l'invio futuro (una tantum o ogni giorno alla stessa ora)
        // dello stesso comando UDP inviabile a mano ('ftbanner' o 'brightsign').
        // L'invio vero e proprio è fatto dal cron scripts/run_scheduler.php:
        // qui ci limitiamo a validare e a leggere/scrivere la coda condivisa
        // in data/schedule.json. Ogni job porta un campo 'type' che dice quale
        // pannello lo possiede (station.php?station=square, brightsign.php,
        // audio.php oppure tesmart_test.php) e quali campi aggiuntivi usa
        // ('mode' per il banner, 'ip'+'command' per BrightSign, 'scene' per
        // l'audio Yamaha, 'input' per lo scenario Schermo Windoor): stessa
        // coda, tipi diversi, così aggiungere un tipo in
        // più non richiede un secondo file.
        case 'schedule_list': {
            // 'type' arriva sempre dal pannello che chiama (vedi data-schedule-type
            // in assets/js/scheduler.js): senza, torna tutta la coda.
            $type = (string) ($body['type'] ?? '');
            $jobs = with_schedule_lock(fn (array $jobs) => null);
            if ($type !== '') {
                $jobs = array_values(array_filter(
                    $jobs,
                    fn ($j) => ($j['type'] ?? 'ftbanner') === $type
                ));
            }
            foreach ($jobs as &$j) {
                [$j['label'], $j['badge']] = schedule_job_display($j);
            }
            unset($j);
            usort($jobs, fn ($a, $b) => [$a['repeat'] === 'once' ? $a['date'] : '', $a['time']]
                <=> [$b['repeat'] === 'once' ? $b['date'] : '', $b['time']]);
            json_response(['ok' => true, 'jobs' => $jobs]);
        }

        case 'schedule_add': {
            $type = (string) ($body['type'] ?? 'ftbanner');
            [$repeat, $date, $time] = validate_schedule_timing($body);
            $job = new_schedule_job($type, $repeat, $date, $time);

            if ($type === 'brightsign') {
                $ip      = (string) ($body['ip'] ?? '');
                $command = (string) ($body['command'] ?? '');
                $devices = array_column(brightsign_devices(), 'label', 'ip');
                $allowedCommands = array_column(brightsign_commands(), 'key');

                if (!array_key_exists($ip, $devices)) {
                    json_response(['ok' => false, 'message' => 'Dispositivo BrightSign non riconosciuto.'], 422);
                }
                if (!in_array($command, $allowedCommands, true)) {
                    json_response(['ok' => false, 'message' => 'Comando non riconosciuto.'], 422);
                }
                $job['ip']      = $ip;
                $job['command'] = $command;
            } elseif ($type === 'yamaha') {
                $scenes = array_column(yamaha_scenes(), null, 'key');
                $scene  = (string) ($body['scene'] ?? '');

                if (!array_key_exists($scene, $scenes)) {
                    json_response(['ok' => false, 'message' => 'Scenario audio non riconosciuto.'], 422);
                }
                $job['scene'] = $scene;
            } elseif ($type === 'soundcraft_snapshot') {
                $snapshots = array_column(soundcraft_snapshots(), null, 'key');
                $snapshot  = (string) ($body['snapshot'] ?? '');

                if (!array_key_exists($snapshot, $snapshots)) {
                    json_response(['ok' => false, 'message' => 'Snapshot non riconosciuto.'], 422);
                }
                $job['snapshot'] = $snapshot;
            } elseif ($type === 'sam') {
                $commands = array_column(sam_commands(), null, 'key');
                $command  = (string) ($body['command'] ?? '');

                if (!array_key_exists($command, $commands)) {
                    json_response(['ok' => false, 'message' => 'Comando Audio SAM non riconosciuto.'], 422);
                }
                $job['command'] = $command;
            } elseif ($type === 'tesmart') {
                $scenarios = tesmart_scenarios();
                $input     = $body['input'] ?? null;

                if (!is_numeric($input) || !array_key_exists((int) $input, $scenarios)) {
                    json_response(['ok' => false, 'message' => 'Scenario Schermo Windoor non riconosciuto.'], 422);
                }
                $job['input'] = (int) $input;
            } else {
                // Il pannello banner vive solo sulla pagina Future Trends ('square'):
                // il comando scelto deve essere una delle voci di 'bannerCommands' di
                // quella stazione, la stessa lista che genera i pulsanti e le opzioni
                // del <select> in station.php.
                $allowedModes = array_column(station('square')['bannerCommands'], 'key');
                $mode = (string) ($body['mode'] ?? '');
                if (!in_array($mode, $allowedModes, true)) {
                    json_response(['ok' => false, 'message' => 'Comando banner non valido.'], 422);
                }
                $job['type'] = 'ftbanner';
                $job['mode'] = $mode;
            }

            with_schedule_lock(function (array $jobs) use ($job) {
                $jobs[] = $job;
                return $jobs;
            });

            json_response(['ok' => true, 'message' => 'Comando programmato.', 'job' => $job]);
        }

        case 'schedule_toggle': {
            $id      = (string) ($body['id'] ?? '');
            $enabled = !empty($body['enabled']);
            $found   = false;

            with_schedule_lock(function (array $jobs) use ($id, $enabled, &$found) {
                foreach ($jobs as &$j) {
                    if ($j['id'] === $id) {
                        $j['enabled'] = $enabled;
                        $found = true;
                    }
                }
                unset($j);
                return $jobs;
            });

            if (!$found) {
                json_response(['ok' => false, 'message' => 'Comando programmato non trovato.'], 404);
            }
            json_response(['ok' => true, 'message' => $enabled ? 'Comando riattivato.' : 'Comando sospeso.']);
        }

        case 'schedule_delete': {
            $id    = (string) ($body['id'] ?? '');
            $found = false;

            with_schedule_lock(function (array $jobs) use ($id, &$found) {
                $filtered = array_filter($jobs, function ($j) use ($id, &$found) {
                    if ($j['id'] === $id) {
                        $found = true;
                        return false;
                    }
                    return true;
                });
                return array_values($filtered);
            });

            if (!$found) {
                json_response(['ok' => false, 'message' => 'Comando programmato non trovato.'], 404);
            }
            json_response(['ok' => true, 'message' => 'Comando programmato rimosso.']);
        }

        // Test Matrice HDMI TESmart 8x8 ---------------------------------------
        // Pagina di prova per instradare le uscite della matrice (host/porta
        // sempre letti da config/tesmart.php lato server, mai dal client) via
        // TCP con send_tesmart_command. Ingresso/uscita arrivano dal client ma
        // sono sempre validati nell'intervallo 1-8 della matrice.
        case 'tesmart': {
            $sub  = (string) ($body['sub'] ?? '');
            $host = tesmart_host();
            $port = tesmart_port();
            $isPort = static fn ($n): bool => is_numeric($n) && (int) $n >= 1 && (int) $n <= 8;

            switch ($sub) {
                case 'switch': {
                    $input  = $body['input'] ?? null;
                    $output = $body['output'] ?? null;
                    if (!$isPort($input) || !$isPort($output)) {
                        json_response(['ok' => false, 'message' => 'Ingresso o uscita non validi (1-8).'], 422);
                    }
                    $input  = (int) $input;
                    $output = (int) $output;
                    $cmd    = sprintf('MT00SW%02d%02dNT', $input, $output);
                    $result = send_tesmart_command($host, $port, $cmd);

                    json_response([
                        'ok' => $result['ok'],
                        'message' => $result['ok']
                            ? "Uscita {$output} instradata su ingresso {$input}."
                            : "Invio alla matrice TESmart non riuscito: {$result['output']}",
                        'output' => $result['output'],
                    ]);
                }

                case 'all': {
                    $input = $body['input'] ?? null;
                    if (!$isPort($input)) {
                        json_response(['ok' => false, 'message' => 'Ingresso non valido (1-8).'], 422);
                    }
                    $input  = (int) $input;
                    $cmd    = sprintf('MT00SW%02d00NT', $input);
                    $result = send_tesmart_command($host, $port, $cmd);

                    json_response([
                        'ok' => $result['ok'],
                        'message' => $result['ok']
                            ? "Tutte le uscite instradate su ingresso {$input}."
                            : "Invio alla matrice TESmart non riuscito: {$result['output']}",
                        'output' => $result['output'],
                    ]);
                }

                case 'mirror': {
                    $result = send_tesmart_command($host, $port, 'MT00SW0000NT');

                    json_response([
                        'ok' => $result['ok'],
                        'message' => $result['ok']
                            ? 'Mappatura 1:1 (ingresso N su uscita N) inviata.'
                            : "Invio alla matrice TESmart non riuscito: {$result['output']}",
                        'output' => $result['output'],
                    ]);
                }

                case 'status': {
                    $result = send_tesmart_command($host, $port, 'MT00RD0000NT');
                    $map    = $result['ok'] ? parse_tesmart_status($result['output']) : [];

                    json_response([
                        'ok' => $result['ok'] && !empty($map),
                        'message' => ($result['ok'] && !empty($map))
                            ? 'Stato letto dalla matrice TESmart.'
                            : "Lettura stato non riuscita: {$result['output']}",
                        'output' => $result['output'],
                        'map' => $map,
                    ]);
                }

                default:
                    json_response(['ok' => false, 'message' => 'Sotto-comando matrice non riconosciuto.'], 422);
            }
        }

        // === TEST GIORNALISTI (rimovibile) ---------------------------------
        // Pagina di test per le anteprime stampa (test_giornalisti.php):
        // pubblica a mano lo stesso comando Redis Start che il percorso
        // normale invia sulla sala Future Trends (canale 'step04in'), con
        // enSubs/itSubs/visitType fissi e il Visit ID inserito a mano nella
        // pagina, indipendente dal contesto visita globale nella barra in
        // alto. Per rimuovere questa funzione: cancella questo case, il
        // file test_giornalisti.php e la voce di menu "Test giornalisti"
        // in includes/layout_top.php (blocco con lo stesso commento).
        case 'journalist_test': {
            static $journalistStepId = 'step04in';

            $visitId = trim((string) ($body['visitId'] ?? ''));
            if ($visitId === '' || !is_valid_visit_id($visitId)) {
                json_response(['ok' => false, 'message' => 'Visit ID non valido (formato UUID richiesto).'], 422);
            }

            $output = run_station_script('GestStep.sh', [
                $journalistStepId, $visitId, 'true', 'true', 'Regular', 'Start',
            ]);

            json_response([
                'ok' => true,
                'message' => "Start inviato su {$journalistStepId} (test giornalisti).",
                'output' => $output,
            ]);
        }
        // === /TEST GIORNALISTI ----------------------------------------------

        default:
            json_response(['ok' => false, 'message' => 'Azione non riconosciuta.'], 400);
    }
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Errore interno: ' . $e->getMessage()], 500);
}
