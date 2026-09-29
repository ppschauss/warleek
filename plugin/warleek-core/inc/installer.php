<?php
/**
 * Warleek — Installer: Inhalte, Medien, Navigation und das benötigte SEO-Plugin per Klick.
 * Alle Schritte sind idempotent; ein zweiter Lauf aktualisiert, statt zu duplizieren.
 *
 * Admin: Warleek → Installation  ·  WP-CLI: wp warleek install [--force]
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Fortschrittsmeldung. Unter WP-CLI direkt ausgegeben, sonst gesammelt –
 * niemals per echo, weil das die JSON-Antwort des Installers zerstören würde.
 *
 * @param string|null $msg Nachricht; null liefert das bisherige Protokoll und leert es.
 * @return array|void
 */
function warleek_log( $msg = null ) {
	static $lines = array();
	if ( null === $msg ) { $out = $lines; $lines = array(); return $out; }
	$msg = rtrim( (string) $msg );
	$lines[] = $msg;
	if ( defined( 'WP_CLI' ) && WP_CLI ) { WP_CLI::log( '  ' . $msg ); }
}

/** Verzeichnis mit _content/ und den Medien (im Plugin gebündelt, überschreibbar per Filter). */
/** WordPress-Pluginfunktionen laden (is_plugin_active etc. sind nur im Admin garantiert). */
function warleek_require_plugin_api() {
	if ( ! function_exists( 'is_plugin_active' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
}

function warleek_content_dir() {
	return apply_filters( 'warleek_content_dir', WARLEEK_CONTENT_DIR );
}
/** Liest eine der gebündelten JSON-Dateien. */
function warleek_content_json( $name ) {
	$file = warleek_content_dir() . '_content/' . $name . '.json';
	if ( ! file_exists( $file ) ) { return array(); }
	$data = json_decode( (string) file_get_contents( $file ), true );
	return is_array( $data ) ? $data : array();
}

/* ------------------------------------------------------------ Medien */
/**
 * Importiert alle Manifest-Dateien in die Mediathek (Dedupe über _warleek_asset_key).
 * @return array use-key => attachment-id
 */
function warleek_seed_media( $assets_dir = '' ) {
	if ( ! $assets_dir ) { $assets_dir = warleek_content_dir(); }
	$map = array();
	if ( ! $assets_dir ) { return $map; }
	$manifest = json_decode( (string) file_get_contents( rtrim( $assets_dir, '/' ) . '/MANIFEST.json' ), true );
	if ( ! is_array( $manifest ) ) { return $map; }
	foreach ( $manifest as $rel => $info ) {
		$file = rtrim( $assets_dir, '/' ) . '/' . ( str_contains( $rel, '/' ) ? $rel : 'img/' . $rel );
		if ( ! file_exists( $file ) ) { warleek_log( "fehlt: $rel" ); continue; }
		if ( str_ends_with( $rel, '.svg' ) ) { continue; } // SVG nicht in die Mediathek (WP blockt SVG standardmäßig)
		$key = $info['use'];
		$q   = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_warleek_asset_key', 'meta_value' => $key, 'no_found_rows' => true ) );
		if ( $q->posts ) {
			$id = (int) $q->posts[0];
			update_post_meta( $id, '_wp_attachment_image_alt', $info['alt'] );
			$map[ $key ] = $id; continue;
		}
		$bits = wp_upload_bits( basename( $rel ), null, file_get_contents( $file ) );
		if ( ! empty( $bits['error'] ) ) { warleek_log( "upload-fehler $rel: {$bits['error']}" ); continue; }
		$ft = wp_check_filetype( $bits['file'] );
		$id = wp_insert_attachment( array(
			'post_mime_type' => $ft['type'], 'post_title' => sanitize_text_field( $info['alt'] ), 'post_status' => 'inherit',
			'post_content' => '', 'post_excerpt' => '',
		), $bits['file'] );
		if ( is_wp_error( $id ) ) { warleek_log( "attach-fehler $rel" ); continue; }
		if ( str_starts_with( $ft['type'], 'image/' ) ) {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $bits['file'] ) );
			update_post_meta( $id, '_wp_attachment_image_alt', $info['alt'] );
		}
		update_post_meta( $id, '_warleek_asset_key', $key );
		$map[ $key ] = (int) $id;
		warleek_log( "media: $rel → #$id" );
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
function warleek_upsert_post( array $data, $type, $parent = 0, $force = false ) {
	$existing = get_posts( array( 'post_type' => $type, 'name' => $data['post_name'], 'post_parent' => $parent, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
	$data = array_merge( $data, array( 'post_type' => $type, 'post_status' => 'publish', 'post_parent' => $parent ) );
	if ( $existing ) {
		// Was jemand bewusst in den Papierkorb gelegt hat, holt der Installer nicht zurück.
		if ( 'trash' === get_post_status( $existing[0] ) ) {
			warleek_log( 'übersprungen (im Papierkorb): ' . $data['post_name'] );
			return 0;
		}
		$data['ID'] = (int) $existing[0];
		wp_update_post( wp_slash( $data ) );
		warleek_log( "update: {$data['post_name']}" );
		return (int) $existing[0];
	}
	$id = wp_insert_post( wp_slash( $data ) );
	warleek_log( "create: {$data['post_name']} (#$id)" );
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
		case 'topics':     return warleek_b_group( warleek_b_block( 'topics' ), array( 'layout' => 'default', 'wide_align' => true ) );
		case 'partner':    return warleek_b_group( warleek_b_block( 'partner-grid', array( 'count' => (int) ( $s['count'] ?? 12 ) ) ), array( 'layout' => 'default', 'wide_align' => true ) );
		case 'guidefilter':return warleek_b_group( warleek_b_block( 'guide-filter' ), array( 'layout' => 'default', 'wide_align' => true ) );
		case 'discord':    return warleek_build_cta( array(
			'eyebrow' => $s['eyebrow'] ?? 'Discord',
			'h2'      => $s['h2'] ?? 'Fragen zu einem Guide?',
			'text'    => $s['text'] ?? '',
		) ) . warleek_b_group( warleek_b_block( 'chat-buttons', array( 'layout' => $s['layout'] ?? 'row' ) ), array( 'layout' => 'default', 'class' => 'wl-cta__chat' ) );
		case 'patchnotes': return warleek_b_group( warleek_b_block( 'patchnotes-latest', array( 'count' => (int) ( $s['count'] ?? 3 ) ) ), array( 'layout' => 'default', 'wide_align' => true ) );
		case 'guides':
			$ga = array( 'count' => (int) ( $s['count'] ?? 6 ), 'thema' => $s['thema'] ?? '' );
			if ( ! empty( $s['newest'] ) ) { $ga['newest'] = true; }
			return warleek_b_group( warleek_b_block( 'guides-grid', $ga ), array( 'layout' => 'default', 'wide_align' => true ) );
		case 'faq':        return warleek_build_faq( $s['items'], $s['heading'] ?? 'Häufige Fragen' );
		case 'cta':        return warleek_build_cta( $s );
		case 'columns':
			$items = array();
			foreach ( $s['items'] as $it ) { $it['image'] = ! empty( $it['image'] ) ? warleek_img( $media, $it['image'], 'medium_large' ) : array(); $items[] = $it; }
			return warleek_build_columns( $items );
	}
	return '';
}

/* ------------------------------------------------------------ Seiten */
function warleek_seed_pages( array $pages, array $media, array $site, $force = false ) {
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
		$id = warleek_upsert_post( array( 'post_title' => $p['title'], 'post_name' => $p['slug'], 'post_content' => $content ), 'page', $parent, $force );
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
function warleek_seed_guides( array $guides, array $media, array $site, $force = false ) {
	foreach ( $site['themen'] as $slug => $t ) {
		$term = term_exists( $slug, 'guide-thema' );
		if ( ! $term ) { $term = wp_insert_term( $t['name'], 'guide-thema', array( 'slug' => $slug, 'description' => $t['desc'] ) ); }
		elseif ( is_array( $term ) ) { wp_update_term( (int) $term['term_id'], 'guide-thema', array( 'name' => $t['name'], 'description' => $t['desc'] ) ); }
	}
	foreach ( $guides as $g ) {
		$content = warleek_html_to_blocks( $g['html'] );
		$id = warleek_upsert_post( array( 'post_title' => $g['title'], 'post_name' => $g['slug'], 'post_content' => $content, 'post_excerpt' => $g['excerpt'], 'menu_order' => (int) ( $g['order'] ?? 0 ) ), 'guide', 0, $force );
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
		if ( $id && get_post( $id ) ) { $data['ID'] = $id; wp_update_post( wp_slash( $data ) ); warleek_log( "nav update: $title" ); }
		else { $id = wp_insert_post( wp_slash( $data ) ); update_option( $opt, $id ); warleek_log( "nav create: $title (#$id)" ); }
	}
}


/* ===================================================================== Schritte */
/**
 * Alle Installationsschritte in Ausführungsreihenfolge.
 * Jeder Schritt läuft für sich (AJAX) – so gibt es auf Shared Hosting keine Timeouts.
 *
 * @return array slug => array{label, callback, description}
 */
function warleek_install_steps() {
	return array(
		'plugins' => array(
			'label'       => 'SEO-Plugin installieren',
			'description' => 'Installiert und aktiviert Rank Math SEO (Sitemap, Meta-Titel, Schema).',
			'callback'    => 'warleek_step_plugins',
		),
		'media' => array(
			'label'       => 'Bilder & Video in die Mediathek',
			'description' => 'Importiert Key-Visuals, Guide-Bilder, Logo-Varianten, Favicon und den Hero-Loop.',
			'callback'    => 'warleek_step_media',
		),
		'pages' => array(
			'label'       => 'Seiten anlegen',
			'description' => 'Startseite, Partner-Seite, Spiel-Hub mit sechs Themenseiten, About us, Impressum, Datenschutz.',
			'callback'    => 'warleek_step_pages',
		),
		'guides' => array(
			'label'       => 'Guides anlegen',
			'description' => 'Die mitgelieferten Guides samt Themen-Taxonomie.',
			'callback'    => 'warleek_step_guides',
		),
		'retire' => array(
			'label'       => 'Alte Seiten zurückziehen',
			'description' => 'Verschiebt Seiten, die es nicht mehr gibt, in den Papierkorb und legt eine 301-Weiterleitung auf das neue Ziel an.',
			'callback'    => 'warleek_step_retire',
		),
		'partners' => array(
			'label'       => 'Partner anlegen',
			'description' => 'Legt die mitgelieferten Partner-Einträge an (später im Backend unter „Partner" pflegbar).',
			'callback'    => 'warleek_step_partners',
		),
		'nav' => array(
			'label'       => 'Navigation & Startseite',
			'description' => 'Haupt- und Footer-Menü, Startseite, Website-Logo, Favicon, Permalinks.',
			'callback'    => 'warleek_step_nav',
		),
		'patchnotes' => array(
			'label'       => 'Patch Notes von Steam holen',
			'description' => 'Erster Sync der offiziellen WARDOGS-Updates; danach stündlich automatisch.',
			'callback'    => 'warleek_step_patchnotes',
		),
		'translate' => array(
			'label'       => 'Patch Notes übersetzen',
			'description' => 'Holt die deutsche Fassung samt Kurzfassung über die Claude-API. Ohne API-Schlüssel wird der Schritt übersprungen.',
			'callback'    => 'warleek_step_translate',
		),
	);
}

/** Rank Math installieren und aktivieren (idempotent). */
function warleek_step_plugins( $force = false ) {
	warleek_require_plugin_api();
	$slug = 'seo-by-rank-math';
	$file = $slug . '/rank-math.php';
	if ( is_plugin_active( $file ) ) { return array( 'ok' => true, 'msg' => 'Rank Math ist bereits aktiv.' ); }
	// WP-CLI läuft ohne angemeldeten Benutzer – dort greift die Rechteprüfung der Kommandozeile.
	$is_cli = defined( 'WP_CLI' ) && WP_CLI;
	if ( ! $is_cli && ! current_user_can( 'install_plugins' ) ) { return array( 'ok' => false, 'msg' => 'Keine Berechtigung zum Installieren von Plugins.' ); }
	if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) { return array( 'ok' => false, 'msg' => 'Der Server erlaubt keine Plugin-Installation (DISALLOW_FILE_MODS). Rank Math bitte manuell installieren.' ); }

	if ( ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) { require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php'; }
		if ( ! function_exists( 'request_filesystem_credentials' ) ) { require_once ABSPATH . 'wp-admin/includes/template.php'; }
		$api = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false ) ) );
		if ( is_wp_error( $api ) ) { return array( 'ok' => false, 'msg' => 'Rank Math konnte nicht geladen werden: ' . $api->get_error_message() ); }
		$skin     = class_exists( 'WP_Ajax_Upgrader_Skin' ) ? new WP_Ajax_Upgrader_Skin() : new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );
		if ( is_wp_error( $result ) || ! $result ) { return array( 'ok' => false, 'msg' => 'Installation fehlgeschlagen. Bitte Rank Math manuell installieren.' ); }
	}
	$activated = activate_plugin( $file, '', false, true ); // silent: keine Aktivierungs-Hooks mit Redirect
	if ( is_wp_error( $activated ) ) { return array( 'ok' => false, 'msg' => 'Aktivierung fehlgeschlagen: ' . $activated->get_error_message() ); }
	// Rank Math nicht in seinen Setup-Assistenten springen lassen – der Nutzer startet ihn selbst.
	delete_transient( '_rank_math_activation_redirect' );
	delete_option( 'rank_math_activate_redirect' );
	return array( 'ok' => true, 'msg' => 'Rank Math installiert und aktiviert.' );
}

function warleek_step_media( $force = false ) {
	$before = count( (array) get_option( 'warleek_media_map', array() ) );
	$map    = warleek_seed_media();
	update_option( 'warleek_media_map', $map, false );
	return array( 'ok' => (bool) $map, 'msg' => sprintf( '%d Medien in der Mediathek (%d neu).', count( $map ), max( 0, count( $map ) - $before ) ), 'data' => array( 'media' => count( $map ) ) );
}

function warleek_step_pages( $force = false ) {
	$media = (array) get_option( 'warleek_media_map', array() );
	if ( ! $media ) { $media = warleek_seed_media(); update_option( 'warleek_media_map', $media, false ); }
	if ( ! $media ) { return array( 'ok' => false, 'msg' => 'Medien fehlen – bitte zuerst den Medien-Schritt ausführen.' ); }
	$pages = warleek_content_json( 'pages' );
	$site  = warleek_content_json( 'site' );
	if ( ! $pages ) { return array( 'ok' => false, 'msg' => 'Keine Seiteninhalte gefunden (content/_content/pages.json fehlt).' ); }
	$ids = warleek_seed_pages( $pages, $media, $site, $force );
	update_option( 'warleek_page_ids', $ids, false );
	return array( 'ok' => true, 'msg' => sprintf( '%d Seiten angelegt bzw. aktualisiert.', count( $ids ) ) );
}

function warleek_step_guides( $force = false ) {
	$media = (array) get_option( 'warleek_media_map', array() );
	if ( ! $media ) { $media = warleek_seed_media(); update_option( 'warleek_media_map', $media, false ); }
	$guides = warleek_content_json( 'guides' );
	$site   = warleek_content_json( 'site' );
	if ( ! $guides ) { return array( 'ok' => false, 'msg' => 'Keine Guides gefunden (content/_content/guides.json fehlt).' ); }
	warleek_seed_guides( $guides, $media, $site, $force );
	return array( 'ok' => true, 'msg' => sprintf( '%d Guides angelegt bzw. aktualisiert.', count( $guides ) ) );
}

/**
 * Seiten, die es in dieser Version nicht mehr gibt, sauber stilllegen:
 * Papierkorb statt Löschen (nichts geht verloren) plus 301 auf das neue Ziel,
 * damit keine verwaisten, aber crawlbaren Seiten zurückbleiben.
 */
function warleek_step_retire( $force = false ) {
	$retired = warleek_content_json( 'retired' );
	if ( ! $retired ) { return array( 'ok' => true, 'msg' => 'Nichts zurückzuziehen.' ); }
	$map     = (array) get_option( 'warleek_redirects', array() );
	$trashed = 0;
	foreach ( $retired as $r ) {
		$slug   = sanitize_title( $r['slug'] ?? '' );
		if ( ! $slug ) { continue; }
		$target = $r['redirect'] ?? '/';
		$parent = 0;
		if ( ! empty( $r['parent'] ) ) {
			$pp = get_posts( array( 'post_type' => 'page', 'name' => sanitize_title( $r['parent'] ), 'post_status' => 'any', 'posts_per_page' => 1 ) );
			if ( $pp ) { $parent = (int) $pp[0]->ID; }
		}
		$found = get_posts( array( 'post_type' => 'page', 'name' => $slug, 'post_parent' => $parent, 'post_status' => 'any', 'posts_per_page' => 1 ) );
		if ( $found ) {
			$post = $found[0];
			$map[ trailingslashit( wp_make_link_relative( get_permalink( $post ) ) ) ] = $target;
			if ( 'trash' !== $post->post_status ) { wp_trash_post( $post->ID ); $trashed++; warleek_log( 'zurückgezogen: ' . $slug ); }
		}
		// Weiterleitung auch dann hinterlegen, wenn die Seite hier nie existiert hat –
		// externe Links und der Google-Index kennen die alte Adresse trotzdem.
		$map[ '/' . $slug . '/' ] = $target;
		if ( ! empty( $r['parent'] ) ) { $map[ '/' . sanitize_title( $r['parent'] ) . '/' . $slug . '/' ] = $target; }
	}
	update_option( 'warleek_redirects', $map, false );
	return array( 'ok' => true, 'msg' => sprintf( '%d Seiten in den Papierkorb, %d Weiterleitungen hinterlegt.', $trashed, count( $map ) ) );
}

/** Mitgelieferte Partner anlegen (idempotent über den Slug). */
function warleek_step_partners( $force = false ) {
	$partners = warleek_content_json( 'partners' );
	if ( ! $partners ) { return array( 'ok' => true, 'msg' => 'Keine Partner mitgeliefert.' ); }
	$media = (array) get_option( 'warleek_media_map', array() );
	$n     = 0;
	foreach ( $partners as $i => $p ) {
		$id = warleek_upsert_post( array(
			'post_title'   => $p['title'],
			'post_name'    => $p['slug'],
			'post_content' => $p['text'] ?? '',
			'menu_order'   => (int) ( $p['order'] ?? ( $i + 1 ) ),
		), 'partner' );
		if ( ! $id ) { continue; }
		update_post_meta( $id, 'partner_url', esc_url_raw( $p['url'] ?? '' ) );
		update_post_meta( $id, 'partner_platform', sanitize_text_field( $p['platform'] ?? 'discord' ) );
		update_post_meta( $id, 'partner_tag', sanitize_text_field( $p['tag'] ?? '' ) );
		update_post_meta( $id, 'partner_featured', ! empty( $p['featured'] ) );
		$key = $p['image'] ?? 'partner-placeholder';
		if ( ! empty( $media[ $key ] ) ) { set_post_thumbnail( $id, $media[ $key ] ); }
		$n++;
	}
	return array( 'ok' => true, 'msg' => sprintf( '%d Partner angelegt bzw. aktualisiert.', $n ) );
}

function warleek_step_nav( $force = false ) {
	$nav   = warleek_content_json( 'nav' );
	$media = (array) get_option( 'warleek_media_map', array() );
	if ( ! $media ) { $media = warleek_seed_media(); update_option( 'warleek_media_map', $media, false ); }
	$site  = warleek_content_json( 'site' );
	if ( $nav ) { warleek_seed_nav( $nav ); }
	if ( ! empty( $site['title'] ) ) { update_option( 'blogname', $site['title'] ); }
	if ( ! empty( $site['tagline'] ) ) { update_option( 'blogdescription', $site['tagline'] ); }
	$front = (int) ( ( (array) get_option( 'warleek_page_ids', array() ) )['startseite'] ?? 0 );
	if ( $front ) { update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $front ); }
	if ( ! empty( $media['logo-icon-png'] ) ) { update_option( 'site_logo', $media['logo-icon-png'] ); set_theme_mod( 'custom_logo', $media['logo-icon-png'] ); }
	if ( ! empty( $media['favicon'] ) ) { update_option( 'site_icon', $media['favicon'] ); }
	if ( ! empty( $media['og-default'] ) ) { update_option( 'warleek_og_default_id', $media['og-default'] ); }
	if ( ! get_option( 'permalink_structure' ) || '/%postname%/' !== get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}
	update_option( 'timezone_string', get_option( 'timezone_string' ) ?: 'Europe/Berlin' );
	flush_rewrite_rules( false );
	return array( 'ok' => true, 'msg' => 'Menüs, Startseite, Logo, Favicon und Permalinks gesetzt.' );
}

function warleek_step_patchnotes( $force = false ) {
	if ( ! function_exists( 'warleek_sync_patchnotes' ) ) { return array( 'ok' => false, 'msg' => 'Sync-Modul nicht geladen.' ); }
	$r = warleek_sync_patchnotes( (bool) $force );
	if ( $r['error'] ) { return array( 'ok' => false, 'msg' => 'Steam nicht erreichbar: ' . $r['error'] . ' – der stündliche Sync versucht es erneut.' ); }
	return array( 'ok' => true, 'msg' => sprintf( '%d neu, %d aktualisiert, %d unverändert.', $r['created'], $r['updated'], $r['skipped'] ) );
}

/** Nachträglich übersetzen, was beim Sync englisch geblieben ist. */
function warleek_step_translate( $force = false ) {
	if ( ! function_exists( 'warleek_translate_enabled' ) || ! warleek_translate_enabled() ) {
		return array( 'ok' => true, 'msg' => 'Übersprungen – kein API-Schlüssel hinterlegt (Einstellungen → Warleek).' );
	}
	$posts = get_posts( array( 'post_type' => 'patchnote', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ) );
	$done = 0; $failed = 0; $skipped = 0; $last = '';
	foreach ( $posts as $post ) {
		$src = get_post_meta( $post->ID, '_warleek_src_html', true );
		if ( ! $src ) { $skipped++; continue; }
		$res = warleek_maybe_translate( get_post_meta( $post->ID, '_warleek_src_title', true ) ?: $post->post_title, $src, $post->ID, $force );
		if ( is_wp_error( $res ) ) {
			if ( 'warleek_tr_budget' === $res->get_error_code() ) { break; }
			$last = $res->get_error_message(); $failed++; warleek_store_translation( $post->ID, null, $last ); continue;
		}
		if ( ! $res ) { $skipped++; continue; }
		wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_title' => $res['title'], 'post_content' => $res['html'] ) ) );
		warleek_store_translation( $post->ID, $res );
		warleek_log( 'übersetzt: ' . $res['title'] );
		$done++;
	}
	return array( 'ok' => 0 === $failed, 'msg' => sprintf( '%d übersetzt, %d unverändert, %d fehlgeschlagen.%s', $done, $skipped, $failed, $last ? ' Zuletzt: ' . $last : '' ) );
}

/** Einen Schritt ausführen (mit Speicher-/Zeitpuffer für Shared Hosting). */
function warleek_run_step( $slug, $force = false ) {
	$steps = warleek_install_steps();
	if ( empty( $steps[ $slug ] ) ) { return array( 'ok' => false, 'msg' => 'Unbekannter Schritt: ' . $slug ); }
	@set_time_limit( 300 );
	wp_raise_memory_limit( 'image' );
	warleek_log();            // Protokoll leeren
	ob_start();               // Sicherheitsnetz: nichts darf in die JSON-Antwort laufen
	try {
		$res = call_user_func( $steps[ $slug ]['callback'], $force );
	} catch ( Throwable $e ) {
		$res = array( 'ok' => false, 'msg' => 'Abbruch: ' . $e->getMessage() );
	}
	$stray = trim( (string) ob_get_clean() );
	if ( $stray && ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) { $res['msg'] = ( $res['msg'] ?? '' ) . ' [Ausgabe: ' . mb_substr( wp_strip_all_tags( $stray ), 0, 200 ) . ']'; }
	$res['details'] = warleek_log();
	$log = (array) get_option( 'warleek_install_log', array() );
	$log[ $slug ] = array( 'time' => time(), 'ok' => ! empty( $res['ok'] ), 'msg' => $res['msg'] ?? '' );
	update_option( 'warleek_install_log', $log, false );
	if ( ! empty( $res['ok'] ) ) { update_option( 'warleek_installed_version', WARLEEK_CORE_VERSION, false ); }
	return $res;
}

/** Kompletter Durchlauf (WP-CLI / Fallback ohne JavaScript). */
function warleek_run_install( $force = false ) {
	$out = array();
	foreach ( array_keys( warleek_install_steps() ) as $slug ) { $out[ $slug ] = warleek_run_step( $slug, $force ); }
	return $out;
}

/** Zählt bereits importierte Warleek-Medien (unabhängig davon, ob die Map-Option existiert). */
function warleek_media_count() {
	$q = new WP_Query( array(
		'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids',
		'meta_key' => '_warleek_asset_key', 'meta_compare' => 'EXISTS', 'no_found_rows' => false,
	) );
	return (int) $q->found_posts;
}

/* ===================================================================== Status */
/**
 * Statusübersicht für die Admin-Seite.
 *
 * @return array Liste aus label, ok, detail.
 */
function warleek_install_status() {
	warleek_require_plugin_api();
	$pages   = wp_count_posts( 'page' );
	$guides  = wp_count_posts( 'guide' );
	$notes   = wp_count_posts( 'patchnote' );
	$media   = warleek_media_count();
	$front   = (int) get_option( 'page_on_front' );
	$logo    = (int) get_option( 'site_logo' );
	$sync    = (int) get_option( 'warleek_sync_last_run', 0 );
	$links   = array_filter( array( warleek_opt( 'discord_url' ), warleek_opt( 'whatsapp_url' ), warleek_opt( 'telegram_url' ) ) );
	$rank    = is_plugin_active( 'seo-by-rank-math/rank-math.php' );
	return array(
		array( 'label' => 'Theme „Warleek“ aktiv', 'ok' => 'warleek' === get_template(), 'detail' => 'warleek' === get_template() ? 'aktiv' : 'anderes Theme aktiv' ),
		array( 'label' => 'Rank Math SEO', 'ok' => $rank, 'detail' => $rank ? 'aktiv' : 'nicht installiert' ),
		array( 'label' => 'Bilder & Video', 'ok' => $media >= 20, 'detail' => $media . ' Medien importiert' ),
		array( 'label' => 'Seiten', 'ok' => (int) $pages->publish >= 12, 'detail' => (int) $pages->publish . ' veröffentlicht' ),
		array( 'label' => 'Guides', 'ok' => (int) $guides->publish >= 6, 'detail' => (int) $guides->publish . ' veröffentlicht' ),
		array( 'label' => 'Partner', 'ok' => (int) wp_count_posts( 'partner' )->publish > 0, 'detail' => (int) wp_count_posts( 'partner' )->publish . ' angelegt' ),
		array( 'label' => 'Weiterleitungen', 'ok' => count( (array) get_option( 'warleek_redirects', array() ) ) > 0, 'detail' => count( (array) get_option( 'warleek_redirects', array() ) ) . ' alte URLs' ),
		array( 'label' => 'Patch Notes', 'ok' => (int) $notes->publish > 0, 'detail' => (int) $notes->publish . ' importiert' . ( $sync ? ', zuletzt ' . wp_date( 'd.m.Y H:i', $sync ) : '' ) ),
		array( 'label' => 'Übersetzung', 'ok' => function_exists( 'warleek_translate_enabled' ) && warleek_translate_enabled(), 'detail' => warleek_translated_count() ),
		array( 'label' => 'Startseite & Logo', 'ok' => $front && $logo, 'detail' => $front ? ( $logo ? 'gesetzt' : 'Logo fehlt' ) : 'Startseite fehlt' ),
		array( 'label' => 'Permalinks', 'ok' => '/%postname%/' === get_option( 'permalink_structure' ), 'detail' => get_option( 'permalink_structure' ) ?: 'Standard (Zahlen)' ),
		array( 'label' => 'Chat-Links', 'ok' => count( $links ) > 0, 'detail' => count( $links ) . ' von 3 gesetzt' ),
	);
}

/** Wie viele Patch Notes liegen auf Deutsch vor? */
function warleek_translated_count() {
	if ( ! function_exists( 'warleek_translate_enabled' ) ) { return 'Modul fehlt'; }
	if ( ! warleek_translate_enabled() ) { return 'aus – kein API-Schlüssel'; }
	$all = (int) wp_count_posts( 'patchnote' )->publish;
	$q   = new WP_Query( array( 'post_type' => 'patchnote', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_warleek_tr_hash', 'meta_compare' => 'EXISTS' ) );
	return sprintf( '%d von %d auf Deutsch', (int) $q->found_posts, $all );
}

/* ===================================================================== Admin */
function warleek_admin_menu() {
	$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M10 1c-.6 0-1 .4-1 1v1.2c-1.7.4-3 2-3 3.8v8c0 2.2 1.8 4 4 4s4-1.8 4-4V7c0-1.9-1.3-3.4-3-3.8V2c0-.6-.4-1-1-1zm0 4.5c.8 0 1.5.7 1.5 1.5v3.2c-.5-.3-1-.4-1.5-.4s-1 .1-1.5.4V7c0-.8.7-1.5 1.5-1.5z"/></svg>' );
	add_menu_page( 'Warleek', 'Warleek', 'manage_options', 'warleek', 'warleek_render_admin_page', $icon, 58 );
	add_submenu_page( 'warleek', 'Installation', 'Installation', 'manage_options', 'warleek', 'warleek_render_admin_page' );
	add_submenu_page( 'warleek', 'Einstellungen', 'Einstellungen', 'manage_options', 'warleek-settings', 'warleek_render_admin_page' );
}
add_action( 'admin_menu', 'warleek_admin_menu' );

function warleek_admin_assets( $hook ) {
	if ( ! str_contains( (string) $hook, 'warleek' ) ) { return; }
	wp_register_style( 'warleek-admin', false, array(), WARLEEK_CORE_VERSION );
	wp_enqueue_style( 'warleek-admin' );
	wp_add_inline_style( 'warleek-admin', '
		.wl-wrap{max-width:900px}
		.wl-hero-box{background:#0d110f;color:#e6eae4;border-radius:10px;padding:22px 26px;margin:16px 0 22px;border-left:5px solid #9be15d}
		.wl-hero-box h1{color:#eef5e6;margin:0 0 6px;font-size:24px}
		.wl-hero-box p{margin:0;color:#8a938c;max-width:64ch}
		.wl-status{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:8px;margin:0 0 22px}
		.wl-status li{display:flex;gap:10px;align-items:baseline;background:#fff;border:1px solid #dcdcde;border-left:4px solid #dba617;border-radius:6px;padding:10px 12px;margin:0}
		.wl-status li.is-ok{border-left-color:#4a8f2f}
		.wl-status .dashicons{font-size:18px;width:18px;height:18px;color:#dba617}
		.wl-status li.is-ok .dashicons{color:#4a8f2f}
		.wl-status strong{display:block;font-weight:600}
		.wl-status span{color:#646970;font-size:12px}
		.wl-steps{margin:0 0 16px;padding:0;list-style:none}
		.wl-steps li{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border:1px solid #dcdcde;border-radius:6px;margin-bottom:8px;background:#fff}
		.wl-steps li[data-state="running"]{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}
		.wl-steps li[data-state="done"]{border-color:#4a8f2f;background:#f6fbf4}
		.wl-steps li[data-state="error"]{border-color:#d63638;background:#fcf2f2}
		.wl-steps .wl-ico{width:22px;text-align:center;font-size:18px;line-height:1.4}
		.wl-steps strong{display:block}
		.wl-steps em{font-style:normal;color:#646970;font-size:12px}
		.wl-steps .wl-msg{display:block;margin-top:4px;font-size:12px;color:#1d2327}
		.wl-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 10px}
		.wl-single{display:flex;flex-wrap:wrap;gap:6px;margin:14px 0 0}
		.wl-next{background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:14px 18px;margin-top:22px}
		.wl-next ol{margin:8px 0 0 18px}
	' );
}
add_action( 'admin_enqueue_scripts', 'warleek_admin_assets' );

/** Eine Seite, zwei Tabs: Installation und Einstellungen. */
function warleek_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$page     = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'warleek';
	$settings = 'warleek-settings' === $page;
	?>
	<div class="wrap wl-wrap">
		<div class="wl-hero-box">
			<h1>Warleek<?php echo $settings ? ' – Einstellungen' : ''; ?></h1>
			<p><?php echo $settings
				? 'Chat-Kanäle, Clan-Tag und Social-Profile. Diese Links erscheinen in den Buttons auf der Website und in den Meta-Daten.'
				: 'Ein Klick installiert Inhalte, Bilder, Menüs und das SEO-Plugin. Alles ist wiederholbar – ein zweiter Durchlauf aktualisiert, statt Doppelte anzulegen.'; ?></p>
		</div>
		<h2 class="nav-tab-wrapper" style="margin-bottom:18px">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=warleek' ) ); ?>" class="nav-tab <?php echo $settings ? '' : 'nav-tab-active'; ?>">Installation</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=warleek-settings' ) ); ?>" class="nav-tab <?php echo $settings ? 'nav-tab-active' : ''; ?>">Einstellungen</a>
		</h2>
		<?php if ( $settings ) { warleek_render_settings_tab(); } else { warleek_render_install_tab(); } ?>
	</div>
	<?php
}

function warleek_render_settings_tab() {
	?>
	<form action="options.php" method="post">
		<?php settings_fields( 'warleek' ); do_settings_sections( 'warleek' ); submit_button(); ?>
	</form>
	<?php
}

function warleek_render_install_tab() {
	$steps  = warleek_install_steps();
	$log    = (array) get_option( 'warleek_install_log', array() );
	$status = warleek_install_status();
	?>
	<h2 style="margin-top:0">Status</h2>
	<ul class="wl-status">
		<?php foreach ( $status as $s ) : ?>
			<li class="<?php echo $s['ok'] ? 'is-ok' : ''; ?>">
				<span class="dashicons dashicons-<?php echo $s['ok'] ? 'yes-alt' : 'warning'; ?>"></span>
				<span><strong><?php echo esc_html( $s['label'] ); ?></strong><span><?php echo esc_html( $s['detail'] ); ?></span></span>
			</li>
		<?php endforeach; ?>
	</ul>

	<h2>Installation</h2>
	<ul class="wl-steps" id="wl-steps">
		<?php foreach ( $steps as $slug => $step ) :
			$done = ! empty( $log[ $slug ]['ok'] ); ?>
			<li data-step="<?php echo esc_attr( $slug ); ?>" data-state="<?php echo $done ? 'done' : 'idle'; ?>">
				<span class="wl-ico"><?php echo $done ? '✓' : '·'; ?></span>
				<span>
					<strong><?php echo esc_html( $step['label'] ); ?></strong>
					<em><?php echo esc_html( $step['description'] ); ?></em>
					<span class="wl-msg"><?php echo $done ? esc_html( $log[ $slug ]['msg'] ) : ''; ?></span>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="wl-actions">
		<button type="button" class="button button-primary button-hero" id="wl-run-all">Komplett installieren</button>
		<label><input type="checkbox" id="wl-force"> Inhalte überschreiben (setzt eigene Änderungen an Seitentexten zurück)</label>
	</div>
	<p class="description">Dauert je nach Server etwa eine Minute. Das Fenster bitte offen lassen.</p>

	<div class="wl-single">
		<span style="align-self:center;color:#646970">Einzeln:</span>
		<?php foreach ( $steps as $slug => $step ) : ?>
			<button type="button" class="button wl-run-one" data-step="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $step['label'] ); ?></button>
		<?php endforeach; ?>
	</div>

	<div class="wl-next">
		<strong>Danach noch von Hand:</strong>
		<ol>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=warleek-settings' ) ); ?>">Chat-Links eintragen</a> (Discord, WhatsApp, Telegram) – ohne sie zeigen die Buttons „bald“.</li>
			<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>">Impressum und Datenschutz</a> mit echten Daten füllen (Platzhalter in eckigen Klammern).</li>
			<li>About us: Team-Karten mit Namen, Texten und Bildern ersetzen.</li>
			<li>Rank Math: Setup-Assistent durchlaufen (Sitemap, Search Console).</li>
		</ol>
	</div>

	<script>
	(function () {
		var steps = <?php echo wp_json_encode( array_keys( $steps ) ); ?>;
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'warleek_install' ) ); ?>;
		var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		function el(slug) { return document.querySelector('[data-step="' + slug + '"]'); }
		function setState(slug, state, msg) {
			var li = el(slug); if (!li) return;
			li.dataset.state = state;
			li.querySelector('.wl-ico').textContent = state === 'done' ? '✓' : (state === 'error' ? '✕' : (state === 'running' ? '⟳' : '·'));
			if (msg !== undefined) li.querySelector('.wl-msg').textContent = msg;
		}
		function run(slug) {
			setState(slug, 'running', 'läuft …');
			var body = new FormData();
			body.append('action', 'warleek_install_step');
			body.append('step', slug);
			body.append('nonce', nonce);
			if (document.getElementById('wl-force').checked) body.append('force', '1');
			return fetch(ajax, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) { return r.text(); })
				.then(function (t) {
					var j = null;
					try { j = JSON.parse(t); } catch (e) {}
					if (!j) { setState(slug, 'error', 'Unerwartete Antwort vom Server – Schritt einzeln erneut ausführen.'); return false; }
					var ok = j.success && j.data && j.data.ok;
					setState(slug, ok ? 'done' : 'error', (j.data && j.data.msg) || 'Unbekannter Fehler');
					return ok;
				})
				.catch(function (e) { setState(slug, 'error', 'Netzwerkfehler: ' + e.message); return false; });
		}
		document.getElementById('wl-run-all').addEventListener('click', function () {
			var btn = this; btn.disabled = true; btn.textContent = 'Installiere …';
			steps.reduce(function (chain, slug) { return chain.then(function () { return run(slug); }); }, Promise.resolve())
				.then(function () { btn.textContent = 'Fertig – Seite neu laden'; btn.disabled = false; btn.onclick = function () { location.reload(); }; });
		});
		document.querySelectorAll('.wl-run-one').forEach(function (b) {
			b.addEventListener('click', function () { b.disabled = true; run(b.dataset.step).then(function () { b.disabled = false; }); });
		});
	})();
	</script>
	<?php
}

/** AJAX: einen Schritt ausführen. */
function warleek_ajax_install_step() {
	check_ajax_referer( 'warleek_install', 'nonce' );
	// Frisch aktivierte Plugins (z. B. Rank Math) leiten im admin_init auf ihren Assistenten um –
	// das würde HTML statt JSON zurückgeben. Während eines Schritts daher keine Redirects.
	add_filter( 'wp_redirect', '__return_false', 99 );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'ok' => false, 'msg' => 'Keine Berechtigung.' ), 403 ); }
	$slug  = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';
	$force = ! empty( $_POST['force'] );
	$res   = warleek_run_step( $slug, $force );
	wp_send_json_success( $res );
}
add_action( 'wp_ajax_warleek_install_step', 'warleek_ajax_install_step' );
