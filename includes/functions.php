<?php
/**
 * Funzioni condivise: lettura configurazione, esecuzione script,
 * risposta JSON. Tenute deliberatamente piccole e senza dipendenze
 * esterne (nessun framework: il progetto resta facile da ispezionare
 * e da far girare su un semplice Apache/Nginx + PHP-FPM).
 */

define('APP_ROOT', dirname(__DIR__));

function stations(): array
{
    static $stations = null;
    if ($stations === null) {
        $stations = require APP_ROOT . '/config/stations.php';
    }
    return $stations;
}

function station(string $key): ?array
{
    $all = stations();
    return $all[$key] ?? null;
}

/** Escaping breve per l'output HTML. */
function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * URL di un asset statico (assets/css/style.css, assets/js/app.js, ...) con
 * in coda `?v=<data modifica file>`: forza il browser a scaricare la nuova
 * versione a ogni deploy invece di continuare a servire quella in cache
 * (senza, il browser può riusare la cache euristica su richieste ripetute
 * dello stesso URL e far girare pagine/JS non allineati al codice server).
 */
function asset_url(string $relativePath): string
{
    $full = APP_ROOT . '/' . $relativePath;
    $v = is_file($full) ? filemtime($full) : time();
    return $relativePath . '?v=' . $v;
}

/** Interrompe l'esecuzione restituendo una risposta JSON. */
function json_response(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Legge il body JSON della richiesta corrente. */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Esegue in modo sicuro uno degli script di sala in scripts/.
 * $args viene passato posizionalmente e ogni valore è sempre
 * quotato con escapeshellarg, esattamente come nella console
 * originale: qui aggiungiamo solo la whitelist dei nomi di script
 * ammessi, per evitare che $script possa mai arrivare da input utente.
 */
function run_station_script(string $script, array $args): string
{
    static $allowed = [
        'GestStep.sh', 'GestSubStep.sh',
    ];
    if (!in_array($script, $allowed, true)) {
        throw new InvalidArgumentException("Script non ammesso: $script");
    }

    $path = APP_ROOT . '/scripts/' . $script;
    if (!is_file($path)) {
        throw new RuntimeException("Script mancante: $script");
    }

    $cmd = escapeshellarg($path);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg((string) $arg);
    }

    return (string) shell_exec($cmd . ' 2>&1');
}

/**
 * Invia un messaggio UDP a una lista di IP sulla stessa porta.
 * Ritorna una mappa [ip => esito invio] usata per il log dei comandi.
 */
function send_udp_message(array $targets, int $port, string $message): array
{
    $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if ($socket === false) {
        throw new RuntimeException('Impossibile creare il socket UDP: ' . socket_strerror(socket_last_error()));
    }
    $sent = [];
    foreach ($targets as $ip) {
        $ok = @socket_sendto($socket, $message, strlen($message), 0, $ip, $port);
        $sent[$ip] = $ok !== false;
    }
    socket_close($socket);
    return $sent;
}

/** Configurazione dispositivi BrightSign (config/brightsign.php). */
function brightsign_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/brightsign.php';
    }
    return $config;
}

/** Elenco dispositivi BrightSign gestibili dal pannello dashboard. */
function brightsign_devices(): array
{
    return brightsign_config()['devices'] ?? [];
}

/** Porta UDP su cui i player BrightSign ascoltano i comandi. */
function brightsign_port(): int
{
    return (int) (brightsign_config()['port'] ?? 5000);
}

/** Comandi di riproduzione BrightSign disponibili (pulsanti + scheduler). */
function brightsign_commands(): array
{
    return brightsign_config()['commands'] ?? [];
}

/** Configurazione processore audio Yamaha (config/yamaha.php). */
function yamaha_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/yamaha.php';
    }
    return $config;
}

/** IP/porta TCP del processore audio Yamaha. */
function yamaha_host(): string
{
    return (string) (yamaha_config()['host'] ?? '');
}

function yamaha_port(): int
{
    return (int) (yamaha_config()['port'] ?? 49280);
}

/** Scenari audio Yamaha disponibili (pulsanti di config/yamaha.php). */
function yamaha_scenes(): array
{
    return yamaha_config()['scenes'] ?? [];
}

/**
 * Richiama uno scenario sul processore audio Yamaha via TCP: stessa
 * sequenza spedita finora a mano da riga di comando con
 * `(printf 'devstatus runmode\n'; sleep 1; printf 'ssrecall N\n'; sleep 1) | nc host port`,
 * riprodotta qui con un socket PHP invece di dipendere dal binario `nc`
 * sul server. Ritorna ['ok' => bool, 'output' => string] per il log dei
 * comandi in pagina.
 */
function send_yamaha_scene(string $host, int $port, int $recall): array
{
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, 4);
    if ($socket === false) {
        return ['ok' => false, 'output' => "Connessione a {$host}:{$port} non riuscita: {$errstr} ({$errno})"];
    }

    $readAvailable = function () use ($socket): string {
        stream_set_blocking($socket, false);
        $chunk = @fread($socket, 4096);
        stream_set_blocking($socket, true);
        return $chunk !== false ? $chunk : '';
    };

    $output = '';
    fwrite($socket, "devstatus runmode\n");
    sleep(1);
    $output .= $readAvailable();
    fwrite($socket, "ssrecall {$recall}\n");
    sleep(1);
    $output .= $readAvailable();

    fclose($socket);

    return ['ok' => true, 'output' => trim($output)];
}

/** Configurazione mixer Soundcraft Ui (config/soundcraft.php). */
function soundcraft_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/soundcraft.php';
    }
    return $config;
}

function soundcraft_host(): string
{
    return (string) (soundcraft_config()['host'] ?? '');
}

function soundcraft_port(): int
{
    return (int) (soundcraft_config()['port'] ?? 80);
}

/** Nome dello Show (contenitore di snapshot) su cui operare. */
function soundcraft_show(): string
{
    return (string) (soundcraft_config()['show'] ?? '');
}

/** Snapshot richiamabili dal pannello "Gestione Audio Jobs". */
function soundcraft_snapshots(): array
{
    return soundcraft_config()['snapshots'] ?? [];
}

/**
 * Incapsula $payload in un frame WebSocket di testo mascherato (RFC 6455):
 * i frame client->server DEVONO essere mascherati con una chiave casuale di
 * 4 byte, XORata ciclicamente sul payload.
 */
function build_websocket_frame(string $payload): string
{
    $length = strlen($payload);
    $maskKey = random_bytes(4);

    $frame = chr(0x81); // FIN=1, opcode=0x1 (testo)

    if ($length <= 125) {
        $frame .= chr($length | 0x80);
    } elseif ($length <= 65535) {
        $frame .= chr(126 | 0x80) . pack('n', $length);
    } else {
        $frame .= chr(127 | 0x80) . pack('J', $length);
    }

    $frame .= $maskKey;
    for ($i = 0; $i < $length; $i++) {
        $frame .= $payload[$i] ^ $maskKey[$i % 4];
    }

    return $frame;
}

/**
 * Apre una connessione WebSocket "a mano" (handshake HTTP Upgrade su socket
 * TCP grezzo, senza estensioni PHP oltre a quelle già in uso) verso un
 * mixer Soundcraft Ui, spedisce $command con il framing Socket.IO 0.9 che
 * usa il firmware del mixer (prefisso `3:::`, vedi la libreria open source
 * fmalcher/soundcraft-ui) e chiude la connessione. Non fa nessun handshake
 * applicativo aggiuntivo (SERIAL/USERTIME/...): il firmware accetta comandi
 * anche da un client "muto" che si limita a inviarli, come i comandi UDP
 * one-shot già usati per BrightSign/banner. Ritorna ['ok' => bool,
 * 'output' => string] per il log dei comandi in pagina.
 */
function send_soundcraft_command(string $host, int $port, string $command): array
{
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, 4);
    if ($socket === false) {
        return ['ok' => false, 'output' => "Connessione a {$host}:{$port} non riuscita: {$errstr} ({$errno})"];
    }
    stream_set_timeout($socket, 4);

    $key = base64_encode(random_bytes(16));
    $request = "GET / HTTP/1.1\r\n"
        . "Host: {$host}:{$port}\r\n"
        . "Upgrade: websocket\r\n"
        . "Connection: Upgrade\r\n"
        . "Sec-WebSocket-Key: {$key}\r\n"
        . "Sec-WebSocket-Version: 13\r\n"
        . "\r\n";
    fwrite($socket, $request);

    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 1024);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if ($line === "\r\n") {
            break;
        }
    }

    if (stripos($response, ' 101 ') === false || stripos($response, 'Upgrade') === false) {
        fclose($socket);
        return ['ok' => false, 'output' => 'Handshake WebSocket non riuscito: ' . trim($response)];
    }

    fwrite($socket, build_websocket_frame("3:::{$command}"));

    // Lettura best-effort dell'eventuale risposta immediata del mixer, solo
    // per il log: nessuna attesa lunga, il comando è già stato spedito.
    stream_set_blocking($socket, false);
    usleep(300000);
    $output = (string) @fread($socket, 4096);
    stream_set_blocking($socket, true);

    fclose($socket);

    return ['ok' => true, 'output' => trim($output)];
}

/**
 * Richiama uno snapshot all'interno di uno show sul mixer Soundcraft Ui
 * (comando `LOADSNAPSHOT^<show>^<snapshot>`, vedi send_soundcraft_command).
 */
function send_soundcraft_snapshot(string $host, int $port, string $show, string $snapshot): array
{
    return send_soundcraft_command($host, $port, "LOADSNAPSHOT^{$show}^{$snapshot}");
}

/** Configurazione PC SAM (config/sam.php). */
function sam_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/sam.php';
    }
    return $config;
}

/** Comandi Audio SAM disponibili (pulsanti + scheduler). */
function sam_commands(): array
{
    return sam_config()['commands'] ?? [];
}

/**
 * Esegue `nircmd.exe mutesysvolume <mute>` sul PC SAM via WMI (Impacket
 * wmiexec.py), stesso comando finora lanciato a mano da riga di comando
 * (vedi config/sam.php). `timeout` limita l'attesa se l'host Windows non
 * risponde, per non bloccare indefinitamente la richiesta web o il cron.
 * $mute non è mai input utente diretto: arriva sempre da 'mute' di uno dei
 * comandi whitelisted in config/sam.php (vedi action 'sam' di api.php),
 * ma viene comunque passato via escapeshellarg come ogni altro argomento
 * di shell in questo file.
 */
function run_sam_command(int $mute): string
{
    $config  = sam_config();
    $python  = (string) ($config['python'] ?? '/usr/bin/python3');
    $wmiexec = (string) ($config['wmiexec'] ?? '/usr/local/bin/wmiexec.py');
    if (!is_file($wmiexec)) {
        throw new RuntimeException("wmiexec.py non trovato: $wmiexec");
    }

    $target    = "{$config['username']}:{$config['password']}@{$config['host']}";
    $remoteCmd = "{$config['nircmd_path']} mutesysvolume {$mute}";

    $cmd = 'timeout 20 '
        . escapeshellarg($python) . ' '
        . escapeshellarg($wmiexec) . ' '
        . escapeshellarg($target) . ' '
        . escapeshellarg($remoteCmd);

    return (string) shell_exec($cmd . ' 2>&1');
}

/** Configurazione della matrice HDMI TESmart 8x8 (config/tesmart.php). */
function tesmart_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/tesmart.php';
    }
    return $config;
}

function tesmart_host(): string
{
    return (string) (tesmart_config()['host'] ?? '');
}

function tesmart_port(): int
{
    return (int) (tesmart_config()['port'] ?? 5000);
}

/** Etichette ingressi/uscite mostrate in tesmart_test.php (config/tesmart.php). */
function tesmart_labels(): array
{
    return tesmart_config()['labels'] ?? ['inputs' => [], 'outputs' => []];
}

/**
 * Ingressi usati come "scenario" nella pagina "Scenari Schermo Windoor"
 * (pulsanti Museo/Resolume Arena e relativa schedulazione): sottoinsieme di
 * tesmart_labels()['inputs'] filtrato secondo 'scenarios' in
 * config/tesmart.php. Ritorna [numero ingresso => etichetta].
 */
function tesmart_scenarios(): array
{
    $inputs = tesmart_labels()['inputs'] ?? [];
    $keys   = tesmart_config()['scenarios'] ?? array_keys($inputs);

    $scenarios = [];
    foreach ($keys as $key) {
        if (array_key_exists($key, $inputs)) {
            $scenarios[$key] = $inputs[$key];
        }
    }
    return $scenarios;
}

/**
 * Invia un comando ASCII alla matrice HDMI TESmart 8x8 via TCP (porta 5000
 * di default, nessun login richiesto), stesso protocollo "MT00...NT"
 * documentato da TESmart (8X8 HDMI matrix communication protocol.pdf) e
 * usato dal modulo Companion open source
 * bitfocus/companion-module-tesmart-hdmimatrix. Ritorna ['ok' => bool,
 * 'output' => string] come le altre send_*_command di questo file.
 */
function send_tesmart_command(string $host, int $port, string $cmd): array
{
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, 4);
    if ($socket === false) {
        return ['ok' => false, 'output' => "Connessione a {$host}:{$port} non riuscita: {$errstr} ({$errno})"];
    }
    stream_set_timeout($socket, 4);
    fwrite($socket, $cmd . "\r\n");

    stream_set_blocking($socket, false);
    usleep(300000);
    $output = (string) @fread($socket, 4096);
    stream_set_blocking($socket, true);

    fclose($socket);

    return ['ok' => true, 'output' => trim($output)];
}

/**
 * Interpreta la risposta di stato della matrice ("LINK:O1I1;O2I2;...;END",
 * una coppia uscita/ingresso per ogni porta) in una mappa [uscita =>
 * ingresso]. Ritorna un array vuoto se il formato non è riconosciuto (es.
 * matrice non raggiungibile o risposta troncata).
 */
function parse_tesmart_status(string $raw): array
{
    $map = [];
    if (!preg_match_all('/O(\d+)I(\d+)/i', $raw, $matches, PREG_SET_ORDER)) {
        return $map;
    }
    foreach ($matches as $m) {
        $map[(int) $m[1]] = (int) $m[2];
    }
    return $map;
}

/** Configurazione scenari master (config/master.php). */
function master_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/master.php';
    }
    return $config;
}

/** Scenari del pannello "Scenari Audio" (pulsanti Evento/Muto/Museo). */
function master_scenarios(): array
{
    return master_config()['scenarios'] ?? [];
}

/**
 * Esegue uno scenario master: un comando per ciascuno dei tre dispositivi
 * audio (Jobs/Soundcraft, SAM, Yamaha), risolti sempre contro i rispettivi
 * config/*.php (mai fidandosi di valori arrivati dal client). Prosegue con
 * gli altri dispositivi anche se uno fallisce, così un problema su un
 * dispositivo non impedisce di comandare gli altri due: ritorna il
 * dettaglio per dispositivo, così il chiamante può riportare esattamente
 * cosa è riuscito e cosa no invece di un generico "errore".
 *
 * @return array<string, array{ok: bool, label: string, output: string}>
 */
function run_master_scenario(array $scenario): array
{
    $results = [];

    $snapshots = array_column(soundcraft_snapshots(), null, 'key');
    $snapshot  = $snapshots[$scenario['soundcraft_snapshot'] ?? ''] ?? null;
    if ($snapshot) {
        $r = send_soundcraft_snapshot(soundcraft_host(), soundcraft_port(), soundcraft_show(), $snapshot['name']);
        $results['soundcraft'] = ['ok' => $r['ok'], 'label' => "Jobs: {$snapshot['label']}", 'output' => $r['output']];
    } else {
        $results['soundcraft'] = ['ok' => false, 'label' => 'Jobs', 'output' => 'Snapshot non configurato per questo scenario.'];
    }

    $samCommands = array_column(sam_commands(), null, 'key');
    $samCmd      = $samCommands[$scenario['sam_command'] ?? ''] ?? null;
    if ($samCmd) {
        $output = run_sam_command((int) $samCmd['mute']);
        $results['sam'] = ['ok' => true, 'label' => "SAM: {$samCmd['label']}", 'output' => $output];
    } else {
        $results['sam'] = ['ok' => false, 'label' => 'SAM', 'output' => 'Comando non configurato per questo scenario.'];
    }

    $yamahaScenes = array_column(yamaha_scenes(), null, 'key');
    $yamahaScene  = $yamahaScenes[$scenario['yamaha_scene'] ?? ''] ?? null;
    if ($yamahaScene) {
        $r = send_yamaha_scene(yamaha_host(), yamaha_port(), (int) $yamahaScene['recall']);
        $results['yamaha'] = ['ok' => $r['ok'], 'label' => "Yamaha: {$yamahaScene['label']}", 'output' => $r['output']];
    } else {
        $results['yamaha'] = ['ok' => false, 'label' => 'Yamaha', 'output' => 'Scenario non configurato per questo scenario.'];
    }

    return $results;
}

/**
 * Valida ripetizione/data/ora di un nuovo job programmato (stessa regola
 * per ogni tipo di schedulazione): interrompe con una risposta di errore
 * se non validi. Estratta da 'schedule_add' perché riusata anche da
 * 'schedule_add_master', che crea più job in un colpo solo.
 *
 * @return array{0: string, 1: ?string, 2: string} [repeat, date, time]
 */
function validate_schedule_timing(array $body): array
{
    $repeat = ($body['repeat'] ?? '') === 'daily' ? 'daily' : 'once';
    $time   = (string) ($body['time'] ?? '');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
        json_response(['ok' => false, 'message' => 'Orario non valido (formato HH:MM).'], 422);
    }

    $date = null;
    if ($repeat === 'once') {
        $date = (string) ($body['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            json_response(['ok' => false, 'message' => 'Data non valida (formato AAAA-MM-GG).'], 422);
        }
        $runAt = DateTimeImmutable::createFromFormat('Y-m-d H:i', "$date $time");
        if (!$runAt || $runAt < new DateTimeImmutable('now')) {
            json_response(['ok' => false, 'message' => 'La data/ora deve essere nel futuro.'], 422);
        }
    }

    return [$repeat, $date, $time];
}

/** Scheletro comune di un job programmato: il chiamante aggiunge i campi specifici del tipo. */
function new_schedule_job(string $type, string $repeat, ?string $date, string $time): array
{
    return [
        'id'          => bin2hex(random_bytes(6)),
        'type'        => $type,
        'repeat'      => $repeat,
        'date'        => $date,
        'time'        => $time,
        'enabled'     => true,
        'missed'      => false,
        'createdAt'   => date('c'),
        'lastRunAt'   => null,
        'lastRunDate' => null,
    ];
}

/** URL base del servizio WallConfig (config/wallconfig.php). */
function wallconfig_base_url(): string
{
    static $config = null;
    if ($config === null) {
        $config = require APP_ROOT . '/config/wallconfig.php';
    }
    return rtrim((string) ($config['base_url'] ?? ''), '/');
}

/**
 * Interroga l'API JSON di WallConfig (GET, timeout breve, errori
 * ignorati): usata per leggere sezioni/setup da scheduler.php senza
 * duplicare il parser di WallConfig.txt in PHP. Ritorna null se il
 * servizio non risponde (spento, non ancora avviato, rete diversa) —
 * il chiamante deve mostrare un avviso invece di un errore fatale: è un
 * servizio volutamente separato, può non essere sempre raggiungibile.
 */
function wallconfig_api_get(string $path): ?array
{
    $url = wallconfig_base_url() . $path;
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

/** Percorso del file di stato dello scheduler banner Future Trends. */
function schedule_file(): string
{
    return APP_ROOT . '/data/schedule.json';
}

/**
 * Esegue $mutator con lock esclusivo su data/schedule.json: gli passa
 * l'array corrente dei job programmati e, se $mutator restituisce un
 * array (invece di null), lo scrive su disco prima di rilasciare il
 * lock. Usata sia da api.php (aggiunta/cancellazione/toggle) sia dallo
 * script cron scripts/run_scheduler.php, così le due parti non possono
 * mai pestarsi i piedi o corrompere il file con scritture concorrenti.
 */
function with_schedule_lock(callable $mutator): array
{
    $path = schedule_file();
    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0775, true);
    }
    $fh = fopen($path, 'c+');
    if ($fh === false) {
        throw new RuntimeException('Impossibile aprire data/schedule.json (permessi di scrittura mancanti?).');
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            throw new RuntimeException('Impossibile bloccare data/schedule.json.');
        }
        $raw  = stream_get_contents($fh);
        $jobs = json_decode((string) $raw, true);
        if (!is_array($jobs)) {
            $jobs = [];
        }

        $result = $mutator($jobs);

        if (is_array($result)) {
            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, json_encode(array_values($result), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            fflush($fh);
            $jobs = $result;
        }

        return $jobs;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/**
 * Stato di un job programmato in questo istante ('due' = va eseguito ora,
 * 'waiting' = non ancora, 'missed' = 'once' con data/ora passata senza
 * mai essere stato eseguito). Un job 'missed' viene disattivato qui come
 * side effect (via $job passato per riferimento): non va MAI spedito in
 * ritardo, meglio segnarlo come non inviato che sorprendere con un
 * comando vecchio. Stessa regola per qualunque tipo di job (banner
 * Future Trends, BrightSign, ...): un solo posto che la implementa,
 * usato da scripts/run_scheduler.php per ogni job in coda.
 */
function schedule_job_status(array &$job, DateTimeImmutable $now, string $today, int $nowMin): string
{
    [$h, $m] = array_map('intval', explode(':', (string) ($job['time'] ?? '')) + [0, 0]);
    $dueMin = $h * 60 + $m;

    if (($job['repeat'] ?? 'once') === 'once') {
        if ((string) ($job['date'] ?? '') < $today) {
            $job['enabled'] = false;
            $job['missed']  = true;
            return 'missed';
        }
        if (($job['date'] ?? '') !== $today || $nowMin < $dueMin) {
            return 'waiting';
        }
        return 'due';
    }

    // daily
    if ($nowMin < $dueMin || ($job['lastRunDate'] ?? null) === $today) {
        return 'waiting';
    }
    return 'due';
}

/**
 * Etichetta e colore badge da mostrare per un job programmato, calcolati
 * al volo dalla configurazione corrente (mai salvati nel job stesso: se
 * un'etichetta cambia in config/*.php, lo scheduler la mostra aggiornata
 * subito, senza bisogno di toccare i job già in coda). Ritorna
 * [label, badge]. 'type' mancante = job creato prima dell'introduzione
 * dei tipi multipli: trattato come 'ftbanner' per compatibilità con
 * data/schedule.json già esistenti.
 */
function schedule_job_display(array $job): array
{
    $type = $job['type'] ?? 'ftbanner';

    if ($type === 'brightsign') {
        $devices  = array_column(brightsign_devices(), 'label', 'ip');
        $commands = array_column(brightsign_commands(), null, 'key');
        $cmd      = $commands[$job['command'] ?? ''] ?? null;
        $device   = $devices[$job['ip'] ?? ''] ?? ($job['ip'] ?? '?');
        $label    = ($cmd['label'] ?? ($job['command'] ?? '?')) . ' — ' . $device;
        return [$label, $cmd['badge'] ?? 'accent'];
    }

    if ($type === 'yamaha') {
        $scenes = array_column(yamaha_scenes(), null, 'key');
        $scene  = $scenes[$job['scene'] ?? ''] ?? null;
        return [$scene['label'] ?? ($job['scene'] ?? '?'), $scene['badge'] ?? 'accent'];
    }

    if ($type === 'soundcraft_snapshot') {
        $snapshots = array_column(soundcraft_snapshots(), null, 'key');
        $snapshot  = $snapshots[$job['snapshot'] ?? ''] ?? null;
        return [$snapshot['label'] ?? ($job['snapshot'] ?? '?'), $snapshot['badge'] ?? 'accent'];
    }

    if ($type === 'sam') {
        $commands = array_column(sam_commands(), null, 'key');
        $cmd      = $commands[$job['command'] ?? ''] ?? null;
        return [$cmd['label'] ?? ($job['command'] ?? '?'), $cmd['badge'] ?? 'accent'];
    }

    if ($type === 'tesmart') {
        $scenarios = tesmart_scenarios();
        $input     = (int) ($job['input'] ?? 0);
        $label     = $scenarios[$input] ?? (string) $input;
        return ['Tutte → ' . $label, 'accent'];
    }

    $commands = array_column(station('square')['bannerCommands'] ?? [], null, 'key');
    $cmd      = $commands[$job['mode'] ?? ''] ?? null;
    return [$cmd['label'] ?? ($job['mode'] ?? '?'), $cmd['badge'] ?? 'accent'];
}

/** Valida un Visit ID: UUID standard oppure l'ID demo di default. */
function is_valid_visit_id(string $id): bool
{
    return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $id);
}

function default_visit_id(): string
{
    return '4a60049d-de84-49de-af21-c09ebe77ede0';
}
