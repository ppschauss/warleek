# Warleek – Wardogs Community-Website (warleek.de)

WordPress-Block-Theme `warleek` + lokale Dev-Umgebung + Seed-Content + Steam-Patch-Notes-Sync.
Spec: `docs/superpowers/specs/2026-09-14-warleek-website-design.md` · Plan: `docs/superpowers/plans/2026-09-14-warleek-website.md` · Deploy: `README-Deploy.md`

## Struktur
```
manage.sh                 Dev-Instanz (docker run): up|down|install|wp|seed|sync|zip|shot|urls
theme/warleek/            das Theme (Block-Theme)
  inc/                    options, bbcode, cpt-guide, cpt-patchnote, steam-sync, builders, blocks, seo
  templates/ parts/ patterns/ assets/
  _content/*.json         Seed-Inhalte (Seiten, Guides, Navigation, Site)   – nicht im Prod-Zip
  seed.php  tests/        Seed + Tests                                        – nicht im Prod-Zip
assets-src/               generierte Bilder/Logos/Video + MANIFEST.json (Alt-Texte) → Mediathek
docs/research/            Faktenbasis mit Quellen
```

## Lokal starten
```bash
./manage.sh up && ./manage.sh install     # http://192.168.0.161:8088 (admin / warleekadmin)
./manage.sh seed                          # Medien, Seiten, Guides, Navigation
./manage.sh sync                          # Patch Notes von Steam
./manage.sh shot /wardogs/fob/ 390 shots/fob.png   # Screenshot (headless Chromium in wh-web-check)
```
Tests: `docker run --rm -v $PWD/theme/warleek:/t php:8.3-cli php /t/tests/bbcode-test.php` und `./manage.sh wp eval-file wp-content/themes/warleek/tests/steam-sync-test.php`.

## Regel: alles editierbar
Kein Text und kein Bild ist im Theme hart verdrahtet. Inhalte sind Blöcke in Seiten/Beiträgen, Bilder liegen in der Mediathek, Logo = Website-Logo, Links = Einstellungen → Warleek. Patterns sind nur Startvorlagen.
