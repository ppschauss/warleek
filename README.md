# Warleek – Wardogs Guides & Tipps (warleek.de)

Deutschsprachige Guide-Seite zum Spiel WARDOGS: Guides mit Themenfilter, automatisch
übersetzte Patch Notes, Partner-Communities. Besteht aus einem Block-Theme (Design)
und dem Plugin *Warleek Core* (Funktion + Inhalte).

Spec: `docs/superpowers/specs/` · Deploy: `README-Deploy.md` · Prüfprotokoll: `docs/verification.md`

## Struktur
```
manage.sh                 Dev-Instanz (docker run): up|down|install|wp|seed|sync|zip|shot|urls
bin/release.sh            Pakete bauen und als GitHub-Release veröffentlichen
theme/warleek/            Theme = nur Design
  theme.json templates/ parts/ patterns/ assets/ functions.php
plugin/warleek-core/      Plugin = Funktion + Inhalte
  warleek-core.php        Bootstrap, Aktivierung, Cron
  inc/                    options, bbcode, markdown, cpt-guide, cpt-patchnote, cpt-partner,
                          steam-sync, translate, builders, blocks, seo, privacy, layout,
                          import-guides, updater, installer, cli
  content/_content/*.json Seiten, Guides, Partner, Navigation, Site-Daten, zurückgezogene Seiten
  content/{img,logo,video}  Medien + MANIFEST.json (Alt-Texte)
  tests/                  BBCode-, Markdown-, Sync-, Übersetzungs- und Updater-Tests
assets-src/               Originale der generierten Medien (raw/ nur lokal)
docs/research/            Faktenbasis mit Quellen
```

Warum die Trennung: Inhalte, Patch Notes und Einstellungen gehören ins Plugin, damit ein
Theme-Wechsel oder -Update sie nicht mitnimmt. Das Theme bringt nur das Aussehen mit.

## Lokal starten
```bash
./manage.sh up && ./manage.sh install     # http://192.168.0.161:8088 (admin / warleekadmin)
./manage.sh seed                          # = wp warleek install (Medien, Seiten, Guides, Partner, Menüs)
./manage.sh seed --force                  # Texte aus _content/*.json neu einspielen
./manage.sh sync                          # Patch Notes von Steam
./manage.sh zip                           # warleek-theme.zip + warleek-core.zip
./manage.sh shot /guides/ 390 shots/guides.png   # Screenshot (headless Chromium in wh-web-check)
```

## Inhalte pflegen
```bash
./manage.sh wp warleek import-guides <pfad> --dry-run   # Markdown/ZIP einspielen
./manage.sh wp warleek translate-patchnotes             # Patch Notes auf Deutsch
./manage.sh wp warleek status                           # Übersicht
```
Im Backend: **Warleek → Installation** (Statusübersicht und Schritte), **Guides importieren**
(Markdown-Upload mit Trockenlauf), **Einstellungen** (Discord, Social, Übersetzung).

## Tests
```bash
docker run --rm -v $PWD/plugin/warleek-core:/c php:8.3-cli php /c/tests/bbcode-test.php
docker run --rm -v $PWD/plugin/warleek-core:/c php:8.3-cli php /c/tests/markdown-test.php
./manage.sh wp eval-file wp-content/plugins/warleek-core/tests/steam-sync-test.php
./manage.sh wp eval-file wp-content/plugins/warleek-core/tests/translate-test.php   # ohne API-Kosten
./manage.sh wp eval-file wp-content/plugins/warleek-core/tests/updater-test.php     # ohne Netzaufruf
```

## Veröffentlichen
```bash
bin/release.sh              # Trockenlauf: baut die Pakete, prüft alle Versionsstellen
bin/release.sh --publish    # Tag + GitHub-Release, Pakete als Anhang
```
Danach meldet WordPress das Update auf jeder Installation von selbst.

## Regel: alles editierbar
Kein Text und kein Bild ist im Theme oder Plugin hart verdrahtet. Inhalte sind Blöcke in
Seiten/Beiträgen, Bilder liegen in der Mediathek, Logo = Website-Logo, Links = Einstellungen → Warleek.
