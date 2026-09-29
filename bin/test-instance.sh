#!/bin/bash
# Warleek — Wegwerf-Instanz für Go-live-Proben (Port 8089, leere Datenbank).
#
# Anders als die Dev-Instanz (manage.sh) wird hier **nichts** hineingemountet:
# Theme und Plugin kommen als echte Kopien aus den gebauten Zips in dist/.
# Genau so landen sie später auf dem Server – Bind-Mounts würden Update- und
# Installationsfehler verdecken.
#
# Nutzung: bin/test-instance.sh fresh | install | wp <args> | urls | down
#   fresh    Container und Volumes wegwerfen, alles neu starten (leere DB)
#   install  WordPress einrichten, Theme und Plugin aus dist/ einspielen
#   down     Container und Volumes entfernen
set -e
cd "$(dirname "$0")/.."

NET=warleek-test-net
DBV=warleek-test-db-data
WPV=warleek-test-wp-data
DB=warleek-test-db
WP=warleek-test-wp
PORT=8089
DBPASS=testpass
HOSTIP=${HOSTIP:-192.168.0.161}
DIST_MOUNT="$(pwd)/dist:/dist:ro"
CONFIG_EXTRA='if(isset($_SERVER["HTTP_HOST"])){$s=(!empty($_SERVER["HTTPS"])&&$_SERVER["HTTPS"]!=="off")?"https":"http";if(!defined("WP_HOME"))define("WP_HOME",$s."://".$_SERVER["HTTP_HOST"]);if(!defined("WP_SITEURL"))define("WP_SITEURL",$s."://".$_SERVER["HTTP_HOST"]);}'

wpcli() {
  docker run --rm --network "$NET" --entrypoint wp \
    -v "$WPV:/var/www/html" -v "$DIST_MOUNT" \
    -e WORDPRESS_DB_HOST="$DB" -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DBPASS" -e WORDPRESS_DB_NAME=wordpress \
    --user 33 wordpress:cli "$@"
}

fresh() {
  docker rm -f "$WP" "$DB" >/dev/null 2>&1 || true
  docker volume rm "$WPV" "$DBV" >/dev/null 2>&1 || true
  docker network create "$NET" >/dev/null 2>&1 || true
  docker run -d --name "$DB" --network "$NET" \
    -e MARIADB_ROOT_PASSWORD=testroot -e MARIADB_DATABASE=wordpress \
    -e MARIADB_USER=wp -e MARIADB_PASSWORD="$DBPASS" \
    -v "$DBV:/var/lib/mysql" mariadb:11 >/dev/null
  printf "warte auf DB"
  for i in $(seq 1 40); do
    docker exec "$DB" mariadb-admin ping -uwp -p"$DBPASS" -h127.0.0.1 --silent 2>/dev/null && break
    printf "."; sleep 2
  done; echo
  docker run -d --name "$WP" --network "$NET" -p "$PORT:80" \
    -e TZ=Europe/Berlin \
    -e WORDPRESS_DB_HOST="$DB" -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DBPASS" -e WORDPRESS_DB_NAME=wordpress \
    -e WORDPRESS_CONFIG_EXTRA="$CONFIG_EXTRA" \
    -v "$WPV:/var/www/html" -v "$DIST_MOUNT" \
    wordpress:php8.3-apache >/dev/null
  printf "warte auf WordPress"
  for i in $(seq 1 40); do
    [ "$(curl -s -o /dev/null -w '%{http_code}' "http://$HOSTIP:$PORT/")" != "000" ] && break
    printf "."; sleep 2
  done; echo
  echo "frische Instanz auf http://$HOSTIP:$PORT"
}

install() {
  local theme core
  theme=$(ls -1 dist/warleek-theme-*.zip | tail -1)
  core=$(ls -1 dist/warleek-core-*.zip | tail -1)
  [ -n "$theme" ] && [ -n "$core" ] || { echo "Keine Zips in dist/ – erst bin/release.sh laufen lassen."; exit 1; }
  wpcli core install --url="http://$HOSTIP:$PORT" --title="Warleek (Test)" \
    --admin_user=admin --admin_password=testadmin --admin_email=admin@warleek.de --skip-email
  wpcli language core install de_DE --activate || true
  wpcli theme install "/dist/$(basename "$theme")" --activate
  wpcli plugin install "/dist/$(basename "$core")" --activate
  wpcli rewrite structure '/%postname%/' --hard
  echo "Eingespielt: $(basename "$theme"), $(basename "$core")"
  echo "Admin: http://$HOSTIP:$PORT/wp-admin  (admin / testadmin — nur lokal!)"
}

case "$1" in
  fresh)   fresh ;;
  install) install ;;
  wp)      shift; wpcli "$@" ;;
  urls)    echo "Frontend: http://$HOSTIP:$PORT   Admin: http://$HOSTIP:$PORT/wp-admin (admin / testadmin)" ;;
  down)    docker rm -f "$WP" "$DB" >/dev/null 2>&1 || true
           docker volume rm "$WPV" "$DBV" >/dev/null 2>&1 || true
           docker network rm "$NET" >/dev/null 2>&1 || true
           echo "Testinstanz entfernt (Container und Volumes)." ;;
  *) echo "Nutzung: $0 {fresh|install|wp <args>|urls|down}" ;;
esac
