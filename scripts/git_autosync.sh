#!/bin/bash
# Auto-sync di STEPconsolle: se ci sono modifiche non commesse nel repo,
# le versiona e le pusha su GitHub. Pensato per girare da un timer systemd
# (stepconsolle-autosync.timer), non per essere lanciato a mano.
#
# Non fallisce mai in modo rumoroso se non c'è nulla da fare: esce
# silenziosamente se lo working tree è pulito, così il timer può girare
# ogni pochi minuti senza generare log/errori inutili.
set -euo pipefail

REPO_DIR="/var/www/STEPconsolle"
cd "$REPO_DIR"

git add -A

if git diff --cached --quiet; then
    exit 0
fi

git commit -m "Auto-sync $(date '+%Y-%m-%d %H:%M:%S')"
git push origin main
