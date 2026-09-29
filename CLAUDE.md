# warleek (Ordner wardogs-gc)

Wardogs-**Guide-Seite** **Warleek** (warleek.de): Block-Theme `theme/warleek` (nur Design) + Plugin `plugin/warleek-core` (CPTs guide/patchnote/partner, Steam-Sync mit Claude-Übersetzung, Markdown-Import, GitHub-Updater, Optionen, Blöcke, SEO, Inhalte, Installer). Dev via `./manage.sh` (plain `docker run`, Port 8088, Login admin/warleekadmin – nur lokal), Testinstanz für Go-live-Proben auf Port 8089 (`warleek-test-*`), Prod auf All-Inkl (`README-Deploy.md`).

## Befehle
- `./manage.sh up|down|install|wp <args>|seed|sync [--force]|zip|shot <pfad> [breite] [datei] [hoehe]|urls`
- Tests: `docker run --rm -v $PWD/plugin/warleek-core:/c php:8.3-cli php /c/tests/{bbcode,markdown}-test.php`, `./manage.sh wp eval-file wp-content/plugins/warleek-core/tests/{steam-sync,translate,updater}-test.php`, `./manage.sh wp warleek status`
- Veröffentlichen: `bin/release.sh` (Trockenlauf) bzw. `--publish`; prüft fünf Versionsstellen (Plugin-Header, WARLEEK_CORE_VERSION, readme Stable tag, style.css, WARLEEK_VERSION)
- Nach Änderungen an `patterns/`: Pattern-Cache ist versionsgebunden → `./manage.sh seed` (löscht ihn) oder `wp eval 'wp_get_theme()->delete_pattern_cache();'`

## Regeln
- **Alles editierbar:** kein Content in Templates/Patterns hart verdrahtet; Bilder in der Mediathek; Logo = Website-Logo; Links/Clan-Tag = Optionsseite (`warleek_opt()`).
- Inhalte kommen aus `plugin/warleek-core/content/_content/*.json` → Installer (idempotent). Textänderungen dort **und** per `./manage.sh seed --force` einspielen, oder direkt im Backend (dann JSON nicht mehr Quelle der Wahrheit).
- Übersetzungs- und Updater-Tests fangen HTTP über `pre_http_request` ab – sie kosten nichts und dürfen nie echte Anfragen stellen.
- Im Installer niemals `echo` verwenden – Ausgabe zerstört die JSON-Antwort. Stattdessen `warleek_log()`; jeder Schritt läuft ohnehin in einem Output-Buffer.
- Frisch aktivierte Fremd-Plugins leiten im `admin_init` um; während AJAX-Schritten ist `wp_redirect` deshalb abgeschaltet.
- Asset-URLs root-relativ, Fonts self-hosted (DSGVO), keine externen Requests außer Steam-CDN in Patch Notes.
- Spielzahlen immer mit „Stand: <Monat Jahr>" (Early Access). Faktenbasis: `docs/research/wardogs-facts.md`.
- Shell-Hinweis: `cd` persistiert nicht zuverlässig zwischen Befehlen → absolute Pfade / `git -C`.
- Nach einem Branch-Wechsel, der `theme/` oder `plugin/` neu anlegt, zeigt der Bind-Mount ins Leere (alter Inode) → `./manage.sh up` neu starten, sonst fehlen im Container Plugin/Theme.
- Screenshots: `?snap=1` friert Motion ein, deaktiviert Lazy-Loading; `--disable-dev-shm-usage` ist Pflicht.

- Beim Entfernen von Seiten: Eintrag in `content/_content/retired.json`, nicht einfach aus `pages.json` löschen – sonst bleibt die Seite veröffentlicht und ohne Weiterleitung online.

## Docs
Spec `docs/superpowers/specs/2026-09-14-warleek-website-design.md`, Plan `docs/superpowers/plans/2026-09-14-warleek-website.md`, Verifikation `docs/verification.md`.
