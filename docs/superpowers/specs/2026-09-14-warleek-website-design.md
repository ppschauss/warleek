# Warleek – Wardogs Community-Website (warleek.de) – Design-Spec

Datum: 2026-09-14 · Status: zur Review

## 1. Ziel

Deutschsprachige Community-Website für das Spiel **WARDOGS** (Steam-AppID 1867240) unter
der Marke **Warleek** (warleek.de). Die Seite soll für die Keywords
*Wardogs Clan*, *Wardogs Community*, *Wardogs Team*, *Wardogs Discord* in Kombination mit
*DACH / Deutschland / Österreich / Schweiz* ranken, Besucher in die Chat-Kanäle
(Discord, WhatsApp, Telegram) leiten und mit Spiel-Content (FOB, Logistik, Gameplay,
Equipment, Guides, automatische Patch Notes) organischen Traffic aufbauen.

**Nicht-Ziele:** kein Forum, keine Nutzerregistrierung, kein Server-Status, keine
Turnierverwaltung, keine Mehrsprachigkeit (nur Deutsch).

## 2. Rahmen

- **Stack:** WordPress Block-Theme `warleek`, Ansatz „r3vive-Skelett forken, alles Sichtbare neu".
  Übernommen werden nur: functions.php-Aufbau, Template-Set-Idee, Seed-aus-JSON-Mechanik,
  `manage.sh`-Muster (plain `docker run`, kein Compose-Plugin), All-Inkl-Deploy-Doku.
- **Hosting:** Produktion auf All-Inkl (PHP/MySQL, WP-CLI per SSH); Dev lokal auf Unraid.
- **Plugins in Prod:** RankMath SEO. Sonst keine.
- **Brand:** Name Warleek, Logo = Dogtag + Lauch (wird generiert), Domain warleek.de.
- **Chat-Kanäle:** Discord, WhatsApp, Telegram – Invite-Links kommen später und werden
  als WP-Optionen gepflegt.
- **Organisation:** drei Stufen – Community (offen) → Team (Feierabend-Gruppe) → Clan (feste Truppe mit Tag).

## 3. Seitenstruktur & URLs

```
/                                  Startseite
/community/                        Übersicht Community · Team · Clan (3 Stufen)
/community/wardogs-community/      ausführliche Community-Seite
/community/wardogs-team/           ausführliche Team-Seite (Feierabend-Gruppe)
/community/wardogs-clan/           ausführliche Clan-Seite
/wardogs-chat/                     Discord · WhatsApp · Telegram
/wardogs-discord/                  eigene Discord-Seite (Haupt-Keyword)
/wardogs/                          Spiel-Hub
/wardogs/fob/
/wardogs/logistik/
/wardogs/gameplay/
/wardogs/equipment/
/guides/                           Archiv CPT `guide`
/guides/<slug>/                    einzelner Guide
/patch-notes/                      Archiv CPT `patchnote`
/patch-notes/<slug>/               einzelne Patch Note
/about-us/                         Über uns
/impressum/  /datenschutz/         Pflichtseiten (Gerüst)
```

**Navigation (Header):** Community ▾ (Übersicht, Community, Team, Clan) · Wardogs ▾ (Hub, FOB,
Logistik, Gameplay, Equipment) · Guides · Patch Notes · About us · **[Discord-Button]**
**Footer:** Chat-Kanäle, Rechtliches, Hinweis „Warleek ist ein Fan-Projekt und steht in keiner
Verbindung zum Entwickler/Publisher von WARDOGS".

## 4. SEO

- RankMath; Title/Description je Seite mit Kern-KW + Länderbezug (z. B.
  „Wardogs Clan Deutschland, Österreich & Schweiz | Warleek").
- DACH-Kombination steht im Fließtext der Startseite und der drei Stufen-Seiten
  (mind. je ein Absatz mit Deutschland/Österreich/Schweiz/deutschsprachig), nicht nur in Titles.
- H1 = eine pro Seite, Kern-KW enthalten; Guides mit sprechenden Slugs.
- Schema.org `Organization` (Warleek, Logo, sameAs → Discord/Steam) auf allen Seiten,
  `Article` auf Guides/Patch Notes (RankMath übernimmt Article).
- Interne Verlinkung: Hub ↔ Themen-Seiten ↔ Guides; Patch Notes verlinken auf Guides, wo passend.
- Fonts selbst gehostet (DSGVO), keine externen Requests außer Steam-Bilder in Patch Notes.

## 5. Visuelles Design

### Palette (theme.json)
| Token | Wert | Verwendung |
|---|---|---|
| base | `#0d110f` | Seitenhintergrund |
| surface | `#161c18` | Karten, Sektionen |
| surface-2 | `#1f2822` | Hover/Erhöhung |
| olive | `#4a5a44` | Rahmen, ruhige Flächen |
| sand | `#c7b58f` | sekundäre Highlights, Eyebrow-Text |
| steel | `#8a938c` | Meta-Text |
| text | `#e6eae4` | Fließtext |
| leek | `#9be15d` | Akzent: Buttons, Links, Marker, Flares |
| leek-light | `#eef5e6` | heller Gegenpol, Headlines auf dunkel |
| alert | `#e0843a` | Badges „Patch"/„Live", sparsam |

### Typografie (self-hosted, `assets/fonts/`, WOFF2)
- Headlines: **Barlow Condensed** 700/800, Versalien, `letter-spacing: .04em`
- Fließtext: **Barlow** 400/500/600
- Details: **JetBrains Mono** 400 für Dogtag-Zeilen wie `WARLEEK // DACH // EST. 2026`
- Skala: fluid (clamp), H1 ~ 3–5.5rem, Body 1.0625rem, Zeilenhöhe 1.6

### Logo
Dogtag-Silhouette mit Kugelkette; auf dem Tag ein stilisierter Lauch (weiß-grün) als Prägung;
Wortmarke WARLEEK in Barlow Condensed. Varianten: Icon-only, Wortmarke, Kombi (horizontal),
jeweils auf transparent/dunkel. Favicon = Icon. Generierung via Higgsfield, Auswahl durch Patrick.

### Bildwelt
Generierte Key-Visuals im Wardogs-Look (osteuropäische Industrie-Berglandschaft, FOB,
Logistik-LKW, Squad, Nacht mit Leuchtspur), leicht entsättigt, lauchgrüner Stich auf Highlights.
Pro Themen-/Stufen-Seite ein Hero-Bild (16:9, ≤ 250 KB WebP), Guide-Cards (3:2), About-Portraits
(Platzhalter). Alle Bilder im Theme unter `assets/img/`.

### Video
- Hero-Loop Startseite: 6–8 s, Rauch über Landschaft, nahtlos, stumm, autoplay, `playsinline`,
  Poster-Fallback (WebP). Ziel ≤ 2,5 MB (WebM VP9 + MP4 H.264, 1280×720).
- Optional zweiter Loop für `/community/` (gleiche Vorgaben). Nur wenn das Ergebnis überzeugt.
- Reduced-Motion / Datensparmodus (`prefers-reduced-data`): Video wird nicht geladen, Poster bleibt.

### Motion
- **Seitenwechsel:** Cross-Document View Transitions API (`@view-transition { navigation: auto }`).
  Smoke-Wipe: alte Seite wird über eine animierte Rauch-Maske (SVG-Turbulence/Textur) weggezogen,
  neue Seite kommt darunter hoch, dazu ein lauchgrüner Flare-Streifen. Dauer ~600 ms.
  Ohne Browser-Support: normale Navigation (progressive Enhancement, kein SPA-Routing).
- **Seiteneinstieg:** Smoke-Drift-Layer im Hero (CSS-animierte Textur), Flare-Glow hinter Logo.
- **Micro:** Button-Flare-Sweep on hover, Card-Lift, Count-up für Stats (IntersectionObserver).
- `prefers-reduced-motion: reduce` → nur einfache Fades, keine Smoke-Layer, keine Count-ups.
- Kein Third-Party-JS; Vanilla JS (≤ 10 KB) + CSS.

## 6. Technik

### Theme-Layout `theme/warleek/`
```
style.css  theme.json  functions.php  screenshot.png
inc/cpt-guide.php        CPT guide + Taxonomie guide-thema (FOB, Logistik, Gameplay, Equipment, Einsteiger)
inc/cpt-patchnote.php    CPT patchnote, Meta: steam_gid, steam_url, steam_published_at
inc/steam-sync.php       Import Steam-News → patchnote (Cron + WP-CLI)
inc/bbcode.php           Steam-BBCode → HTML (reine Funktion, testbar)
inc/options.php          Settings-Seite „Warleek": discord_url, whatsapp_url, telegram_url,
                         steam_appid (Default 1867240), clan_tag, kontakt_email
inc/seo.php              Organization-Schema, OG-Defaults, Fan-Projekt-Hinweis
inc/blocks.php           dynamische Blöcke: warleek/patchnotes-latest, warleek/chat-buttons, warleek/guides-grid
templates/  front-page, index, page, page-hub, single, single-guide, archive-guide,
            single-patchnote, archive-patchnote, search, 404
parts/      header.html, footer.html
patterns/   hero, three-tiers, chat-cta, guides-grid, patchnotes-latest, faq, stats, about-team, page-hero
assets/     css/main.css css/motion.css js/main.js img/ video/ fonts/
_content/   pages.json guides.json nav.json   (nur für Seed, nicht im Prod-Zip nötig)
```

### Patch-Notes-Sync
- Endpoint: `https://api.steampowered.com/ISteamNews/GetNewsForApp/v2/?appid={appid}&count=30&maxlength=0&feeds=steam_community_announcements&format=json`
- Auswahl: Items mit Tag `patchnotes`; Fallback: Titel matcht `/patch|update|hotfix|changelog/i`.
- Dedupe: `steam_gid` als Post-Meta; vorhandene Posts werden aktualisiert, wenn sich `contents` ändert.
- Post: Titel = Steam-Titel, Datum = Steam `date`, Inhalt = BBCode→HTML, Meta `steam_url`.
  Bilder bleiben auf Steam-CDN (`{STEAM_CLAN_IMAGE}` wird zu `https://clan.akamai.steamstatic.com/images/` aufgelöst).
- Trigger: WP-Cron `warleek_sync_patchnotes` stündlich; `wp warleek sync-patchnotes [--force]`.
- Fehler: HTTP-Fehler/ungültiges JSON → Option `warleek_sync_last_error` + Admin-Notice; bestehende Posts unberührt.
- Jede Note zeigt Quellenhinweis + Link zur Steam-Ankündigung.

### BBCode-Konverter (`inc/bbcode.php`)
Unterstützt `[h1]..[h3]`, `[b]`, `[i]`, `[u]`, `[strike]`, `[list]/[olist]/[*]`, `[url=]`,
`[img]`, `[quote]`, `[code]`, `[hr]`, `[previewyoutube=ID;full]` → YouTube-Link (kein Embed),
Zeilenumbrüche → `<br>`/`<p>`. Unbekannte Tags werden entfernt. Ausgabe wird durch `wp_kses_post` geführt.

### Lokale Dev (`manage.sh`)
Befehle: `up | down | wpcli <args> | seed | sync | zip | urls | shot <pfad>`
- Container `warleek-db` (mariadb:11), `warleek-wp` (wordpress:php8.3-apache), Netz `warleek-net`, Port **8088**.
- Theme per Bind-Mount, Dev-Login `admin / warleekadmin` (nur lokal).
- Dynamischer `WP_HOME`/`WP_SITEURL`-Fix aus dem bestehenden WP-Setup (Zugriff via IP/Hostname) – Dev-only.
- `seed`: legt Seiten/Guides/Navigation aus `_content/*.json` an, setzt Startseite, Permalinks, Optionen.
- `sync`: führt `wp warleek sync-patchnotes` aus.
- `zip`: erzeugt `warleek.zip` ohne `_content/`.
- `shot`: Screenshot per headless Chromium (Gotcha aus wp-theme-dev-Skill beachten).

## 7. Inhalte

Alle Texte Deutsch, „du"-Ansprache, eigenständig geschrieben, Fakten geprüft gegen Steam-Store/News,
wardogswiki.com, wardogs-game.com, GameSpot. Kein Copy-Paste.

| Seite | Kern-KW | Inhalt |
|---|---|---|
| Startseite | Wardogs Community Deutschland | Hero + Claim, 3-Stufen-Teaser, Chat-CTA, letzte 3 Patch Notes, 3 Guides, „Warum Warleek", DACH-Absatz |
| /community/ | Wardogs Community / Clan / Team | Erklärung der 3 Stufen, Vergleichstabelle, CTA je Stufe |
| Community | Wardogs Community DACH | offen für alle, Regeln, Kanäle, Sprache, Spielzeiten, FAQ |
| Team | Wardogs Team Feierabend | wer wir sind, feste Abende, Squad-Rollen, „mitspielen"-CTA |
| Clan | Wardogs Clan Deutschland/Österreich/Schweiz | Tag, Struktur, Voraussetzungen, Bewerbung, Verhaltenskodex |
| Chat | Wardogs Discord / WhatsApp / Telegram | 3 Karten mit Button, was wo passiert |
| Discord | Wardogs Discord Deutsch | Kanalstruktur, Regeln, Rollen, Invite-CTA |
| Hub | Wardogs Guide Deutsch | Was ist Wardogs (100 Spieler, 3 Teams, Zone, Cash), Links zu 4 Themen |
| FOB | Wardogs FOB | Bau, Versorgung, Verteidigung, Platzierung, typische Fehler |
| Logistik | Wardogs Logistik | Paletten, LKW/Flug, Kosten/Erlös, Routen, Tipps |
| Gameplay | Wardogs Gameplay | Zone, 3-Fraktionen-Dynamik, Economy, Squad, Kommunikation |
| Equipment | Wardogs Equipment / Loadout | Kits/Klassen, Waffen, Fahrzeuge, Kauf-Reihenfolge |
| About us | Warleek | Story, Gründer, Werte, Kontakt |
| Impressum / Datenschutz | – | Gerüst mit `[PLATZHALTER]`, Datenschutz nennt Hosting, keine Cookies/Tracking, Steam-Bilder |

Themen-Seiten ~800–1200 Wörter. **Guides (6):** Erste Runde als Neuling · FOB-Bau Schritt für
Schritt · Der Logistik-Run, der sich lohnt · Geld verdienen & sinnvoll ausgeben ·
Squad-Kommunikation & Funk · Fahrzeuge fahren/fliegen ohne Teamkill (je ~600–900 Wörter).

Spielzahlen (Preise, Erlöse, Spielerzahl) werden mit Stand-Datum im Text markiert, da Patches sie ändern.

## 8. Vorgehen & Qualitätssicherung

1. Projekt anlegen (git init), Theme-Skelett, `manage.sh`, Dev hochfahren.
2. Logo-Varianten, Key-Visuals, Hero-Video generieren → Logo-Auswahl durch Patrick.
3. theme.json, Templates, Parts, Patterns, CSS/Motion.
4. CPTs, Optionen, Blöcke, BBCode-Konverter (PHPUnit-freie Tests: `php tests/bbcode-test.php` mit Fixtures), Steam-Sync (Testlauf gegen echte API via `manage.sh sync`).
5. Inhalte in `_content/*.json`, Seed, Navigation.
6. Screenshot-Check aller Seiten Desktop (1440) + Mobile (390), Lighthouse ≥ 90 Performance/SEO/A11y auf Startseite, Reduced-Motion-Check, Firefox-Check (Transitions-Fallback).
7. `README-Deploy.md` (All-Inkl: Theme-Zip/rsync, WP-CLI-Seed, RankMath, Optionen setzen, Cron), Theme-Zip.

**Definition of Done:** alle Seiten aus Abschnitt 3 existieren mit Inhalt; Patch Notes werden per
Sync importiert und angezeigt; Chat-Buttons ziehen Links aus Optionen; Transitions laufen in Chrome
und degradieren sauber; Screenshots liegen vor; Deploy-Doku vorhanden.
