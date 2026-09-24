#!/usr/bin/env bash
#
# Crea il pacchetto zip di release di Mail Bridge, completo di vendor/ e
# public/build, per l'installazione via FTP su hosting senza SSH.
#
# Il pacchetto contiene solo i file committati (git archive HEAD), più le
# dipendenze PHP di produzione e gli asset compilati. Non contiene .env,
# node_modules e test.
#
# Uso:
#   bin/build-release.sh                # -> dist/mail-bridge-<versione>.zip
#   bin/build-release.sh --no-build     # usa public/build committato senza ricompilare
#   bin/build-release.sh --output /tmp  # cartella di destinazione
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

OUT_DIR="$ROOT/dist"
BUILD=1

usage() {
    sed -n '2,13p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

info() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33mAttenzione:\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31mErrore:\033[0m %s\n' "$*" >&2; exit 1; }

while [ $# -gt 0 ]; do
    case "$1" in
        --no-build)  BUILD=0; shift ;;
        --output)    OUT_DIR="${2:-}"; shift 2 ;;
        --output=*)  OUT_DIR="${1#--output=}"; shift ;;
        -h|--help)   usage; exit 0 ;;
        *)           usage >&2; die "opzione sconosciuta: $1" ;;
    esac
done

for tool in git php composer zip; do
    command -v "$tool" >/dev/null 2>&1 || die "comando richiesto non trovato: $tool"
done
if [ "$BUILD" -eq 1 ]; then
    command -v npm >/dev/null 2>&1 || die "npm non trovato (usa --no-build per riusare public/build committato)"
fi

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || die "non è un repository git"

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    warn "ci sono modifiche non committate: il pacchetto conterrà solo i file committati"
fi

VERSION="$(git describe --tags --always 2>/dev/null || echo dev)"
NAME="mail-bridge-${VERSION}"
WORK="$(mktemp -d)"
STAGE="$WORK/$NAME"
trap 'rm -rf "$WORK"' EXIT

mkdir -p "$STAGE" "$OUT_DIR"
OUT_DIR="$(cd "$OUT_DIR" && pwd)"
ZIP="$OUT_DIR/$NAME.zip"

info "Esporto i file committati (HEAD = $VERSION)"
git archive --format=tar HEAD | tar -x -C "$STAGE"

cd "$STAGE"

info "Installo le dipendenze PHP di produzione"
COMPOSER_NO_INTERACTION=1 composer install --no-dev --prefer-dist --optimize-autoloader --no-progress --quiet

if [ "$BUILD" -eq 1 ]; then
    info "Compilo gli asset frontend"
    npm ci --no-audit --no-fund
    npm run build
else
    [ -f public/build/manifest.json ] || die "public/build/manifest.json mancante: senza --no-build verrebbe compilato"
    info "Uso public/build committato"
fi

info "Rimuovo ciò che non serve in produzione"
rm -rf node_modules tests
# Generati durante la build, servono solo a Vite.
rm -rf resources/js/actions resources/js/routes resources/js/wayfinder
# Gli script artisan lanciati da composer/vite possono aver creato il .env
# tramite EnvBootstrap: non deve MAI finire nel pacchetto (conterrebbe una
# APP_KEY condivisa da tutte le installazioni).
rm -f .env storage/app/installed.json storage/app/install-completed.json
find storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    -type f ! -name .gitignore -delete 2>/dev/null || true

[ ! -e .env ] || die "il .env è ancora presente nel pacchetto"
[ -f vendor/autoload.php ] || die "vendor/autoload.php mancante"
[ -f public/build/manifest.json ] || die "public/build/manifest.json mancante"

info "Creo $ZIP"
rm -f "$ZIP"
zip -qr -X "$ZIP" . -x '.git/*'

cd "$ROOT"
printf '\nPacchetto pronto: %s (%s)\n' "$ZIP" "$(du -h "$ZIP" | cut -f1)"
