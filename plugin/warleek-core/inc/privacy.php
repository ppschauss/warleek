<?php
/**
 * Warleek — Datenschutz-Hardening: keine Requests zu Dritten, keine Kommentare/Pingbacks.
 * Liegt im Plugin, damit es unabhängig vom Theme greift.
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

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

function warleek_disable_comments() {
	foreach ( get_post_types() as $pt ) {
		if ( post_type_supports( $pt, 'comments' ) ) { remove_post_type_support( $pt, 'comments' ); remove_post_type_support( $pt, 'trackbacks' ); }
	}
}
add_action( 'init', 'warleek_disable_comments', 20 );
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
add_action( 'admin_menu', function () { remove_menu_page( 'edit-comments.php' ); }, 20 );

/**
 * Navigation in Template-Parts an die vom Installer angelegten Menüs binden
 * (Header/Footer referenzieren sie über die Klassen wl-nav-main / wl-nav-footer).
 */
function warleek_navigation_ref( $parsed ) {
	if ( 'core/navigation' !== ( $parsed['blockName'] ?? '' ) || ! empty( $parsed['attrs']['ref'] ) ) { return $parsed; }
	$cls = $parsed['attrs']['className'] ?? '';
	$opt = str_contains( $cls, 'wl-nav-footer' ) ? 'warleek_nav_footer_id' : ( str_contains( $cls, 'wl-nav-main' ) ? 'warleek_nav_main_id' : '' );
	if ( $opt ) {
		$id = (int) get_option( $opt, 0 );
		if ( $id && 'publish' === get_post_status( $id ) ) { $parsed['attrs']['ref'] = $id; }
	}
	return $parsed;
}
add_filter( 'render_block_data', 'warleek_navigation_ref' );
