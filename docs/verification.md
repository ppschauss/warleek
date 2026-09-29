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

## Performance-Runde 14.09.2026 (Handy fühlte sich träge an)
Ursache nicht JS/DOM (DOM 313–405 Knoten, keine Long Tasks), sondern GPU-Last: `backdrop-filter` auf sticky Header, Smoke-Layer mit `mix-blend-mode` über laufendem Video, SVG-`feTurbulence`-Maske beim Seitenwechsel (Re-Rasterizing pro Frame).
- **Mobile-Budget** (≤ 781px oder Touch): kein Hero-Video (Poster-Bild), kein Smoke, kein Header-Blur, kein Logo-Glow, Seitenwechsel = Crossfade, keine Hover-Transforms (kein klebender Hover beim Tippen), `scroll-behavior:smooth` nur mit Hover-Geräten.
- **Desktop**: Smoke-Wipe mit vorgerasterter PNG-Maske (kein SVG-Filter), Smoke-Drift als CSS-Mask (LA-PNG, 63 KB), nur `transform`/`opacity`/`mask-size` animiert.
- **Video lazy**: `render_block`-Filter liefert `<video data-src data-poster preload="none">`; JS setzt `src` nur auf Desktop und nur, wenn der Hero sichtbar ist; pausiert außerhalb des Viewports.
- `content-visibility:auto` für Sektionen und Footer, Core-Block-CSS nur für genutzte Blöcke (`should_load_separate_core_block_assets`).
- Ergebnis Startseite mobil: 776 KB → **367 KB**, 15 Requests; Lighthouse **mobile Perf 98, A11y 100**, LCP 2,3 s, TBT 0 ms, CLS 0, Speed Index 1,5 s. Desktop lädt Video (793 KB) und spielt es; mobiles Menü weiterhin per Klick verifiziert.
- Quellcode-Gruß: HTML-Kommentar im `<head>` + gestylte `console.log`-Nachricht.

## Warleek Core (Plugin) – 25.09.2026
Architektur getrennt: Theme = Design, Plugin = Funktion + Inhalte (CPTs, Steam-Sync, Optionen, Blöcke, Builder, SEO, Privacy, Installer, WP-CLI). Theme zeigt ohne Plugin einen Hinweis, Patterns sind per `function_exists` abgesichert; Plugin warnt, wenn das Theme fehlt.

**Installer**: Admin-Seite *Warleek → Installation* mit Statusübersicht (9 Punkte) und sechs Schritten, einzeln oder als Kette per AJAX. Dazu `wp warleek install [--force|--skip-plugins]`, `wp warleek status`, `wp warleek sync-patchnotes`.

**Test auf frischer WordPress-Instanz (Port 8089, leere DB, nur die beiden Zips):**
- Theme- und Plugin-Zip installiert, „Komplett installieren“ im Browser geklickt: alle sechs Schritte grün – Rank Math installiert und aktiviert, 27 Medien, 15 Seiten, 6 Guides, Menüs/Startseite/Logo/Favicon/Permalinks, 3 Patch Notes. Keine JS-Fehler.
- Frontend danach: alle geprüften URLs 200, Titel „Wardogs Community Deutschland, Österreich & Schweiz | Warleek“, Hero/Stufen/Patch-Liste/Logo vorhanden, mobiles Overlay-Menü per Klick geprüft (390×844, 15/15 Links).
- Zweiter Durchlauf: 31 Attachments → 31, 17 Seiten → 17, „0 neu“ – idempotent.

**Drei Fehler, die erst dieser Test gezeigt hat und behoben sind:**
1. `warleek_normalize_headings()` war beim Verschieben des WP-CLI-Blocks verloren gegangen → Fatal beim Sync. Ergänzt; zusätzlich prüft ein Skript alle `warleek_*`-Aufrufe gegen die Definitionen (89 Funktionen, 0 fehlend).
2. Die Seed-Funktionen schrieben Fortschritt per `echo` → zerstörte die JSON-Antwort der AJAX-Schritte. Ersetzt durch `warleek_log()`, jeder Schritt läuft zusätzlich in einem Output-Buffer mit try/catch.
3. Rank Math leitet nach der Aktivierung im `admin_init` auf seinen Assistenten um → der folgende AJAX-Schritt bekam HTML. Während eines Schritts ist `wp_redirect` jetzt abgeschaltet, Aktivierung erfolgt „silent“, und das JS meldet Nicht-JSON-Antworten verständlich.

**SEO-Übergabe:** Rank Math gibt im Frontend erst nach seinem Setup-Assistenten aus. `warleek_seo_plugin_active()` prüft deshalb `rank_math_is_configured` – bis dahin liefert Warleek Core Titles, Descriptions, OG, Dublin Core selbst. Auf der frischen Instanz verifiziert.

**Pakete:** `warleek-theme.zip` (1,3 MB), `warleek-core.zip` (2,4 MB, Inhalte und Medien enthalten).

## Hotfix 1.0.1 – Aktivierung neben altem Theme (25.09.2026)
**Meldung des Nutzers:** „Das Plugin konnte nicht aktiviert werden, da es einen fatalen Fehler ausgelöst hat.“

**Reproduziert** auf frischer Instanz mit dem Theme-Stand aus dem ersten Paket (Commit 67823d6, Theme 0.1.0 mit eigenem `inc/`):
`PHP Fatal error: Cannot redeclare warleek_option_fields() (previously declared in themes/warleek/inc/options.php:11) in plugins/warleek-core/inc/options.php:11`.
Ursache: Bis 0.1.x brachte das Theme die Funktionsmodule mit; Plugin 1.0.0 deklariert dieselben Funktionen erneut. Beim Aktivieren lädt WordPress die Plugin-Datei in einer Anfrage, in der das Theme bereits geladen ist → Fatal.

**Behoben in 1.0.1:**
- Das Plugin prüft beim Laden, ob das aktive Theme noch `inc/options.php` hat. Falls ja: nur der Installer wird geladen, der Funktionsteil bleibt beim Theme, und im Backend steht ein Hinweis mit der Bitte, das Theme zu aktualisieren.
- WP-CLI-Klasse heißt jetzt `Warleek_Core_CLI` und wird nur registriert, wenn keine `Warleek_CLI` existiert.
- `WARLEEK_VERSION` wird nur gesetzt, wenn kein altes Theme die Konstante selbst definiert (sonst PHP-Warnung → „headers already sent“).

**Nachgetestet:**
- Altes Theme 0.1.0 + Plugin 1.0.1: Aktivierung erfolgreich, Installer-Seite erreichbar, alle sechs Schritte grün (Rank Math, 27 Medien, 15 Seiten, 6 Guides, Menüs, Patch Notes), Frontend 200 auf allen geprüften URLs, keine Fatals im Log.
- Neues Theme 1.0.0 + Plugin 1.0.1 (Dev-Instanz): unverändert, `WARLEEK_CORE_LEGACY_THEME` false, Status vollständig, Tests grün.

## Breiteres Layout + weiße Untermenü-Schrift (25.09.2026)
Auf Wunsch übernommen: Inhaltsbreite 80vw (Desktop) bzw. 95vw (Mobil), Bilder im Fließtext ebenso, Untermenü-Schrift weiß.
- Umsetzung über `--wp--style--global--content-size` im CSS statt in theme.json, weil die Breite je nach Bildschirm unterschiedlich sein soll. Die Wide-Breite wurde auf 92vw (Desktop) / 95vw (Mobil) mitgezogen, sonst wären Karten und Raster schmaler als der Fließtext gewesen.
- Zwischenstand verworfen: ein Lesbarkeits-Limit von 80ch für Absätze ließ den Text gegenüber den Überschriften eingerückt erscheinen (constrained Layout zentriert schmalere Kinder). Entfernt – die gewünschte Breite gilt jetzt unverändert.
- Patch-Notes-Bilder: Einbindung von `large` auf `full` mit `sizes="(max-width: 781px) 95vw, 80vw"` umgestellt, sonst wurden 1024-px-Dateien auf ~1150 px hochskaliert. Bestehende Bilder neu importiert (Quellen 2560×1440).
- Aufklappmenü: `min-width: 280px` und kein Umbruch, weil der Eintrag „Übersicht: Community · Team · Clan“ im schmalen Menü dreizeilig wurde.
- Geprüft: alle Seiten 200, mobiles Overlay-Menü weiterhin vollflächig (15/15 Links), Lighthouse mobil Performance 98 / A11y 100, CLS 0, LCP 2,3 s.

## Feste Breiten entfernt (25.09.2026)
**Rückmeldung:** „mich stört es wirklich immens, dass der text content nur auf 720px beschränkt ist“.

**Ursache:** Nicht das CSS, sondern das Blockmarkup. Die Builder schrieben `"layout":{"contentSize":"760px","wideSize":"1240px"}` in jede Sektion; WordPress erzeugt daraus eine eigene Container-Regel, die jede globale Breite überstimmt. Header und Footer hatten `wideSize:1240px` fest im Template, der Hero zusätzlich `max-width:760px` im CSS. Die vorherige Umstellung auf 80vw wirkte deshalb nur dort, wo keine Inline-Breite gesetzt war.

**Behoben:**
- Builder schreiben keine Pixelbreiten mehr ins Markup (nur noch, wenn ausdrücklich eine Breite übergeben wird).
- Neues immer geladenes Modul `inc/layout.php`: entfernt beim Rendern `contentSize`/`wideSize` aus Blöcken mit den Klassen `wl-section` und `wl-hero__inner`, damit bestehende Seiten ohne Neuanlage die aktuelle Breite übernehmen. Eigene Breiten aus dem Editor bleiben unangetastet.
- `wideSize:1240px` aus header.html und footer.html entfernt, `.wl-hero__inner > *` und `.wl-section__head` ohne Pixel-Deckel.
- theme.json: contentSize 80vw, wideSize 92vw (CSS überschreibt sie auf Mobil mit 95vw).

**Geprüft:** Startseite, FOB, Guide, Clan, Patch Note – keine `max-width:760px`-Regel mehr im Dokument; Text läuft über die volle Inhaltsbreite; mobiles Menü unverändert (15/15 Links); Tests grün. Zusätzlich gegengetestet auf der Testinstanz mit altem Theme 0.1.0 (Filter greift, keine Fatals) und mit Theme 1.1.1.

## Umbau zur Guide-Seite – Version 2.0.0 (29.09.2026)

**Ausgangspunkt:** Die Seite war als Community-Seite gebaut (drei Mitglieds-Stufen, Chat-Aufruf auf 13 von 15 Seiten, SEO auf „Wardogs Community/Clan/Team/Discord"). Ziel: Guides und Themen ins Zentrum, Partner-Seite statt Mitgliedschaft.

### Was geprüft wurde
- **URL-Matrix:** 13 Seiten liefern 200 (inkl. der neuen `/wardogs/einsteiger/` und `/wardogs/technik/`), die fünf entfernten Community-URLs liefern **301** auf ihr neues Ziel, `/nix/` liefert 404.
- **Entfernte Seiten** liegen im Papierkorb statt verwaist online; die Weiterleitungen stehen in der Option `warleek_redirects` (8 Einträge).
- **Installer** zweimal hintereinander: nur `update:`, Medien- und Seitenzahl unverändert.
- **Editor-Schutz:** Ein von Hand geänderter Seitentext bleibt beim erneuten Lauf erhalten; erst `--force` spielt den mitgelieferten Text zurück.
- **Tests:** BBCode (22), Markdown (26), Steam-Sync, Übersetzung (12 Fälle, HTTP abgefangen), Updater (11 Fälle, HTTP abgefangen) – alle grün.
- **Funktionsaudit:** 125 `warleek_*`-Funktionen definiert, 0 Aufrufe ins Leere.
- **Mobiles Menü:** per Klick geprüft auf `/`, `/guides/`, `/community/` – Overlay 390×844, 17/17 Links sichtbar.
- **Lighthouse mobil (Startseite):** Performance 97, Accessibility 100, SEO 100, LCP 2,4 s, CLS 0,006, DOM 336 Knoten.

### Fehler, die erst die Prüfung gezeigt hat
1. **Verwandte Guides zeigten fremde Themen.** Der Themenfilter wurde aufgebaut, bevor `thema_current` den Wert setzen konnte. Behoben; zusätzlich fällt die Liste auf die neuesten Guides zurück, wenn ein Thema nur einen Guide hat.
2. **Rückfall griff zu früh.** Die Schwelle stand auf „weniger als zwei" – ein Guide mit genau einem Geschwister bekam dadurch fremde Themen. Jetzt nur noch bei leerer Liste.
3. **Variablenschatten im Importer.** Die Schleife über die Themen-Synonyme benutzte `$slug` und überschrieb damit den Guide-Slug; jeder Import hätte den Slug „einsteiger" bekommen.
4. **Guide-Bild wirkte leer.** Das HOTAS-Bild war 16:9; der 3:2-Zuschnitt der Karte zeigte nur den dunklen Boden. Bild passend zugeschnitten.

### Neu in 2.0.0
Partner-Inhaltstyp · Themen-Chips, Suche, Lesezeit, verwandte Guides · Themen Einsteiger und Technik · Markdown-Import (Backend und CLI) · deutsche Patch Notes über die Claude-API mit Kurzfassung und englischem Original · Update-Kanal über GitHub-Releases · Zurückziehen mit 301 statt verwaister Seiten.
