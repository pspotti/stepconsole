# WallConfig Web Control

Web app per gestire i comandi WallConfig.txt su tutte le sezioni, utilizzabile
da browser, pensata per girare su una macchina Linux.

## Stato dei protocolli
- **SAMSUNG #INP** (cambio sorgente): validato su hardware reale.
- **SAMSUNG #WALL** (posizionamento video wall, comando 0x8B): validato
  tramite cattura di rete (Wireshark) — il display ha risposto con ACK (0x41).
- **LG** (sezione JOBS): SPERIMENTALE. Il comando "xb" (Input Select) è quello
  pubblicamente documentato da LG per il protocollo Set-ID, ma non è stato
  ancora testato su un display LG reale. Usa il dry-run e testa su un solo
  display prima di usarlo su tutti.

## Installazione su macchina Linux

Sulla macchina in uso `pip`/`venv` non erano disponibili senza pacchetti di
sistema, quindi si installa Flask tramite apt:

```bash
sudo apt update
sudo apt install -y python3-flask
```

(In alternativa, se preferisci un virtualenv: `sudo apt install -y
python3.10-venv`, poi `python3 -m venv venv && source venv/bin/activate &&
pip install -r requirements.txt`.)

## Percorso di installazione

L'app vive in `/var/www/STEPconsolle/wallconfig/`. `WallConfig.txt` di
default viene letto dalla stessa cartella dell'app (indipendente dalla
directory da cui lanci `python3 app.py`); puoi comunque sovrascriverlo con
`WALLCONFIG_PATH`.

## Avvio

```bash
cd /var/www/STEPconsolle/wallconfig
export WALLCONFIG_PATH=/var/www/STEPconsolle/wallconfig/WallConfig.txt   # opzionale, è già il default
python3 app.py
```

Poi apri nel browser: `http://<ip-della-macchina-linux>:8000`

## Avvio automatico (systemd)

È presente un file di unit pronto in `wallconfig.service`. Per installarlo:

```bash
sudo cp /var/www/STEPconsolle/wallconfig/wallconfig.service /etc/systemd/system/wallconfig.service
sudo systemctl daemon-reload
sudo systemctl enable --now wallconfig.service
sudo systemctl status wallconfig.service
```

## Uso
1. Scegli la sezione (GATE, GALLERY, JOBS, FUTURE TRENDS, WALL)
2. Scegli il setup (STANDARD / GUEST)
3. Premi "Invia comando": IN a tutti gli schermi della sezione e, per i setup
   che lo prevedono (oggi solo WALL), anche WALL dopo 3 secondi.

## Cambi di stato programmati (scheduler)

Sotto il form di invio immediato c'è un pannello per mettere in coda lo
stesso comando "Sezione::Setup" a una data/ora futura, una tantum oppure
ogni giorno alla stessa ora (es. passaggio da configurazione notturna a
diurna). Non serve installare un cron: WallConfig gira già come servizio
persistente (systemd), quindi un thread in background nello stesso
processo controlla la coda ogni 20 secondi e spedisce i comandi scaduti
(vedi `scheduler_store.py`).

I job vengono salvati in `schedule.json` (accanto ad `app.py`, path
sovrascrivibile con `WALLCONFIG_SCHEDULE_PATH`). Un job "una tantum" la cui
data/ora è passata senza che il servizio fosse in esecuzione viene marcato
come scaduto e disattivato, non spedito in ritardo.

## Eseguirlo in background su Linux (opzionale)
```bash
nohup python3 app.py > wallconfig.log 2>&1 &
```
