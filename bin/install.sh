#!/usr/bin/env bash
#
# Installa le dipendenze PHP di Mail Bridge sul server.
#
# Pensato per l'hosting condiviso (cPanel, Plesk...) dove il comando `php`
# non esiste o punta a una versione vecchia: cerca da solo un binario PHP
# >= 8.4.1 (es. /usr/local/bin/ea-php85), scarica composer.phar se serve e
# lancia `composer install --no-dev`.
#
# Lo script e' idempotente: se le dipendenze sono gia' installate e
# composer.lock non e' cambiato, esce subito senza fare nulla. Puo' quindi
# essere rilanciato a ogni deploy, o lasciato in un'attivita' pianificata,
# senza effetti collaterali.
#
# Uso:
#   bin/install.sh                    # rileva PHP e Composer automaticamente
#   bin/install.sh --php /usr/local/bin/ea-php85
#   bin/install.sh --dev              # include le dipendenze di sviluppo
#   bin/install.sh --force            # reinstalla anche se e' gia' aggiornato
#
# Variabili d'ambiente equivalenti: PHP_BIN, COMPOSER_BIN.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MIN_PHP_ID=80401
MIN_PHP_LABEL="8.4.1"
PHP_BIN="${PHP_BIN:-}"
COMPOSER_BIN="${COMPOSER_BIN:-}"
DEV=0
FORCE=0

# Impronta dell'installazione riuscita: vive dentro vendor/, così sparisce
# insieme alle dipendenze se la cartella viene cancellata.
STAMP="$ROOT/vendor/.install-stamp"

usage() {
    # Stampa il blocco di commenti iniziale, shebang escluso.
    awk 'NR > 1 && /^#/ { sub(/^# ?/, ""); print; next } NR > 1 { exit }' "${BASH_SOURCE[0]}"
}

info() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33mAttenzione:\033[0m %s\n' "$*" >&2; }
die()  { printf '\033[1;31mErrore:\033[0m %s\n' "$*" >&2; exit 1; }

while [ $# -gt 0 ]; do
    case "$1" in
        --php)        PHP_BIN="${2:-}"; shift 2 ;;
        --php=*)      PHP_BIN="${1#--php=}"; shift ;;
        --composer)   COMPOSER_BIN="${2:-}"; shift 2 ;;
        --composer=*) COMPOSER_BIN="${1#--composer=}"; shift ;;
        --dev)        DEV=1; shift ;;
        --force)      FORCE=1; shift ;;
        -h|--help)    usage; exit 0 ;;
        *)            usage >&2; die "opzione sconosciuta: $1" ;;
    esac
done

# --- PHP -------------------------------------------------------------------

php_is_recent() {
    "$1" -r "exit(PHP_VERSION_ID >= ${MIN_PHP_ID} ? 0 : 1);" >/dev/null 2>&1
}

find_php() {
    local candidates=(
        # cPanel / EasyApache
        ea-php85 ea-php84 ea-php83
        /usr/local/bin/ea-php85 /usr/local/bin/ea-php84 /usr/local/bin/ea-php83
        /opt/cpanel/ea-php85/root/usr/bin/php
        /opt/cpanel/ea-php84/root/usr/bin/php
        /opt/cpanel/ea-php83/root/usr/bin/php
        # Plesk
        /opt/plesk/php/8.5/bin/php /opt/plesk/php/8.4/bin/php /opt/plesk/php/8.3/bin/php
        # Debian/Ubuntu (deb.sury.org), Remi, generici
        php8.5 php85 php8.4 php84 php8.3 php83
        /usr/bin/php8.5 /usr/bin/php8.4 /usr/bin/php8.3
        /opt/remi/php85/root/usr/bin/php /opt/remi/php84/root/usr/bin/php /opt/remi/php83/root/usr/bin/php
        php
    )
    local candidate bin

    for candidate in "${candidates[@]}"; do
        if [[ "$candidate" == /* ]]; then
            bin="$candidate"
            [ -x "$bin" ] || continue
        else
            bin="$(command -v "$candidate" 2>/dev/null || true)"
            [ -n "$bin" ] || continue
        fi

        if php_is_recent "$bin"; then
            printf '%s\n' "$bin"
            return 0
        fi
    done

    return 1
}

if [ -n "$PHP_BIN" ]; then
    command -v "$PHP_BIN" >/dev/null 2>&1 || [ -x "$PHP_BIN" ] || die "PHP non trovato: $PHP_BIN"
    php_is_recent "$PHP_BIN" || die "$PHP_BIN è più vecchio di PHP ${MIN_PHP_LABEL} ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"
else
    PHP_BIN="$(find_php)" || die "nessun PHP >= ${MIN_PHP_LABEL} trovato. Indica il percorso con: $0 --php /percorso/php"
fi

info "PHP: $PHP_BIN ($("$PHP_BIN" -r 'echo PHP_VERSION;'))"

# --- Serve installare? -----------------------------------------------------

# Identifica lo stato atteso di vendor/: dipendenze bloccate + presenza o meno
# di quelle di sviluppo. Se combacia con l'ultima installazione riuscita non
# c'è niente da fare.
install_fingerprint() {
    "$PHP_BIN" -r 'echo is_file($argv[1]) ? hash_file("sha256", $argv[1]) : "no-lock";' "$ROOT/composer.lock"
    printf ' dev=%s\n' "$DEV"
}

if [ "$FORCE" -eq 0 ] \
    && [ -f "$ROOT/vendor/autoload.php" ] \
    && [ -f "$STAMP" ] \
    && [ "$(cat "$STAMP")" = "$(install_fingerprint)" ]; then
    info "Dipendenze già installate e aggiornate: niente da fare."
    exit 0
fi

# Composer ha bisogno di una home scrivibile per la cache. In alcuni cron
# $HOME non è scrivibile (o non è impostata): in quel caso la mettiamo dentro
# storage/, che è già ignorata da git.
if [ -z "${COMPOSER_HOME:-}" ] && ! { [ -n "${HOME:-}" ] && [ -w "${HOME:-}" ]; }; then
    export COMPOSER_HOME="$ROOT/storage/composer"
    mkdir -p "$COMPOSER_HOME"
    warn "\$HOME non scrivibile: uso $COMPOSER_HOME come home di Composer"
fi

# --- Composer --------------------------------------------------------------

download() {
    # download URL DESTINAZIONE — usa curl, wget o in ultima istanza PHP.
    if command -v curl >/dev/null 2>&1; then
        curl -fsSL "$1" -o "$2"
    elif command -v wget >/dev/null 2>&1; then
        wget -qO "$2" "$1"
    else
        "$PHP_BIN" -r 'copy($argv[1], $argv[2]) || exit(1);' "$1" "$2"
    fi
}

install_composer() {
    local setup="$ROOT/composer-setup.php" expected actual

    info "Composer non trovato: scarico composer.phar da getcomposer.org"

    download https://getcomposer.org/installer "$setup"
    expected="$(download https://composer.github.io/installer.sig /dev/stdout | tr -d '[:space:]')"
    actual="$("$PHP_BIN" -r "echo hash_file('sha384', '$setup');")"

    if [ "$expected" != "$actual" ]; then
        rm -f "$setup"
        die "firma dell'installer di Composer non valida: download corrotto o manomesso"
    fi

    "$PHP_BIN" "$setup" --quiet --install-dir="$ROOT" --filename=composer.phar
    rm -f "$setup"
}

if [ -z "$COMPOSER_BIN" ]; then
    if [ -f "$ROOT/composer.phar" ]; then
        COMPOSER_BIN="$ROOT/composer.phar"
    elif command -v composer >/dev/null 2>&1; then
        COMPOSER_BIN="$(command -v composer)"
    else
        install_composer
        COMPOSER_BIN="$ROOT/composer.phar"
    fi
fi

[ -f "$COMPOSER_BIN" ] || die "Composer non trovato: $COMPOSER_BIN"

info "Composer: $COMPOSER_BIN"

# --- Dipendenze ------------------------------------------------------------

export COMPOSER_MEMORY_LIMIT=-1
export COMPOSER_NO_INTERACTION=1


COMPOSER_ARGS=(install --prefer-dist --optimize-autoloader --no-progress)
if [ "$DEV" -eq 0 ]; then
    COMPOSER_ARGS+=(--no-dev)
fi

info "Installo le dipendenze PHP (${COMPOSER_ARGS[*]})"
"$PHP_BIN" "$COMPOSER_BIN" "${COMPOSER_ARGS[@]}"

install_fingerprint > "$STAMP"

# Cartelle che Laravel deve poter scrivere.
chmod -R u+rwX storage bootstrap/cache 2>/dev/null || warn "impossibile aggiornare i permessi di storage/ e bootstrap/cache"

# --- Prossimi passi --------------------------------------------------------

cat <<MSG

Dipendenze installate. Prossimi passi:

  1. Punta il document root del sito su:  $ROOT/public
  2. Apri il sito nel browser: verrai reindirizzato al wizard /install
     (il file .env e la APP_KEY vengono creati dal wizard).
  3. Aggiungi il cron (ogni minuto) che processa la coda email:

     * * * * * cd $ROOT && $PHP_BIN artisan schedule:run >> /dev/null 2>&1

MSG
