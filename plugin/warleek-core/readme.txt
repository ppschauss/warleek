=== Warleek Core ===
Contributors: warleek
Tags: guides, gaming, steam, patch notes
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 2.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Funktionskern und Installer der Warleek-Guide-Seite: Guides mit Themenfilter, Patch Notes von Steam, Partner-Verwaltung, eigene Blöcke – plus Klick-Installer für alle Inhalte.

== Description ==

Warleek Core enthält alles Funktionale der Website warleek.de:

* Inhaltstypen **Guides** und **Patch Notes** samt Themen-Taxonomie
* **Steam-Sync**: holt die offiziellen WARDOGS-Ankündigungen stündlich, wandelt Steam-BBCode in saubere Blöcke um und lädt enthaltene Bilder in die eigene Mediathek (kein Request an Valve beim Besucher)
* **Optionsseite** für Discord, WhatsApp, Telegram, Clan-Tag, Social-Profile und Steam-AppID
* **Eigene Blöcke**: Chat-Buttons, neueste Patch Notes, Guides-Raster
* **SEO-Metas**: Dublin Core, Open Graph, Twitter Cards, Organization-Schema – tritt automatisch zurück, sobald Rank Math eingerichtet ist
* **Deutsche Patch Notes**: Übersetzung und Kurzfassung über die Claude-API (serverseitig; es werden nur die öffentlichen Ankündigungstexte übertragen, keine Besucherdaten)
* **Datenschutz**: keine Emoji-Skripte, kein oEmbed, kein XML-RPC, keine Kommentare, keine externen Requests im Browser
* **Guide-Import**: Markdown-Dateien oder ZIPs per Backend-Formular oder WP-CLI einspielen – mit Trockenlauf, Bildern aus dem Archiv und Themenerkennung aus den Stichwörtern
* **Installer**: legt Seiten, Guides, Partner, Menüs, Startseite, Logo und Favicon an, zieht alte Seiten zurück und installiert Rank Math – wiederholbar, ohne Doppelte anzulegen

== Installation ==

1. Plugin hochladen und aktivieren.
2. Theme *Warleek* aktivieren (Design → Themes).
3. Menü **Warleek → Installation** öffnen und auf *Komplett installieren* klicken.
4. Unter **Warleek → Einstellungen** die Chat-Links eintragen.

Per WP-CLI: `wp warleek install`, `wp warleek status`, `wp warleek sync-patchnotes`, `wp warleek translate-patchnotes`, `wp warleek import-guides <pfad>`.

== Frequently Asked Questions ==

= Überschreibt ein zweiter Durchlauf meine Texte? =
Nein. Standardmäßig werden vorhandene Seiten anhand ihres Permalinks aktualisiert, aber nicht ersetzt. Nur mit der Option *Inhalte überschreiben* bzw. `--force` werden die mitgelieferten Texte erneut eingespielt.

= Was passiert, wenn Steam nicht erreichbar ist? =
Bestehende Patch Notes bleiben unverändert, im Backend erscheint ein Hinweis, und der stündliche Cron versucht es erneut.

= Brauche ich Rank Math? =
Nein. Ohne SEO-Plugin liefert Warleek Core Titles, Descriptions, Open Graph und Dublin Core selbst.

== Changelog ==

= 2.4.0 =
* SEO auf DACH umgestellt: Titel und Beschreibungen aller Guides und Seiten neu geschrieben nach dem Muster Wardogs + Thema + Sprache/Land, Titel und Beschreibung bewusst unterschiedlich formuliert und auf Suchergebnis-Länge geprüft.
* Echte DACH-Signale: hreflang für de-DE, de-AT, de-CH, de und x-default, dazu og:locale:alternate und geo.region für alle drei Länder.
* Patch Notes tragen jetzt Datum und Version in Titel und Beschreibung; die Übersichtsseite nennt das Datum der jüngsten Patch Note.
* Strukturierte Daten für Patch Notes (Article mit Bezug auf das Spiel), für die Übersicht (CollectionPage mit ItemList) und Brotkrumen auf allen Inhaltsseiten.
* Impressum und Datenschutzerklärung mit den echten Betreiberdaten gefüllt, Datenschutz um Einwilligung, externe Videos und Aufbewahrungsfristen erweitert.
* Neu: Einwilligung für externe Medien (`inc/consent.php`) samt Shortcode `[warleek_video]`. Vor der Zustimmung entsteht keine Verbindung zu YouTube – zu sehen ist nur eine Vorschau vom eigenen Server. Die Entscheidung liegt im localStorage, nicht in einem Cookie.
* Markdown erkennt Warleek-Shortcodes als eigene Blöcke.

= 2.3.0 =
* Zwei neue HOTAS-Guides, ausgewertet aus zwei englischsprachigen Video-Guides: Empfindlichkeit, Deadzone und Kurven – sowie Flugtechnik mit Combat Orbit und schneller Landung.
* Drei eigene Schaubilder (Kennlinien, Combat Orbit, Landeprofil) als SVG gezeichnet und gerendert – statt Bildschirmfotos aus fremden Videos.
* Faktenbasis um den HOTAS-Abschnitt erweitert, inklusive des offenen Widerspruchs zwischen beiden Quellen zum Thema Kurven.

= 2.2.0 =
* Bilder im Fließtext: Guides können Bilder mitten im Text tragen, referenziert über einen Asset-Schlüssel (`![Alt](bild-hot-zone)`), optional mit Bildunterschrift (`![Alt](schluessel "Unterschrift")`).
* 20 neue Bilder im Text verteilt – jeder neue Guide hat jetzt mindestens eines.
* Der Guide-Import holt Bilder aus dem Fließtext mit: Datei neben der Markdown-Datei oder in `images/` im ZIP.
* Unbekannte Bild-Schlüssel werden entfernt statt als kaputtes Bild ausgeliefert.

= 2.1.0 =
* 20 neue Guides: Fraktionen, Anfängerfehler, Fortschritts-Tracks, Spawnen, Kontrollzone, Medic, Recon, Panzerabwehr, Bauen, FOB-Verteidigung, Flugabwehr, Logistik-Fuhrpark, Waffenkauf, Zeroing, Gewichtsklassen, Helikopter, Einstellungen, Ruckler, Tastenbelegung – und ein Satire-Guide für Team Blau.
* Guides werden jetzt aus Markdown gebaut: `content-src/guides/*.md` plus `bin/guides-build.php` schreiben Inhalts-JSON und Medien-Manifest.
* Markdown kennt `> [!stand] …` für die Datumszeile über jedem Guide.
* 20 neue Guide-Bilder, Reihenfolge der Guides thematisch in Zehnerblöcken.
* Faktenbasis in `docs/research/wardogs-facts.md` erweitert (Fraktionen, Kontrollzone, Waffen- und Fahrzeugpreise, Spawn, Ballistik, Gewicht).

= 2.0.0 =
* Updates über GitHub-Releases: Theme und Plugin melden neue Versionen im WordPress-Dashboard und lassen sich dort mit einem Klick aktualisieren.
* Guide-Import aus Markdown (Backend-Tab und WP-CLI) samt ZIP, Bildern, Trockenlauf und Themenerkennung.
* Patch Notes werden ins Deutsche übersetzt (Claude-API) und bekommen eine Kurzfassung „Das Wichtigste in Kürze"; das englische Original bleibt zum Aufklappen erhalten.
* Umbau zur Guide- und Tipps-Seite: Community-, Team- und Clan-Seiten entfallen, `/community/` wird zur Partner-Seite.
* Neuer Inhaltstyp **Partner** mit Karten-Block, Plattform-Chip und Sortierung.
* Guide-Bibliothek mit Themen-Chips, Suche, einheitlicher Sortierung, Seitenumbruch und Lesezeit; verwandte Guides zeigen dasselbe Thema ohne den gerade gelesenen.
* Neues Thema **Technik** samt Themenseite; neue Themenseite **Einsteiger**.
* Entfernte Seiten wandern in den Papierkorb und bekommen eine 301-Weiterleitung statt verwaist online zu bleiben.
* Der Installer überschreibt von Hand bearbeitete Texte nicht mehr; `--force` wirkt jetzt wirklich.
* Markdown-Modul (`inc/markdown.php`) als Grundlage für den Guide-Import.
* SEO auf Guide-Suchintention umgestellt, WebSite- und TechArticle-Schema ergänzt.

= 1.1.0 =
* Inhalte kleben nicht mehr auf einer festen Breite: Der Seed schreibt keine Pixelbreiten mehr ins Blockmarkup, und vorhandene Seiten werden beim Rendern davon befreit. Die Breite kommt jetzt aus dem Theme (theme.json/CSS) und lässt sich jederzeit ändern.

= 1.0.2 =
* Bilder aus Patch Notes werden in Originalgröße mit srcset eingebunden (vorher „large“, dadurch auf breiten Layouts unscharf).

= 1.0.1 =
* Verträglich mit älteren Warleek-Themes (0.1.x), die die Funktionsmodule noch selbst mitbrachten: das Plugin erkennt das, überlässt dem Theme den Funktionsteil, stellt nur den Installer bereit und weist auf das Theme-Update hin. Vorher schlug die Aktivierung mit einem Fatal Error fehl.
* WP-CLI-Klasse umbenannt (Warleek_Core_CLI), damit sie nie mit einer Theme-Variante kollidiert.
* Keine PHP-Warnung mehr wegen doppelt definierter Konstante WARLEEK_VERSION.

= 1.0.0 =
* Erste Veröffentlichung: CPTs, Steam-Sync, Optionen, Blöcke, SEO-Metas, Datenschutz-Hardening und Klick-Installer.
