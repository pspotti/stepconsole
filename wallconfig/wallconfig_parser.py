"""
wallconfig_parser.py — Parsing generico del file WallConfig.txt.

Estrae TUTTE le sezioni (#SET ... #ENDSET), con:
  - protocollo (SAMSUNG / LG)
  - elementi (#ELE: id, ip, porta, id_display)
  - setup (#SETUP ... #ENDSETUP), ognuno con i comandi #INP per elemento
"""

import re


ELE_RE = re.compile(r'#ELE\s+(\d+)\s+"([\d\.]+)"\s+(\d+)\s+(\d+)')
SET_START_RE = re.compile(r'#SET\s+"([^"]+)"')
SETUP_START_RE = re.compile(r'#SETUP\s+"([^"]+)"')
PROTOCOL_RE = re.compile(r'#PROTOCOL\s+(\S+)')
INP_RE = re.compile(r'^\s*(\d+)\s+#INP\s+"([^"]*)"')
WALL_RE = re.compile(r'^\s*(\d+)\s+#WALL\s+"((?:\\[0-9A-Fa-f]{1,2})+)"')


def _decode_hex_escapes(seq):
    """'\\21\\23' -> [0x21, 0x23]  (le cifre dopo il backslash sono ESADECIMALI,
    confermato empiricamente: il display Samsung ha risposto NAK quando le
    stesse cifre venivano lette come ottali, e ACK quando lette come hex —
    0x21/0x23/0x25 sono infatti gli ID ufficiali HDMI1/HDMI2/HDMI3)."""
    return [int(c, 16) for c in re.findall(r'\\([0-9A-Fa-f]{1,2})', seq)]


def parse_all_sections(path):
    """
    Ritorna un dict:
    {
      "GALLERY": {
          "protocol": "SAMSUNG",
          "elements": {1: {"ip": "...", "port": 1515, "display_id": 1}, ...},
          "setups": {"STANDARD": {1: [0x23], ...}, "GUEST": {...}}
      },
      ...
    }
    """
    with open(path, "r", encoding="utf-8", errors="ignore") as f:
        lines = f.readlines()

    sections = {}
    current_section = None
    current_setup = None

    for raw_line in lines:
        line = raw_line.strip()
        if not line or line.startswith(";"):
            continue

        m = SET_START_RE.match(line)
        if m:
            current_section = m.group(1)
            sections[current_section] = {
                "protocol": None,
                "elements": {},
                "setups": {},
                "wall_setups": {},
            }
            current_setup = None
            continue

        # #ENDSETUP va controllato PRIMA di #ENDSET (ne è un prefisso!)
        if line.startswith("#ENDSETUP"):
            current_setup = None
            continue

        if line.startswith("#ENDSET"):
            current_section = None
            current_setup = None
            continue

        if current_section is None:
            continue

        sec = sections[current_section]

        m = PROTOCOL_RE.match(line)
        if m:
            sec["protocol"] = m.group(1).upper()
            continue

        m = ELE_RE.search(line)
        if m:
            eid, ip, port, disp_id = m.groups()
            sec["elements"][int(eid)] = {
                "ip": ip,
                "port": int(port),
                "display_id": int(disp_id),
            }
            continue

        m = SETUP_START_RE.search(line)
        if m:
            current_setup = m.group(1)
            sec["setups"][current_setup] = {}
            sec["wall_setups"][current_setup] = {}
            continue

        if current_setup is not None:
            m = INP_RE.match(line)
            if m:
                eid, raw_value = m.groups()
                protocol = sec.get("protocol")
                if protocol == "SAMSUNG":
                    value = _decode_hex_escapes(raw_value)
                else:
                    # LG (o altro): comando testuale letterale, es. "A0"
                    value = raw_value
                sec["setups"][current_setup][int(eid)] = value
                continue

            m = WALL_RE.match(line)
            if m:
                eid, hex_seq = m.groups()
                sec["wall_setups"][current_setup][int(eid)] = _decode_hex_escapes(hex_seq)

    return sections
