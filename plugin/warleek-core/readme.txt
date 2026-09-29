=== Warleek Core ===
Contributors: warleek
Tags: community, gaming, steam, patch notes
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 2.0.0
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
* **Datenschutz**: keine Emoji-Skripte, kein oEmbed, kein XML-RPC, keine Kommentare
* **Installer**: legt Seiten, Guides, Menüs, Startseite, Logo und Favicon an und installiert Rank Math – wiederholbar, ohne Doppelte anzulegen

== Installation ==

1. Plugin hochladen und aktivieren.
2. Theme *Warleek* aktivieren (Design → Themes).
3. Menü **Warleek → Installation** öffnen und auf *Komplett installieren* klicken.
4. Unter **Warleek → Einstellungen** die Chat-Links eintragen.

Per WP-CLI: `wp warleek install`, `wp warleek status`, `wp warleek sync-patchnotes`.

== Frequently Asked Questions ==

= Überschreibt ein zweiter Durchlauf meine Texte? =
Nein. Standardmäßig werden vorhandene Seiten anhand ihres Permalinks aktualisiert, aber nicht ersetzt. Nur mit der Option *Inhalte überschreiben* bzw. `--force` werden die mitgelieferten Texte erneut eingespielt.

= Was passiert, wenn Steam nicht erreichbar ist? =
Bestehende Patch Notes bleiben unverändert, im Backend erscheint ein Hinweis, und der stündliche Cron versucht es erneut.

= Brauche ich Rank Math? =
Nein. Ohne SEO-Plugin liefert Warleek Core Titles, Descriptions, Open Graph und Dublin Core selbst.

== Changelog ==

= 2.0.0 =
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
