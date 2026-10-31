#!/usr/bin/env bash
# Export the dossier (PDF + HTML, English and French) and offer to open it.
#
#   scripts/dossier.sh            # latest target
#   scripts/dossier.sh 2          # a specific target id
#   scripts/dossier.sh 2 --lang=fr
#
# Files land in dossier-exports/<target>/ in this project folder.
set -euo pipefail

cd "$(dirname "$0")/.."

docker compose exec -T app php artisan sectf:dossier "$@" || {
    echo
    echo "Export finished with warnings (see above). The HTML files, if listed, are still usable."
}

FOLDER="$(ls -dt dossier-exports/*/ 2>/dev/null | head -1 || true)"
[ -n "$FOLDER" ] || exit 0
PDF="$(ls -t "${FOLDER}"*.pdf 2>/dev/null | head -1 || true)"

open_path() {
    if command -v explorer.exe >/dev/null 2>&1 && command -v wslpath >/dev/null 2>&1; then
        # explorer.exe exits 1 even on success; it only needs the Windows path
        explorer.exe "$(wslpath -w "$1")" || true
    elif command -v open >/dev/null 2>&1; then
        open "$1"
    elif command -v xdg-open >/dev/null 2>&1; then
        xdg-open "$1" >/dev/null 2>&1 &
    else
        echo "No opener found. Open this yourself: $PWD/$1"
    fi
}

[ -t 0 ] || exit 0   # not interactive: just print, never block

echo
printf 'Open now?  [p] the PDF   [f] the folder (PDF + HTML)   [Enter] nothing: '
read -r answer
case "$answer" in
    p|P) [ -n "$PDF" ] && open_path "$PDF" || echo "No PDF was produced." ;;
    f|F) open_path "$FOLDER" ;;
esac
