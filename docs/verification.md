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

## Nachbesserung 14.09.2026 (Runde 2)
- **Mobiles Menü:** Ursache war `backdrop-filter` auf dem sticky Header (Containing Block für `position:fixed` → WP-Overlay im Header eingesperrt). Fix: Blur auf `::before`, Overlay explizit gestylt. Verifiziert per Puppeteer-Klick (390×844): Container 0/0/390/844, 15/15 Links sichtbar, Untermenüs aufgeklappt – auf `/`, `/community/wardogs-clan/`, `/guides/`.
- **Ladeverhalten:** Reveal nur für Elemente unterhalb des Folds (kein Aufploppen), eine Smoke-Lage, kein Delay beim View-Transition-Einblenden, Hero als ein Fade. Timeline 150/400/900/2000 ms: Header sofort, Hero ab 400 ms vollständig; CLS 0,008; keine Konsolenfehler.
- **Datenschutz:** Emoji-Script/oEmbed/RSD/WLW/Generator/Shortlink/dns-prefetch entfernt, XML-RPC & Kommentare aus. Steam-Bilder werden beim Sync in die Mediathek geholt (responsiv, width/height) – Besucher laden nichts von Valve. Audit über 11 Seiten: keine externen Requests (nur Links zu Steam/Discord/YouTube). Datenschutz-Seite entsprechend angepasst.
- **Barrierefreiheit:** Video-Pause-Button (WCAG 2.2.2), `:focus-visible` in Lauchgrün, Skip-Link (WP-Core) vorhanden, Nav-`aria-label`s, versteckter Text für „bald"-Buttons, `prefers-contrast`/`forced-colors`-Regeln, Überschriften in Patch Notes normalisiert. Lighthouse A11y 100 auf /, Clan, Guide, Patch Note, Chat.
- **Metas:** Dublin Core (DC.title/description/language/publisher/creator/type/format/identifier/rights/subject/coverage/date, DCTERMS.created/modified, DC.source bei Patch Notes), keywords, theme-color, geo.region; Open Graph komplett (image:width/height/type/alt, secure_url, article:published/modified/section/tag, fb:app_id optional), Twitter Cards (site/creator aus Option), author/application-name. Neue Optionen: X-Handle, YouTube, Twitch, Instagram, TikTok, Steam-Gruppe, FB-App-ID (fließen in Organization sameAs).
