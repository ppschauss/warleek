# warleek (Ordner wardogs-gc)

Wardogs-Community-Website **Warleek** (warleek.de): Block-Theme `theme/warleek` (nur Design) + Plugin `plugin/warleek-core` (CPTs, Steam-Sync, Optionen, Blöcke, SEO, Inhalte, Installer). Dev via `./manage.sh` (plain `docker run`, Port 8088, Login admin/warleekadmin – nur lokal), Testinstanz für Go-live-Proben auf Port 8089 (`warleek-test-*`), Prod auf All-Inkl (`README-Deploy.md`).

## Befehle
- `./manage.sh up|down|install|wp <args>|seed|sync [--force]|zip|shot <pfad> [breite] [datei] [hoehe]|urls`
- Tests: `docker run --rm -v $PWD/plugin/warleek-core:/c php:8.3-cli php /c/tests/bbcode-test.php`, `./manage.sh wp eval-file wp-content/plugins/warleek-core/tests/steam-sync-test.php`, `./manage.sh wp warleek status`
- Nach Änderungen an `patterns/`: Pattern-Cache ist versionsgebunden → `./manage.sh seed` (löscht ihn) oder `wp eval 'wp_get_theme()->delete_pattern_cache();'`

## Regeln
- **Alles editierbar:** kein Content in Templates/Patterns hart verdrahtet; Bilder in der Mediathek; Logo = Website-Logo; Links/Clan-Tag = Optionsseite (`warleek_opt()`).
- Inhalte kommen aus `plugin/warleek-core/content/_content/*.json` → Installer (idempotent). Textänderungen dort **und** per `./manage.sh seed --force` einspielen, oder direkt im Backend (dann JSON nicht mehr Quelle der Wahrheit).
- Im Installer niemals `echo` verwenden – Ausgabe zerstört die JSON-Antwort. Stattdessen `warleek_log()`; jeder Schritt läuft ohnehin in einem Output-Buffer.
- Frisch aktivierte Fremd-Plugins leiten im `admin_init` um; während AJAX-Schritten ist `wp_redirect` deshalb abgeschaltet.
- Asset-URLs root-relativ, Fonts self-hosted (DSGVO), keine externen Requests außer Steam-CDN in Patch Notes.
- Spielzahlen immer mit „Stand: <Monat Jahr>" (Early Access). Faktenbasis: `docs/research/wardogs-facts.md`.
- Shell-Hinweis: `cd` persistiert nicht zuverlässig zwischen Befehlen → absolute Pfade / `git -C`.
- Screenshots: `?snap=1` friert Motion ein, deaktiviert Lazy-Loading; `--disable-dev-shm-usage` ist Pflicht.

## Docs
Spec `docs/superpowers/specs/2026-09-14-warleek-website-design.md`, Plan `docs/superpowers/plans/2026-09-14-warleek-website.md`, Verifikation `docs/verification.md`.
