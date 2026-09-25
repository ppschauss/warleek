# Warleek – Wardogs Community-Website (warleek.de)

WordPress-Block-Theme `warleek` + lokale Dev-Umgebung + Seed-Content + Steam-Patch-Notes-Sync.
Spec: `docs/superpowers/specs/2026-09-14-warleek-website-design.md` · Plan: `docs/superpowers/plans/2026-09-14-warleek-website.md` · Deploy: `README-Deploy.md`

## Struktur
```
manage.sh                 Dev-Instanz (docker run): up|down|install|wp|seed|sync|zip|shot|urls
theme/warleek/            Theme = nur Design
  theme.json templates/ parts/ patterns/ assets/ functions.php
plugin/warleek-core/      Plugin = Funktion + Inhalte
  warleek-core.php        Bootstrap, Aktivierung, Cron
  inc/                    options, bbcode, cpt-guide, cpt-patchnote, steam-sync,
                          builders, blocks, seo, privacy, installer, cli
  content/_content/*.json Seiten, Guides, Navigation, Site-Daten
  content/{img,logo,video}  Bilder, Logo-Varianten, Hero-Video + MANIFEST.json (Alt-Texte)
  tests/                  BBCode- und Sync-Tests                 – nicht im Prod-Zip
assets-src/               Originale der generierten Medien (raw/ nur lokal)
docs/research/            Faktenbasis mit Quellen
```

Warum die Trennung: Inhalte, Patch Notes und Einstellungen gehören ins Plugin, damit ein
Theme-Wechsel oder -Update sie nicht mitnimmt. Das Theme bringt nur noch Aussehen mit.

## Lokal starten
```bash
./manage.sh up && ./manage.sh install     # http://192.168.0.161:8088 (admin / warleekadmin)
./manage.sh seed                          # = wp warleek install (Medien, Seiten, Guides, Menüs)
./manage.sh seed --force                  # Texte aus _content/*.json neu einspielen
./manage.sh sync                          # Patch Notes von Steam
./manage.sh zip                           # warleek-theme.zip + warleek-core.zip
./manage.sh shot /wardogs/fob/ 390 shots/fob.png   # Screenshot (headless Chromium in wh-web-check)
```
Tests:
```bash
docker run --rm -v $PWD/plugin/warleek-core:/c php:8.3-cli php /c/tests/bbcode-test.php
./manage.sh wp eval-file wp-content/plugins/warleek-core/tests/steam-sync-test.php
./manage.sh wp warleek status
```

## Regel: alles editierbar
Kein Text und kein Bild ist im Theme oder Plugin hart verdrahtet. Inhalte sind Blöcke in Seiten/Beiträgen, Bilder liegen in der Mediathek, Logo = Website-Logo, Links = Einstellungen → Warleek. Patterns sind nur Startvorlagen.
