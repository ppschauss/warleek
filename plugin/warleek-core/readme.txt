=== Warleek Core ===
Contributors: warleek
Tags: community, gaming, steam, patch notes
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Funktionskern und Installer der Warleek-Website: Guides, Patch Notes von Steam, Chat-Optionen, eigene Blöcke – plus Klick-Installer für alle Inhalte.

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

= 1.0.0 =
* Erste Veröffentlichung: CPTs, Steam-Sync, Optionen, Blöcke, SEO-Metas, Datenschutz-Hardening und Klick-Installer.
