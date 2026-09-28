"""
scheduler_store.py — Scheduler dei cambi di stato programmati per i
monitor gestiti da WallConfig (schedule.json).

Stesso schema dello scheduler banner Future Trends della console
STEPconsolle (vedi ../scripts/run_scheduler.php e ../includes/functions.php,
funzione with_schedule_lock): un job programma l'invio futuro — una
tantum a data/ora scelta, oppure ogni giorno alla stessa ora — dello
stesso comando "Sezione::Setup" che il form principale invia subito.
Non è un comando diverso, solo lo stesso invio rimandato.

Differenza rispetto alla console STEPconsolle: lì l'invio è fatto da un
cron (scripts/run_scheduler.php) perché le pagine PHP girano solo su
richiesta HTTP. WallConfig invece è già un servizio persistente
(systemd, vedi wallconfig.service), quindi qui basta un thread in
background nello stesso processo (start_background_loop, avviato da
app.py) che controlla la coda ogni pochi secondi: non serve installare
nessun cron.
"""

import json
import logging
import os
import threading
import uuid
from datetime import datetime

APP_DIR = os.path.dirname(os.path.abspath(__file__))
SCHEDULE_PATH = os.environ.get("WALLCONFIG_SCHEDULE_PATH", os.path.join(APP_DIR, "schedule.json"))

_lock = threading.Lock()

logger = logging.getLogger("wallconfig.scheduler")


def _load():
    if not os.path.exists(SCHEDULE_PATH):
        return []
    try:
        with open(SCHEDULE_PATH, "r", encoding="utf-8") as fh:
            data = json.load(fh)
    except (json.JSONDecodeError, OSError):
        return []
    return data if isinstance(data, list) else []


def _save(jobs):
    # Scrittura su file temporaneo + rename atomico: un crash a metà
    # scrittura non puo' mai lasciare schedule.json a metà/corrotto.
    tmp_path = SCHEDULE_PATH + ".tmp"
    with open(tmp_path, "w", encoding="utf-8") as fh:
        json.dump(jobs, fh, indent=2, ensure_ascii=False)
    os.replace(tmp_path, SCHEDULE_PATH)


def list_jobs():
    with _lock:
        jobs = _load()
    jobs.sort(key=lambda j: ((j.get("date") or "") if j.get("repeat") == "once" else "", j.get("time") or ""))
    return jobs


def add_job(setup_combo, section_label, setup_label, repeat, date_str, time_str):
    """Crea e salva un nuovo job. Nessuna validazione qui: la fa il
    chiamante (route /schedule/add in app.py), come per l'azione
    'schedule_add' di api.php."""
    job = {
        "id": uuid.uuid4().hex[:12],
        "setup": setup_combo,          # "Sezione::Setup", stessa chiave del form principale
        "sectionLabel": section_label,  # solo per la lista: nome leggibile
        "setupLabel": setup_label,
        "repeat": repeat,
        "date": date_str,               # None se repeat == 'daily'
        "time": time_str,
        "enabled": True,
        "missed": False,
        "createdAt": datetime.now().isoformat(timespec="seconds"),
        "lastRunAt": None,
        "lastRunDate": None,
    }
    with _lock:
        jobs = _load()
        jobs.append(job)
        _save(jobs)
    return job


def toggle_job(job_id, enabled):
    with _lock:
        jobs = _load()
        found = False
        for j in jobs:
            if j["id"] == job_id:
                j["enabled"] = enabled
                found = True
        if found:
            _save(jobs)
    return found


def delete_job(job_id):
    with _lock:
        jobs = _load()
        remaining = [j for j in jobs if j["id"] != job_id]
        found = len(remaining) != len(jobs)
        if found:
            _save(remaining)
    return found


def run_due_jobs(send_fn):
    """
    Manda avanti la coda: per ogni job abilitato e scaduto chiama
    send_fn(section_name, setup_name) — la stessa funzione usata dal
    form immediato in app.py — e aggiorna lo stato del job.

    Stessa semantica del cron PHP (scripts/run_scheduler.php):
      - 'once' con data passata e mai eseguito -> segnato "scaduto"
        (missed) e disattivato: MAI spedito in ritardo (meglio non
        spedire un cambio di stato fuori contesto che spedirne uno
        vecchio a sorpresa);
      - 'once' eseguito -> disattivato;
      - 'daily' -> eseguito al massimo una volta al giorno, resta attivo.
    """
    now = datetime.now()
    today = now.strftime("%Y-%m-%d")
    now_min = now.hour * 60 + now.minute

    with _lock:
        jobs = _load()
        changed = False

        for job in jobs:
            if not job.get("enabled"):
                continue

            try:
                h, m = (int(p) for p in str(job.get("time", "")).split(":"))
            except ValueError:
                continue
            due_min = h * 60 + m

            if job.get("repeat") == "once":
                if not job.get("date"):
                    continue
                if job["date"] < today:
                    job["enabled"] = False
                    job["missed"] = True
                    changed = True
                    continue
                if job["date"] != today or now_min < due_min:
                    continue
            else:  # daily
                if now_min < due_min or job.get("lastRunDate") == today:
                    continue

            section_name, sep, setup_name = job.get("setup", "").partition("::")
            if not sep:
                logger.warning("job %s: valore setup non valido (%r), salto.", job["id"], job.get("setup"))
                continue

            try:
                results, error = send_fn(section_name, setup_name)
            except Exception as e:  # un job che esplode non deve fermare gli altri
                results, error = [], str(e)

            ok = not error and bool(results) and all(r.get("status") != "ERRORE" for r in results)
            logger.info(
                "job %s (%s/%s) -> %s%s",
                job["id"], job["repeat"], job["setup"],
                "OK" if ok else "ERRORI", f" ({error})" if error else "",
            )

            job["lastRunAt"] = now.isoformat(timespec="seconds")
            if job["repeat"] == "once":
                job["enabled"] = False
            else:
                job["lastRunDate"] = today
            changed = True

        if changed:
            _save(jobs)


def start_background_loop(send_fn, interval_seconds=20):
    """Avvia il controllo periodico della coda in un thread daemon.
    Non serve un cron esterno: WallConfig gira già come servizio
    persistente (systemd), quindi il thread vive per tutta la vita del
    processo e basta a sé stesso."""
    stop_event = threading.Event()

    def _loop():
        while not stop_event.is_set():
            try:
                run_due_jobs(send_fn)
            except Exception:
                logger.exception("errore inatteso nel ciclo dello scheduler")
            stop_event.wait(interval_seconds)

    t = threading.Thread(target=_loop, name="wallconfig-scheduler", daemon=True)
    t.start()
    return t
