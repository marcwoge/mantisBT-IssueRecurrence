#!/usr/bin/env bash
#
# Baut ein Release-Artefakt, das AUSSCHLIESSLICH den Plugin-Ordner
# IssueRecurrence/ enthaelt (fertig zum Entpacken nach mantisbt/plugins/).
#
# Ergebnis: IssueRecurrence-<version>.zip
#
set -euo pipefail
cd "$(dirname "$0")"

# Version aus der Plugin-Klasse lesen.
VERSION=$(grep -oE "this->version[[:space:]]*=[[:space:]]*'[^']+'" \
	IssueRecurrence/IssueRecurrence.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+")

if [ -z "${VERSION:-}" ]; then
	echo "Konnte Version nicht ermitteln." >&2
	exit 1
fi

OUT="IssueRecurrence-${VERSION}.zip"
rm -f "$OUT"

# Nur den Plugin-Ordner packen, ohne OS-/Editor-Muell.
zip -r "$OUT" IssueRecurrence \
	-x '*/.DS_Store' '*/Thumbs.db' '*.log' '*/.git/*' >/dev/null

echo "Erstellt: $OUT"
zip -sf "$OUT" | sed -n '1,8p'
