<?php
/**
 * Warleek — Seed: Medien → Mediathek, Seiten, Guides, Navigation, Site-Optionen.
 * Idempotent: mehrfach ausführbar, aktualisiert statt zu duplizieren.
 * Aufruf: wp eval-file wp-content/themes/warleek/seed.php
 * Assets: /assets-src (Dev-Mount) | ABSPATH/../assets-src | Theme/_assets-src | Konstante WARLEEK_ASSETS_DIR
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! function_exists( 'warleek_build_hero' ) ) { echo "Theme warleek muss aktiv sein.\n"; return; }
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$WL_CONTENT = WARLEEK_DIR . '/_content/';
$WL_ASSETS  = defined( 'WARLEEK_ASSETS_DIR' ) ? WARLEEK_ASSETS_DIR : '';
foreach ( array( '/assets-src', dirname( ABSPATH ) . '/assets-src', WARLEEK_DIR . '/_assets-src' ) as $cand ) {
	if ( ! $WL_ASSETS && is_dir( $cand ) && file_exists( $cand . '/MANIFEST.json' ) ) { $WL_ASSETS = $cand; }
}
if ( ! $WL_ASSETS ) { echo "WARN: assets-src nicht gefunden – Seiten werden ohne Bilder angelegt.\n"; }

$site   = json_decode( file_get_contents( $WL_CONTENT . 'site.json' ), true );
$pages  = json_decode( file_get_contents( $WL_CONTENT . 'pages.json' ), true );
$guides = json_decode( file_get_contents( $WL_CONTENT . 'guides.json' ), true );
$nav    = json_decode( file_get_contents( $WL_CONTENT . 'nav.json' ), true );

update_option( 'permalink_structure', '/%postname%/' );
update_option( 'blogname', $site['title'] );
update_option( 'blogdescription', $site['tagline'] );
wp_get_theme()->delete_pattern_cache();

/* ------------------------------------------------------------ Medien */
/**
 * Importiert alle Manifest-Dateien in die Mediathek (Dedupe über _warleek_asset_key).
 * @return array use-key => attachment-id
 */
function warleek_seed_media( $assets_dir ) {
	$map = array();
	if ( ! $assets_dir ) { return $map; }
	$manifest = json_decode( file_get_contents( $assets_dir . '/MANIFEST.json' ), true );
	foreach ( $manifest as $rel => $info ) {
		$file = $assets_dir . '/' . ( str_contains( $rel, '/' ) ? $rel : 'img/' . $rel );
		if ( ! file_exists( $file ) ) { echo "  fehlt: $rel\n"; continue; }
		if ( str_ends_with( $rel, '.svg' ) ) { continue; } // SVG nicht in die Mediathek (WP blockt SVG standardmäßig)
		$key = $info['use'];
		$q   = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_warleek_asset_key', 'meta_value' => $key, 'no_found_rows' => true ) );
		if ( $q->posts ) {
			$id = (int) $q->posts[0];
			update_post_meta( $id, '_wp_attachment_image_alt', $info['alt'] );
			$map[ $key ] = $id; continue;
		}
		$bits = wp_upload_bits( basename( $rel ), null, file_get_contents( $file ) );
		if ( ! empty( $bits['error'] ) ) { echo "  upload-fehler $rel: {$bits['error']}\n"; continue; }
		$ft = wp_check_filetype( $bits['file'] );
		$id = wp_insert_attachment( array(
			'post_mime_type' => $ft['type'], 'post_title' => sanitize_text_field( $info['alt'] ), 'post_status' => 'inherit',
			'post_content' => '', 'post_excerpt' => '',
		), $bits['file'] );
		if ( is_wp_error( $id ) ) { echo "  attach-fehler $rel\n"; continue; }
		if ( str_starts_with( $ft['type'], 'image/' ) ) {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $bits['file'] ) );
			update_post_meta( $id, '_wp_attachment_image_alt', $info['alt'] );
		}
		update_post_meta( $id, '_warleek_asset_key', $key );
		$map[ $key ] = (int) $id;
		echo "  media: $rel → #$id\n";
	}
	return $map;
}
function warleek_img( array $media, $key, $size = 'full' ) {
	if ( empty( $media[ $key ] ) ) { return array(); }
	$id = $media[ $key ];
	// Root-relativ, damit Dev (LAN/Tailscale/Domain) und Prod dieselben Inhalte nutzen können.
	return array( 'id' => $id, 'url' => wp_make_link_relative( wp_get_attachment_image_url( $id, $size ) ), 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) );
}

/* ------------------------------------------------------------ Upsert */
function warleek_upsert_post( array $data, $type, $parent = 0 ) {
	$existing = get_posts( array( 'post_type' => $type, 'name' => $data['post_name'], 'post_parent' => $parent, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
	$data = array_merge( $data, array( 'post_type' => $type, 'post_status' => 'publish', 'post_parent' => $parent ) );
	if ( $existing ) {
		$data['ID'] = (int) $existing[0];
		wp_update_post( wp_slash( $data ) );
		echo "  update: {$data['post_name']}\n";
		return (int) $existing[0];
	}
	$id = wp_insert_post( wp_slash( $data ) );
	echo "  create: {$data['post_name']} (#$id)\n";
	return (int) $id;
}
function warleek_set_seo( $id, array $seo, $noindex = false ) {
	update_post_meta( $id, '_warleek_seo_title', $seo['title'] ?? '' );
	update_post_meta( $id, '_warleek_seo_desc', $seo['description'] ?? '' );
	update_post_meta( $id, '_warleek_noindex', $noindex ? 1 : 0 );
	// RankMath-kompatibel (wird gelesen, sobald das Plugin aktiv ist)
	update_post_meta( $id, 'rank_math_title', $seo['title'] ?? '' );
	update_post_meta( $id, 'rank_math_description', $seo['description'] ?? '' );
	if ( $noindex ) { update_post_meta( $id, 'rank_math_robots', array( 'noindex' ) ); } else { delete_post_meta( $id, 'rank_math_robots' ); }
}

/* ------------------------------------------------------------ Sections → Blöcke */
function warleek_render_sections( array $sections, array $media, array $site ) {
	$out = '';
	foreach ( $sections as $s ) {
		$out .= warleek_render_section( $s, $media, $site );
	}
	return $out;
}
function warleek_render_section( array $s, array $media, array $site ) {
	switch ( $s['type'] ) {
		case 'section':
			$inner = '';
			foreach ( $s['inner'] ?? array() as $i ) { $inner .= warleek_render_section( $i, $media, $site ); }
			return warleek_build_section( $inner, array( 'eyebrow' => $s['eyebrow'] ?? '', 'h2' => $s['h2'] ?? '', 'text' => $s['text'] ?? '', 'surface' => ! empty( $s['surface'] ) ) );
		case 'prose':      return warleek_html_to_blocks( $s['html'] );
		case 'stats':      return warleek_build_stats( $s['items'] );
		case 'tiers':      return warleek_build_tiers( $site['tiers'] );
		case 'chat':       return warleek_build_chat_cta( array( 'eyebrow' => $s['eyebrow'] ?? 'Wardogs Chat', 'h2' => $s['h2'] ?? 'Rein in den Chat', 'text' => $s['text'] ?? '', 'layout' => $s['layout'] ?? 'row' ) );
		case 'patchnotes': return warleek_b_group( warleek_b_block( 'patchnotes-latest', array( 'count' => (int) ( $s['count'] ?? 3 ) ) ), array( 'layout' => 'default', 'wide_align' => true ) );
		case 'guides':     return warleek_b_group( warleek_b_block( 'guides-grid', array( 'count' => (int) ( $s['count'] ?? 6 ), 'thema' => $s['thema'] ?? '' ) ), array( 'layout' => 'default', 'wide_align' => true ) );
		case 'faq':        return warleek_build_faq( $s['items'], $s['heading'] ?? 'Häufige Fragen' );
		case 'cta':        return warleek_build_cta( $s );
		case 'team':
			$members = array();
			foreach ( $s['members'] as $m ) { $m['image'] = ! empty( $m['image'] ) ? warleek_img( $media, $m['image'], 'medium' ) : array(); $members[] = $m; }
			return warleek_build_team( $members );
		case 'columns':
			$items = array();
			foreach ( $s['items'] as $it ) { $it['image'] = ! empty( $it['image'] ) ? warleek_img( $media, $it['image'], 'medium_large' ) : array(); $items[] = $it; }
			return warleek_build_columns( $items );
	}
	return '';
}

/* ------------------------------------------------------------ Seiten */
function warleek_seed_pages( array $pages, array $media, array $site ) {
	$ids = array();
	// Eltern zuerst
	usort( $pages, function ( $a, $b ) { return ( $a['parent'] ? 1 : 0 ) <=> ( $b['parent'] ? 1 : 0 ); } );
	foreach ( $pages as $p ) {
		$parent  = $p['parent'] ? ( $ids[ $p['parent'] ] ?? 0 ) : 0;
		$content = '';
		if ( ! empty( $p['hero'] ) ) {
			$h = $p['hero'];
			$hero = array(
				'home' => ! empty( $h['home'] ), 'page' => ! empty( $h['page'] ),
				'eyebrow' => $h['eyebrow'] ?? '', 'h1' => $h['h1'], 'lead' => $h['lead'] ?? '',
				'buttons' => $h['buttons'] ?? array(), 'tags' => $h['tags'] ?? array(),
				'image' => ! empty( $h['image'] ) ? warleek_img( $media, $h['image'], 'full' ) : array(),
			);
			if ( ! empty( $h['video'] ) && ! empty( $media['video-hero-mp4'] ) ) {
				$hero['video'] = array( 'id' => $media['video-hero-mp4'], 'url' => wp_make_link_relative( wp_get_attachment_url( $media['video-hero-mp4'] ) ), 'poster' => ! empty( $media['video-hero-poster'] ) ? wp_make_link_relative( wp_get_attachment_image_url( $media['video-hero-poster'], 'full' ) ) : '' );
			}
			$content .= warleek_build_hero( $hero );
		}
		$content .= warleek_render_sections( $p['sections'], $media, $site );
		$id = warleek_upsert_post( array( 'post_title' => $p['title'], 'post_name' => $p['slug'], 'post_content' => $content ), 'page', $parent );
		$ids[ $p['slug'] ] = $id;
		update_post_meta( $id, '_wp_page_template', ( $p['template'] ?? 'page' ) === 'page-hub' ? 'page-hub' : 'default' );
		if ( ! empty( $p['hero']['image'] ) && ! empty( $media[ $p['hero']['image'] ] ) ) { set_post_thumbnail( $id, $media[ $p['hero']['image'] ] ); }
		warleek_set_seo( $id, $p['seo'] ?? array(), ! empty( $p['noindex'] ) );
		if ( ! empty( $p['front'] ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $id );
		}
	}
	return $ids;
}

/* ------------------------------------------------------------ Guides */
function warleek_seed_guides( array $guides, array $media, array $site ) {
	foreach ( $site['themen'] as $slug => $t ) {
		$term = term_exists( $slug, 'guide-thema' );
		if ( ! $term ) { $term = wp_insert_term( $t['name'], 'guide-thema', array( 'slug' => $slug, 'description' => $t['desc'] ) ); }
		elseif ( is_array( $term ) ) { wp_update_term( (int) $term['term_id'], 'guide-thema', array( 'name' => $t['name'], 'description' => $t['desc'] ) ); }
	}
	foreach ( $guides as $g ) {
		$content = warleek_html_to_blocks( $g['html'] );
		$id = warleek_upsert_post( array( 'post_title' => $g['title'], 'post_name' => $g['slug'], 'post_content' => $content, 'post_excerpt' => $g['excerpt'], 'menu_order' => (int) ( $g['order'] ?? 0 ) ), 'guide' );
		wp_set_object_terms( $id, $g['thema'], 'guide-thema' );
		if ( ! empty( $g['image'] ) && ! empty( $media[ $g['image'] ] ) ) { set_post_thumbnail( $id, $media[ $g['image'] ] ); }
		warleek_set_seo( $id, $g['seo'] ?? array() );
	}
}

/* ------------------------------------------------------------ Navigation */
function warleek_nav_links( array $items ) {
	$out = '';
	foreach ( $items as $it ) {
		$a = array( 'label' => $it['label'], 'url' => $it['url'], 'kind' => 'custom' );
		if ( ! empty( $it['children'] ) ) {
			$out .= '<!-- wp:navigation-submenu ' . wp_json_encode( $a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' -->' . warleek_nav_links( $it['children'] ) . '<!-- /wp:navigation-submenu -->';
		} else {
			$out .= '<!-- wp:navigation-link ' . wp_json_encode( $a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->';
		}
	}
	return $out;
}
function warleek_seed_nav( array $nav ) {
	foreach ( array( 'main' => array( 'Hauptmenü', 'warleek_nav_main_id' ), 'footer' => array( 'Footer', 'warleek_nav_footer_id' ) ) as $key => $def ) {
		list( $title, $opt ) = $def;
		$id = (int) get_option( $opt, 0 );
		$data = array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_content' => warleek_nav_links( $nav[ $key ] ) );
		if ( $id && get_post( $id ) ) { $data['ID'] = $id; wp_update_post( wp_slash( $data ) ); echo "  nav update: $title\n"; }
		else { $id = wp_insert_post( wp_slash( $data ) ); update_option( $opt, $id ); echo "  nav create: $title (#$id)\n"; }
	}
}

/* ------------------------------------------------------------ Run */
echo "Medien…\n";
$media = warleek_seed_media( $WL_ASSETS );
echo "Seiten…\n";
$page_ids = warleek_seed_pages( $pages, $media, $site );
echo "Guides…\n";
warleek_seed_guides( $guides, $media, $site );
echo "Navigation…\n";
warleek_seed_nav( $nav );
if ( ! empty( $media['logo-icon-png'] ) ) { update_option( 'site_logo', $media['logo-icon-png'] ); }
if ( ! empty( $media['favicon'] ) ) { update_option( 'site_icon', $media['favicon'] ); }
if ( ! empty( $media['og-default'] ) ) { update_option( 'warleek_og_default_id', $media['og-default'] ); }
flush_rewrite_rules( false );
echo "Fertig: " . count( $page_ids ) . " Seiten, " . count( $guides ) . " Guides, " . count( $media ) . " Medien.\n";
