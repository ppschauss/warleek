=== Warleek Core ===
Contributors: warleek
Tags: guides, gaming, steam, patch notes
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 2.20.1
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

= 2.20.1 =
* Medic-Guide: Eine Rauchgranate hält lang genug für Wiederbeleben **und** Heilen – der Rat, eine zweite für den Rückweg zu werfen, war falsch und ist ersetzt.

= 2.20.0 =
* Neuer Guide „Der Medic-Rucksack": Pistole statt Gewehr, Rauch als Werkzeug, und der Griff, mit dem die eingesetzte Batterie einen Rucksackplatz freigibt.
* Korrigiert: Der Guide zum Entfernungsmesser im Heli beschrieb eine Mechanik, die seit dem Hotfix vom 02.10. nicht mehr im Spiel ist. Jetzt mit Hinweis und einem Abschnitt zu Season 2.
* Korrigiert: Der Drill Rig fördert keinen Nachschub – er zieht die Hot Zone zur FOB.

= 2.19.0 =
* **Wichtig:** SEO-Titel, SEO-Beschreibungen, Beitragstitel und Datenblatt-Werte wurden bei jedem Einspielen überschrieben – auch ohne „überschreiben". Nur die Texte waren geschützt. Das ist behoben: Was im Backend geändert wurde, bleibt.
* Gilt auch für Installationen, die älter sind als dieser Schutz: Weicht ein Wert von dem ab, was mitgeliefert wird, gilt er als bearbeitet und bleibt.
* „Auswahl neu einspielen" überschreibt weiterhin alles – das ist der Zweck.
* Neuer Test `seed-schutz-test.php` sichert das ab.

= 2.18.0 =
* Neu: Herkunftsnachweis unter `/herkunft.json` – Quellen, Prüfstand, Bildherkunft und Änderungsdatum je Eintrag, maschinenlesbar als JSON-LD. Wird bei jedem Abruf erzeugt und kann deshalb nicht veralten.
* Datenbank-Seiten hatten bisher **kein** eigenes Schema. Jetzt `TechArticle` mit allen Datenblatt-Werten als `PropertyValue`, dazu Quellen (`citation`), Prüfer (`reviewedBy`) und Brotkrumen.
* Die Datenbank als Ganzes ist ein `Dataset` mit Stand, Umfang und Verweis auf den Nachweis.
* Neue Optionen für die prüfende Person (Name, Autorenseite, Profile) – ohne Namen entfällt die Angabe, statt anonym zu behaupten.

= 2.17.0 =
* 25 neue Datenbank-Einträge: die drei Hämmer, FOB, Claymore, AT-Mine, Munitionskiste, Bunkerboden und -dach, Mörser-Unterstand, acht Fahrzeugbewaffnungen, 9K333 Verba und M12G. Jetzt 98 statt 73.
* Fehler behoben: Nachgelieferte Einträge bekamen einen eigenen Plan ab morgen und stapelten sich auf die bestehenden Termine (20 an einem Tag statt 5–10). Die Warteschlange wird jetzt immer gemeinsam verteilt.
* 97 von 98 Einträgen haben ihr eigenes Symbol aus dem Spiel.

= 2.16.0 =
* 72 von 73 Datenbank-Einträgen haben jetzt ihr eigenes Symbol aus dem Spiel, freigestellt auf einem abgedunkelten Spiel-Hintergrund.
* Jeder Link auf einen Eintrag trägt `title="Wardogs <Name>"` – in der Tabelle, in den Listen und auf den Kategorie-Chips.
* Z20 Lakota heißt im Spiel inzwischen UH-1Y; beide Einträge umbenannt.
* Umbenannte Einträge werden sauber zurückgezogen, statt als Dublette liegen zu bleiben.

= 2.15.0 =
* Datenbank-Bilder jetzt dreistufig: eigenes Bild, sonst Gruppenbild (Scharfschützengewehre, Helikopter, Panzer …), sonst Kategoriebild. Statt zwei Motiven auf 73 Einträgen sind es elf.
* Neue Gruppen- und Kategoriebilder aus den offiziellen Trailern des Pressekits – Maschinengewehr, Logistik-LKW, gepanzertes Fahrzeug, Stellungen, Bauwerke.
* Alle 73 Einträge sind im Spiel abgeglichen; der Kasten „Woher die Angaben stammen" weist das jetzt aus, statt „nicht nachgeprüft" zu melden.
* Neu: `bin/items-bilder-holen.php` holt die Einzel-Symbole je Gegenstand.

= 2.14.0 =
* Datenbank: Die 15 Einträge, die im Spiel ohne Freischaltung verfügbar sind, erscheinen sofort statt in der Warteschlange.
* Veröffentlichungsplan auf 5–10 Einträge pro Tag erhöht (vorher 3). Ein geänderter Plan verteilt die Warteschlange neu, ohne Veröffentlichtes zurückzunehmen.
* Zwei neue Guides: „Was du ohne Freischaltung schon hast" und „Entfernungsmesser im Heli: der Pilot sieht alles Markierte".
* Verlinkung: Vorgabe für Links je Beitrag von 8 auf 15 erhöht.

= 2.13.0 =
* Neu: **Warleek → Verlinkung**. Gegenstände aus der Datenbank werden im Fließtext automatisch verlinkt, mit `title="Wardogs <Name>"`. Das Werkzeug zeigt vorher, wo jeder Gegenstand vorkommt, und schaltet einzelne ab.
* Verlinkt wird beim Anzeigen, nicht im gespeicherten Text: Beiträge bleiben unverändert, es entstehen keine Links auf noch nicht veröffentlichte Einträge, Abschalten stellt den alten Zustand her.
* Erste Fundstelle je Gegenstand, Deckel je Beitrag, nie in Überschriften, Code, vorhandenen Links oder Attributen – und kein Eintrag verlinkt sich selbst.

= 2.12.0 =
* Installation: Schritte lassen sich einzeln ankreuzen und ausführen – „Auswahl ausführen" oder „Auswahl neu einspielen" (mit Rückfrage). Jeder Schritt sagt, was ein erzwungener Lauf überschreibt.
* Der Medien-Schritt ersetzt mit „neu einspielen" jetzt wirklich geänderte Dateien – die Anhang-ID bleibt, Beitragsbilder und Bild-URLs in Seitentexten also gültig.
* WP-CLI: `wp warleek install --steps=media,items [--force]`.

= 2.11.0 =
* Datenbank: Kategoriebilder aus dem offiziellen Pressematerial von BULKHEAD/Team17 – alle 73 Einträge haben jetzt ein Beitragsbild.
* Jedes Bild kann eine Quellenangabe tragen (`credits.json` → `_warleek_credit`); sie erscheint im Kasten „Woher die Angaben stammen".
* Neu: Shortcode `[warleek_bildquelle]` und Funktion `warleek_bildquelle()` für Bildquellen an beliebigen Beiträgen.

= 2.10.0 =
* **Jeder Datenbank-Eintrag hat jetzt eine eigene Beschreibung** – Freischaltstufe, einmalige Freischaltgebühr, Preis pro Leben und wofür man den Gegenstand überhaupt nimmt. 73 Texte, keiner davon ein Platzhalter.
* Neues Feld **Freischaltgebühr**: Die einmalige Gebühr beim Erreichen einer Track-Stufe ist etwas anderes als der Preis pro Leben – 34 Einträge tragen jetzt beides getrennt.
* Bilder je Eintrag vorbereitet: Dateien aus `content-src/items/img/` werden über den Dateinamen zugeordnet (`wardogs-ak-74.webp` für einen Eintrag, `typ-waffe.webp` als Ersatzbild der Kategorie), eingespielt mit `php bin/items-media.php`.
* **Keine erzeugten Bilder für Gegenstände.** Solange kein Originalmaterial vorliegt, bleiben die Einträge bildlos – ein KI-Bild zeigt nicht die Waffe, um die es geht.
* Faktenbasis deutlich erweitert: Freischaltgebühren aller Waffen und Fahrzeuge, Schadens- und Reichweitenwerte, Fahrzeugpanzerung.

= 2.9.0 =
* **Datenbank** unter `/datenbank/`: neuer Inhaltstyp für Waffen, Fahrzeuge, Emplacements und Bauwerke, mit Kategorien, Datenblatt je Eintrag und Querverweisen auf die passenden Guides.
* **73 Einträge terminiert angelegt** – drei pro Tag, verteilt über gut drei Wochen. Veröffentlicht wird von WordPress selbst; vorhandene Einträge bleiben beim erneuten Lauf unberührt.
* Jeder Eintrag trägt denselben Herkunftskasten wie die Guides: Quelle genannt, „im Spiel nachgeprüft: nein", bis jemand nachmisst. In der Backend-Übersicht gibt es dafür eine eigene Spalte.
* Übersicht als gruppierte Tabelle statt Kartenraster – bei Preisen und Freischaltungen will man vergleichen, nicht blättern. Spalten erscheinen nur, wenn die Gruppe sie füllt.
* Guide-Archiv und Quelltext-Gruß behaupteten weiterhin „selbst gespielt" – berichtigt.

= 2.8.0 =
* **Herkunftskasten in jedem Guide.** Am Ende steht jetzt, aus welchen Quellen die Angaben stammen und ob sie im Spiel nachgeprüft sind. Erzeugt wird er aus den Daten (`quellen`, `geprueft` im Kopfblock), nicht von Hand getippt – fehlt die Angabe, gilt „nicht nachgeprüft".
* **About-us richtiggestellt.** Dort stand „Jeder Ablauf in einem Guide ist im Spiel durchgegangen". Das traf auf die recherchierten Guides nicht zu. Der Text beschreibt jetzt, wie die Guides wirklich entstehen.
* 27 von 29 Guides sind als recherchiert gekennzeichnet, der HOTAS-Dual-Stick-Guide als selbst geprüft.

= 2.7.0 =
* **Hell/Dunkel-Modus** mit drei Einstellungen: System, Hell, Dunkel. Ohne Wahl folgt die Seite dem Betriebssystem.
* **Sechs Farbwelten** zur Auswahl: Warleek (Lauchgrün), Monochrom, Valkyra (Rot), Lonestar (Blau), Manticore (Grün) und Pastell (Rosa/Violett) – die drei Fraktionsfarben nach den Teams benannt.
* Umschalter im Fußbereich, als Block „Darstellung umschalten" oder Shortcode `[warleek_appearance]` überall platzierbar. Die Wahl liegt im localStorage und wird im Kopfbereich gesetzt, also ohne Aufblitzen der Standardfarbe.
* Alle zwölf Kombinationen aus Farbwelt und Modus sind auf Kontrast gemessen und bestehen WCAG AA; der schwächste Wert liegt bei 5,15:1.
* Held-Bereiche und der Sperrkasten über Videos bleiben im Hell-Modus dunkel – darunter liegen dunkle Fotos.
* Zwei fehlende Schriftschnitte werden jetzt vorgeladen: Das Layout sprang beim Nachladen, CLS auf der Guide-Übersicht von 0,10 auf 0.

= 2.6.0 =
* **Navigation entdoppelt.** Fünf Themen standen zweimal im Menü – einmal als Guide-Filter, einmal als Themenseite. Jetzt führt „Das Spiel" die Themen, „Guides" ist ein einfacher Link auf die Bibliothek mit ihren Filter-Chips.
* **Technik war überhaupt nicht verlinkt** – das zweitgrößte Thema mit sechs Guides war über das Menü nicht erreichbar. Jetzt drin, im Menü wie im Footer.
* Mobiles Menü: Untermenü klappt auf Tipp auf statt dauerhaft offen zu stehen – aus 17 Zeilen werden 5. Ohne JavaScript bleibt alles offen und bedienbar.
* Suchfeld oben im mobilen Menü, auf Guides eingeschränkt.
* Die aktuelle Seite wird im Menü hervorgehoben, mit `aria-current` für Screenreader.

= 2.5.1 =
* Die Schaltfläche „Jetzt nach Updates suchen" fehlte in der Oberfläche – der Handler war da, nur verlinkt hat ihn niemand. Sie steht jetzt samt Versionszeile über allen drei Registerkarten.
* „Erneut prüfen" auf Dashboard → Aktualisierungen leert jetzt auch unseren Manifest-Zwischenspeicher. Vorher klickte man dort und bekam bis zu sechs Stunden lang die alte Antwort.

= 2.5.0 =
* **Mobiles Menü lesbar.** Die obersten Menüpunkte standen schwarz auf schwarz, weil eine Core-Regel `color:#000` auf das offene Overlay setzt und spezifischer war als die eigene. Kontrast jetzt 12:1 bis 17:1 statt 1,1:1.
* Das Einwilligungsbanner verschwindet, solange das mobile Menü offen ist – ein höherer z-index half nicht, weil der sticky Header einen eigenen Stapelkontext aufmacht.
* Einwilligung kennt jetzt zwei Kategorien: externe Medien und Statistik, bei mehreren Kategorien mit Einzelauswahl im Banner.
* Neues Feld für den Einbindungscode einer Reichweitenmessung. Der Code liegt bis zur Einwilligung wirkungslos im Quelltext und wird erst danach ausgeführt.
* `[warleek_video]` unterstützt Vimeo (`plattform="vimeo"`, numerische IDs werden erkannt) mit „Do Not Track“.
* Externe Links in Guides und Patch Notes bekommen `rel="nofollow noopener noreferrer"` – abschaltbar in den Einstellungen.
* Die beiden HOTAS-Guides zeigen statt eingebetteter Videos jetzt Bildschirmfotos daraus, jeweils mit Kanal und Zeitangabe.
* Datenschutzerklärung um Reichweitenmessung und Vimeo erweitert.

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
