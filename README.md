# STEP Console v2

Copia moderna della console di regia `STEPconsolle`, con la stessa logica di
controllo hardware ma un frontend e un backend riorganizzati.

## Cosa è cambiato rispetto all'originale

- **Una sola fonte di verità**: `config/stations.php` elenca tutte le stazioni
  (STEPID, immagine, sotto-azioni). Sidebar, dashboard e pagina di dettaglio
  sono generate da questo array — prima ogni stazione era un file HTML
  copia-incollato a mano (Square.html, Jobs.html, Wall.html, …).
- **Un solo endpoint API** (`api.php`, JSON in/out) al posto di
  `RecoveryVisit.php`, `RecoverySubVisit.php`, `RecoveryGigante.php`,
  `RecoverySAM.php`, `FT_Black.php`, `FT_Reset.php`. Ogni richiesta è
  validata contro una whitelist server-side (stazioni, comandi, target
  ammessi) invece di fidarsi ciecamente dei campi hidden del form.
- **Frontend a fetch()**, non più `<form action="...">` che ricaricano la
  pagina: pulsanti con stato di caricamento, conferma prima dello Stop,
  notifiche toast e un log dei comandi al posto di `alert()` +
  `window.history.go(-1)`.
- **Contesto visita condiviso**: Visit ID / sottotitoli / tipo visita si
  impostano una volta nella barra in alto (persistiti in `localStorage`) e
  vengono riusati da ogni pulsante, invece di essere ripetuti come input
  hidden identici in decine di form.
- **Comandi del player Gigante Bracco proxati dal server** (`action:
  projector` in `api.php`) invece di aprire una scheda del browser verso
  `192.168.11.230`: stesso effetto, ma con conferma di riuscita/errore.
- Gli script di sala in `scripts/` (`GestStep.sh`, `GestSubStep.sh`) sono
  **invariati**: stesso `redis-cli publish` verso lo stesso host, stessi
  parametri. Non cambia nulla lato hardware/regia — cambia solo come ci si
  arriva.
- Rimossi: pulsante Stop sulla sala Theater, sotto-azioni di Jobs, e le
  pagine di emergenza "Sblocco Gigante" / "Gestione SAM" (con i relativi
  script `GestGigante.sh`/`GestSAM.sh` e azioni API).
- **Novità senza equivalente nell'originale**: uno scheduler per programmare
  l'invio futuro di comandi (una tantum o ogni giorno alla stessa ora),
  presente nella pagina Future Trends (banner UDP), nella pagina BrightSign
  (riproduzione UDP), in WallConfig (cambi di stato monitor), nella pagina
  Gestione Audio Yamaha (richiamo scenari via TCP), nella pagina Gestione
  Audio Jobs (richiamo snapshot via WebSocket sul mixer Soundcraft) e nella
  pagina Gestione Audio SAM (mute/unmute via WMI), più una pagina di
  riepilogo unica — vedi "Schedulazioni" più sotto.
- **Nuova sezione "Gestione audio"**, con quattro pagine indipendenti:
  `audio_master.php` ("Scenari Audio"), un solo pulsante che comanda insieme
  i tre dispositivi sotto; `audio.php` per richiamare via TCP uno dei tre
  scenari preimpostati (Museo/Evento/Muto) sul processore audio Yamaha;
  `audio_jobs.php` per richiamare via WebSocket uno snapshot (Evento/Museo)
  sul mixer Soundcraft Ui24R; `audio_sam.php` per attivare/disattivare
  l'audio di sistema del PC SAM via WMI — vedi "Scenari Audio", "Gestione
  Audio Yamaha", "Gestione Audio Jobs" e "Gestione Audio SAM" più sotto.

Le pagine legacy senza link attivi nel sito originale (`Jobs1.php`…
`Jobs10.php`, `Wall1.php`/`Wall2.php`, `Gate_Start.php`/`Gate_Reset.php`,
`Holo_*`, `Magic_Start.php`/`Magic_Reset.php`, ecc. — script "orfani" mai
richiamati da nessuna pagina HTML) non sono stati riportati: la
console attuale passa sempre da `RecoveryVisit.php`/`GestStep.sh`.

## Struttura

```
config/stations.php     definizione delle stazioni
config/brightsign.php   dispositivi BrightSign (IP placeholder) e porta UDP comandi
config/yamaha.php       processore audio Yamaha (IP/porta TCP) e scenari (Museo/Evento/Muto)
config/soundcraft.php   mixer Soundcraft Ui24R (IP, show) e snapshot (Evento/Museo)
config/sam.php          PC SAM: host, credenziali, percorsi python3/wmiexec.py/nircmd.exe
config/master.php       scenari "master" (Evento/Muto/Museo): quale comando per Jobs/SAM/Yamaha
includes/functions.php  helper condivisi (escaping, esecuzione script, JSON)
includes/layout_*.php   header/footer HTML comuni (sidebar generata da config)
api.php                 endpoint unico, azioni: visit / substep / recovery /
                         projector / ftbanner / brightsign / yamaha /
                         soundcraft_snapshot / sam / audio_master /
                         schedule_add_master / schedule_list / schedule_add /
                         schedule_toggle / schedule_delete
index.php               dashboard con card di tutte le stazioni
station.php             pagina di dettaglio (?station=<key>)
recovery.php            sblocco generico (ex Console.html)
brightsign.php          gestione player BrightSign (voce menu "Dispositivi")
audio_master.php        scenari audio combinati Jobs+SAM+Yamaha (voce menu "Gestione audio")
audio.php               gestione audio Yamaha (voce menu "Gestione audio")
audio_jobs.php          gestione audio Jobs / mixer Soundcraft (voce menu "Gestione audio")
audio_sam.php           gestione audio PC SAM / WMI (voce menu "Gestione audio")
assets/css/style.css    design system (light/dark via prefers-color-scheme)
assets/js/app.js        fetch API, toast, contesto visita in localStorage
assets/js/scheduler.js  pannelli scheduler generici (banner Future Trends, BrightSign)
scripts/                script di sala originali, copiati invariati
scripts/run_scheduler.php  cron: spedisce i comandi programmati (banner e BrightSign)
data/schedule.json      coda dei comandi programmati, di entrambi i tipi (unico stato server-side)
images/                 immagini originali, copiate invariate
```

Nessun database, nessuna sessione server-side: lo stato "contesto visita"
vive solo nel browser, quindi il deploy non richiede permessi di scrittura
sul filesystem del webserver — **eccetto** per `data/schedule.json`, che è
l'unico file di stato lato server del progetto e serve solo allo scheduler
(sezione sotto).

## Schedulazioni

Due pagine hanno un pannello per **programmare** l'invio futuro di un comando
UDP (una tantum o ogni giorno alla stessa ora), invece di doverlo spedire a
mano ogni volta:

- **Future Trends** (`station.php?station=square`), sotto i pulsanti manuali
  di accensione/spegnimento banner: stesso comando UDP dell'azione `ftbanner`
  (`reset`/`black`/`evento` verso i totem del percorso).
- **BrightSign** (`brightsign.php`), sotto i pulsanti di riproduzione: stesso
  comando UDP dell'azione `brightsign` (`play`/`stop`/`prev`/`next`) verso il
  player scelto.
- **Gestione Audio Yamaha** (`audio.php`), sotto i pulsanti degli scenari:
  stesso comando TCP dell'azione `yamaha` (richiamo scena `ssrecall`) verso il
  processore audio.
- **Gestione Audio Jobs** (`audio_jobs.php`), sotto i pulsanti degli
  snapshot: stesso comando WebSocket dell'azione `soundcraft_snapshot`
  (`LOADSNAPSHOT`) verso il mixer Soundcraft.
- **Gestione Audio SAM** (`audio_sam.php`), sotto i pulsanti mute/unmute:
  stesso comando via WMI dell'azione `sam` (`nircmd.exe mutesysvolume`) verso
  il PC SAM.

Tutti e cinque condividono la stessa coda (`data/schedule.json`, un campo
`type` per pannello) e lo stesso meccanismo:

- **una tantum**, a una data/ora scelta (es. un evento fuori orario);
- **ogni giorno alla stessa ora** (es. spegni alle 20:00, riaccendi alle
  09:00) — comodo per allineare banner/player agli orari di apertura/chiusura
  senza intervento manuale.

Non c'è nessun processo in background: i comandi programmati vengono solo
**accodati** in `data/schedule.json` da `api.php`, e sono realmente spediti
da uno script CLI, `scripts/run_scheduler.php`, pensato per girare via
**cron ogni minuto**:

```
* * * * * php /var/www/STEPconsolle/scripts/run_scheduler.php >> /var/www/STEPconsolle/data/scheduler.log 2>&1
```

Installalo con l'utente che già esegue il sito (`sudo crontab -u www-data -e`),
così legge/scrive `data/schedule.json` con gli stessi permessi di `api.php`.
Se il cron non è installato i comandi restano semplicemente in coda (visibili
e cancellabili dal pannello) senza essere spediti — nessun errore silenzioso,
ma nessun invio.

Un job "una tantum" la cui data è passata senza mai essere stato eseguito
(cron fermo per giorni) viene marcato **scaduto** e disattivato invece di
essere spedito in ritardo fuori contesto.

### Cosa serve in più rispetto al resto del progetto (senza filesystem/DB)

1. **`data/` scrivibile da `www-data`** (l'unica eccezione ai permessi
   644/755 di sola lettura descritti sotto in "Deploy"):
   ```bash
   sudo chown -R www-data:www-data /var/www/STEPconsolle/data
   sudo chmod 775 /var/www/STEPconsolle/data
   sudo chmod 664 /var/www/STEPconsolle/data/schedule.json
   ```
2. **Il cron sopra**, come utente `www-data`.
3. **`data/schedule.json` non deve essere raggiungibile via HTTP.** C'è già
   un `data/.htaccess` con `Require all denied`, ma su questo host Apache ha
   `AllowOverride None` di default per `/var/www/` (vedi
   `/etc/apache2/apache2.conf`), quindi gli `.htaccess` **non vengono letti**:
   la protezione va messa nel vhost stesso. Aggiungi in
   `/etc/apache2/sites-available/STEPconsolle.conf` (o nel vhost usato per il
   deploy):
   ```apache
   <Directory "/var/www/STEPconsolle/data">
       Require all denied
   </Directory>
   ```
   poi `sudo systemctl reload apache2`. Su nginx l'equivalente è un blocco
   `location ^~ /data/ { deny all; }` nel `server {}`.

## Gestione BrightSign

Voce di menu dedicata nella sidebar ("Dispositivi" → "Gestione BrightSign",
`brightsign.php`), non sulla dashboard. La pagina permette di scegliere un
player da un menu a tendina e inviargli un comando di riproduzione via UDP,
con gli stessi pulsanti (stile/classi) usati altrove nell'app: **Primo / Precedente /
Play / Stop / Successivo / Ultimo** (`first`/`prev`/`play`/`stop`/`next`/`last`,
spediti come stringa letterale, stesso meccanismo `send_udp_message` già usato
per il banner).

I dispositivi (etichetta + IP) e la porta UDP sono definiti in
`config/brightsign.php` — gli IP inclusi sono **segnaposto**: sostituiscili con
gli indirizzi reali dei player quando saranno assegnati/collegati in rete,
nient'altro va toccato. Come per ogni altra azione di `api.php`, l'IP scelto
lato client viene sempre rivalidato contro questa lista lato server prima
dell'invio.

Sotto i pulsanti c'è anche il pannello "Schedulazioni" (stesso
meccanismo del banner Future Trends: vedi "Schedulazioni" più
sopra) per programmare uno di questi comandi a una data/ora futura, una
tantum o ogni giorno alla stessa ora.

## Scenari Schermi STEP (WallConfig)

Voce di menu nella sidebar, sezione "Dispositivi" (link diretto a
`config/wallconfig.php` → `base_url`, es. `http://192.168.11.240:8000/`, non
una pagina di questa app PHP). Cambia il "setup" mostrato sugli schermi della
sezione scelta, subito o via schedulazione — gestito dal servizio **WallConfig**,
un'app Flask separata (cartella `wallconfig/`, sua porta/processo propria: vedi
`wallconfig/README.md` per l'uso e il deploy).

**Perché la sidebar di quella pagina è una copia a mano di quella PHP**: WallConfig
gira come processo/porta separati (Flask, non Apache/PHP-FPM) apposta per non
dipendere dal resto della console — ma questo significa che non può fare un
semplice `require` di `includes/layout_top.php`. Per non far sembrare
WallConfig "un'app diversa" quando ci si arriva dal menu, `wallconfig/templates/index.html`
ricostruisce la stessa sidebar (stesso CSS, caricato cross-origin dalla console
PHP tramite `step_base_url`) come blocco HTML/Jinja duplicato a mano.

**Questo significa che ogni modifica al menu in `includes/layout_top.php`
(nuova pagina, nuova sezione, voce rinominata o rimossa) va riportata
identica anche in `wallconfig/templates/index.html`** — altrimenti chi apre
questa pagina dal menu vede un menu diverso/incompleto rispetto al resto
della console (esattamente il problema che ha portato a scrivere questa
sezione: la sidebar di WallConfig era rimasta ferma a una versione precedente,
mancavano "Percorso mostra", "Gestione audio", "Schedulazioni" come sezione
propria). Non c'è (ancora) un meccanismo automatico che tenga le due sidebar
sincronizzate: è una sincronizzazione manuale, da fare consapevolmente a ogni
modifica del menu.

## Scenari Audio (master)

Voce di menu dedicata nella sidebar, in cima a "Gestione audio"
(`audio_master.php`). Combina in un solo pulsante un comando per ciascuno dei
tre dispositivi audio descritti nelle sezioni seguenti — invece di premere tre
pulsanti su tre pagine diverse per cambiare "scena" all'intero impianto audio.

Tre scenari, definiti in `config/master.php`:

| Scenario | Jobs (Soundcraft) | SAM       | Yamaha |
|----------|--------------------|-----------|--------|
| Evento   | EVENTO             | Muto      | Evento |
| Muto     | MUSEO_1006          | Muto      | Muto   |
| Museo    | MUSEO_1006          | Attivo    | Museo  |

Premendo uno scenario, `run_master_scenario()` in `includes/functions.php`
esegue in sequenza i tre comandi già descritti nelle sezioni seguenti
(stesse funzioni `send_soundcraft_snapshot()`, `run_sam_command()`,
`send_yamaha_scene()`, stessa validazione server-side contro i rispettivi
`config/*.php`). **Se un dispositivo non risponde gli altri due vengono
comunque comandati**: la richiesta non si interrompe al primo errore,
perché un problema isolato (es. il PC SAM spento) non deve impedire di
cambiare almeno gli altri due. Il log dei comandi in pagina riporta
l'esito (✓/✗) di ciascuno dei tre.

Il pannello "Schedulazione aggregata" sotto i pulsanti **non tiene una coda
propria**: programmare uno scenario crea qui, in un colpo solo, le tre
schedulazioni singole già esistenti (tipo `soundcraft_snapshot`, `sam`,
`yamaha` in `data/schedule.json`, stessa data/ora/ripetizione), tramite
l'azione `schedule_add_master` di `api.php`. Una volta create sono job
identici a quelli creati manualmente dalle pagine dei singoli dispositivi:
compaiono — e restano sospendibili/eliminabili singolarmente — nelle pagine
Audio Jobs/SAM/Yamaha e nel riepilogo Schedulazioni, non in una lista a sé;
il cron `scripts/run_scheduler.php` non ha bisogno di sapere che erano nati
da uno scenario aggregato, li esegue come i job di quel tipo che già gestiva.

## Gestione Audio Yamaha

Voce di menu dedicata nella sidebar ("Gestione audio" → "Gestione Audio
Yamaha", `audio.php`). La pagina permette di richiamare uno dei tre scenari
preimpostati sul processore audio: **Museo / Evento / Muto**, ognuno associato
a un numero di scena (`ssrecall 1`/`2`/`3`).

A differenza di BrightSign (comando UDP one-shot), il processore Yamaha parla
TCP e richiede una breve sequenza con pause fra i comandi — la stessa finora
lanciata a mano da riga di comando:

```bash
(printf 'devstatus runmode\n'; sleep 1; printf 'ssrecall 1\n'; sleep 1) | nc 192.168.11.162 49280
```

`send_yamaha_scene()` in `includes/functions.php` riproduce esattamente questa
sequenza con un socket PHP (`fsockopen`), così l'invio non dipende dalla
presenza del binario `nc` sul server. Host, porta e mappatura scenario→scena
sono definiti in `config/yamaha.php`: come per BrightSign, il valore scelto
lato client viene sempre rivalidato contro questa lista lato server (azione
`yamaha` di `api.php`) prima dell'invio.

Sotto i pulsanti c'è anche il pannello "Schedulazioni" (stesso meccanismo di
BrightSign/banner: vedi "Schedulazioni" più sopra) per programmare il
richiamo di uno scenario a una data/ora futura, una tantum o ogni giorno alla
stessa ora — utile ad esempio per passare automaticamente da Museo a Muto
alla chiusura.

## Gestione Audio Jobs

Voce di menu dedicata nella sidebar ("Gestione audio" → "Gestione Audio
Jobs", `audio_jobs.php`). La pagina permette di richiamare uno snapshot
salvato dentro lo show **NewJobs** sul mixer digitale Soundcraft Ui24R
(`192.168.11.242`): **Evento** (snapshot `EVENTO`) e **Museo** (snapshot
`MUSEO_1006`).

A differenza di BrightSign/Yamaha (UDP/TCP puri), il firmware Ui24R parla
**WebSocket** con un framing testuale in stile Socket.IO 0.9 (confermato
leggendo il codice sorgente della libreria open source
[fmalcher/soundcraft-ui](https://github.com/fmalcher/soundcraft-ui), che la
console ufficiale del mixer usa per parlargli): ogni messaggio applicativo è
preceduto dal prefisso `3:::`. Il comando per richiamare uno snapshot è
`LOADSNAPSHOT^<show>^<snapshot>`, quindi ad esempio:

```
3:::LOADSNAPSHOT^NewJobs^EVENTO
```

`send_soundcraft_command()`/`send_soundcraft_snapshot()` in
`includes/functions.php` fanno l'handshake HTTP Upgrade e incapsulano il
comando in un frame WebSocket **a mano**, su un socket TCP grezzo
(`fsockopen`): nessuna estensione PHP aggiuntiva né libreria esterna
richiesta, stesso stile "senza dipendenze" del resto del progetto.

Host, porta, show e mappatura snapshot→nome sono definiti in
`config/soundcraft.php` — come per BrightSign/Yamaha, il valore scelto lato
client viene sempre rivalidato contro questa lista lato server (azione
`soundcraft_snapshot` di `api.php`) prima dell'invio.

Sotto i pulsanti c'è anche il pannello "Schedulazioni" (stesso meccanismo
degli altri pannelli: vedi "Schedulazioni" più sopra) per programmare il
richiamo di uno snapshot a una data/ora futura, una tantum o ogni giorno alla
stessa ora.

**Attenzione**: a differenza degli altri dispositivi di questo progetto,
`192.168.11.242` è risultato raggiungibile anche dagli ambienti di sviluppo
usati per testare questo codice — un comando inviato per errore durante lo
sviluppo arriva quindi al mixer reale, non a un dispositivo isolato. Prima di
testare da un ambiente non in sala, verificare la raggiungibilità di rete.

## Gestione Audio SAM

Voce di menu dedicata nella sidebar ("Gestione audio" → "Gestione Audio
SAM", `audio_sam.php`). A differenza degli altri dispositivi audio (che
parlano un protocollo di rete diretto), il PC SAM viene comandato eseguendo
`nircmd.exe` **da remoto via WMI** con [Impacket](https://github.com/fortra/impacket)
`wmiexec.py`, lo stesso comando finora lanciato a mano da riga di comando:

```bash
python3 examples/wmiexec.py Fastweb:[PASSWORD]@192.168.11.209 "C:\Tools\nircmd.exe mutesysvolume 0"
```

I due pulsanti disponibili sono **Attiva Audio SAM** (`mutesysvolume 0`,
unmute) e **Disattiva Audio SAM** (`mutesysvolume 1`, mute) — semantica
nircmd standard (`0`=riattiva, `1`=silenzia).

`run_sam_command()` in `includes/functions.php` costruisce ed esegue il
comando con `shell_exec`, con lo stesso schema di sicurezza già usato per gli
script di sala (`escapeshellarg` su ogni argomento, nessun input utente
diretto: il valore mute arriva sempre da uno dei comandi whitelisted in
`config/sam.php`, mai dal client). Il comando è avvolto in `timeout 20` per
non bloccare indefinitamente la richiesta web (o il cron dello scheduler) se
il PC Windows non risponde.

Host, username e i percorsi di `python3`/`wmiexec.py`/`nircmd.exe` sono
definiti in `config/sam.php`; la password vera va invece in
`config/sam.local.php` (non versionato, vedi `.gitignore`) — `config/sam.php`
usa `[PASSWORD]` come placeholder se quel file non esiste. **Limiti di
sicurezza da conoscere, non introdotti da questa integrazione ma ereditati
dallo strumento**:

- Le credenziali sono in chiaro in `config/sam.local.php` e sulla riga di
  comando lanciata dal server: chiunque possa leggere la process list
  (`ps aux`) nel breve istante di esecuzione le vede. Limitare l'accesso al
  server a chi deve già poterlo amministrare.
- `wmiexec.py` è uno strumento di esecuzione comandi remota (parte di
  Impacket): questa pagina espone via HTTP, dietro due pulsanti, la capacità
  di eseguire un comando specifico e fisso (`mutesysvolume 0`/`1`) su un host
  Windows della rete interna. Non è possibile far eseguire altro dal client:
  il comando remoto è cablato in `config/sam.php`, non costruito da input
  utente.
- Come per il resto della console, `api.php` non ha autenticazione: chiunque
  raggiunga la rete della console può premere questi pulsanti. Vale la stessa
  considerazione già valida per Start/Stop delle sale — non specifica di
  questa funzione.

Sotto i pulsanti c'è anche il pannello "Schedulazioni" (stesso meccanismo
degli altri pannelli: vedi "Schedulazioni" più sopra) per programmare
mute/unmute a una data/ora futura, una tantum o ogni giorno alla stessa ora.

## Deploy

Il mio utente non ha permessi di scrittura su `/var/www`. Per pubblicare la
copia accanto all'originale (senza toccarlo):

```bash
sudo cp -r "<questa-cartella>" /var/www/STEPconsolle-v2
sudo chown -R root:root /var/www/STEPconsolle-v2
sudo find /var/www/STEPconsolle-v2 -type d -exec chmod 755 {} \;
sudo find /var/www/STEPconsolle-v2 -type f -exec chmod 644 {} \;
sudo chmod +x /var/www/STEPconsolle-v2/scripts/*.sh /var/www/STEPconsolle-v2/scripts/run_scheduler.php
```

Poi punta un vhost (o un `Alias`/`location`) a `/var/www/STEPconsolle-v2`
per provarla in parallelo a `STEPconsolle`, oppure, quando sei pronto a
sostituirla, rinomina le due cartelle. Se vuoi anche lo scheduler banner
funzionante (non solo la UI), applica in più i tre passaggi descritti sopra
in "Scheduler banner Future Trends" (permessi su `data/`, cron, blocco
Apache/nginx su `data/`), aggiornando i percorsi con `STEPconsolle-v2` se
stai ancora provando la copia in parallelo.

Richiede l'estensione PHP `sockets` (già usata dall'originale `FT_Black.php`)
e `redis-cli` disponibile sul PATH (come per l'originale).
