<?php
/**
 * PC "SAM" gestibile dal pannello "Gestione Audio SAM": non parla un
 * protocollo di rete diretto come gli altri dispositivi audio, ma viene
 * comandato eseguendo `nircmd.exe` da remoto via WMI (Impacket wmiexec.py),
 * lo stesso comando finora lanciato a mano da riga di comando:
 *
 *   python3 examples/wmiexec.py Fastweb:[PASSWORD]@192.168.11.209 \
 *     "C:\Tools\nircmd.exe mutesysvolume 0"
 *
 * Le credenziali sono in chiaro sulla riga di comando lanciata da
 * run_sam_command() (limite dello strumento, non evitabile senza cambiare
 * meccanismo): restano visibili a chiunque possa leggere la process list
 * del server nel breve istante di esecuzione. Vedi il README, sezione
 * "Gestione Audio SAM".
 *
 * La password vera vive in config/sam.local.php, non versionato (vedi
 * .gitignore): questo file resta tracciabile su git con un placeholder,
 * config/sam.local.php resta solo su questo server.
 */

$local = is_file(__DIR__ . '/sam.local.php') ? require __DIR__ . '/sam.local.php' : [];

return [
    'host'        => '192.168.11.209',
    'username'    => 'Fastweb',
    'password'    => $local['password'] ?? '[PASSWORD]',
    'python'      => '/usr/bin/python3',
    'wmiexec'     => '/usr/local/bin/wmiexec.py',
    'nircmd_path' => 'C:\\Tools\\nircmd.exe',
    'commands' => [
        ['key' => 'unmute', 'label' => 'Attiva Audio SAM',    'style' => 'btn-start', 'badge' => 'start', 'mute' => 0],
        ['key' => 'mute',   'label' => 'Disattiva Audio SAM', 'style' => 'btn-stop',  'badge' => 'stop',  'mute' => 1],
    ],
];
