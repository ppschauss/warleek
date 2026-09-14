<?php
/**
 * Warleek — SEO: Organization-Schema (immer), Title/Description/OG/Canonical (nur ohne RankMath).
 * Meta-Quellen: _warleek_seo_title, _warleek_seo_desc, _warleek_noindex (vom Seed gesetzt, im Backend über RankMath pflegbar).
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function warleek_seo_plugin_active() {
	return class_exists( 'RankMath' ) || defined( 'WPSEO_VERSION' );
}

/** Titel: eigenes Meta > Standard. */
function warleek_seo_title( $title ) {
	if ( warleek_seo_plugin_active() ) { return $title; }
	if ( is_singular() ) {
		$t = get_post_meta( get_queried_object_id(), '_warleek_seo_title', true );
		if ( $t ) { return $t; }
	}
	if ( is_post_type_archive( 'patchnote' ) ) { return 'Wardogs Patch Notes – alle Updates & Hotfixes | Warleek'; }
	if ( is_post_type_archive( 'guide' ) ) { return 'Wardogs Guides auf Deutsch – Einsteiger, FOB, Logistik | Warleek'; }
	return $title;
}
add_filter( 'pre_get_document_title', 'warleek_seo_title', 20 );

/** Description je Kontext. */
function warleek_seo_description() {
	if ( is_singular() ) {
		$d = get_post_meta( get_queried_object_id(), '_warleek_seo_desc', true );
		if ( $d ) { return $d; }
		$p = get_queried_object();
		return wp_trim_words( wp_strip_all_tags( $p->post_excerpt ?: $p->post_content ), 28, '…' );
	}
	if ( is_post_type_archive( 'patchnote' ) ) { return 'Alle offiziellen WARDOGS Patch Notes, Hotfixes und Changelogs – stündlich von Steam synchronisiert, übersichtlich auf Deutsch verlinkt.'; }
	if ( is_post_type_archive( 'guide' ) || is_tax( 'guide-thema' ) ) { return 'Deutschsprachige Wardogs Guides der Warleek-Community: Einsteiger, FOB, Logistik, Gameplay und Equipment.'; }
	return get_bloginfo( 'description' );
}

function warleek_seo_head() {
	$home = home_url( '/' );
	$logo = get_theme_mod( 'custom_logo' ) ?: (int) get_option( 'site_logo', 0 );
	$logo_url = $logo ? wp_get_attachment_image_url( $logo, 'full' ) : '';
	$same = array_filter( array( warleek_opt( 'discord_url' ), warleek_opt( 'whatsapp_url' ), warleek_opt( 'telegram_url' ), warleek_opt( 'steam_url' ) ) );
	$org = array(
		'@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Warleek', 'url' => $home,
		'description' => get_bloginfo( 'description' ), 'sameAs' => array_values( $same ),
	);
	if ( $logo_url ) { $org['logo'] = $logo_url; }
	echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";

	if ( warleek_seo_plugin_active() ) { return; }

	$desc = warleek_seo_description();
	$noindex = is_singular() && get_post_meta( get_queried_object_id(), '_warleek_noindex', true );
	if ( $noindex || is_search() || is_404() ) { echo '<meta name="robots" content="noindex,follow">' . "\n"; }
	if ( $desc ) { echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n"; }

	$url = is_singular() ? get_permalink() : ( is_post_type_archive() ? get_post_type_archive_link( get_query_var( 'post_type' ) ) : ( is_front_page() ? $home : '' ) );
	if ( $url && ! is_singular() ) { echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n"; }

	$img = '';
	if ( is_singular() && has_post_thumbnail() ) { $img = get_the_post_thumbnail_url( null, 'large' ); }
	if ( ! $img ) { $og = (int) get_option( 'warleek_og_default_id', 0 ); $img = $og ? wp_get_attachment_image_url( $og, 'full' ) : ''; }
	$title = wp_get_document_title();
	echo '<meta property="og:type" content="' . ( is_singular( array( 'guide', 'patchnote', 'post' ) ) ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:site_name" content="Warleek">' . "\n";
	echo '<meta property="og:locale" content="de_DE">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc ) { echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n"; }
	if ( $url ) { echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n"; }
	if ( $img ) { echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n"; echo '<meta name="twitter:card" content="summary_large_image">' . "\n"; }
}
add_action( 'wp_head', 'warleek_seo_head', 5 );

/** Sprache/Region explizit. */
add_filter( 'language_attributes', function ( $attr ) { return str_contains( $attr, 'lang=' ) ? $attr : $attr . ' lang="de-DE"'; } );
