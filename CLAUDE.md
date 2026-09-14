# warleek (Ordner wardogs-gc)

Wardogs-Community-Website **Warleek** (warleek.de): WordPress-Block-Theme `theme/warleek`, Dev via `./manage.sh` (plain `docker run`, Port 8088, Login admin/warleekadmin – nur lokal), Prod auf All-Inkl (`README-Deploy.md`).

## Befehle
- `./manage.sh up|down|install|wp <args>|seed|sync [--force]|zip|shot <pfad> [breite] [datei] [hoehe]|urls`
- Tests: `docker run --rm -v $PWD/theme/warleek:/t php:8.3-cli php /t/tests/bbcode-test.php`, `./manage.sh wp eval-file wp-content/themes/warleek/tests/steam-sync-test.php`
- Nach Änderungen an `patterns/`: Pattern-Cache ist versionsgebunden → `./manage.sh seed` (löscht ihn) oder `wp eval 'wp_get_theme()->delete_pattern_cache();'`

## Regeln
- **Alles editierbar:** kein Content in Templates/Patterns hart verdrahtet; Bilder in der Mediathek; Logo = Website-Logo; Links/Clan-Tag = Optionsseite (`warleek_opt()`).
- Inhalte kommen aus `theme/warleek/_content/*.json` → `seed.php` (idempotent). Textänderungen dort **und** per Seed einspielen, oder direkt im Backend (dann JSON nicht mehr Quelle der Wahrheit).
- Asset-URLs root-relativ, Fonts self-hosted (DSGVO), keine externen Requests außer Steam-CDN in Patch Notes.
- Spielzahlen immer mit „Stand: <Monat Jahr>" (Early Access). Faktenbasis: `docs/research/wardogs-facts.md`.
- Shell-Hinweis: `cd` persistiert nicht zuverlässig zwischen Befehlen → absolute Pfade / `git -C`.
- Screenshots: `?snap=1` friert Motion ein, deaktiviert Lazy-Loading; `--disable-dev-shm-usage` ist Pflicht.

## Docs
Spec `docs/superpowers/specs/2026-09-14-warleek-website-design.md`, Plan `docs/superpowers/plans/2026-09-14-warleek-website.md`, Verifikation `docs/verification.md`.
