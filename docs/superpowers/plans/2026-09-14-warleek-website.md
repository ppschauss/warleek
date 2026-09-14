# Warleek Website Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** WordPress-Block-Theme `warleek` + lokale Dev-Umgebung + Seed-Content + Steam-Patch-Notes-Sync für warleek.de, deploybar auf All-Inkl.

**Architecture:** Block-Theme (FSE) mit schlanken `inc/`-Modulen (CPTs, Optionen, Steam-Sync, BBCode, Blöcke, SEO). Sämtlicher Content liegt als Blöcke in Seiten/Beiträgen und wird per idempotentem `seed.php` aus `_content/*.json` erzeugt; Bilder/Videos gehen in die Mediathek. Motion via CSS (View Transitions) + ≤10 KB Vanilla JS.

**Tech Stack:** WordPress 6.8+ (wordpress:php8.3-apache), MariaDB 11, WP-CLI, PHP 8.3, plain `docker run` (kein Compose-Plugin), headless Chromium in `wh-web-check` für Screenshots, Higgsfield MCP für Bild/Video, ffmpeg/cwebp für Optimierung.

**Spec:** `docs/superpowers/specs/2026-09-14-warleek-website-design.md`

## Global Constraints

- Alle Texte/Bilder im WP-Backend änderbar: kein Content in Templates/Patterns hart verdrahtet; Bilder in der Mediathek; Logo = Website-Logo; Links/Clan-Tag = Optionsseite.
- Asset-URLs im Theme root-relativ (`/wp-content/themes/warleek/...`), nie absolut.
- Fonts self-hosted (Barlow, Barlow Condensed, JetBrains Mono, WOFF2, latin subset). Keine externen Requests außer Steam-Bild-CDN in Patch Notes.
- Dev: Container `warleek-wp`/`warleek-db`, Netz `warleek-net`, Volumes `warleek-wp-data`/`warleek-db-data`, Port **8088**, Login `admin / warleekadmin` (nur lokal).
- Palette-Tokens exakt: base `#0d110f`, surface `#161c18`, surface-2 `#1f2822`, olive `#4a5a44`, sand `#c7b58f`, steel `#8a938c`, text `#e6eae4`, leek `#9be15d`, leek-light `#eef5e6`, alert `#e0843a`.
- Steam AppID Default `1867240`; Sync filtert Tag `patchnotes`, Fallback Titel-Regex `/patch|update|hotfix|changelog/i`; Dedupe über Meta `steam_gid`.
- `prefers-reduced-motion: reduce` → nur Fades; kein Third-Party-JS.
- Texte Deutsch, „du"-Ansprache, DACH-Bezug im Fließtext der Startseite + 3 Stufen-Seiten. Spielzahlen mit Stand-Datum.
- Commits nach jedem Task mit Co-Authored-By-Trailer (siehe Session).
- Shell-Hinweis: `cd` persistiert nicht zuverlässig → absolute Pfade / `git -C`.

**Projektpfad:** `P=/mnt/cache/appdata/scratch/wardogs-gc`, Theme `T=$P/theme/warleek`.

---

### Task 1: Projekt-Skelett, manage.sh, Dev-Instanz läuft

**Files:**
- Create: `manage.sh`, `.gitignore`, `theme/warleek/style.css`, `theme/warleek/theme.json` (minimal), `theme/warleek/functions.php` (Setup + Assets), `theme/warleek/templates/index.html`, `theme/warleek/parts/header.html`, `theme/warleek/parts/footer.html`, `theme/warleek/assets/css/main.css` (leer), `theme/warleek/assets/js/main.js` (leer)

**Interfaces:**
- Produces: `./manage.sh up|down|install|wp|seed|sync|zip|shot|urls`; Funktionen `warleek_setup()`, `warleek_assets()`, `warleek_snapshot_mode()`; Konstante `WARLEEK_VERSION`.

- [ ] **Step 1: manage.sh** (nach wp-theme-dev-Vorlage, Port 8088, plus `seed`, `sync`, `zip`, `shot`):

```bash
#!/bin/bash
# Warleek — Verwaltung (Docker-Run, Host ohne compose-Plugin).
set -e
cd "$(dirname "$0")"
NET=warleek-net; DBV=warleek-db-data; WPV=warleek-wp-data; DB=warleek-db; WP=warleek-wp
PORT=8088; DBPASS=wppass; HOSTIP=${HOSTIP:-192.168.0.161}
THEME_MOUNT="$(pwd)/theme/warleek:/var/www/html/wp-content/themes/warleek"
ASSETS_MOUNT="$(pwd)/assets-src:/assets-src:ro"
CONFIG_EXTRA='if(isset($_SERVER["HTTP_HOST"])){$s=(!empty($_SERVER["HTTPS"])&&$_SERVER["HTTPS"]!=="off")?"https":"http";if(!defined("WP_HOME"))define("WP_HOME",$s."://".$_SERVER["HTTP_HOST"]);if(!defined("WP_SITEURL"))define("WP_SITEURL",$s."://".$_SERVER["HTTP_HOST"]);}'
wpcli() {
  docker run --rm --network "$NET" --entrypoint wp -v "$WPV:/var/www/html" -v "$THEME_MOUNT" -v "$ASSETS_MOUNT" \
    -e WORDPRESS_DB_HOST="$DB" -e WORDPRESS_DB_USER=wp -e WORDPRESS_DB_PASSWORD="$DBPASS" -e WORDPRESS_DB_NAME=wordpress \
    --user 33 wordpress:cli "$@"
}
up() { ... wie Vorlage, zusätzlich -v "$ASSETS_MOUNT" am WP-Container ... }
install() { url="${1:-http://$HOSTIP:$PORT}"; wpcli core install --url="$url" --title="Warleek" --admin_user=admin --admin_password=warleekadmin --admin_email=admin@warleek.de --skip-email; wpcli language core install de_DE --activate; wpcli theme activate warleek; wpcli option update timezone_string Europe/Berlin; wpcli rewrite structure '/%postname%/' --hard; }
seed() { wpcli eval-file wp-content/themes/warleek/seed.php; wpcli rewrite flush --hard; }
sync() { wpcli warleek sync-patchnotes "$@"; }
zipit() { rm -f warleek.zip; (cd theme && zip -qr ../warleek.zip warleek -x 'warleek/_content/*' 'warleek/seed.php' 'warleek/tests/*'); ls -la warleek.zip; }
shot() { # $1 = Pfad (z.B. /), $2 = Breite, $3 = Ausgabedatei
  docker exec wh-web-check chromium --headless=new --no-sandbox --disable-dev-shm-usage --hide-scrollbars \
    --screenshot=/tmp/shot.png --window-size="${2:-1440},${4:-2400}" "http://$HOSTIP:$PORT${1:-/}?snap=1" >/dev/null 2>&1
  docker cp wh-web-check:/tmp/shot.png "${3:-shots/shot.png}"; }
case "$1" in up) up;; down) ...;; install) shift; install "$@";; wp) shift; wpcli "$@";; seed) seed;; sync) shift; sync "$@";; zip) zipit;; shot) shift; shot "$@";; urls) echo "http://$HOSTIP:$PORT";; logs) docker logs -f "$WP";; *) echo "Nutzung: ...";; esac
```

- [ ] **Step 2: Theme-Minimum** — `style.css` Header (`Theme Name: Warleek`, `Text Domain: warleek`, `Version: 0.1.0`, `Requires at least: 6.6`, `Requires PHP: 8.1`), `functions.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'WARLEEK_VERSION', '0.1.0' );
define( 'WARLEEK_DIR', get_template_directory() );
define( 'WARLEEK_URI', get_template_directory_uri() );
foreach ( array( 'options', 'bbcode', 'cpt-guide', 'cpt-patchnote', 'steam-sync', 'blocks', 'seo' ) as $inc ) {
	$f = WARLEEK_DIR . '/inc/' . $inc . '.php';
	if ( file_exists( $f ) ) { require_once $f; }
}
function warleek_setup() {
	add_theme_support( 'title-tag' ); add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' ); add_theme_support( 'align-wide' );
	add_theme_support( 'html5', array( 'search-form','gallery','caption','style','script' ) );
	add_editor_style( 'assets/css/main.css' );
	register_block_pattern_category( 'warleek', array( 'label' => 'Warleek' ) );
}
add_action( 'after_setup_theme', 'warleek_setup' );
function warleek_assets() {
	foreach ( array( 'main' => 'css/main.css', 'motion' => 'css/motion.css' ) as $h => $rel ) {
		$p = WARLEEK_DIR . '/assets/' . $rel; if ( ! file_exists( $p ) ) continue;
		wp_enqueue_style( 'warleek-' . $h, WARLEEK_URI . '/assets/' . $rel, array(), filemtime( $p ) );
	}
	$js = WARLEEK_DIR . '/assets/js/main.js';
	if ( file_exists( $js ) ) { wp_enqueue_script( 'warleek-main', WARLEEK_URI . '/assets/js/main.js', array(), filemtime( $js ), array( 'strategy' => 'defer' ) ); }
}
add_action( 'wp_enqueue_scripts', 'warleek_assets' );
function warleek_snapshot_mode() {
	if ( isset( $_GET['snap'] ) ) { echo '<style id="warleek-snap">*,*::before,*::after{animation:none!important;transition:none!important}.wl-reveal{opacity:1!important;transform:none!important}.wl-smoke,.wl-flare{display:none!important}</style>'; }
}
add_action( 'wp_head', 'warleek_snapshot_mode', 99 );
```
`templates/index.html`: header-Part, `<main>` mit `core/post-content` (bzw. query loop), footer-Part. Parts vorerst minimal (site-title, navigation).

- [ ] **Step 3: Hochfahren + installieren**: `$P/manage.sh up && $P/manage.sh install` → Erwartung: „Installiert."
- [ ] **Step 4: Verifizieren**: `curl -s -o /dev/null -w '%{http_code}' http://192.168.0.161:8088/` → `200`; `$P/manage.sh wp theme list` zeigt warleek active.
- [ ] **Step 5: Commit** `feat: Projekt-Skelett, manage.sh, Dev-Instanz`

---

### Task 2: Design-Tokens: theme.json, Fonts, Basis-CSS

**Files:**
- Create: `theme/warleek/assets/fonts/*.woff2` (barlow-condensed-700/800, barlow-400/500/600, jetbrains-mono-400), `theme/warleek/assets/css/main.css`
- Modify: `theme/warleek/theme.json`, `functions.php` (Font-Preload)

**Interfaces:**
- Produces: CSS-Variablen `--wp--preset--color--{base,surface,surface-2,olive,sand,steel,text,leek,leek-light,alert}`, Font-Slugs `heading`, `body`, `mono`; Utility-Klassen `wl-eyebrow`, `wl-tag`, `wl-card`, `wl-section`, `wl-btn-ghost` (Block-Style `core/button` „ghost"), `wl-reveal`.

- [ ] **Step 1: Fonts laden** (fontsource via jsdelivr, latin, woff2):
```bash
F=$T/assets/fonts; mkdir -p $F; cd $F
for w in 700 800; do curl -fsSL -o barlow-condensed-latin-$w.woff2 https://cdn.jsdelivr.net/npm/@fontsource/barlow-condensed@5/files/barlow-condensed-latin-$w-normal.woff2; done
for w in 400 500 600; do curl -fsSL -o barlow-latin-$w.woff2 https://cdn.jsdelivr.net/npm/@fontsource/barlow@5/files/barlow-latin-$w-normal.woff2; done
curl -fsSL -o jetbrains-mono-latin-400.woff2 https://cdn.jsdelivr.net/npm/@fontsource/jetbrains-mono@5/files/jetbrains-mono-latin-400-normal.woff2
file *.woff2   # Erwartung: „Web Open Font Format (Version 2)"
```
- [ ] **Step 2: theme.json** v3: `settings.color.palette` (10 Tokens), `settings.typography.fontFamilies` mit `fontFace` (root-relative `file:./assets/fonts/...`), `fontSizes` fluid, `spacing.spacingSizes` 20–80, `layout.contentSize 760px / wideSize 1240px`, `styles`: body bg base / text text, headings heading-Font uppercase, links leek, `elements.button` leek bg / base text, `core/button` Style ghost.
- [ ] **Step 3: main.css** — Reset-Ergänzungen, Header/Footer-Layout, Cards, Eyebrow, Tag-Chips, Prose-Stile, Tabellen, Details/FAQ, Responsive-Regeln (≥ 16px Seitenrand), `.wl-reveal` Grundzustand (opacity 0 → JS setzt `.is-in`; ohne JS sichtbar via `html:not(.wl-js) .wl-reveal{opacity:1}`).
- [ ] **Step 4: Font-Preload** in functions.php (barlow-condensed-800 + barlow-400).
- [ ] **Step 5: Verifizieren**: `curl -s http://192.168.0.161:8088/ | grep -c "barlow"` ≥ 1; `curl -sI http://192.168.0.161:8088/wp-content/themes/warleek/assets/fonts/barlow-latin-400.woff2 | head -1` → 200.
- [ ] **Step 6: Commit** `feat(theme): Design-Tokens, Fonts, Basis-CSS`

---

### Task 3: Optionsseite (inc/options.php)

**Files:**
- Create: `theme/warleek/inc/options.php`

**Interfaces:**
- Produces: `warleek_opt( string $key, $default = '' )` liest aus Option-Array `warleek_options`; Keys: `discord_url`, `whatsapp_url`, `telegram_url`, `steam_appid` (Default `1867240`), `clan_tag` (Default `[WLK]`), `kontakt_email`, `steam_url` (Store-Link). Settings-Seite unter *Einstellungen → Warleek* (`options-general.php?page=warleek`).

- [ ] **Step 1: Implementieren** — `register_setting('warleek', 'warleek_options', ['sanitize_callback' => 'warleek_sanitize_options'])`, `add_options_page`, Felder via `add_settings_field` (URL-Felder `esc_url_raw`, AppID `absint`, Text `sanitize_text_field`).
- [ ] **Step 2: Test via WP-CLI**:
```bash
$P/manage.sh wp option update warleek_options '{"discord_url":"https://discord.gg/test"}' --format=json
$P/manage.sh wp eval 'echo warleek_opt("discord_url"), "|", warleek_opt("steam_appid"), "\n";'
```
Erwartung: `https://discord.gg/test|1867240`
- [ ] **Step 3: Commit** `feat: Optionsseite Warleek`

---

### Task 4: BBCode-Konverter (TDD)

**Files:**
- Create: `theme/warleek/inc/bbcode.php`, `theme/warleek/tests/bbcode-test.php`

**Interfaces:**
- Produces: `warleek_bbcode_to_html( string $bb ): string` (reine Funktion, keine WP-Abhängigkeit außer optional `wp_kses_post` wenn vorhanden).

- [ ] **Step 1: Test schreiben** (`tests/bbcode-test.php`, ausführbar mit `php`, ohne WP; definiert Mini-`assert_eq`):
```php
<?php
require __DIR__ . '/../inc/bbcode.php';
$cases = [
  ['[h1]Patch 1.2[/h1]', '<h2>Patch 1.2</h2>'],
  ['[b]fett[/b] [i]kursiv[/i] [u]u[/u] [strike]s[/strike]', '<p><strong>fett</strong> <em>kursiv</em> <u>u</u> <s>s</s></p>'],
  ["[list]\n[*]eins\n[*]zwei\n[/list]", '<ul><li>eins</li><li>zwei</li></ul>'],
  ["[olist]\n[*]a\n[/olist]", '<ol><li>a</li></ol>'],
  ['[url=https://x.de]Link[/url]', '<p><a href="https://x.de" rel="noopener" target="_blank">Link</a></p>'],
  ['[img]{STEAM_CLAN_IMAGE}/123/abc.png[/img]', '<p><img src="https://clan.akamai.steamstatic.com/images/123/abc.png" alt="" loading="lazy"></p>'],
  ['[quote]zitat[/quote]', '<blockquote>zitat</blockquote>'],
  ['[code]x = 1[/code]', '<pre><code>x = 1</code></pre>'],
  ['[hr][/hr]', '<hr>'],
  ['[previewyoutube=AbC123;full][/previewyoutube]', '<p><a href="https://www.youtube.com/watch?v=AbC123" rel="noopener" target="_blank">Video auf YouTube</a></p>'],
  ["Zeile 1\nZeile 2\n\nAbsatz 2", '<p>Zeile 1<br>Zeile 2</p><p>Absatz 2</p>'],
  ['<script>alert(1)</script>[unknown]x[/unknown]', '<p>&lt;script&gt;alert(1)&lt;/script&gt;x</p>'],
];
$fail = 0;
foreach ($cases as [$in, $want]) { $got = warleek_bbcode_to_html($in); if (trim($got) !== $want) { $fail++; echo "FAIL\n in:   $in\n want: $want\n got:  $got\n"; } }
echo $fail ? "$fail FAILED\n" : "OK (" . count($cases) . ")\n"; exit($fail ? 1 : 0);
```
- [ ] **Step 2: Fehlschlag prüfen**: `docker run --rm -v $T:/t php:8.3-cli php /t/tests/bbcode-test.php` → Fatal (Funktion fehlt).
- [ ] **Step 3: Implementieren** — Reihenfolge: `htmlspecialchars` des Rohtexts (ENT_QUOTES, UTF-8) → Block-Tags (`[h1-3]`→`<h2>-<h4>`, list/olist, quote, code, hr) per Regex → Inline (`b,i,u,strike,url,img,previewyoutube`) → unbekannte `[tag]`/`[/tag]` entfernen → Absätze: Text außerhalb von Block-Elementen an `\n\n` splitten zu `<p>`, einzelne `\n` → `<br>`. `{STEAM_CLAN_IMAGE}` → `https://clan.akamai.steamstatic.com/images`. Wenn `function_exists('wp_kses_post')` → Ergebnis durch `wp_kses_post`.
- [ ] **Step 4: Test grün**: gleicher Befehl → `OK (12)`.
- [ ] **Step 5: Commit** `feat: Steam-BBCode → HTML Konverter + Tests`

---

### Task 5: CPTs guide + patchnote

**Files:**
- Create: `theme/warleek/inc/cpt-guide.php`, `theme/warleek/inc/cpt-patchnote.php`

**Interfaces:**
- Produces: CPT `guide` (slug `guides`, has_archive `guides`, supports title/editor/thumbnail/excerpt, show_in_rest, menu_icon `dashicons-book`), Taxonomie `guide-thema` (hierarchisch, slug `thema`, Terme werden im Seed angelegt: fob, logistik, gameplay, equipment, einsteiger). CPT `patchnote` (slug `patch-notes`, has_archive `patch-notes`, supports title/editor/excerpt, show_in_rest, `capability_type post`, menu_icon `dashicons-update`), Meta registriert: `steam_gid` (string), `steam_url` (string), `steam_published_at` (integer) — `show_in_rest`, `single`.
- Helper: `warleek_patchnote_source_link( int $post_id ): string` liefert `<p class="wl-source">Quelle: <a …>Steam-Ankündigung</a></p>` oder ''.

- [ ] **Step 1: Implementieren** beide Dateien.
- [ ] **Step 2: Verifizieren**: `$P/manage.sh wp rewrite flush --hard && $P/manage.sh wp post-type list --fields=name,public,has_archive` enthält guide und patchnote; `$P/manage.sh wp taxonomy list --fields=name` enthält guide-thema.
- [ ] **Step 3: Commit** `feat: CPTs guide und patchnote`

---

### Task 6: Steam-Sync (Cron + WP-CLI)

**Files:**
- Create: `theme/warleek/inc/steam-sync.php`, `theme/warleek/tests/fixtures/steam-news.json` (2 Items: eins mit Tag patchnotes, eins ohne aber Titel „Hotfix 1.0.3", eins News ohne Match)

**Interfaces:**
- Consumes: `warleek_opt('steam_appid')`, `warleek_bbcode_to_html()`.
- Produces: `warleek_steam_fetch_news( int $appid, int $count = 30 ): array|WP_Error`; `warleek_steam_is_patchnote( array $item ): bool`; `warleek_steam_upsert_patchnote( array $item ): int|WP_Error` (gibt Post-ID zurück, aktualisiert wenn `contents`-Hash abweicht); `warleek_sync_patchnotes( bool $force = false ): array{created:int,updated:int,skipped:int,error:?string}`; Cron-Hook `warleek_sync_patchnotes` (hourly, Aktivierung bei `after_switch_theme`, Aufräumen bei `switch_theme`); WP-CLI `wp warleek sync-patchnotes [--force]`; Option `warleek_sync_last_error` + Admin-Notice; Option `warleek_sync_last_run`.

- [ ] **Step 1: Implementieren** — Fetch mit `wp_remote_get` (timeout 15), URL exakt: `https://api.steampowered.com/ISteamNews/GetNewsForApp/v2/?appid={appid}&count={count}&maxlength=0&feeds=steam_community_announcements&format=json`; `is_patchnote`: `in_array('patchnotes', $item['tags'] ?? [])` ODER `preg_match('/patch|update|hotfix|changelog/i', $item['title'])`; Upsert: Query nach `meta_key=steam_gid`; `post_date` aus `date` (Unix, `wp_date('Y-m-d H:i:s', $ts)`), `post_content` = `warleek_bbcode_to_html($item['contents'])`, Excerpt = erste 160 Zeichen Klartext, Meta `steam_gid/steam_url/steam_published_at`, `_warleek_hash` = md5(contents).
- [ ] **Step 2: Fixture-Test** via `wp eval-file tests/steam-sync-test.php`: lädt Fixture, ruft `warleek_steam_is_patchnote` für 3 Items (true,true,false) und `warleek_steam_upsert_patchnote` zweimal mit gleichem Item → zweite Rückgabe gleiche ID, Post-Count unverändert. Ausgabe `OK`.
- [ ] **Step 3: Live-Test**: `$P/manage.sh sync` → Ausgabe `created: N updated: 0 skipped: M`; `$P/manage.sh wp post list --post_type=patchnote --fields=ID,post_title,post_date` zeigt ≥ 1 Eintrag; `curl -s -o /dev/null -w '%{http_code}' http://192.168.0.161:8088/patch-notes/` → 200.
- [ ] **Step 4: Cron prüfen**: `$P/manage.sh wp cron event list | grep warleek` zeigt hourly.
- [ ] **Step 5: Commit** `feat: Steam-Patch-Notes-Sync (Cron + WP-CLI)`

---

### Task 7: Dynamische Blöcke (inc/blocks.php)

**Files:**
- Create: `theme/warleek/inc/blocks.php`

**Interfaces:**
- Consumes: `warleek_opt()`, CPTs.
- Produces: Server-Side-Blöcke via `register_block_type` mit `render_callback` (ohne JS-Build, `editor_script` = kleines Inline-Registrierungs-Script via `wp_add_inline_script` an `wp-blocks`), Namen:
  - `warleek/chat-buttons` — Attr `layout` (`row|grid`), rendert nur Kanäle mit gesetzter URL; ohne URL: Button disabled mit Titel „Link folgt". Klassen `wl-chat`, `wl-chat__btn wl-chat__btn--discord|whatsapp|telegram`, Inline-SVG-Icons.
  - `warleek/patchnotes-latest` — Attr `count` (Default 3), rendert `<ul class="wl-patchlist">` mit Datum, Titel-Link, Excerpt, letztes Element Link „Alle Patch Notes".
  - `warleek/guides-grid` — Attr `count` (6), `thema` (Slug oder leer); Karten mit Thumbnail, Thema-Chip, Titel, Excerpt.
  - Shortcodes als Fallback für den Seed: `[warleek_chat]`, `[warleek_patchnotes count=3]`, `[warleek_guides count=6 thema=""]` (gleiche Renderer).
- [ ] **Step 1: Implementieren**; Editor-Registrierung: `wp.blocks.registerBlockType('warleek/chat-buttons', {title:'Warleek Chat-Buttons', icon:'format-chat', category:'widgets', edit: () => wp.element.createElement(wp.serverSideRender, {block:'warleek/chat-buttons'}), save: () => null})` etc. (Script-Deps `wp-blocks, wp-element, wp-server-side-render`).
- [ ] **Step 2: Verifizieren**: `$P/manage.sh wp eval 'echo do_shortcode("[warleek_patchnotes count=2]");' | grep -c wl-patchlist` → 1; `... do_shortcode("[warleek_chat]")` enthält `wl-chat__btn--discord`.
- [ ] **Step 3: Commit** `feat: Blöcke chat-buttons, patchnotes-latest, guides-grid`

---

### Task 8: Templates & Parts

**Files:**
- Create: `templates/front-page.html`, `page.html`, `page-hub.html` (Vollbreite, Beitragsbild als Hero-Cover mit Titel darüber), `single.html`, `single-guide.html`, `archive-guide.html`, `taxonomy-guide-thema.html`, `single-patchnote.html`, `archive-patchnote.html`, `search.html`, `404.html`; überschreiben `index.html`, `parts/header.html`, `parts/footer.html`
- Modify: `theme.json` → `customTemplates` (page-hub „Hub-Seite (Vollbreite)"), `templateParts`.

**Interfaces:**
- Consumes: Blöcke aus Task 7, `warleek_patchnote_source_link()` (via kleiner Shortcode `[warleek_source]`, in blocks.php ergänzen).
- Produces: Header = Website-Logo (`core/site-logo`, width 44) + Site-Title + Navigation (`core/navigation` ref auf Menü „Hauptmenü", Overlay mobile) + Discord-Button (`warleek/chat-buttons layout=header` → nur Discord). Footer = 3 Spalten (Kanäle-Block, Navigation „Footer", Text-Absatz Fan-Hinweis + Rechtliches-Links). Alle Textinhalte in Parts sind entweder Blöcke aus Optionen oder Navigation (im Backend editierbar), einziger fester Text: Fan-Hinweis + © (als `core/paragraph` im Part — editierbar über Website-Editor).
- `front-page.html` = header + `<main>` `core/post-content` + footer (Content kommt von der Startseite-Page). `page.html` = Titel (H1, `core/post-title`) + Post-Content, `page-hub.html` = Cover mit Featured Image + Titel + Post-Content ohne extra Titel.

- [ ] **Step 1: Alle Templates/Parts schreiben.**
- [ ] **Step 2: Verifizieren**: `$P/manage.sh wp post create --post_type=page --post_title='Test' --post_status=publish --post_name=test --porcelain` → curl `/test/` 200 und enthält `<h1`; `/patch-notes/` 200; `/nonexistent/` 404 mit Text „404".
- [ ] **Step 3: Test-Seite löschen**, Commit `feat(theme): Templates und Parts`

---

### Task 9: Patterns (Startvorlagen für den Seed)

**Files:**
- Create: `patterns/hero.php`, `page-hero.php`, `three-tiers.php`, `chat-cta.php`, `faq.php`, `stats.php`, `about-team.php`, `content-columns.php`

**Interfaces:**
- Produces: PHP-Pattern-Dateien mit Header (`Title`, `Slug: warleek/<name>`, `Categories: warleek`, `Inserter: yes`); Markup nutzt nur Core-Blöcke + Task-7-Blöcke; Bildplatzhalter als `core/cover`/`core/image` ohne feste URL (Seed setzt Mediathek-URL/ID). Klassen `wl-hero`, `wl-hero__media`, `wl-tiers`, `wl-tier`, `wl-stats`, `wl-stat__num` (data-count), `wl-faq`, `wl-team`.
- Zusätzlich `inc/patterns.php`-freie Lösung: Seed liest Pattern-Markup über `WP_Block_Patterns_Registry::get_instance()->get_registered('warleek/hero')['content']` und ersetzt Platzhalter `{{H1}}`, `{{LEAD}}`, `{{IMG_URL}}`, `{{IMG_ID}}`, `{{CTA_URL}}`, `{{CTA_LABEL}}` — Patterns enthalten diese Platzhalter als Default-Text, damit sie auch manuell einsetzbar sind (sichtbarer Beispieltext statt `{{}}`: Pattern enthält Beispieltext; Seed nutzt eigene Builder-Funktionen mit identischem Markup — siehe Task 13). **Entscheidung:** Patterns = manuell einsetzbare Vorlagen mit Beispieltext; Seed baut Markup über Builder-Funktionen in `seed.php` (kein doppelter Pflegeaufwand nötig, weil Builder das Pattern-Markup 1:1 wiederverwenden: Pattern-Dateien rufen dieselben Builder auf — `patterns/hero.php` gibt `echo warleek_build_hero([...Beispiel...])` aus; Builder liegen in `inc/builders.php`).
- [ ] **Step 1: `inc/builders.php`** mit `warleek_build_hero(array $a)`, `warleek_build_page_hero`, `warleek_build_tiers(array $tiers)`, `warleek_build_chat_cta(array $a)`, `warleek_build_faq(array $items, string $heading)`, `warleek_build_stats(array $stats)`, `warleek_build_team(array $members)`, `warleek_build_section(string $inner, array $opts)` → Strings mit Block-Kommentaren. Bilder als `{"id":ID,"url":URL}` im Cover/Image-Block, damit der Editor sie als Mediathek-Bild erkennt.
- [ ] **Step 2: Pattern-Dateien** rufen Builder mit Beispieltexten auf.
- [ ] **Step 3: Verifizieren**: `$P/manage.sh wp eval 'echo count(WP_Block_Patterns_Registry::get_instance()->get_all_registered(true));'` und `wp eval 'echo strlen(warleek_build_hero(["h1"=>"x","lead"=>"y"]));'` > 100; Block-Validität: `wp eval '$b=parse_blocks(warleek_build_tiers([["title"=>"a","text"=>"b","url"=>"/","cta"=>"c"]])); echo $b[0]["blockName"];'` → `core/group`.
- [ ] **Step 4: Commit** `feat(theme): Builder + Patterns`

---

### Task 10: Motion: View Transitions, Smoke, Flares, JS

**Files:**
- Create: `assets/css/motion.css`, `assets/img/smoke-mask.svg` (SVG mit feTurbulence-Filter als Maske), `assets/js/main.js`

**Interfaces:**
- Produces: `@view-transition { navigation: auto; }`; `::view-transition-old(root)` Smoke-Wipe (mask-image smoke-mask.svg, mask-size animiert 100%→400%, opacity 1→0, 600ms), `::view-transition-new(root)` fade/slide-up 500ms; `.wl-flare-bar` (fixed, top, lauchgrün, `view-transition-name: flare`, animiert Breite 0→100 %); `.wl-smoke` Layer im Hero (zwei absolut positionierte Divs mit Textur-Background, `animation: wl-drift 40s linear infinite alternate`); Button-Flare-Sweep `::after` on hover; `.wl-card` Lift; `@media (prefers-reduced-motion: reduce)` alles auf `opacity` Fades 200ms, `.wl-smoke {display:none}`.
- `main.js` (≤ 10 KB): setzt `document.documentElement.classList.add('wl-js')`; IntersectionObserver für `.wl-reveal` → `.is-in`; Count-up für `[data-count]` (respektiert reduced motion); fügt `.wl-flare-bar` einmalig in `body` ein; Hero-Video: wenn `matchMedia('(prefers-reduced-motion: reduce)')` oder `navigator.connection?.saveData` → `video` entfernen, Poster bleibt; sonst `video.play()` catch.
- [ ] **Step 1: Implementieren.**
- [ ] **Step 2: Verifizieren**: `curl -s http://192.168.0.161:8088/ | grep -c 'motion.css'` → 1; `wc -c $T/assets/js/main.js` < 10240; `docker run --rm -v $T:/t node:20-alpine node --check /t/assets/js/main.js` (Syntax).
- [ ] **Step 3: Commit** `feat(theme): Motion – View Transitions, Smoke, Flares`

---

### Task 11: Assets generieren (Logo, Key-Visuals, Hero-Video)

**Files:**
- Create: `assets-src/raw/` (Rohdaten von Higgsfield), `assets-src/logo/*.png|svg`, `assets-src/img/*.webp`, `assets-src/video/hero.webm|mp4|poster.webp`, `assets-src/MANIFEST.json` (Dateiname → Alt-Text, Verwendung), `theme/warleek/assets/img/logo-mark.svg` (nur Favicon/Fallback), `theme/warleek/screenshot.png`

**Interfaces:**
- Produces: MANIFEST.json-Struktur `{ "hero-home.webp": {"alt": "...", "use": "startseite-hero"}, ... }`; Seed (Task 13) importiert alles aus `/assets-src/img`, `/assets-src/video`, `/assets-src/logo` in die Mediathek und mappt über `use`.
- Bilder (Higgsfield `generate_image_batch`, 16:9 bzw. 3:2): hero-home (Rauch über Industrie-Bergland, Dämmerung), hero-community, hero-team, hero-clan, hero-chat, hero-discord, hero-hub, hero-fob, hero-logistik, hero-gameplay, hero-equipment, hero-about, hero-guides, 6 Guide-Cards, og-default (1200×630). Stil-Prompt-Suffix konstant: „modern military, desaturated olive/charcoal, subtle leek-green highlights, cinematic, no text, no logos".
- Logo: 3 Varianten (Icon Dogtag+Lauch, Wortmarke, Kombi) auf dunkel → Auswahl durch Patrick nach Fertigstellung (bis dahin Variante 1 im Seed). Freistellung via Higgsfield `remove_background`.
- Video: Higgsfield `generate_video` 6–8 s, Prompt: langsam ziehender Rauch über Industrieruine im Bergland, Dämmerung, statische Kamera, loopfähig; Nachbearbeitung `ffmpeg` → 1280×720, WebM VP9 (`-b:v 0 -crf 34`), MP4 H.264 (`-crf 26`), `-an`, Poster = Frame 0 als WebP. Ziel ≤ 2,5 MB.
- Optimierung: `cwebp -q 80` bzw. ffmpeg → WebP, max 1920 px breit, Hero ≤ 250 KB.
- [ ] **Step 1: Bilder generieren + herunterladen + optimieren + MANIFEST schreiben.**
- [ ] **Step 2: Logo-Varianten generieren, freistellen, als PNG + einfache SVG-Wortmarke ablegen; Favicon (`assets/img/favicon.svg` + png 512) erzeugen.**
- [ ] **Step 3: Video generieren, ffmpeg-Pipeline, Größen prüfen** (`ls -la assets-src/video`).
- [ ] **Step 4: Commit** `feat(assets): Logo, Key-Visuals, Hero-Video` (Rohdaten in `assets-src/raw` per .gitignore ausschließen, wenn > 20 MB).

---

### Task 12: Inhalte (_content/*.json)

**Files:**
- Create: `theme/warleek/_content/pages.json`, `_content/guides.json`, `_content/nav.json`, `_content/site.json` (Tagline, Stats, Team-Platzhalter, FAQ-Listen)

**Interfaces:**
- Produces JSON-Schema `pages.json`: Array von `{ "slug", "title", "parent" (slug|null), "template" ("page"|"page-hub"), "hero": {"image": "<manifest-key>", "eyebrow", "h1", "lead", "cta": {"label","url"}|null, "video": "hero"|null}, "seo": {"title","description"}, "sections": [ {"type": "prose", "html": "<h2>…</h2><p>…</p>"} | {"type":"tiers"} | {"type":"chat"} | {"type":"patchnotes","count":3} | {"type":"guides","count":3,"thema":""} | {"type":"faq","heading","items":[{"q","a"}]} | {"type":"stats","items":[{"num","label"}]} | {"type":"team"} | {"type":"columns","items":[{"title","html","image"}]} | {"type":"cta","h2","text","label","url"} ] }`.
  `guides.json`: `{ "slug","title","thema" (Term-Slug),"image","excerpt","seo":{…},"html" }`.
  `nav.json`: `{ "main": [ {"label","url","children":[…]} ], "footer": [ … ] }`.
- Inhalt gemäß Spec §7 (14 Seiten, 6 Guides), Themen-Seiten 800–1200 Wörter, Guides 600–900, DACH-Absätze auf Startseite + 3 Stufen-Seiten, Spielzahlen mit „(Stand: September 2026)". Recherche-Quellen: Steam-Store, wardogswiki.com, wardogs-game.com, GameSpot, games.gg; Fakten prüfen (100 Spieler, 3 Teams, Kontrollzone, Cash-Economy, Paletten $400 → $2500, FOB-Versorgung: Baumaterial/Munition/Treibstoff).
- [ ] **Step 1: Recherche** (WebFetch der Quellen, Notizen nach `docs/research/wardogs-facts.md` mit Quelle je Fakt).
- [ ] **Step 2: pages.json, guides.json, nav.json, site.json schreiben.**
- [ ] **Step 3: Verifizieren**: `python3 -c 'import json,glob;[json.load(open(f)) for f in glob.glob("$T/_content/*.json")];print("json ok")'`; Wortzahl-Check: Script zählt Wörter je Seite/Guide und druckt Tabelle; Themen-Seiten ≥ 800, Guides ≥ 600; grep `Deutschland` + `Österreich` + `Schweiz` in Startseite/Community/Team/Clan-Einträgen.
- [ ] **Step 4: Commit** `content: Seiten, Guides, Navigation`

---

### Task 13: seed.php (Mediathek, Seiten, Guides, Navigation, Optionen)

**Files:**
- Create: `theme/warleek/seed.php`
- Consumes: Builder (Task 9), Blöcke/Shortcodes (Task 7), JSON (Task 12), `/assets-src` (Task 11, im Container gemountet).

**Interfaces:**
- Produces idempotente Funktionen: `warleek_seed_media(): array` (Manifest-Key → Attachment-ID; Dedupe über Meta `_warleek_asset_key`), `warleek_seed_page(array $p, array $media): int` (Upsert über Slug + post_parent; Content aus sections via Builder; `_wp_page_template` = `page-hub` wenn gesetzt; Beitragsbild = hero.image; RankMath-Meta `rank_math_title`/`rank_math_description` + Fallback `_warleek_seo_title/_desc`), `warleek_seed_guides()`, `warleek_seed_nav()` (Navigation-Post „Hauptmenü" + „Footer" als `wp_navigation` mit verschachtelten `core/navigation-link`/`core/navigation-submenu`; Header-Part referenziert per `ref` → Seed schreibt die ID in Option `warleek_nav_main_id`, Part nutzt Fallback: `functions.php` filtert `render_block_data` für `core/navigation` ohne ref und setzt ref aus Option), `warleek_seed_site()` (Startseite = Page `startseite`, `show_on_front=page`, `page_on_front`, Website-Logo `site_logo` = Logo-Attachment, `site_icon` = Favicon-Attachment, `blogdescription`), Optionen-Defaults nur setzen wenn leer.
- [ ] **Step 1: Implementieren** (Hilfsfunktion `warleek_upsert_post`; Media-Import via `media_sideload_image`-frei: `wp_upload_bits` + `wp_insert_attachment` + `wp_generate_attachment_metadata`, Alt-Text aus Manifest → `_wp_attachment_image_alt`).
- [ ] **Step 2: Seed ausführen**: `$P/manage.sh seed` → Ausgabe je Objekt `create:`/`update:`; zweiter Lauf → nur `update:` und Attachment-Anzahl unverändert (`wp post list --post_type=attachment --format=count` vorher/nachher gleich).
- [ ] **Step 3: Verifizieren**: alle 16 URLs aus Spec §3 mit `curl -o /dev/null -w '%{http_code} %{url_effective}\n'` → 200; Startseite enthält `wl-hero` und `wl-patchlist`; `wp option get page_on_front` ≠ 0; `wp option get site_logo` ≠ 0.
- [ ] **Step 4: Commit** `feat: Seed – Medien, Seiten, Guides, Navigation`

---

### Task 14: SEO (inc/seo.php)

**Files:**
- Create: `theme/warleek/inc/seo.php`

**Interfaces:**
- Produces: JSON-LD `Organization` (name Warleek, url home_url, logo = site_logo URL, sameAs = gesetzte Chat-URLs + Steam-Store-URL) auf allen Seiten via `wp_head`; wenn RankMath **nicht** aktiv: `<meta name="description">` aus `_warleek_seo_desc`, Title-Filter `pre_get_document_title` aus `_warleek_seo_title`, OG-Tags (title, description, image = Beitragsbild oder og-default aus Mediathek über Option `warleek_og_default_id`), `<link rel="canonical">`. Wenn RankMath aktiv: nur JSON-LD Organization (RankMath macht Rest) — Prüfung `class_exists('RankMath')`.
- [ ] **Step 1: Implementieren.**
- [ ] **Step 2: Verifizieren**: `curl -s http://192.168.0.161:8088/community/wardogs-clan/ | grep -o '<title>[^<]*'` enthält „Wardogs Clan"; `grep -c 'application/ld+json'` ≥ 1; `grep -c 'og:image'` ≥ 1.
- [ ] **Step 3: Commit** `feat: SEO-Meta, OG, Organization-Schema`

---

### Task 15: Visuelle Verifikation & Feinschliff

**Files:**
- Create: `shots/*.png` (nicht committen → .gitignore), `docs/verification.md` (Ergebnisliste)

- [ ] **Step 1: Screenshots** aller 16 Seiten bei 1440 und 390 (`$P/manage.sh shot /pfad/ 1440 shots/name-desktop.png`, `... 390 ...-mobile.png`), jeden Screenshot mit Read ansehen; Fehler (Overflow, Kontrast, fehlende Bilder, kaputte Blöcke) fixen und erneut shooten.
- [ ] **Step 2: Reduced-Motion-Check**: `curl -s .../assets/css/motion.css | grep -c 'prefers-reduced-motion'` ≥ 1; JS: `grep -c 'prefers-reduced-motion' main.js` ≥ 1.
- [ ] **Step 3: Performance-Check**: Startseiten-HTML-Größe, Gesamtgewicht der Assets (`curl` aller enqueued Assets summieren) < 3,5 MB inkl. Video; Lighthouse falls `npx lighthouse` in `wh-web-check` oder node-Container verfügbar (`docker run --rm --network host -v $P/shots:/out node:20 npx -y lighthouse http://192.168.0.161:8088/ --chrome-flags='--headless --no-sandbox' --only-categories=performance,seo,accessibility --output=json --output-path=/out/lh.json`) → Scores notieren; wenn Chrome im node-Image fehlt: Schritt als „nicht verfügbar" dokumentieren.
- [ ] **Step 4: Editor-Check**: `wp post get <startseite-id> --field=content | wp eval` → `parse_blocks` ohne `core/missing`/`null`-Blocks: `wp eval '$c=get_post(ID)->post_content; foreach(parse_blocks($c) as $b) if(!$b["blockName"] && trim($b["innerHTML"])) echo "FREIER HTML-REST\n";'` → keine Ausgabe.
- [ ] **Step 5: Commit** `chore: Verifikation, Feinschliff`

---

### Task 16: Deploy-Doku, Zip, Abschluss

**Files:**
- Create: `README-Deploy.md`, `README.md`, `.gitignore` (shots/, warleek.zip, assets-src/raw/)
- Modify: `CLAUDE.md` (Projektbeschreibung, Befehle, Konventionen)

- [ ] **Step 1: README-Deploy.md** (All-Inkl analog r3vive): WordPress installieren, Theme-Zip hochladen/aktivieren, RankMath installieren, `assets-src/` + `_content/` + `seed.php` per rsync ins Theme-Verzeichnis, `wp eval-file seed.php` (Seed sucht `/assets-src` oder `ABSPATH/../assets-src` oder Theme-`_assets-src` → Reihenfolge dokumentieren; Seed nutzt Konstante `WARLEEK_ASSETS_DIR` mit Fallbacks), Optionen setzen (`wp option patch update warleek_options discord_url 'https://…'`), Cron (WP-Cron reicht; alternativ All-Inkl-Cronjob `wp cron event run warleek_sync_patchnotes` stündlich), Impressum/Datenschutz ausfüllen, Permalinks, SSL, Logo-Wechsel-Anleitung (Design → Website-Editor → Logo).
- [ ] **Step 2: `$P/manage.sh zip`** → `warleek.zip` vorhanden, `unzip -l warleek.zip | grep -c _content` → 0.
- [ ] **Step 3: CLAUDE.md** aktualisieren (Stack, Befehle, Editierbarkeits-Regel, Port 8088).
- [ ] **Step 4: Commit** `docs: Deploy-Anleitung, README, Zip`
- [ ] **Step 5: Abschlussmeldung** an Patrick: URLs (Dev), Logo-Varianten zur Auswahl, offene Punkte (Chat-Links, Impressum, RankMath-Setup, Logo-Entscheidung).
