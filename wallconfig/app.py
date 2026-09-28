"""
app.py — Web app Flask per gestire tutte le sezioni di WallConfig.txt.

Avvio:
    export WALLCONFIG_PATH=/percorso/a/WallConfig.txt   # opzionale, default ./WallConfig.txt
    python3 app.py
Poi apri http://<ip-macchina-linux>:8000

NOTA:
  - Comandi Samsung (#INP): protocollo VALIDATO su hardware reale.
  - Comandi LG (#INP): protocollo SPERIMENTALE.
  - Ogni invio agisce sempre su TUTTI gli schermi della sezione scelta (non
    esiste più selezione di un sottoinsieme di display, né una modalità
    dry-run: ogni comando inviato dalla GUI viene sempre spedito davvero).
  - Il doppio invio IN + WALL è automatico, non è una scelta manuale:
    scatta solo per i setup che hanno davvero comandi #WALL definiti
    (oggi solo la sezione "WALL", sia per GUEST che per STANDARD). In quel
    caso l'app invia prima IN a TUTTI gli schermi, poi attende 3 secondi e
    infine invia WALL a TUTTI gli schermi (due passate separate, non uno
    schermo alla volta). Per qualsiasi altra sezione (senza comandi #WALL)
    viene inviato solo IN, senza attesa.
  - Oltre all'invio immediato, la pagina ha un pannello "Cambi di stato
    programmati" per mettere in coda lo stesso invio a una data/ora futura
    (una tantum o ogni giorno): vedi scheduler_store.py.
"""

import json
import logging
import os
import re
import time
from datetime import datetime

from flask import Flask, flash, get_flashed_messages, jsonify, redirect, render_template, request, url_for

STEP_CONSOLE_BASE_URL = os.environ.get("STEP_CONSOLE_BASE_URL", "").rstrip("/")

import scheduler_store as sched
from wallconfig_parser import parse_all_sections
from protocols import (
    build_samsung_packet,
    build_samsung_wall_packet,
    build_lg_packet,
    send_packet,
    format_packet_for_display,
)

APP_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG_PATH = os.environ.get("WALLCONFIG_PATH", os.path.join(APP_DIR, "WallConfig.txt"))

# Valore di "sezione" usato nel form/API per i job schedulati "tutti gli
# schermi": non è il nome di una sezione reale di WallConfig.txt, è un
# sentinel riconosciuto da _validate_schedule_add() e dal callback dello
# scheduler in background per instradare verso send_setup_all_sections()
# invece che verso una singola sezione.
ALL_SECTIONS_KEY = "__ALL__"
ALL_SECTIONS_LABEL = "Tutte le sezioni"

app = Flask(__name__)
# Serve solo per i messaggi flash dopo le azioni dello scheduler (redirect
# POST->GET): niente login, niente dati sensibili in sessione, quindi va
# bene generarla a ogni avvio invece di doverla configurare a mano.
app.secret_key = os.environ.get("WALLCONFIG_SECRET_KEY") or os.urandom(24)

logging.basicConfig(level=logging.INFO, format="[%(asctime)s] %(message)s")


def load_sections():
    return parse_all_sections(CONFIG_PATH)


def step_base_url():
    """
    Base URL della console STEPconsolle (PHP), per caricare lo stesso
    foglio di stile e per i link "torna alla console" nella sidebar di
    questa pagina — così WallConfig ha lo stesso aspetto e lo stesso
    menu del resto della console, invece di sembrare un'app a parte.

    Di default si assume la stessa macchina di WallConfig, sulla porta
    standard (WallConfig gira sulla :8000, la console PHP su Apache
    :80/:443): si sovrascrive con STEP_CONSOLE_BASE_URL se il layout di
    rete è diverso (altra macchina, altra porta, HTTPS con dominio).
    """
    if STEP_CONSOLE_BASE_URL:
        return STEP_CONSOLE_BASE_URL
    host = request.host.split(":")[0]
    return f"{request.scheme}://{host}"


@app.context_processor
def inject_step_base_url():
    """Rende step_base_url disponibile in tutti i template senza doverlo
    passare a mano a ogni render_template()."""
    return {"step_base_url": step_base_url()}


# File JSON condiviso con includes/layout_top.php (console PHP): un solo
# posto da modificare per aggiungere/togliere/rinominare una voce di menu
# statica (Dashboard, Emergenza, Dispositivi, Gestione audio, Schedulazioni,
# Test giornalisti), invece di tenere sincronizzate a mano due sidebar
# scritte in due linguaggi diversi — vedi templates/index.html, che ci
# fa un ciclo sopra invece di avere l'HTML delle voci scritto a mano.
# Esclusa la sezione "Percorso mostra" (dinamica da config/stations.php,
# un array PHP che questa app non può leggere): resta hardcoded qui sotto
# come già prima.
SIDEBAR_CONFIG_PATH = os.path.join(APP_DIR, "..", "config", "sidebar.json")


def load_sidebar_sections():
    try:
        with open(SIDEBAR_CONFIG_PATH, encoding="utf-8") as f:
            return json.load(f).get("sections", [])
    except (OSError, ValueError) as exc:
        logging.warning("Impossibile leggere %s: %s", SIDEBAR_CONFIG_PATH, exc)
        return []


@app.context_processor
def inject_sidebar_sections():
    """Rende le voci di menu statiche disponibili a ogni template (vedi
    load_sidebar_sections sopra)."""
    return {"sidebar_sections": load_sidebar_sections()}


@app.context_processor
def inject_all_sections_key():
    """Rende il sentinel "tutte le sezioni" disponibile al template, così
    il valore usato per costruire le option "Tutte le sezioni" nel form di
    schedulazione resta sempre lo stesso usato da _validate_schedule_add()
    e da _scheduled_send(), senza doverlo duplicare a mano nell'HTML."""
    return {"all_sections_key": ALL_SECTIONS_KEY, "all_sections_label": ALL_SECTIONS_LABEL}


def send_setup_command(section_name, setup_name, sections=None):
    """
    Invia il comando (IN, e se previsto WALL) del setup scelto a TUTTI gli
    schermi della sezione. Estratta dalla route /send perché è la stessa
    identica logica usata dallo scheduler in background (scheduler_store.py)
    per gli invii programmati: un solo posto che sa come si spedisce un
    comando, sia che parta da un click sia che parta da un job schedulato.

    Ritorna (results, error) — error è None se non ci sono stati problemi
    di validazione (sezione/setup non trovati ecc.), risultati per-schermo
    sono sempre in "results" anche quando error è valorizzato.
    """
    if sections is None:
        sections = load_sections()

    error = None
    results = []

    if section_name not in sections:
        return results, f'Sezione "{section_name}" non trovata.'

    sec = sections[section_name]
    protocol = sec["protocol"]
    elements = sec["elements"]

    def _build_packet(label, el, value):
        """label: 'IN' o 'WALL' -> (packet, kind) secondo il protocollo della sezione."""
        if label == "WALL":
            return build_samsung_wall_packet(el["display_id"], value)
        if protocol == "SAMSUNG":
            return build_samsung_packet(el["display_id"], value)
        if protocol == "LG":
            return build_lg_packet(el["display_id"], value)
        return None, None

    def _send_and_record(eid, el, label, value):
        """Costruisce, invia e registra il pacchetto per un singolo comando."""
        packet, kind = _build_packet(label, el, value)
        if packet is None:
            results.append({"id": eid, "status": "ERRORE", "detail": f"protocollo sconosciuto: {protocol}"})
            return
        packet_str = format_packet_for_display(packet, kind)
        prefix = f"{label}: "

        try:
            resp = send_packet(el["ip"], el["port"], packet)
            resp_str = format_packet_for_display(resp, kind) if resp else "(nessuna risposta)"
            results.append({
                "id": eid, "ip": el["ip"], "port": el["port"],
                "status": "INVIATO", "detail": f"{prefix}{packet_str} | risposta: {resp_str}",
            })
        except Exception as e:
            results.append({
                "id": eid, "ip": el["ip"], "port": el["port"],
                "status": "ERRORE", "detail": f"{prefix}{e}",
            })

    inp_setup_map = sec["setups"].get(setup_name)
    wall_setup_map = sec["wall_setups"].get(setup_name)
    # Il setup è "wall" (richiede la sequenza IN + attesa + WALL) solo se
    # ha davvero almeno un comando #WALL definito — oggi succede solo
    # nella sezione "WALL", sia per il setup GUEST che per lo STANDARD.
    # Non è una scelta manuale dell'utente.
    is_wall = bool(wall_setup_map)

    if inp_setup_map is None:
        return results, f'Setup "{setup_name}" non trovato nella sezione "{section_name}".'
    if is_wall and protocol != "SAMSUNG":
        return results, "I comandi #WALL sono supportati solo per il protocollo SAMSUNG."

    # Il target è sempre TUTTI gli schermi definiti nella sezione.
    valid = sorted(elements.keys())

    # Passata 1: comando IN a TUTTI gli schermi del target.
    for eid in valid:
        el = elements[eid]
        if eid not in inp_setup_map:
            results.append({"id": eid, "status": "SALTATO", "detail": "nessun comando IN per questo setup"})
        else:
            _send_and_record(eid, el, "IN", inp_setup_map[eid])

    if is_wall:
        # Attesa di 3 secondi prima della passata WALL.
        if valid:
            time.sleep(3)

        # Passata 2: comando WALL a TUTTI gli schermi del target.
        for eid in valid:
            el = elements[eid]
            if eid not in wall_setup_map:
                results.append({"id": eid, "status": "SALTATO", "detail": "nessun comando WALL per questo setup"})
            else:
                _send_and_record(eid, el, "WALL", wall_setup_map[eid])

    return results, error


def send_setup_all_sections(setup_name, sections=None):
    """
    Come send_setup_command, ma applica lo stesso setup (GUEST o STANDARD) a
    TUTTE le sezioni di WallConfig.txt in sequenza, una dopo l'altra — usata
    dai due pulsanti "Tutti gli schermi" in pagina per portare l'intero show
    su un unico scenario con un solo click, invece di ripetere l'invio
    sezione per sezione. Le sezioni che non hanno quel setup vengono
    saltate senza bloccare le altre (non è un errore: non tutte le sezioni
    sono obbligate ad avere gli stessi nomi di setup).
    """
    if sections is None:
        sections = load_sections()

    all_results = []
    for section_name in sections:
        results, _section_error = send_setup_command(section_name, setup_name, sections)
        for r in results:
            r = dict(r)
            r["section"] = section_name
            all_results.append(r)

    return all_results, None


def _job_view(job):
    """Aggiunge al job i campi già pronti per il template (testo di stato e
    "quando"), sul modello di statusText()/renderJobs() in assets/js/scheduler.js
    della console STEPconsolle — qui però calcolati lato server perché questa
    pagina non ha un layer JS/AJAX."""
    j = dict(job)
    if j["repeat"] == "once":
        j["whenText"] = f'{j.get("date")} alle {j.get("time")}'
    else:
        j["whenText"] = f'ogni giorno alle {j.get("time")}'

    if j.get("missed"):
        j["statusText"] = "scaduto (non inviato)"
    elif not j.get("enabled") and j["repeat"] == "once" and j.get("lastRunAt"):
        j["statusText"] = "inviato"
    elif not j.get("enabled"):
        j["statusText"] = "sospeso"
    elif j["repeat"] == "daily" and j.get("lastRunDate"):
        j["statusText"] = f'ultimo invio {j["lastRunDate"]}'
    else:
        j["statusText"] = "in attesa"
    return j


def _job_api_view(job):
    """
    Stessa forma dei job restituiti da 'schedule_list' in api.php (la
    console STEPconsolle): id/type/repeat/date/time/enabled/missed/
    lastRunAt/lastRunDate + label/badge già calcolati, "quando" e testo di
    stato NON precalcolati (li ricava assets/js/scheduler.js lato client,
    come già fa per i job banner/BrightSign). Permette alla pagina di
    riepilogo scheduler.php di mostrare e gestire anche i job WallConfig
    con lo STESSO scheduler.js condiviso, senza sapere nulla di
    sezioni/setup: per lei è solo un altro tipo di job.
    """
    return {
        "id": job["id"],
        "type": "wallconfig",
        "repeat": job["repeat"],
        "date": job.get("date"),
        "time": job.get("time"),
        "enabled": bool(job.get("enabled")),
        "missed": bool(job.get("missed")),
        "lastRunAt": job.get("lastRunAt"),
        "lastRunDate": job.get("lastRunDate"),
        "label": f'{job.get("setupLabel")} ({job.get("sectionLabel")})',
        "badge": "accent",
    }


def _section_label(section_name):
    """Nome leggibile della sezione per la lista dei job programmati:
    il sentinel ALL_SECTIONS_KEY diventa "Tutte le sezioni" invece di
    comparire nella UI con il suo valore interno."""
    return ALL_SECTIONS_LABEL if section_name == ALL_SECTIONS_KEY else section_name


def _validate_schedule_add(sections, setup_combo, repeat, date_str, time_str):
    """
    Valida i campi di un nuovo job (stessa forma sia dal form HTML di
    /schedule/add sia dall'API JSON /api/schedule): ritorna un messaggio
    d'errore in italiano, oppure None se tutto è valido. Un solo posto
    per questa logica, invece di duplicarla fra route e API.
    """
    section_name, sep, setup_name = setup_combo.partition("::")
    is_all_sections = section_name == ALL_SECTIONS_KEY and setup_name in ("GUEST", "STANDARD")
    is_single_section = section_name in sections and setup_name in sections[section_name]["setups"]
    if not sep or not (is_all_sections or is_single_section):
        return "Setup non valido: scegli sezione e setup dal menu."
    if not re.match(r"^([01]\d|2[0-3]):[0-5]\d$", time_str):
        return "Orario non valido (formato HH:MM)."
    if repeat == "once":
        if not re.match(r"^\d{4}-\d{2}-\d{2}$", date_str):
            return "Data non valida (formato AAAA-MM-GG)."
        if datetime.strptime(f"{date_str} {time_str}", "%Y-%m-%d %H:%M") < datetime.now():
            return "La data/ora deve essere nel futuro."
    return None


@app.route("/", methods=["GET"])
def index():
    sections = load_sections()
    jobs = [_job_view(j) for j in sched.list_jobs()]
    return render_template(
        "index.html", sections=sections, results=None, error=None,
        jobs=jobs, messages=get_flashed_messages(with_categories=True),
    )


@app.route("/send", methods=["POST"])
def send():
    sections = load_sections()

    # Il campo "setup" arriva nel formato "Sezione::Setup": è l'unica fonte
    # di verità per la coppia sezione+setup, così i comandi IN e WALL sono
    # sempre presi dalla stessa sezione/setup, senza possibilità che i due
    # selettori in pagina si disallineino.
    setup_combo = request.form.get("setup", "")
    section_name, sep, setup_name = setup_combo.partition("::")
    if not sep:
        section_name, setup_name = None, None

    if not sep:
        error, results = 'Setup non valido: seleziona sezione e setup dal menu.', []
    else:
        results, error = send_setup_command(section_name, setup_name, sections)

    jobs = [_job_view(j) for j in sched.list_jobs()]
    return render_template(
        "index.html",
        sections=sections,
        results=results,
        error=error,
        selected_section=section_name,
        selected_setup=setup_name,
        jobs=jobs,
        messages=get_flashed_messages(with_categories=True),
    )


@app.route("/send_all", methods=["POST"])
def send_all():
    """Pulsanti "Tutti gli schermi -> Guest/Standard": stesso setup su TUTTE
    le sezioni in un solo invio, invece di dover scegliere sezione per
    sezione dal form 'Invia comando'."""
    setup_name = request.form.get("setup", "")
    if setup_name not in ("GUEST", "STANDARD"):
        error, results = "Scenario non valido.", []
    else:
        sections_for_send = load_sections()
        results, error = send_setup_all_sections(setup_name, sections_for_send)

    sections = load_sections()
    jobs = [_job_view(j) for j in sched.list_jobs()]
    return render_template(
        "index.html",
        sections=sections,
        results=results,
        error=error,
        selected_section=ALL_SECTIONS_LABEL,
        selected_setup=setup_name,
        jobs=jobs,
        messages=get_flashed_messages(with_categories=True),
    )


# Scheduler — cambi di stato programmati per i monitor -----------------------
# Programma l'invio futuro (una tantum o ogni giorno alla stessa ora) dello
# stesso comando "Sezione::Setup" del form qui sopra. L'invio vero e proprio
# è fatto in background dal thread avviato da start_background_loop() (vedi
# scheduler_store.py): qui ci limitiamo a validare e a leggere/scrivere la
# coda in schedule.json, sullo stesso modello dell'azione 'schedule_add' di
# api.php nella console STEPconsolle.
@app.route("/schedule/add", methods=["POST"])
def schedule_add():
    sections = load_sections()

    setup_combo = request.form.get("setup", "")
    section_name, _, setup_name = setup_combo.partition("::")
    repeat = "daily" if request.form.get("repeat") == "daily" else "once"
    time_str = (request.form.get("time") or "").strip()
    date_str = (request.form.get("date") or "").strip()

    error = _validate_schedule_add(sections, setup_combo, repeat, date_str, time_str)
    if error:
        flash(error, "err")
    else:
        sched.add_job(
            setup_combo, _section_label(section_name), setup_name,
            repeat, date_str if repeat == "once" else None, time_str,
        )
        flash("Cambio di stato programmato.", "ok")

    return redirect(url_for("index"))


@app.route("/schedule/toggle", methods=["POST"])
def schedule_toggle():
    job_id = request.form.get("id", "")
    enabled = request.form.get("enabled") == "1"
    if sched.toggle_job(job_id, enabled):
        flash("Comando riattivato." if enabled else "Comando sospeso.", "ok")
    else:
        flash("Comando programmato non trovato.", "err")
    return redirect(url_for("index"))


@app.route("/schedule/delete", methods=["POST"])
def schedule_delete():
    job_id = request.form.get("id", "")
    if sched.delete_job(job_id):
        flash("Comando programmato rimosso.", "ok")
    else:
        flash("Comando programmato non trovato.", "err")
    return redirect(url_for("index"))


# API JSON — consumata dalla pagina di riepilogo "Schedulazioni"
# (scheduler.php) della console STEPconsolle, che gira su un'altra origine
# (porta 80/443 invece che 8000): serve CORS, non richiesto da nessun'altra
# rotta di questo file (tutte le altre sono form HTML della stessa origine).
# Nessuna autenticazione qui — coerente col resto del progetto, che non ne
# ha in nessuna pagina: chiunque raggiunga questa rete può già inviare
# comandi dal form HTML sopra, l'API JSON non apre una superficie nuova.
@app.after_request
def add_cors_headers(response):
    if request.path.startswith("/api/"):
        origin = request.headers.get("Origin")
        if origin:
            response.headers["Access-Control-Allow-Origin"] = origin
            response.headers["Vary"] = "Origin"
        response.headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS"
        response.headers["Access-Control-Allow-Headers"] = "Content-Type"
    return response


@app.route("/api/sections", methods=["GET", "OPTIONS"])
def api_sections():
    """Nomi di sezioni/setup (niente protocollo/IP: non serve a scheduler.php),
    per costruire lì le stesse select di sezione/setup del form qui sopra
    senza dover riscrivere il parser di WallConfig.txt in PHP."""
    if request.method == "OPTIONS":
        return ("", 204)
    sections = load_sections()
    return jsonify({
        "ok": True,
        "sections": {name: sorted(sec["setups"].keys()) for name, sec in sections.items()},
    })


@app.route("/api/schedule", methods=["POST", "OPTIONS"])
def api_schedule():
    """
    Stesso contratto JSON di api.php in STEPconsolle: {"action": "...", ...}
    in ingresso, {"ok": ..., ...} in uscita — così scheduler.php riusa lo
    stesso assets/js/scheduler.js sia per parlare con api.php (banner,
    BrightSign) sia con questo endpoint (WallConfig), solo l'URL cambia.
    """
    if request.method == "OPTIONS":
        return ("", 204)

    body = request.get_json(silent=True) or {}
    action = body.get("action", "")

    if action == "schedule_list":
        jobs = [_job_api_view(j) for j in sched.list_jobs()]
        return jsonify({"ok": True, "jobs": jobs})

    if action == "schedule_add":
        sections = load_sections()
        setup_combo = str(body.get("setup", ""))
        section_name, _, setup_name = setup_combo.partition("::")
        repeat = "daily" if body.get("repeat") == "daily" else "once"
        time_str = str(body.get("time", "")).strip()
        date_str = str(body.get("date", "")).strip()

        error = _validate_schedule_add(sections, setup_combo, repeat, date_str, time_str)
        if error:
            return jsonify({"ok": False, "message": error}), 422

        job = sched.add_job(
            setup_combo, _section_label(section_name), setup_name,
            repeat, date_str if repeat == "once" else None, time_str,
        )
        return jsonify({"ok": True, "message": "Cambio di stato programmato.", "job": _job_api_view(job)})

    if action == "schedule_toggle":
        job_id = str(body.get("id", ""))
        enabled = bool(body.get("enabled"))
        if not sched.toggle_job(job_id, enabled):
            return jsonify({"ok": False, "message": "Comando programmato non trovato."}), 404
        return jsonify({"ok": True, "message": "Comando riattivato." if enabled else "Comando sospeso."})

    if action == "schedule_delete":
        job_id = str(body.get("id", ""))
        if not sched.delete_job(job_id):
            return jsonify({"ok": False, "message": "Comando programmato non trovato."}), 404
        return jsonify({"ok": True, "message": "Comando programmato rimosso."})

    return jsonify({"ok": False, "message": "Azione non riconosciuta."}), 400


def _scheduled_send(section_name, setup_name):
    """Callback passato allo scheduler in background: instrada verso
    send_setup_all_sections() per i job "tutti gli schermi" (sezione =
    ALL_SECTIONS_KEY), altrimenti verso il normale invio a una sezione."""
    if section_name == ALL_SECTIONS_KEY:
        return send_setup_all_sections(setup_name)
    return send_setup_command(section_name, setup_name)


if __name__ == "__main__":
    # Nessun cron da installare: lo scheduler gira nello stesso processo,
    # come thread daemon, per tutta la vita del servizio systemd.
    sched.start_background_loop(_scheduled_send)
    app.run(host="0.0.0.0", port=8000, debug=False)
