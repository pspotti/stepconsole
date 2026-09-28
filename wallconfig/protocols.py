"""
protocols.py — Costruzione pacchetti per i protocolli display.

SAMSUNG (MDC): VALIDATO su hardware reale.
LG: SPERIMENTALE — formato non ancora confermato su hardware reale.
    Il comando "xb" (Input Select) è quello documentato pubblicamente da LG
    per il protocollo seriale/IP "Set-ID", ma va testato con cautela
    (dry-run prima, poi un solo display) prima di usarlo su tutti gli schermi.
"""

import socket


SAMSUNG_CMD_INPUT_SOURCE = 0x14
SAMSUNG_CMD_WALL = 0x8B  # confermato via cattura di rete (ACK reale dal display)
LG_CMD_INPUT_SELECT = "xb"  # sperimentale, da verificare su hardware LG reale


def build_samsung_packet(display_id, data_bytes):
    """[0xAA][CMD][ID][LEN][DATA...][CHECKSUM] — CHECKSUM = somma(CMD..DATA) mod 256"""
    length = len(data_bytes)
    body = [SAMSUNG_CMD_INPUT_SOURCE, display_id, length] + data_bytes
    checksum = sum(body) & 0xFF
    return bytes([0xAA] + body + [checksum]), "binary"


def build_samsung_wall_packet(display_id, data_bytes):
    """Stesso formato di build_samsung_packet ma con CMD=0x8B (video wall).
    Validato su hardware reale: il display risponde con ACK (0x41)."""
    length = len(data_bytes)
    body = [SAMSUNG_CMD_WALL, display_id, length] + data_bytes
    checksum = sum(body) & 0xFF
    return bytes([0xAA] + body + [checksum]), "binary"


def build_lg_packet(display_id, data_bytes):
    """
    SPERIMENTALE. Formato ASCII ipotizzato (standard pubblico LG Set-ID):
        "<cmd1><cmd2> <ID hex> <DATA hex>\\r"
    Esempio: comando "A0" su display id 1 -> "xb 01 A0\\r"
    I byte in data_bytes nel file sono già i valori letterali (es. "A0"),
    quindi qui li trattiamo come stringa ASCII, non come byte binari.
    """
    # data_bytes qui arriva come lista di caratteri ASCII del comando originale
    # (vedi wallconfig_parser: per LG non convertiamo in hex, teniamo la stringa)
    data_str = data_bytes  # stringa tipo "A0"
    cmd = f"{LG_CMD_INPUT_SELECT} {display_id:02X} {data_str}\r"
    return cmd.encode("ascii"), "text"


def send_packet(ip, port, packet, timeout=3.0):
    with socket.create_connection((ip, port), timeout=timeout) as s:
        s.sendall(packet)
        try:
            s.settimeout(1.0)
            return s.recv(64)
        except socket.timeout:
            return None


def format_packet_for_display(packet, kind):
    if kind == "binary":
        return " ".join(f"{b:02X}" for b in packet)
    return repr(packet.decode("ascii", errors="replace"))
