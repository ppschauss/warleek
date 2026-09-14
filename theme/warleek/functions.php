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
