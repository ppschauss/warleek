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

### Generalprobe auf frischer Instanz (Port 8089, leere Datenbank, nur die beiden Zips)
- Installer im Browser durchgeklickt, alle neun Schritte grün: `plugins`, `media` (34 neu), `pages` (12), `guides` (7), `retire` (8 Weiterleitungen), `partners` (2), `nav`, `patchnotes` (3 neu), `translate` (ohne Schlüssel übersprungen) – `errors: []`.
- Alle geprüften URLs 200: `/`, `/guides/`, `/wardogs/einsteiger/`, `/wardogs/technik/`, `/community/`, `/patch-notes/`, `/guides/wardogs-hotas-dual-stick/`, `/thema/technik/`, `/about-us/`.
- `/wardogs-discord/` und `/community/wardogs-clan/` → 301 auf `/community/`.
- Titel der Startseite: „Wardogs Guides & Tipps auf Deutsch | Warleek".
- Mobiles Menü per Klick: Overlay 390×844, 17/17 Links sichtbar.
- Lighthouse mobil `/guides/`: Performance 92, Accessibility 100, SEO 100.

### Noch offen (braucht Zugang oder Inhalte vom Betreiber)
- **GitHub-Repo `ppschauss/warleek` und Release `v2.0.0`** sind noch nicht angelegt. Erst danach lässt sich der Update-Kanal echt prüfen (Version lokal senken → Dashboard bietet beide Pakete an).
- **Anthropic-Schlüssel** in `wp-config.php` (`WARLEEK_ANTHROPIC_KEY`), sonst bleiben die Patch Notes englisch.
- **Chat-Links, Impressum, Datenschutz, About-us, Partner-Einträge** enthalten Platzhalter.

### Update-Kanal echt durchgespielt (29.09.2026, ohne GitHub)
Das GitHub-Repo existiert noch nicht, deshalb lief die Probe gegen eine lokale Attrappe: ein nginx-Container im Testnetz liefert `warleek-update.json` und beide Pakete, ein mu-Plugin biegt `warleek_update_manifest_url` dorthin um. Alles danach ist der echte Weg – Transient, Update-Filter, WordPress-Installer.

Ablauf auf einer frischen Instanz (`bin/test-instance.sh fresh|install`, Theme und Plugin als echte Zip-Kopien, **keine** Bind-Mounts): 2.0.0 installiert, Inhalte geseedet, eine Seite von Hand ergänzt. Dann Testpakete 2.0.1 gebaut und angeboten.

- Dashboard bietet **beide** Pakete an: `warleek-core 2.0.0 → 2.0.1`, Theme `warleek 2.0.0 → 2.0.1`.
- Update eingespielt: beide „Updated", Ordnernamen bleiben `warleek/` und `warleek-core/` (kein `repo-2.0.1/`), Plugin bleibt aktiv, Theme bleibt aktiviert.
- Inhalte unberührt: 13 Seiten, 7 Guides, 2 Partner, 3 Patch Notes, 8 Weiterleitungen – vor und nach dem Update identisch; die Handänderung an `/wardogs/fob/` hat das Update überlebt (gleiche Prüfsumme).
- Nach dem Update wird kein weiteres Update mehr gemeldet.
- Fehlerpfad: Manifest-Adresse auf eine nicht vorhandene Datei gebogen → `warleek_update_manifest()` liefert `false`, im Zwischenspeicher steht kurzzeitig `fehler`, es wird kein Update gemeldet, Seite und Update-Seite laufen normal weiter.

**Ein Stolperstein, den erst die echte Probe gezeigt hat:** WordPress lehnt Downloads von privaten Netzadressen ab (`wp_http_validate_url`) – der erste Versuch scheiterte mit „Es wurde keine gültige URL angegeben". Das betrifft nur die lokale Attrappe (Docker-Netz 172.x); für die Probe wurde der Host per `http_request_host_is_external` freigegeben. Bei github.com greift die Sperre nicht.

**Noch nicht geprüft:** der echte Abruf von `https://github.com/ppschauss/warleek/releases/latest/download/warleek-update.json` – dafür muss das Repo samt Release existieren.

### Update-Kanal gegen das echte GitHub-Release geprüft (29.09.2026)
Repo `ppschauss/warleek` (öffentlich) und Release `v2.0.0` mit drei Anhängen sind angelegt. Danach der Weg, den ein Server später geht – ohne Attrappe, ohne mu-Plugin, ohne Bind-Mounts:

- `https://github.com/ppschauss/warleek/releases/latest/download/warleek-update.json` liefert 200 (über die Weiterleitung auf `release-assets.githubusercontent.com`, der WordPress folgt).
- Frische Instanz, 2.0.0 aus den Zips installiert, Inhalte geseedet, eine Seite von Hand ergänzt. Dann die installierte Version **im Container** künstlich auf 1.9.0 gesenkt.
- `warleek_update_manifest()` holt das Manifest von GitHub (Plugin 2.0.0 / Theme 2.0.0); Dashboard bietet beide Pakete an: `1.9.0 → 2.0.0`.
- Beide Updates eingespielt, Pakete kamen von `releases/download/v2.0.0/…`: „Updated", Ordnernamen bleiben `warleek/` und `warleek-core/`, Plugin aktiv, Theme aktiviert.
- Inhalte unberührt (13 Seiten, 7 Guides, 2 Partner, 3 Patch Notes, 8 Weiterleitungen), Handänderung erhalten, alle geprüften Seiten 200.
- Danach wird kein weiteres Update mehr gemeldet.
- Details-Dialog (`plugins_api`) liefert Name, Version, Paket und Changelog.

Damit ist der Update-Kanal vollständig geprüft. Künftige Veröffentlichung: Version an den fünf Stellen erhöhen, Changelog in `readme.txt` ergänzen, `bin/release.sh --publish`.

## Guide-Offensive – Version 2.1.0 (29.09.2026)

**Was dazugekommen ist:** 20 neue Guides (insgesamt 27), 20 neue Bilder, eine Markdown-Strecke für die Guide-Pflege und ein Satire-Guide über Team Blau.

### Recherche
Quellen sind überwiegend Guide-Seiten Dritter, kein offizielles Wiki – deshalb stehen alle Zahlen mit „rund" und Stand-Datum im Text, und die Fundstellen sind in `docs/research/wardogs-facts.md` mit Quelle vermerkt. Wo sich Quellen widersprechen (Beispiel: Verlust der getragenen Ausrüstung beim Tod – „alles weg" gegen „90 % Erstattung"), ist das dort notiert und im Guide vorsichtig formuliert.

### Neue Werkzeuge
- `content-src/guides/*.md` als Quelle der Wahrheit, `php bin/guides-build.php` baut daraus `content/_content/guides.json` und trägt die Bilder ins `MANIFEST.json` ein. Trockenlauf mit `--dry-run`, warnt bei fehlendem Titel, Auszug, Bild oder Alt-Text.
- Markdown kennt jetzt `> [!stand] …` für die Datumszeile (`p.wl-stand`), abgesichert im Markdown-Test.

### Geprüft
- `php bin/guides-build.php --dry-run`: 20 neu, 0 Warnungen, 27 Guides gesamt.
- `./manage.sh seed`: 27 Guides angelegt, 20 Medien neu, **alle 27 Guides haben ein Beitragsbild**.
- Alle 21 internen Links aus den neuen Guides liefern 200 – kein toter Querverweis.
- Stichprobe Rendering (`/guides/wardogs-waffen-kaufen/`): Stand-Zeile, drei Tabellen, Hinweiskasten, neun H2, Lesezeit „3 Min." – alles da.
- Satire-Guide trägt den Achtung-Kasten mit dem Satire-Hinweis ganz oben und verlinkt die ernst gemeinte Fraktionsseite.
- Themen-Chips auf `/guides/`: Gameplay 7 · Einsteiger 5 · Equipment 5 · FOB 4 · Technik 4 · Logistik 2 = 27, Seitenumbruch über drei Seiten.
- Mobil (390 px) geprüft: Lesezeit, Themen-Chip, Bild, Stand-Zeile und Tabellen brechen sauber um.
- Alle fünf Testdateien grün.

**Ein Phantom, das keines war:** Auf dem Screenshot der Guide-Bibliothek blieben die unteren Karten bildlos. Nachgemessen mit einem echten Browser (Puppeteer, `naturalWidth` je Bild): **alle 14 Bilder geladen**. Das Headless-Chromium hatte innerhalb seines virtuellen Zeitbudgets nur die oberen dekodiert – kein Fehler auf der Seite. Screenshots taugen hier nicht als Beweis.

## SEO auf DACH, Rechtstexte und Einwilligung – Version 2.4.0 (30.09.2026)

### SEO
- **Titel und Beschreibungen neu** für 29 Guides und 12 Seiten. Muster: Titel nennt Wardogs + Thema + Sprache/Land und bleibt unter 62 Zeichen inklusive „ | Warleek"; die Beschreibung bleibt unter 158 Zeichen und ist **anders formuliert** – der Titel knapp, die Beschreibung mit ausgeschriebener Region und Nebenbegriffen. Ein Prüfskript bricht ab, wenn eine Länge reißt oder beide gleich anfangen; zwei Beschreibungen wurden deshalb gekürzt, bevor irgendetwas geschrieben wurde.
- **Patch Notes** tragen Datum und Version: Einzelseite „Wardogs Update 0.1.2 – Patch Notes Deutsch (30.09.2026)", Übersicht „Wardogs Patch Notes Deutsch (30.09.2026)" mit dem Datum der jüngsten Note (stündlich zwischengespeichert, wird beim Speichern einer Note geleert).
- **DACH-Signale**, die Suchmaschinen wirklich auswerten: hreflang für de-DE, de-AT, de-CH, de und x-default auf dieselbe Adresse, dazu `og:locale:alternate` und drei `geo.region`-Angaben.
- **Strukturierte Daten**: Patch Note als `Article` mit Bezug auf das Spiel (`VideoGame`, Entwickler BULKHEAD, Publisher Team17, Steam als `sameAs`) und Quelle bei Steam; Übersicht als `CollectionPage` mit `ItemList` der zehn jüngsten Notes; Brotkrumen (`BreadcrumbList`) auf Guides, Seiten, Archiven und Themenseiten.
- **Gemessen** (Zeichenzahl): Startseite 44/151, Guide-Archiv 53/140, `/thema/fob/` 47/130, Patch-Notes-Übersicht 50/157, Patch Note 65/151. Alles im Rahmen.

**Eine Entscheidung gegen die Bequemlichkeit:** In die Beschreibung einer Patch Note darf **kein englischer Originaltext** geraten. Der erste Entwurf zitierte den Anfang der Steam-Meldung – in einer Beschreibung, die „auf Deutsch" verspricht, stand dann „WARDOGS will be entering a maintenance window". Jetzt wird nur die deutsche Kurzfassung der Übersetzung zitiert; fehlt sie, steht dort ein fertiger deutscher Satz.

### Impressum und Datenschutz
Echte Betreiberdaten eingesetzt (Name, Anschrift, Telefon, E-Mail), Redaktionshinweise entfernt. Impressum um Haftung für Inhalte und den VSBG-Satz ergänzt; die EU-Streitschlichtungsplattform wird **nicht** mehr verlinkt, weil sie seit 2025 eingestellt ist. Datenschutz um Einwilligung, eingebettete Videos, Aufbewahrungsfrist der Logfiles und die zuständige Aufsichtsbehörde erweitert.

### Einwilligung für externe Medien
Die Seite setzt keine Cookies und lädt nichts von fremden Servern – ein klassisches Cookie-Banner wäre dafür sachlich falsch. Gebaut ist deshalb eine echte Sperre für eingebettete Videos: `[warleek_video]` zeigt bis zur Zustimmung nur eine Vorschau vom eigenen Server.

Mit echten Klicks geprüft (Puppeteer, 390 px):

| Prüfung | Ergebnis |
| --- | --- |
| Banner beim ersten Besuch | sichtbar, volle Breite, am unteren Rand, zwei gleichwertige Schaltflächen |
| „Nur notwendige" | Banner weg, `{"v":1,"media":false}` im localStorage |
| Neu laden | Banner bleibt weg |
| „Externe Videos erlauben" | `{"v":1,"media":true}` |
| Tastatur | beide Schaltflächen erreichbar |
| **Fremde Hosts beim Seitenaufruf** | **keine** |
| Guide mit Video **vor** Zustimmung | 0 iframes, **0 fremde Hosts** |
| nach Klick auf „Video laden" | 1 iframe auf youtube-nocookie.com, erst jetzt Google-Hosts |

Mobiles Menü gegengeprüft: Overlay 390×844, z-index 100000 über dem Banner (90) – das Menü verdeckt den Hinweis, nicht umgekehrt. Keine JS-Fehler.

Lighthouse mobil nach dem Einbau: Performance 97, Accessibility 100, SEO 100, CLS 0,006, TBT 10 ms. Best Practices 79 nur wegen HTTP auf der Dev-Instanz – auf der echten Domain mit TLS entfällt das.

### Tests
Sieben Testdateien grün, davon neu `seo-test.php` (Versionsnummern, Kürzung an der Wortgrenze, Datum in Titel und Beschreibung, Länderbezug, kein englischer Text ohne Übersetzung, Spiel-Schema).

## Mobiles Menü, Einwilligungs-Kategorien und Video-Screenshots – Version 2.5.0 (30.09.2026)

### Der Fehler, der auf der Live-Seite aufgefallen ist
Die obersten Menüpunkte im mobilen Overlay waren **schwarz auf schwarz**. Gemessen: `rgb(0,0,0)` auf `rgb(13,17,15)` – Kontrast **1,10:1**. Ursache ist eine Core-Regel, die auf das offene Overlay ein hartes `color:#000` legt:

```
.wp-block-navigation:not(.has-text-color) .wp-block-navigation__responsive-container.is-menu-open
```

Diese Regel hat drei Klassen, die eigene hatte zwei – der Core gewinnt. Der Hintergrund stimmte nur deshalb, weil er mit `!important` gesetzt war, die Schriftfarbe nicht.

Behoben mit gleicher Spezifität plus `!important` **und** einer Farbe direkt auf den Einträgen. Nachgemessen: **12,06:1** für aufklappbare Punkte, **17,06:1** für die übrigen, **15,63:1** für Untermenüs – alle über 4,5:1.

**Und ein zweiter Fehler, den erst der Screenshot zeigte:** Das Einwilligungsbanner lag über dem offenen Menü, obwohl das Menü z-index 100000 hat und das Banner 90. Grund: Der sticky Header (`z-index: 50`) macht einen eigenen Stapelkontext auf – die 100000 gelten nur *innerhalb* des Headers. Mein vorheriger Test verglich blanke z-index-Werte und hat das deshalb nicht gefunden. Gelöst über `html.has-modal-open .wl-consent { display: none; }`.

### Einwilligung mit Kategorien
Zwei Kategorien: **externe Medien** (YouTube, Vimeo) und **Statistik**. Bei mehr als einer Kategorie zeigt das Banner Einzelauswahl plus „Auswahl speichern"; bei einer bleibt es bei zwei Schaltflächen.

Der Einbindungscode einer Reichweitenmessung steht als `type="text/plain"` im Quelltext und wird erst nach der Einwilligung ausgeführt. Mit echtem Klick geprüft:

| Zeitpunkt | Skript gelaufen |
| --- | --- |
| Seitenaufruf, keine Entscheidung | **nein** |
| nach „Alles erlauben" | ja, gespeichert als `{"v":2,"media":true,"statistik":true}` |

### Externe Links
Alle externen Links in Guides und Patch Notes bekommen `rel="nofollow noopener noreferrer"`; interne und relative Links bleiben unangetastet, vorhandene `rel`-Werte werden ergänzt statt ersetzt. Abschaltbar in den Einstellungen. Eigener Test mit acht Fällen.

Zur Klarstellung: **`noindex` gibt es für Links nicht** – das ist eine Anweisung für eine ganze Seite (Robots-Meta). Das Gegenstück auf Linkebene ist `nofollow`, und das ist gesetzt.

### Video-Screenshots statt Einbettung
Die Einbettungen sind wieder raus. Stattdessen fünf Bildschirmfotos aus den beiden Videos, jeweils mit Kanal und Zeitangabe in der Bildunterschrift und einem Bildnachweis im Guide:

- Gamepad-Menü mit Empfindlichkeit 1,05 und Deadzone nur beim Gieren (Tote Torres, 0:48)
- Kurveneditor der Stick-Software (Sim Controls, 7:20)
- beschriftete HOTAS-Belegung (Sim Controls, 0:50)
- Steuerungsmenü mit Freelook an und Flugassistenten aus (Tote Torres, 3:20)
- Sichtfeld-Menü mit Vorschau 60 gegen 90 Grad (Tote Torres, 4:35)

Die Aufnahmen bestätigen die Zahlen, die vorher nur aus den Untertiteln stammten. Höhere Auflösung als 640 px war nicht zu bekommen: YouTube liefert für diese Videos über die zugängliche Schnittstelle nur noch 360p.

### Tests
Acht Testdateien grün, davon neu `nofollow-test.php`.

## Update-Meldung kam nicht an – Version 2.5.1 (30.09.2026)

Auf der Live-Seite (2.4.0) tauchte keine Update-Meldung auf, obwohl 2.5.0 veröffentlicht war. Zwei Ursachen, beide behoben:

1. **Die Schaltfläche „Jetzt nach Updates suchen" gab es gar nicht.** Der Handler war seit 2.0.0 vorhanden und funktionsfähig, aber nichts in der Oberfläche verlinkte ihn – ein toter Pfad, der beim Bauen des Update-Kanals nie aufgefallen ist, weil die Dev-Instanz ohnehin immer die neueste Version hatte. Jetzt steht über allen drei Registerkarten eine Zeile mit installierter Version, zuletzt veröffentlichter Version und der Schaltfläche.
2. **„Erneut prüfen" auf Dashboard → Aktualisierungen half nicht.** Es leert die Zwischenspeicher von WordPress, nicht unseren – der hält das Manifest sechs Stunden. Jetzt gilt `force-check` auch für uns.

**Geprüft** (Puppeteer, echte Anmeldung im Backend): Zwischenspeicher künstlich auf ein 2.4.0-Manifest gesetzt, Schaltfläche gefunden und geklickt, Rückmeldung „Neueste Veröffentlichung: Plugin 2.5.0, Theme 2.5.0", Zwischenspeicher danach auf 2.5.0.

**Zum Zeitverhalten ohne Knopf:** WordPress fragt von sich aus etwa zweimal täglich nach, unser Manifest liegt sechs Stunden im Zwischenspeicher. Ohne Zutun kann eine neue Veröffentlichung also einen halben bis ganzen Tag brauchen, bis sie im Dashboard steht. Das ist normal – und ab 2.5.1 mit einem Klick abkürzbar.

## Mobile Navigation neu gebaut – Version 2.6.0 (30.09.2026)

### Was falsch war
Ein Blick ins Menü und in `nav.json` zeigte drei Dinge auf einmal:

1. **Fünf Themen standen doppelt.** Einsteiger, FOB, Logistik, Gameplay und Equipment gab es einmal als `/thema/…` (Guide-Filter) und einmal als `/wardogs/…` (Themenseite) – im mobilen Overlay direkt untereinander.
2. **Technik fehlte komplett.** Weder als Filter noch als Themenseite verlinkt, obwohl es mit sechs Guides das zweitgrößte Thema ist. Über die Navigation war es nicht erreichbar.
3. Die `/thema/…`-Seiten sind absichtlich **noindex** – wir verlinkten also prominent genau die Seiten, die wir Suchmaschinen vorenthalten.

### Was jetzt steht
Guides · Das Spiel (mit Überblick und allen sechs Themen) · Patch Notes · Partner · About us. Die Themenauswahl für die Bibliothek passiert über die Filter-Chips auf `/guides/`, die es ohnehin gibt.

### Drei Anläufe, bis das Akkordeon saß
- **Erster Versuch:** die vorhandene Core-Logik nutzen. Der Umschalter war da, wurde aber von meinem eigenen `display:none` versteckt. Nach dem Entfernen zeigte die Messung: `aria-expanded` bleibt im Overlay dauerhaft `true`, der Core hält dort alle Untermenüs offen und reagiert nicht auf den Knopf.
- **Zweiter Versuch:** eigene Klasse `wl-open`, gesetzt per Skript. Funktionierte sofort – 5 Zeilen zugeklappt, 12 aufgeklappt, beim zweiten Tipp wieder 5.
- **Dritter Punkt:** Beschriftung und Pfeil standen zentriert untereinander, weil der Core die Einträge im Overlay als zentrierte Flex-Container anlegt. Statt weiter gegen fremde Flex-Regeln anzuschreiben, ist die Beschriftung jetzt ein normaler Block und der Umschalter sitzt absolut am rechten Rand. Gemessen: Beschriftung bei x=23 über die volle Breite, Umschalter bei x=319.

### Geprüft
| Prüfung | Ergebnis |
| --- | --- |
| zugeklappt | 5 sichtbare Links, Inhaltshöhe 844 px = genau ein Bildschirm |
| Tipp auf den Umschalter | 12 Links |
| erneuter Tipp | wieder 5 |
| Suchfeld im Overlay | vorhanden und sichtbar, auf Desktop ausgeblendet |
| aktuelle Seite | markiert, mit `aria-current` |
| auf `/wardogs/technik/` | Zweig öffnet sich von selbst, „Technik" markiert |
| Kontrast | alle Einträge über 4,5:1 |
| JS-Fehler | keine |

Lighthouse mobil unverändert: Performance 97, Accessibility 100, SEO 100. Acht Testdateien grün.

**Lehre aus diesem Durchgang:** Ein Screenshot und eine Messung sagen Verschiedenes. Die Zahlen meldeten „Umschalter bei x=319, alles richtig", während das Bild noch die alte Anordnung zeigte – weil `head -4` in der Prüfkette den Node-Prozess vor dem Screenshot abgeschossen hatte. Beides ansehen, nicht eines statt des anderen.

## Hell/Dunkel und sechs Farbwelten – Version 2.7.0 (30.09.2026)

### Aufbau
Alles läuft über die Preset-Variablen aus `theme.json`, überschrieben auf `html[data-wl-theme]` und `html[data-wl-mode]`. Damit Transparenzen mitwandern, wurden **36 feste Farbwerte** in `main.css` und `motion.css` durch RGB-Tripel ersetzt (`rgb(var(--wl-accent-rgb) / 0.4)`). Die Wahl liegt im localStorage und wird von einem Skript im Kopfbereich gesetzt – vor dem ersten Bild, sonst blitzt die Standardfarbe auf.

Farbwelten: Warleek (Lauchgrün), Monochrom, Valkyra (Rot), Lonestar (Blau), Manticore (Grün), Pastell (Rosa/Violett). Modi: System, Hell, Dunkel.

### Gemessen statt angesehen
`bin/themecheck.js` schaltet alle 6 Farbwelten × 2 Modi durch, über sechs Seiten, und misst für jeden Textknoten Farbe gegen tatsächlichen Hintergrund:

| Durchgang | Ergebnis |
| --- | --- |
| erster Lauf | **24 Stellen** unter der Grenze |
| nach Held-Korrektur | 31 (andere Fehler sichtbar geworden) |
| nach Akzent-Token | 11 |
| nach gepinntem Held-Akzent | 5 |
| Endstand | **alle 12 Kombinationen bestehen WCAG AA**, schwächster Wert 5,15:1 |

Vier echte Fehler hat erst diese Messung gezeigt:

1. **Dogtag-Pille im Held-Bereich.** Text hell, Hintergrund im Hell-Modus weiß – 1,01:1. Ursache: Der Held-Bereich hatte nur Text und Grundfarbe gepinnt, nicht die Flächen darin.
2. **Schrift auf Akzentflächen.** Knöpfe nutzten `--base`. Im Hell-Modus ist der Akzent dunkel und `--base` hell, im Dunkel-Modus umgekehrt – bei Rot und Blau reichte es in keiner Richtung. Jetzt gibt es `--wl-on-accent-rgb`, das je Modus kippt.
3. **Akzent im Held-Bereich.** Im Hell-Modus wurde er dunkel und stand damit dunkel auf dunklem Foto (2,55:1). Jeder Farbwelt hängt jetzt ein `--wl-hero-accent-rgb` an, das der Modus nicht anfasst.
4. **Kernblock-Knöpfe im Held-Bereich.** `--base` war dort nicht gepinnt, der Knopf bekam helle Schrift auf hellem Akzent (1,68:1 bei Pastell).

**Und ein Fehler im Messgerät:** Fünf Meldungen blieben übrig, die sich nicht nachvollziehen ließen – die Knöpfe hatten nachweislich die richtige Farbe. Ursache: Das Skript maß 150 ms nach dem Umschalten, mitten in der CSS-Überblendung, und las Zwischenwerte. Seitdem schaltet es Übergänge vor dem Messen ab.

### Weiteres
- Umschalter per echtem Mausklick **und** per Tastatur geprüft: Attribut gesetzt, im localStorage gemerkt, übersteht das Neuladen, „System" entfernt das Attribut wieder.
- Eingabefelder auf das übliche 1×1-Muster statt 0×0 – manche Vorlesehilfen überspringen 0×0.
- **CLS von 0,104 auf 0** auf der Guide-Übersicht. Die Verschiebung kam nicht vom Umschalter, sondern von zwei Schriftschnitten, die nicht vorgeladen wurden (Kartenüberschriften und Monoschrift). Lighthouse nennt die Ursache im Audit `layout-shifts` direkt.
- Lighthouse mobil: Startseite Performance 98, Barrierefreiheit 100, SEO 100, CLS 0, LCP 2,3 s. Guide-Übersicht 94 / 100 / CLS 0.
- Acht Testdateien grün.
