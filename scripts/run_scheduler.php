#!/usr/bin/env php
<?php
/**
 * Cron dello scheduler dei comandi programmati (banner Future Trends,
 * BrightSign, ...).
 *
 * Legge data/schedule.json e spedisce (via UDP, come le action 'ftbanner'
 * e 'brightsign' di api.php) i comandi la cui ora programmata è arrivata:
 * una tantum ('once', un solo invio poi disattivato) o ogni giorno alla
 * stessa ora ('daily'). Non richiede nessun servizio in background: va
 * lanciato da cron ogni minuto, come utente www-data (stesso utente che
 * esegue già index.php/api.php via Apache), così legge e scrive
 * data/schedule.json con gli stessi permessi del sito.
 *
 * Esempio crontab (crontab -u www-data -e):
 *   * * * * * php /var/www/STEPconsolle/scripts/run_scheduler.php >> /var/www/STEPconsolle/data/scheduler.log 2>&1
 *
 * Ogni job porta un campo 'type' ('ftbanner', 'brightsign', 'yamaha',
 * 'soundcraft_snapshot', 'sam' o 'tesmart', assente = 'ftbanner' per
 * compatibilità con le code create prima di questo tipo) che dice quale coppia di
 * funzioni usare per spedirlo: stessa coda, stesso ciclo, dispatch
 * diverso solo nel punto di invio qui sotto.
 *
 * Un job 'once' la cui data è passata senza essere mai stato eseguito
 * (es. cron fermo per giorni) viene marcato "scaduto" e disattivato
 * invece di essere spedito in ritardo: meglio non spedire un comando
 * fuori contesto che spedirne uno vecchio a sorpresa (vedi
 * schedule_job_status() in includes/functions.php, condivisa da tutti i tipi).
 */

require __DIR__ . '/../includes/functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo da riga di comando (cron).');
}

// Stessa lista di comandi banner usata dai pulsanti e dallo scheduler in
// station.php e dalla action 'ftbanner' di api.php: un job 'ftbanner' qui
// dentro ha 'mode' uguale alla 'key' di uno di questi comandi (Nero/Default/Evento...).
$ftBannerCommands = station('square')['bannerCommands'];

// Stessi dispositivi/comandi usati dal pannello BrightSign e dalla action
// 'brightsign' di api.php.
$brightsignDevices  = array_column(brightsign_devices(), 'label', 'ip');
$brightsignPort     = brightsign_port();
$brightsignCommands = array_column(brightsign_commands(), 'key');

// Stessi scenari usati dal pannello Gestione Audio Yamaha e dalla action
// 'yamaha' di api.php.
$yamahaScenes = array_column(yamaha_scenes(), null, 'key');
$yamahaHost   = yamaha_host();
$yamahaPort   = yamaha_port();

// Stessi snapshot usati dal pannello Gestione Audio Jobs e dalla action
// 'soundcraft_snapshot' di api.php.
$soundcraftSnapshots = array_column(soundcraft_snapshots(), null, 'key');
$soundcraftHost      = soundcraft_host();
$soundcraftPort      = soundcraft_port();
$soundcraftShow      = soundcraft_show();

// Stessi comandi usati dal pannello Gestione Audio SAM e dalla action 'sam'
// di api.php.
$samCommands = array_column(sam_commands(), null, 'key');

// Stessi scenari usati dal pannello "Scenari Schermo Windoor" (tesmart_test.php)
// e dalla action 'tesmart'/'all' di api.php.
$tesmartScenarios = tesmart_scenarios();
$tesmartHost      = tesmart_host();
$tesmartPort      = tesmart_port();

$now    = new DateTimeImmutable('now');
$today  = $now->format('Y-m-d');
$nowMin = ((int) $now->format('H')) * 60 + (int) $now->format('i');

with_schedule_lock(function (array $jobs) use (
    $now, $today, $nowMin, $ftBannerCommands, $brightsignDevices, $brightsignPort, $brightsignCommands,
    $yamahaScenes, $yamahaHost, $yamahaPort, $soundcraftSnapshots, $soundcraftHost, $soundcraftPort, $soundcraftShow,
    $samCommands, $tesmartScenarios, $tesmartHost, $tesmartPort
) {
    $changed = false;

    foreach ($jobs as &$job) {
        if (empty($job['enabled'])) {
            continue;
        }

        $status = schedule_job_status($job, $now, $today, $nowMin);
        if ($status === 'missed') {
            printf("[%s] job %s: scaduto senza essere stato inviato, disattivato.\n", $now->format('c'), $job['id']);
            $changed = true;
            continue;
        }
        if ($status !== 'due') {
            continue;
        }

        $type = $job['type'] ?? 'ftbanner';

        if ($type === 'brightsign') {
            $ip      = $job['ip'] ?? '';
            $command = $job['command'] ?? '';

            if (!array_key_exists($ip, $brightsignDevices) || !in_array($command, $brightsignCommands, true)) {
                // Dispositivo/comando rimosso da config/brightsign.php dopo che il
                // job era stato programmato: meglio saltarlo che spedire un UDP a caso.
                printf("[%s] job %s: dispositivo/comando BrightSign non piu' valido, salto.\n", $now->format('c'), $job['id']);
                continue;
            }

            $sent = send_udp_message([$ip], $brightsignPort, $command);
            $ok   = $sent[$ip] ?? false;

            printf(
                "[%s] job %s (%s/brightsign) -> %s a %s: %s%s\n",
                $now->format('c'), $job['id'], $job['repeat'], $command, $ip,
                json_encode($sent), $ok ? '' : ' (ERRORI DI INVIO)'
            );
        } elseif ($type === 'yamaha') {
            $sceneKey = $job['scene'] ?? '';
            $scene    = $yamahaScenes[$sceneKey] ?? null;

            if (!$scene) {
                // Scenario rimosso/rinominato da config/yamaha.php dopo che il job
                // era stato programmato: meglio saltarlo che spedire un ssrecall a caso.
                printf("[%s] job %s: scenario audio '%s' non piu' valido, salto.\n", $now->format('c'), $job['id'], $sceneKey);
                continue;
            }

            $result = send_yamaha_scene($yamahaHost, $yamahaPort, (int) $scene['recall']);

            printf(
                "[%s] job %s (%s/yamaha) -> %s: %s%s\n",
                $now->format('c'), $job['id'], $job['repeat'], $scene['label'],
                $result['output'], $result['ok'] ? '' : ' (ERRORE DI INVIO)'
            );
        } elseif ($type === 'soundcraft_snapshot') {
            $snapshotKey = $job['snapshot'] ?? '';
            $snapshot    = $soundcraftSnapshots[$snapshotKey] ?? null;

            if (!$snapshot) {
                // Snapshot rimosso/rinominato da config/soundcraft.php dopo che il job
                // era stato programmato: meglio saltarlo che spedire un LOADSNAPSHOT a caso.
                printf("[%s] job %s: snapshot '%s' non piu' valido, salto.\n", $now->format('c'), $job['id'], $snapshotKey);
                continue;
            }

            $result = send_soundcraft_snapshot($soundcraftHost, $soundcraftPort, $soundcraftShow, $snapshot['name']);

            printf(
                "[%s] job %s (%s/soundcraft) -> %s: %s%s\n",
                $now->format('c'), $job['id'], $job['repeat'], $snapshot['label'],
                $result['output'], $result['ok'] ? '' : ' (ERRORE DI INVIO)'
            );
        } elseif ($type === 'sam') {
            $cmdKey = $job['command'] ?? '';
            $cmd    = $samCommands[$cmdKey] ?? null;

            if (!$cmd) {
                // Comando rimosso/rinominato da config/sam.php dopo che il job era
                // stato programmato: meglio saltarlo che eseguire mutesysvolume a caso.
                printf("[%s] job %s: comando Audio SAM '%s' non piu' valido, salto.\n", $now->format('c'), $job['id'], $cmdKey);
                continue;
            }

            $output = run_sam_command((int) $cmd['mute']);

            printf(
                "[%s] job %s (%s/sam) -> %s: %s\n",
                $now->format('c'), $job['id'], $job['repeat'], $cmd['label'], trim($output)
            );
        } elseif ($type === 'tesmart') {
            $input = (int) ($job['input'] ?? 0);
            $label = $tesmartScenarios[$input] ?? null;

            if ($label === null) {
                // Scenario rimosso/rinominato da config/tesmart.php dopo che il job
                // era stato programmato: meglio saltarlo che spedire un MT00SW a caso.
                printf("[%s] job %s: scenario Schermo Windoor '%s' non piu' valido, salto.\n", $now->format('c'), $job['id'], $input);
                continue;
            }

            $cmd    = sprintf('MT00SW%02d00NT', $input);
            $result = send_tesmart_command($tesmartHost, $tesmartPort, $cmd);

            printf(
                "[%s] job %s (%s/tesmart) -> Tutte le uscite su %s: %s%s\n",
                $now->format('c'), $job['id'], $job['repeat'], $label,
                $result['output'], $result['ok'] ? '' : ' (ERRORE DI INVIO)'
            );
        } else {
            $cmd = null;
            foreach ($ftBannerCommands as $candidate) {
                if ($candidate['key'] === $job['mode']) {
                    $cmd = $candidate;
                    break;
                }
            }
            if (!$cmd) {
                // Comando rimosso/rinominato in config/stations.php dopo che il job
                // era stato programmato: meglio saltarlo che spedire un UDP a caso.
                printf("[%s] job %s: comando '%s' non piu' valido, salto.\n", $now->format('c'), $job['id'], $job['mode']);
                continue;
            }
            $sent = send_udp_message($cmd['targets'], (int) $cmd['port'], $cmd['message']);
            $ok   = !in_array(false, $sent, true);

            printf(
                "[%s] job %s (%s/%s) -> %s%s\n",
                $now->format('c'), $job['id'], $job['repeat'], $job['mode'],
                json_encode($sent), $ok ? '' : ' (ERRORI DI INVIO)'
            );
        }

        $job['lastRunAt'] = $now->format('c');
        if ($job['repeat'] === 'once') {
            $job['enabled'] = false;
        } else {
            $job['lastRunDate'] = $today;
        }
        $changed = true;
    }
    unset($job);

    return $changed ? $jobs : null;
});
