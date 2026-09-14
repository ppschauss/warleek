# Warleek – Deploy auf All-Inkl (warleek.de)

Produktion: All-Inkl (Shared Hosting, PHP ≥ 8.1, MySQL). Dev: lokal via `./manage.sh` (siehe README.md).

## Was du brauchst
- `warleek.zip` (Theme ohne Seed-Dateien) → `./manage.sh zip`
- `theme/warleek/_content/` + `theme/warleek/seed.php` + `assets-src/` (nur fürs einmalige Seeding)
- RankMath SEO (Plugin, kostenlos)
- Die Invite-Links für Discord / WhatsApp / Telegram

## Variante A – KAS-Panel (ohne SSH)
1. **WordPress installieren:** KAS → Software-Installer → WordPress auf `warleek.de`. Sprache Deutsch, Permalinks später.
2. **Theme hochladen:** WP-Admin → Design → Themes → Hinzufügen → Theme hochladen → `warleek.zip` → aktivieren.
3. **Plugin:** RankMath SEO installieren & aktivieren (Setup-Wizard: Sitemap an, Titles nach Vorgabe – die Seiten bringen `rank_math_title/description` bereits mit).
4. **Seed (einmalig):** Per FTP in `wp-content/themes/warleek/` die Ordner `_content/` und die Datei `seed.php` hochladen, außerdem `assets-src/` als `wp-content/themes/warleek/_assets-src/`. Dann WP-Admin → Werkzeuge → *(WP-CLI fehlt hier)* – deshalb: Variante B empfohlen. Ohne SSH: die Seiten lassen sich alternativ aus einem Export der Dev-Instanz importieren (`./manage.sh wp export --post_type=page,guide,attachment,wp_navigation`), Werkzeuge → Importieren.
5. **Optionen:** Einstellungen → Warleek → Discord/WhatsApp/Telegram-Links, Clan-Tag, Kontakt-Mail.
6. **Permalinks:** Einstellungen → Permalinks → „Beitragsname".
7. **Impressum/Datenschutz** mit echten Daten füllen (Platzhalter in eckigen Klammern).

## Variante B – SSH / WP-CLI (empfohlen)
All-Inkl Premium hat SSH (Host/User im KAS unter „SSH-Zugang"). `wp` ist dort in der Regel vorhanden; sonst `wp-cli.phar` ins Home laden.

```bash
# 0) Lokal: Zip bauen
./manage.sh zip

# 1) Theme + Seed-Material hochladen (Pfad an dein Paket anpassen)
REMOTE=ssh-wXXXXXXX@wXXXXXXX.kasserver.com
DOCROOT=/www/htdocs/wXXXXXXX/warleek.de
rsync -avz --delete theme/warleek/ $REMOTE:$DOCROOT/wp-content/themes/warleek/
rsync -avz assets-src/ --exclude raw $REMOTE:$DOCROOT/wp-content/themes/warleek/_assets-src/

# 2) Auf dem Server
ssh $REMOTE
cd $DOCROOT
wp theme activate warleek
wp plugin install seo-by-rank-math --activate
wp language core install de_DE --activate
wp option update timezone_string Europe/Berlin
wp rewrite structure '/%postname%/' --hard

# 3) Seed (Medien in Mediathek, Seiten, Guides, Navigation, Logo, Startseite)
wp eval-file wp-content/themes/warleek/seed.php
wp warleek sync-patchnotes
wp rewrite flush --hard

# 4) Seed-Material entfernen (Inhalte liegen jetzt in der DB/Mediathek)
rm -rf wp-content/themes/warleek/_content wp-content/themes/warleek/_assets-src wp-content/themes/warleek/seed.php wp-content/themes/warleek/tests

# 5) Optionen setzen
wp option patch update warleek_options discord_url  'https://discord.gg/DEIN-INVITE'
wp option patch update warleek_options whatsapp_url 'https://chat.whatsapp.com/DEIN-LINK'
wp option patch update warleek_options telegram_url 'https://t.me/DEINE-GRUPPE'
wp option patch update warleek_options clan_tag '[WLK]'
wp option patch update warleek_options kontakt_email 'kontakt@warleek.de'
```

## Nach dem Deploy – Pflicht
- **SSL** im KAS aktivieren (Let's Encrypt), `wp option update home https://warleek.de && wp option update siteurl https://warleek.de`, Redirect http→https im KAS.
- **Impressum & Datenschutz** ausfüllen (Seiten → Impressum / Datenschutz, Platzhalter `[…]`).
- **About us:** Team-Karten mit echten Namen/Texten/Bildern füllen (Seite bearbeiten, Bild-Block ersetzen).
- **RankMath:** Setup-Wizard, Sitemap an, Search Console verbinden. Die Seed-Titles/-Descriptions sind bereits als RankMath-Meta gesetzt.
- **Patch-Notes-Cron:** WP-Cron läuft bei Seitenaufrufen (stündlich). Zuverlässiger: im KAS einen Cronjob anlegen, der stündlich `https://warleek.de/wp-cron.php?doing_wp_cron` aufruft, und in `wp-config.php` `define('DISABLE_WP_CRON', true);` setzen.
- **Logo tauschen** (falls andere Variante gewünscht): Design → Website-Editor → Header → Logo anklicken → Ersetzen. Varianten liegen in `assets-src/logo/` (Icon, Crest, Lockup, jeweils PNG + SVG).

## Inhalte pflegen (Backend)
- **Seiten:** Seiten → bearbeiten. Hero = Bild-Block + Video-Block (ersetzen über „Ersetzen"), Texte sind normale Absätze/Überschriften/Listen/Tabellen.
- **Guides:** Guides → Neu. Beitragsbild, Auszug (erscheint auf Karten), Thema zuweisen.
- **Patch Notes:** kommen automatisch. Manuell: Einstellungen → Warleek → „Jetzt synchronisieren" oder `wp warleek sync-patchnotes [--force]`.
- **Navigation:** Design → Website-Editor → Navigation („Hauptmenü", „Footer").
- **Chat-Links / Clan-Tag:** Einstellungen → Warleek.
- **Patterns:** Im Editor unter „Muster → Warleek" (Hero, Drei Stufen, Chat-CTA, FAQ, Zahlen, Team, Karten).

## Update des Themes
Nur `theme/warleek/` neu hochladen (rsync wie oben, `--exclude '_*' --exclude seed.php --exclude tests`). Inhalte bleiben unberührt.
