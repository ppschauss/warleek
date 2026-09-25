#!/bin/bash
# Warleek — Verwaltung der lokalen Dev-Instanz (plain docker run, Host ohne compose-Plugin).
# Nutzung: ./manage.sh up | down | restart | logs | install [url] | wp <args> | seed | sync [--force] | zip | shot <pfad> [breite] [datei] [hoehe] | urls
set -e
cd "$(dirname "$0")"

NET=warleek-net
DBV=warleek-db-data
WPV=warleek-wp-data
DB=warleek-db
WP=warleek-wp
PORT=8088
DBPASS=wppass
HOSTIP=${HOSTIP:-192.168.0.161}
THEME_MOUNT="$(pwd)/theme/warleek:/var/www/html/wp-content/themes/warleek"
PLUGIN_MOUNT="$(pwd)/plugin/warleek-core:/var/www/html/wp-content/plugins/warleek-core"
ASSETS_MOUNT="$(pwd)/assets-src:/assets-src:ro"
# Dynamische Site-URL (einzeilig für docker run -e) — behebt localhost-Redirect. Dev-only!
CONFIG_EXTRA='if(isset($_SERVER["HTTP_HOST"])){$s=(!empty($_SERVER["HTTPS"])&&$_SERVER["HTTPS"]!=="off")?"https":"http";if(!defined("WP_HOME"))define("WP_HOME",$s."://".$_SERVER["HTTP_HOST"]);if(!defined("WP_SITEURL"))define("WP_SITEURL",$s."://".$_SERVER["HTTP_HOST"]);}'

wpcli() {
  docker run --rm --network "$NET" --entrypoint wp \
    -v "$WPV:/var/www/html" -v "$THEME_MOUNT" -v "$PLUGIN_MOUNT" -v "$ASSETS_MOUNT" \
    -e WORDPRESS_DB_HOST="$DB" -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DBPASS" -e WORDPRESS_DB_NAME=wordpress \
    --user 33 wordpress:cli "$@"
}

up() {
  docker network create "$NET" 2>/dev/null || true
  docker rm -f "$DB" 2>/dev/null || true
  docker run -d --name "$DB" --network "$NET" --restart unless-stopped \
    -e MARIADB_ROOT_PASSWORD=warleekroot -e MARIADB_DATABASE=wordpress \
    -e MARIADB_USER=wp -e MARIADB_PASSWORD="$DBPASS" \
    -v "$DBV:/var/lib/mysql" mariadb:11 >/dev/null
  printf "warte auf DB"
  for i in $(seq 1 40); do
    docker exec "$DB" mariadb-admin ping -uwp -p"$DBPASS" -h127.0.0.1 --silent 2>/dev/null && break
    printf "."; sleep 2
  done; echo
  docker rm -f "$WP" 2>/dev/null || true
  docker run -d --name "$WP" --network "$NET" --restart unless-stopped -p "$PORT:80" \
    -e TZ=Europe/Berlin \
    -e WORDPRESS_DB_HOST="$DB" -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DBPASS" -e WORDPRESS_DB_NAME=wordpress \
    -e WORDPRESS_CONFIG_EXTRA="$CONFIG_EXTRA" \
    -v "$WPV:/var/www/html" -v "$THEME_MOUNT" -v "$PLUGIN_MOUNT" -v "$ASSETS_MOUNT" \
    wordpress:php8.3-apache >/dev/null
  echo "gestartet auf Port $PORT → http://$HOSTIP:$PORT"
}

install() {
  local url="${1:-http://$HOSTIP:$PORT}"
  wpcli core install --url="$url" --title="Warleek" \
    --admin_user=admin --admin_password=warleekadmin --admin_email=admin@warleek.de --skip-email
  wpcli language core install de_DE --activate || true
  wpcli theme activate warleek
  wpcli plugin activate warleek-core
  wpcli option update timezone_string Europe/Berlin
  wpcli option update blogdescription "Wardogs Community, Team & Clan für Deutschland, Österreich und die Schweiz"
  wpcli rewrite structure '/%postname%/' --hard
  echo "Installiert. Admin: $url/wp-admin  (admin / warleekadmin)"
}

seed() {
  wpcli warleek install "$@"
  wpcli rewrite flush --hard
}

sync() { wpcli warleek sync-patchnotes "$@"; }

zipit() {
  rm -f warleek-theme.zip warleek-core.zip
  (cd theme && zip -qr ../warleek-theme.zip warleek)
  (cd plugin && zip -qr ../warleek-core.zip warleek-core -x 'warleek-core/tests/*')
  ls -la warleek-theme.zip warleek-core.zip
}

shot() {
  # $1 Pfad (z.B. /community/), $2 Breite, $3 Ausgabedatei, $4 Höhe
  local path="${1:-/}" w="${2:-1440}" out="${3:-shots/shot.png}" h="${4:-2400}"
  local sep='?'; [[ "$path" == *\?* ]] && sep='&'
  docker exec wh-web-check chromium --headless=new --no-sandbox --disable-dev-shm-usage --hide-scrollbars \
    --virtual-time-budget=6000 --screenshot=/tmp/shot.png --window-size="$w,$h" "http://$HOSTIP:$PORT${path}${sep}snap=1" >/dev/null 2>&1 || true
  docker cp wh-web-check:/tmp/shot.png "$out"
  echo "→ $out"
}

case "$1" in
  up)      up ;;
  down)    docker rm -f "$WP" "$DB" 2>/dev/null || true; echo "gestoppt (Volumes bleiben)." ;;
  restart) docker restart "$WP" "$DB" ;;
  logs)    docker logs -f "$WP" ;;
  install) shift; install "$@" ;;
  wp)      shift; wpcli "$@" ;;
  seed)    shift; seed "$@" ;;
  sync)    shift; sync "$@" ;;
  zip)     zipit ;;
  shot)    shift; shot "$@" ;;
  urls)    echo "Frontend: http://$HOSTIP:$PORT   Admin: http://$HOSTIP:$PORT/wp-admin (admin / warleekadmin)" ;;
  *) echo "Nutzung: $0 {up|down|restart|logs|install [url]|wp <args>|seed|sync [--force]|zip|shot <pfad> [breite] [datei] [hoehe]|urls}" ;;
esac
