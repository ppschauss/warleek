<?php
/**
 * Warleek — Theme-Funktionen (Block-Theme / FSE).
 * Module liegen in inc/: Optionen, BBCode, CPTs, Steam-Sync, Blöcke, Builder, SEO.
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'WARLEEK_VERSION' ) ) { define( 'WARLEEK_VERSION', '2.5.1' ); }
define( 'WARLEEK_DIR', get_template_directory() );
define( 'WARLEEK_URI', get_template_directory_uri() );

/* ------------------------------------------------------------------ Setup */
function warleek_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_editor_style( 'assets/css/main.css' );
	register_block_pattern_category( 'warleek', array( 'label' => 'Warleek' ) );
}
add_action( 'after_setup_theme', 'warleek_setup' );

function warleek_block_styles() {
	register_block_style( 'core/button', array( 'name' => 'ghost', 'label' => 'Umriss' ) );
}
add_action( 'init', 'warleek_block_styles' );

/* ------------------------------------------------------- Plugin-Hinweis */
/** Ohne Warleek Core fehlen CPTs, Blöcke und Optionen – Hinweis für Redakteure. */
function warleek_require_core_notice() {
	if ( function_exists( 'warleek_opt' ) || ! current_user_can( 'activate_plugins' ) ) { return; }
	echo '<div class="notice notice-error"><p><strong>Warleek:</strong> Das Plugin <em>Warleek Core</em> ist nicht aktiv. Ohne es fehlen Guides, Patch Notes, Chat-Buttons und die Einstellungen. Bitte im Plugin-Bereich aktivieren.</p></div>';
}
add_action( 'admin_notices', 'warleek_require_core_notice' );

/* ------------------------------------------------------------ Performance */
/** Core-Block-CSS nur für tatsächlich genutzte Blöcke laden (statt der kompletten Block-Library). */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );
/** Interactivity-API-Script nur laden, wenn ein Block es braucht (Navigation-Overlay) – WP macht das bereits; hier nichts erzwingen. */

/** Quellcode-Gruß ganz oben im <head>. */
function warleek_source_greeting() {
	echo "\n<!--\n"
		. "     ___                    W A R L E E K  //  DACH  //  seit 2026\n"
		. "    /   \\   <- Dogtag        Wardogs Guides · Tipps · Patch Notes\n"
		. "    | ~ |   <- Lauch         Du liest Quellcode. Respekt. Steht auch alles in den Guides.\n"
		. "    \\___/                    Wer hier reinschaut, passt zu uns: /community/\n"
		. "\n"
		. "    Kein Tracking, keine Cookies, keine externen Requests im Browser. Fonts, Bilder, Video: alles von hier.\n"
		. "    Guides: selbst gespielt. Patch Notes: von Steam geholt und serverseitig übersetzt. Zahlen: mit Stand-Datum.\n"
		. "    Patch Notes kommen stündlich von Steam (BBCode -> HTML, 22 Tests, alle grün – bis Valve wieder ein Tag erfindet).\n"
		. "    Bugs? #fragen im Discord. Cheats? Nicht hier. Teamkills? Nur mit dem Ural, und nur aus Versehen.\n"
		. "-->\n";
}
add_action( 'wp_head', 'warleek_source_greeting', 0 );

/* ----------------------------------------------------------------- Assets */
function warleek_assets() {
	foreach ( array( 'main' => 'css/main.css', 'motion' => 'css/motion.css' ) as $handle => $rel ) {
		$path = WARLEEK_DIR . '/assets/' . $rel;
		if ( ! file_exists( $path ) ) { continue; }
		wp_enqueue_style( 'warleek-' . $handle, WARLEEK_URI . '/assets/' . $rel, array(), filemtime( $path ) );
	}
	$js = WARLEEK_DIR . '/assets/js/main.js';
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'warleek-main', WARLEEK_URI . '/assets/js/main.js', array(), filemtime( $js ), array( 'strategy' => 'defer' ) );
	}
}
add_action( 'wp_enqueue_scripts', 'warleek_assets' );

/** Preload der Above-the-fold-Schriften (H1 + Fließtext). */
function warleek_font_preload() {
	foreach ( array( 'barlow-condensed-latin-800.woff2', 'barlow-latin-400.woff2' ) as $font ) {
		if ( ! file_exists( WARLEEK_DIR . '/assets/fonts/' . $font ) ) { continue; }
		echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( WARLEEK_URI . '/assets/fonts/' . $font ) . '" crossorigin>' . "\n";
	}
}
add_action( 'wp_head', 'warleek_font_preload', 1 );

/** ?snap=1 friert Animationen ein und blendet Deko-Layer aus (Screenshots). */
function warleek_snapshot_mode() {
	if ( isset( $_GET['snap'] ) ) {
		echo '<style id="warleek-snap">*,*::before,*::after{animation:none!important;transition:none!important}.wl-reveal{opacity:1!important;transform:none!important}.wl-smoke,.wl-flare-bar{display:none!important}</style>';
	}
}
add_action( 'wp_head', 'warleek_snapshot_mode', 99 );



/** Im Snapshot-Modus kein Lazy-Loading (Screenshots sollen alle Bilder zeigen). */
add_filter( 'wp_lazy_loading_enabled', function ( $enabled ) { return isset( $_GET['snap'] ) ? false : $enabled; } );



/**
 * Hero-Video lazy: Server liefert das <video> ohne src/autoplay/poster (data-Attribute),
 * main.js aktiviert es nur auf Desktop und nur, wenn der Hero sichtbar ist.
 * → Mobile lädt keine 350 KB Video, Desktop startet es erst bei Bedarf.
 */
function warleek_lazy_hero_video( $content, $block ) {
	if ( 'core/video' !== $block['blockName'] || ! str_contains( $block['attrs']['className'] ?? '', 'wl-hero__video' ) ) { return $content; }
	$content = preg_replace( '/\s(autoplay|loop|muted|playsinline)\b(="[^"]*")?/i', '', $content );
	$content = preg_replace( '/\ssrc="([^"]+)"/i', ' data-src="$1" preload="none" muted playsinline', $content, 1 );
	$content = preg_replace( '/\sposter="([^"]+)"/i', ' data-poster="$1"', $content, 1 );
	return $content;
}
add_filter( 'render_block', 'warleek_lazy_hero_video', 10, 2 );
