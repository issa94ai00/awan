#!/bin/bash
# Regenerate public/erd.html (schema docs + ERD + health + workflow + logic diagnostics) from the live DB. Read-only on the DB.
set -e
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"; HERE="$ROOT/scripts/erd"; TMP="$(mktemp -d)"
cd "$ROOT"
OUT="$TMP/schema.json" php artisan tinker --execute="require '$HERE/dump.php';" < /dev/null > /dev/null
DIAG_OUT="$TMP/diag.json" php artisan tinker --execute="require '$HERE/diagnose.php';" < /dev/null > /dev/null
WF_OUT="$TMP/wf.json" php artisan tinker --execute="require '$HERE/workflow.php';" < /dev/null > /dev/null
LOGIC_OUT="$TMP/logic.json" php artisan tinker --execute="require '$HERE/logic.php';" < /dev/null > /dev/null
python3 -I "$HERE/gen_erd.py" "$TMP/schema.json" "$TMP/erd.html" "$HERE/erd_template.html" "$TMP/diag.json" "$TMP/wf.json" "$TMP/logic.json"
install -m 644 "$TMP/erd.html" "$ROOT/public/erd.html"
rm -rf "$TMP"
echo "erd.html updated"
