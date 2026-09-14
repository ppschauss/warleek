# Verifikation (Dev, 14.09.2026)

Dev-URL: http://192.168.0.161:8088 (Container warleek-wp/warleek-db)

## HTTP
Alle 19 Seiten-URLs aus der Spec (inkl. /thema/fob/, Guide- und Patch-Note-Einzelseiten) → 200; /nonexistent/ → 404.

## Seed
- Zweiter Lauf: nur `update:`, Attachment-Anzahl unverändert (27 → 27).
- Editor-Check: keine freien HTML-Reste in Seiten/Guides (alles Core-/Warleek-Blöcke).

## Steam-Sync
- `php tests/bbcode-test.php` → OK (22 Fälle, inkl. neues Steam-Format [p]/[img src]/[table colwidth]/[/*]).
- `wp eval-file tests/steam-sync-test.php` → OK (Filter, Dedupe, Force-Update).
- Live: 3 Patch Notes importiert, Cron `warleek_sync_patchnotes` hourly registriert.

## Screenshots (shots/, nicht committet)
Desktop 1440: alle Seiten. Mobile 390: Startseite, Community, FOB. Befunde behoben: Prosa-Breite 760px, Count-up im Snapshot, Lazy-Loading im Snapshot, Patch-Note-Tabellen, doppelte Titel-Überschrift, H1-Umbruch mobil.

## Lighthouse 12 (Startseite, Headless, HTTP)
Performance 90 · Accessibility 100 · Best Practices 79 (nur HTTP/kein HTTP2 – Dev) · SEO 100
LCP 2,9 s · CLS 0 · TBT 260 ms. Seitengewicht Startseite ≈ 740 KB ohne Video, Video +365 KB (MP4).

## Reduced Motion
`prefers-reduced-motion` in motion.css und main.js behandelt (Fades statt Smoke/Flare, Video wird entfernt).
