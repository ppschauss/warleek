<?php
/**
 * Warleek — Theme-Funktionen (Block-Theme / FSE).
 * Module liegen in inc/: Optionen, BBCode, CPTs, Steam-Sync, Blöcke, Builder, SEO.
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WARLEEK_VERSION', '0.1.0' );
define( 'WARLEEK_DIR', get_template_directory() );
define( 'WARLEEK_URI', get_template_directory_uri() );

foreach ( array( 'options', 'bbcode', 'cpt-guide', 'cpt-patchnote', 'steam-sync', 'builders', 'blocks', 'seo' ) as $warleek_inc ) {
	$warleek_file = WARLEEK_DIR . '/inc/' . $warleek_inc . '.php';
	if ( file_exists( $warleek_file ) ) {
		require_once $warleek_file;
	}
}
unset( $warleek_inc, $warleek_file );

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

/* ------------------------------------------------------- Navigation-Ref */
/**
 * Header/Footer-Parts referenzieren ihre Menüs über Klassen (wl-nav-main / wl-nav-footer)
 * statt über feste IDs; der Seed speichert die IDs der wp_navigation-Posts in Optionen.
 * So bleibt das Theme portabel (Dev ≠ Prod-IDs) und die Menüs sind im Website-Editor editierbar.
 */
function warleek_navigation_ref( $parsed ) {
	if ( 'core/navigation' !== $parsed['blockName'] || ! empty( $parsed['attrs']['ref'] ) ) { return $parsed; }
	$cls = $parsed['attrs']['className'] ?? '';
	$opt = str_contains( $cls, 'wl-nav-footer' ) ? 'warleek_nav_footer_id' : ( str_contains( $cls, 'wl-nav-main' ) ? 'warleek_nav_main_id' : '' );
	if ( $opt ) {
		$id = (int) get_option( $opt, 0 );
		if ( $id && 'publish' === get_post_status( $id ) ) { $parsed['attrs']['ref'] = $id; }
	}
	return $parsed;
}
add_filter( 'render_block_data', 'warleek_navigation_ref' );

/** Im Snapshot-Modus kein Lazy-Loading (Screenshots sollen alle Bilder zeigen). */
add_filter( 'wp_lazy_loading_enabled', function ( $enabled ) { return isset( $_GET['snap'] ) ? false : $enabled; } );

/* ------------------------------------------------------ Datenschutz */
/**
 * Keine Requests zu Dritten: Emoji-Script (s.w.org), oEmbed-Discovery, RSD/WLW, Generator,
 * Shortlink, dns-prefetch. Kommentare/Pingbacks/XML-RPC sind aus (kein Gravatar, keine Trackbacks).
 */
function warleek_privacy_hardening() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
	add_filter( 'wp_resource_hints', function ( $urls, $rel ) { return 'dns-prefetch' === $rel ? array() : $urls; }, 10, 2 );
	add_filter( 'xmlrpc_enabled', '__return_false' );
	add_filter( 'embed_oembed_discover', '__return_false' );
}
add_action( 'init', 'warleek_privacy_hardening' );

/** Kommentare & Pingbacks komplett aus (Community läuft im Discord). */
function warleek_disable_comments() {
	foreach ( get_post_types() as $pt ) {
		if ( post_type_supports( $pt, 'comments' ) ) { remove_post_type_support( $pt, 'comments' ); remove_post_type_support( $pt, 'trackbacks' ); }
	}
}
add_action( 'init', 'warleek_disable_comments', 20 );
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
add_action( 'admin_menu', function () { remove_menu_page( 'edit-comments.php' ); } );

/** Externe YouTube-Links in Patch Notes: rel="noopener nofollow", kein Embed. */
