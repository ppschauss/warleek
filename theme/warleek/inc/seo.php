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
	$same = array_filter( array( warleek_opt( 'discord_url' ), warleek_opt( 'whatsapp_url' ), warleek_opt( 'telegram_url' ), warleek_opt( 'steam_url' ), warleek_opt( 'steam_group_url' ), warleek_opt( 'youtube_url' ), warleek_opt( 'twitch_url' ), warleek_opt( 'instagram_url' ), warleek_opt( 'tiktok_url' ), warleek_opt( 'twitter_handle' ) ? 'https://x.com/' . ltrim( warleek_opt( 'twitter_handle' ), '@' ) : '' ) );
	$org = array(
		'@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Warleek', 'url' => $home,
		'description' => get_bloginfo( 'description' ), 'sameAs' => array_values( $same ),
	);
	if ( $logo_url ) { $org['logo'] = $logo_url; }
	echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";

	$desc  = warleek_seo_description();
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : ( is_post_type_archive() ? get_post_type_archive_link( get_query_var( 'post_type' ) ) : ( is_front_page() ? $home : '' ) );
	$img   = ''; $img_w = 0; $img_h = 0; $img_alt = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$tid = get_post_thumbnail_id(); $src = wp_get_attachment_image_src( $tid, 'large' );
		if ( $src ) { $img = $src[0]; $img_w = $src[1]; $img_h = $src[2]; $img_alt = get_post_meta( $tid, '_wp_attachment_image_alt', true ); }
	}
	if ( ! $img ) {
		$og = (int) get_option( 'warleek_og_default_id', 0 ); $src = $og ? wp_get_attachment_image_src( $og, 'full' ) : false;
		if ( $src ) { $img = $src[0]; $img_w = $src[1]; $img_h = $src[2]; $img_alt = get_post_meta( $og, '_wp_attachment_image_alt', true ); }
	}
	$is_article = is_singular( array( 'guide', 'patchnote', 'post' ) );
	$post       = is_singular() ? get_queried_object() : null;

	/* Dublin Core (immer, unabhängig vom SEO-Plugin) */
	echo '<link rel="schema.DC" href="http://purl.org/dc/elements/1.1/">' . "\n";
	echo '<link rel="schema.DCTERMS" href="http://purl.org/dc/terms/">' . "\n";
	$dc = array(
		'DC.title'       => $title,
		'DC.description' => $desc,
		'DC.language'    => 'de-DE',
		'DC.publisher'   => 'Warleek',
		'DC.creator'     => ( $post && $is_article && get_the_author_meta( 'display_name', $post->post_author ) ) ? get_the_author_meta( 'display_name', $post->post_author ) : 'Warleek',
		'DC.type'        => 'Text',
		'DC.format'      => 'text/html',
		'DC.identifier'  => $url ?: $home,
		'DC.rights'      => '© ' . gmdate( 'Y' ) . ' Warleek – Fan-Projekt, unabhängig vom Entwickler von WARDOGS',
		'DC.subject'     => warleek_seo_keywords(),
		'DC.coverage'    => 'Deutschland, Österreich, Schweiz',
	);
	if ( $post ) {
		$dc['DC.date']          = get_the_date( 'Y-m-d', $post );
		$dc['DCTERMS.created']  = get_the_date( 'c', $post );
		$dc['DCTERMS.modified'] = get_the_modified_date( 'c', $post );
	}
	if ( is_singular( 'patchnote' ) ) { $dc['DC.source'] = get_post_meta( get_queried_object_id(), 'steam_url', true ); $dc['DC.creator'] = 'BULKHEAD (via Steam)'; }
	foreach ( $dc as $k => $v ) { if ( '' !== $v && null !== $v ) { echo '<meta name="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n"; } }
	echo '<meta name="keywords" content="' . esc_attr( warleek_seo_keywords() ) . '">' . "\n";
	echo '<meta name="theme-color" content="#0d110f">' . "\n";
	echo '<meta name="geo.region" content="DE">' . "\n";

	if ( warleek_seo_plugin_active() ) { return; }

	$noindex = is_singular() && get_post_meta( get_queried_object_id(), '_warleek_noindex', true );
	if ( $noindex || is_search() || is_404() ) { echo '<meta name="robots" content="noindex,follow">' . "\n"; }
	if ( $desc ) { echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n"; }

	if ( $url && ! is_singular() ) { echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n"; }

	/* Open Graph */
	echo '<meta property="og:type" content="' . ( $is_article ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:site_name" content="Warleek">' . "\n";
	echo '<meta property="og:locale" content="de_DE">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc ) { echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n"; }
	if ( $url ) { echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n"; }
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
		echo '<meta property="og:image:secure_url" content="' . esc_url( set_url_scheme( $img, 'https' ) ) . '">' . "\n";
		if ( $img_w ) { echo '<meta property="og:image:width" content="' . (int) $img_w . '">' . "\n"; echo '<meta property="og:image:height" content="' . (int) $img_h . '">' . "\n"; }
		echo '<meta property="og:image:type" content="' . esc_attr( wp_check_filetype( $img )['type'] ?: 'image/jpeg' ) . '">' . "\n";
		if ( $img_alt ) { echo '<meta property="og:image:alt" content="' . esc_attr( $img_alt ) . '">' . "\n"; }
	}
	if ( $is_article && $post ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c', $post ) ) . '">' . "\n";
		echo '<meta property="og:updated_time" content="' . esc_attr( get_the_modified_date( 'c', $post ) ) . '">' . "\n";
		echo '<meta property="article:author" content="Warleek">' . "\n";
		echo '<meta property="article:section" content="' . esc_attr( is_singular( 'patchnote' ) ? 'Patch Notes' : 'Guides' ) . '">' . "\n";
		$terms = is_singular( 'guide' ) ? get_the_terms( $post, 'guide-thema' ) : array();
		if ( $terms && ! is_wp_error( $terms ) ) { foreach ( $terms as $t ) { echo '<meta property="article:tag" content="' . esc_attr( $t->name ) . '">' . "\n"; } }
	}
	$fb = warleek_opt( 'fb_app_id' );
	if ( $fb ) { echo '<meta property="fb:app_id" content="' . esc_attr( $fb ) . '">' . "\n"; }

	/* Twitter / X */
	$tw = ltrim( (string) warleek_opt( 'twitter_handle' ), '@' );
	echo '<meta name="twitter:card" content="' . ( $img ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc ) { echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n"; }
	if ( $img ) { echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n"; if ( $img_alt ) { echo '<meta name="twitter:image:alt" content="' . esc_attr( $img_alt ) . '">' . "\n"; } }
	if ( $tw ) { echo '<meta name="twitter:site" content="@' . esc_attr( $tw ) . '">' . "\n"; echo '<meta name="twitter:creator" content="@' . esc_attr( $tw ) . '">' . "\n"; }

	/* Weitere Plattformen (Pinterest/Discord/Telegram/WhatsApp lesen OG; Discord zusätzlich theme-color) */
	echo '<meta name="author" content="Warleek">' . "\n";
	echo '<meta name="application-name" content="Warleek">' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="Warleek">' . "\n";
}

/** Keyword-Liste (Site-weit + Seiten-Thema). */
function warleek_seo_keywords() {
	$base = array( 'Wardogs', 'Wardogs Community', 'Wardogs Clan', 'Wardogs Team', 'Wardogs Discord', 'Deutschland', 'Österreich', 'Schweiz', 'DACH', 'deutschsprachig' );
	if ( is_singular( 'guide' ) ) {
		$base[] = 'Wardogs Guide';
		$terms  = get_the_terms( get_queried_object(), 'guide-thema' );
		if ( $terms && ! is_wp_error( $terms ) ) { foreach ( $terms as $t ) { $base[] = 'Wardogs ' . $t->name; } }
	} elseif ( is_singular( 'patchnote' ) || is_post_type_archive( 'patchnote' ) ) {
		array_push( $base, 'Wardogs Patch Notes', 'Wardogs Update', 'Wardogs Hotfix' );
	} elseif ( is_singular() ) {
		$base[] = get_the_title();
	}
	return implode( ', ', array_unique( $base ) );
}
add_action( 'wp_head', 'warleek_seo_head', 5 );

/** Sprache/Region explizit. */
add_filter( 'language_attributes', function ( $attr ) { return str_contains( $attr, 'lang=' ) ? $attr : $attr . ' lang="de-DE"'; } );
