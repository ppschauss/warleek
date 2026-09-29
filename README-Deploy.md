# Warleek – Deploy auf All-Inkl (warleek.de)

Produktion: All-Inkl (Shared Hosting, PHP ≥ 8.1, MySQL). Dev: lokal via `./manage.sh` (siehe README.md).

Die Website besteht aus zwei Teilen:

| Datei | Was |
|---|---|
| `warleek-theme.zip` | Das Theme – Design, Templates, Schriften, Bewegung |
| `warleek-core.zip` | Das Plugin *Warleek Core* – Guides, Patch Notes, Optionen, Blöcke, SEO **und alle Inhalte samt Bildern** |

Beide baust du mit `./manage.sh zip`.

## Der schnelle Weg (drei Klicks)

1. **WordPress installieren** – KAS → Software-Installer → WordPress auf `warleek.de`.
2. **Theme hochladen:** WP-Admin → *Design → Themes → Hinzufügen → Theme hochladen* → `warleek-theme.zip` → aktivieren.
3. **Plugin hochladen:** *Plugins → Installieren → Plugin hochladen* → `warleek-core.zip` → aktivieren.
   WordPress springt danach direkt auf die Seite **Warleek → Installation**.
4. Dort auf **„Komplett installieren"** klicken. Das Plugin legt der Reihe nach an:
   Rank Math SEO installieren → Bilder & Video in die Mediathek → 15 Seiten → 6 Guides →
   Menüs, Startseite, Logo, Favicon, Permalinks → Patch Notes von Steam.
   Dauert etwa eine Minute; der Fortschritt steht live auf der Seite.
5. **Warleek → Einstellungen:** Discord-, WhatsApp- und Telegram-Link eintragen (ohne sie steht auf den Buttons „bald").

Der Durchlauf ist beliebig wiederholbar: Ein zweiter Klick aktualisiert, statt Doppelte anzulegen.
Nur wenn du *Inhalte überschreiben* ankreuzt, werden eigene Textänderungen durch die mitgelieferten Texte ersetzt.

## Variante mit SSH / WP-CLI

All-Inkl Premium hat SSH (Zugangsdaten im KAS unter „SSH-Zugang").

```bash
# Lokal: Pakete bauen
./manage.sh zip

# Hochladen (Pfade an dein Paket anpassen)
REMOTE=ssh-wXXXXXXX@wXXXXXXX.kasserver.com
DOCROOT=/www/htdocs/wXXXXXXX/warleek.de
scp warleek-theme.zip warleek-core.zip $REMOTE:$DOCROOT/

# Auf dem Server
ssh $REMOTE && cd $DOCROOT
wp theme install ./warleek-theme.zip --activate
wp plugin install ./warleek-core.zip --activate
wp warleek install          # alle Schritte, inklusive Rank Math
wp warleek status           # Übersicht, was steht und was fehlt
rm warleek-theme.zip warleek-core.zip

# Chat-Links setzen
wp option patch update warleek_options discord_url  'https://discord.gg/DEIN-INVITE'
wp option patch update warleek_options whatsapp_url 'https://chat.whatsapp.com/DEIN-LINK'
wp option patch update warleek_options telegram_url 'https://t.me/DEINE-GRUPPE'
```

Weitere Befehle: `wp warleek install --force` (Texte überschreiben), `wp warleek install --skip-plugins`,
`wp warleek sync-patchnotes [--force]`.

## Updates ab jetzt

Ab Version 2.0.0 melden sich Theme und Plugin selbst: Sobald eine neue Veröffentlichung
auf GitHub liegt, steht unter *Dashboard → Aktualisierungen* „Aktualisierung verfügbar",
ein Klick installiert sie. Kein ZIP-Upload mehr nötig. Inhalte bleiben dabei unberührt.

Manuell prüfen: *Warleek → Installation → Jetzt nach Updates suchen*.

## Nach dem Deploy – Pflicht

- **SSL** im KAS aktivieren (Let's Encrypt), danach
  `wp option update home https://warleek.de && wp option update siteurl https://warleek.de`, http→https im KAS umleiten.
- **Impressum & Datenschutz** ausfüllen (Seiten → Impressum / Datenschutz, Platzhalter in eckigen Klammern).
- **About us:** Team-Karten mit echten Namen, Texten und Bildern füllen.
- **Rank Math:** Setup-Assistent durchlaufen (Sitemap, Search Console). Bis dahin liefert Warleek Core die Meta-Daten selbst – es gibt also nie eine Lücke.
- **Patch Notes auf Deutsch:** Unter *Warleek → Einstellungen* einen Anthropic-API-Schlüssel eintragen (oder sicherer: `define( 'WARLEEK_ANTHROPIC_KEY', 'sk-ant-…' );` in die `wp-config.php`). Ohne Schlüssel bleiben die Patch Notes englisch, alles andere funktioniert normal. Der Deckel „Übersetzungen je Lauf" (Standard 5) begrenzt die Kosten; der stündliche Cron holt den Rest nach.
- **Patch-Notes-Cron:** WP-Cron läuft bei Seitenaufrufen (stündlich). Zuverlässiger: im KAS einen Cronjob anlegen, der stündlich
  `https://warleek.de/wp-cron.php?doing_wp_cron` aufruft, und in `wp-config.php` `define('DISABLE_WP_CRON', true);` setzen.
- **Logo tauschen** (falls andere Variante gewünscht): Design → Website-Editor → Header → Logo → Ersetzen.
  Die Varianten (Icon, Wappen, Schriftzug) liegen nach der Installation in der Mediathek.

## Inhalte pflegen (Backend)

- **Seiten:** Seiten → bearbeiten. Hero = Bild-Block + Video-Block, Texte sind normale Absätze, Überschriften, Listen und Tabellen.
- **Guides:** Guides → Neu. Beitragsbild, Auszug (erscheint auf den Karten), Thema zuweisen.
- **Guides in Menge:** *Warleek → Guides importieren* – Markdown-Datei oder ZIP hochladen, „Nur prüfen" zeigt vorher, was angelegt würde. Auf der Kommandozeile: `wp warleek import-guides <pfad> --dry-run`.
- **Partner:** eigener Menüpunkt *Partner*. Titel, Kurztext, Logo als Beitragsbild, Link und Plattform in der Seitenleiste, Reihenfolge über „Seitenattribute".
- **Patch Notes:** kommen automatisch. Manuell: *Warleek → Installation → Patch Notes von Steam holen* oder `wp warleek sync-patchnotes`.
- **Navigation:** Design → Website-Editor → Navigation („Hauptmenü", „Footer").
- **Discord-Link, Social, Übersetzung:** Warleek → Einstellungen.
- **Muster:** Im Editor unter *Muster → Warleek* (Hero, Drei Stufen, Chat-CTA, FAQ, Zahlen, Team, Karten).

## Updates

Theme und Plugin lassen sich unabhängig voneinander neu hochladen (*Theme/Plugin hochladen* → „Ersetzen"),
ohne dass Inhalte verloren gehen. Die Inhalte liegen in der Datenbank und der Mediathek, nicht im Paket.

## Deinstallation

Das Plugin entfernt beim Löschen nur seine eigenen Einstellungen. Seiten, Guides, Patch Notes und Medien bleiben erhalten.
