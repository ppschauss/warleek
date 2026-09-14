<?php
/**
 * Warleek — Steam-News → CPT patchnote.
 * Stündlicher WP-Cron + WP-CLI `wp warleek sync-patchnotes [--force]`.
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const WARLEEK_STEAM_NEWS_API = 'https://api.steampowered.com/ISteamNews/GetNewsForApp/v2/';

/**
 * Holt News-Items der Steam-Community-Announcements.
 *
 * @param int $appid
 * @param int $count
 * @return array|WP_Error Liste der newsitems.
 */
function warleek_steam_fetch_news( $appid, $count = 30 ) {
	$url = add_query_arg( array(
		'appid'     => (int) $appid,
		'count'     => (int) $count,
		'maxlength' => 0,
		'feeds'     => 'steam_community_announcements',
		'format'    => 'json',
	), WARLEEK_STEAM_NEWS_API );
	$res = wp_remote_get( $url, array( 'timeout' => 15, 'user-agent' => 'Warleek/' . WARLEEK_VERSION . ' (+https://warleek.de)' ) );
	if ( is_wp_error( $res ) ) { return $res; }
	$code = wp_remote_retrieve_response_code( $res );
	if ( 200 !== (int) $code ) { return new WP_Error( 'steam_http', 'Steam-API HTTP ' . $code ); }
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $data ) || empty( $data['appnews']['newsitems'] ) || ! is_array( $data['appnews']['newsitems'] ) ) {
		return new WP_Error( 'steam_json', 'Steam-API: unerwartete Antwort' );
	}
	return $data['appnews']['newsitems'];
}

/** Patch Note? Tag „patchnotes" oder Titel-Fallback (patch|update|hotfix|changelog). */
function warleek_steam_is_patchnote( array $item ) {
	$tags = isset( $item['tags'] ) && is_array( $item['tags'] ) ? array_map( 'strtolower', $item['tags'] ) : array();
	if ( in_array( 'patchnotes', $tags, true ) ) { return true; }
	return (bool) preg_match( '/patch|update|hotfix|changelog/i', (string) ( $item['title'] ?? '' ) );
}

/** Findet den Post zu einer Steam-GID. */
function warleek_patchnote_by_gid( $gid ) {
	$q = new WP_Query( array(
		'post_type' => 'patchnote', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids',
		'meta_key' => 'steam_gid', 'meta_value' => (string) $gid, 'no_found_rows' => true,
	) );
	return $q->posts ? (int) $q->posts[0] : 0;
}

/**
 * Legt eine Patch Note an oder aktualisiert sie (Dedupe über steam_gid, Änderung über Content-Hash).
 *
 * @return array{id:int,action:string}|WP_Error action = created|updated|skipped
 */
function warleek_steam_upsert_patchnote( array $item, $force = false ) {
	$gid = (string) ( $item['gid'] ?? '' );
	if ( '' === $gid ) { return new WP_Error( 'steam_item', 'Item ohne gid' ); }
	$contents = (string) ( $item['contents'] ?? '' );
	$hash     = md5( $item['title'] . '|' . $contents );
	$existing = warleek_patchnote_by_gid( $gid );

	if ( $existing && ! $force && get_post_meta( $existing, '_warleek_hash', true ) === $hash ) {
		return array( 'id' => $existing, 'action' => 'skipped' );
	}

	$ts    = (int) ( $item['date'] ?? time() );
	$html  = warleek_bbcode_to_html( $contents );
	// Führende Überschrift entfernen, wenn sie dem Titel entspricht (Steam wiederholt den Titel oft als [h1]).
	if ( preg_match( '#^\s*<h2>(.*?)</h2>#is', $html, $hm ) && 0 === strcasecmp( trim( html_entity_decode( wp_strip_all_tags( $hm[1] ), ENT_QUOTES, 'UTF-8' ) ), trim( (string) ( $item['title'] ?? '' ) ) ) ) {
		$html = preg_replace( '#^\s*<h2>.*?</h2>#is', '', $html, 1 );
	}
	$html  = warleek_normalize_headings( $html );
	$plain = html_entity_decode( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( preg_replace( '/<\/(p|li|h[1-6]|blockquote|tr)>/i', ' ', $html ) ) ) ), ENT_QUOTES, 'UTF-8' );
	$title = trim( (string) ( $item['title'] ?? '' ) );
	if ( $title && 0 === strcasecmp( mb_substr( $plain, 0, mb_strlen( $title ) ), $title ) ) { $plain = trim( mb_substr( $plain, mb_strlen( $title ) ) ); }
	$data  = array(
		'post_type'     => 'patchnote',
		'post_status'   => 'publish',
		'post_title'    => sanitize_text_field( $item['title'] ?? 'Patch Note' ),
		'post_content'  => $html,
		'post_excerpt'  => mb_substr( $plain, 0, 160 ) . ( mb_strlen( $plain ) > 160 ? '…' : '' ),
		'post_date'     => wp_date( 'Y-m-d H:i:s', $ts ),
		'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $ts ),
		'meta_input'    => array(
			'steam_gid'          => $gid,
			'steam_url'          => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
			'steam_published_at' => $ts,
			'_warleek_hash'      => $hash,
		),
	);
	if ( $existing ) {
		$data['ID'] = $existing;
		$id = wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $id ) ) { return $id; }
		warleek_localize_remote_images( $existing );
		return array( 'id' => $existing, 'action' => 'updated' );
	}
	$id = wp_insert_post( wp_slash( $data ), true );
	if ( is_wp_error( $id ) ) { return $id; }
	warleek_localize_remote_images( (int) $id );
	return array( 'id' => (int) $id, 'action' => 'created' );
}

/**
 * Lädt Bilder vom Steam-CDN in die Mediathek und ersetzt die URLs im Beitrag
 * (Datenschutz: Besucher laden nichts von Valve-Servern). Dedupe über Option warleek_steam_img_map.
 */
function warleek_localize_remote_images( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) { return; }
	$html = $post->post_content;
	if ( ! preg_match_all( '#https?://[a-z0-9.\-]*(?:steamstatic\.com|akamaihd\.net|steamusercontent\.com)/[^"\'\s<>]+#i', $html, $m ) ) { return; }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$map     = (array) get_option( 'warleek_steam_img_map', array() );
	$changed = false;
	foreach ( array_unique( $m[0] ) as $url ) {
		$key = md5( $url );
		if ( empty( $map[ $key ] ) || ! get_post( $map[ $key ] ) ) {
			$att = media_sideload_image( $url, $post_id, 'Patch-Notes-Bild (Steam)', 'id' );
			if ( is_wp_error( $att ) ) { continue; }
			$map[ $key ] = (int) $att;
			update_post_meta( (int) $att, '_warleek_steam_src', esc_url_raw( $url ) );
		}
		$att_id = (int) $map[ $key ];
		$tag    = wp_get_attachment_image( $att_id, 'large', false, array( 'loading' => 'lazy', 'alt' => '', 'decoding' => 'async' ) );
		if ( $tag ) {
			// kompletten <img>-Tag mit dieser Quelle durch responsives Markup (srcset, width/height) ersetzen
			$html = preg_replace( '#<img[^>]*src=["\']' . preg_quote( $url, '#' ) . '["\'][^>]*>#i', wp_make_link_relative( $tag ), $html, -1, $n );
			if ( $n ) { $changed = true; }
		} else {
			$local = wp_get_attachment_url( $att_id );
			if ( $local ) { $html = str_replace( $url, wp_make_link_relative( $local ), $html ); $changed = true; }
		}
	}
	update_option( 'warleek_steam_img_map', $map, false );
	if ( $changed ) {
		wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $html ) ) );
	}
}

/**
 * Kompletter Sync-Lauf.
 *
 * @return array{created:int,updated:int,skipped:int,error:?string}
 */
function warleek_sync_patchnotes( $force = false ) {
	$r     = array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => null );
	$items = warleek_steam_fetch_news( (int) warleek_opt( 'steam_appid' ), 30 );
	if ( is_wp_error( $items ) ) {
		$r['error'] = $items->get_error_message();
		update_option( 'warleek_sync_last_error', $r['error'], false );
		return $r;
	}
	foreach ( $items as $item ) {
		if ( ! warleek_steam_is_patchnote( $item ) ) { continue; }
		$res = warleek_steam_upsert_patchnote( $item, $force );
		if ( is_wp_error( $res ) ) { $r['error'] = $res->get_error_message(); continue; }
		$r[ $res['action'] ]++;
	}
	update_option( 'warleek_sync_last_run', time(), false );
	update_option( 'warleek_sync_last_error', $r['error'] ?: '', false );
	return $r;
}

/* ------------------------------------------------------------------- Cron */
add_action( 'warleek_sync_patchnotes', 'warleek_sync_patchnotes' );

function warleek_schedule_sync() {
	if ( ! wp_next_scheduled( 'warleek_sync_patchnotes' ) ) {
		wp_schedule_event( time() + 60, 'hourly', 'warleek_sync_patchnotes' );
	}
}
add_action( 'after_switch_theme', 'warleek_schedule_sync' );
add_action( 'init', 'warleek_schedule_sync' ); // Sicherheitsnetz, falls Theme ohne Switch-Hook aktiviert wurde.

function warleek_unschedule_sync() {
	$ts = wp_next_scheduled( 'warleek_sync_patchnotes' );
	if ( $ts ) { wp_unschedule_event( $ts, 'warleek_sync_patchnotes' ); }
}
add_action( 'switch_theme', 'warleek_unschedule_sync' );

/* ----------------------------------------------------------------- WP-CLI */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Warleek-Befehle.
	 */
	class Warleek_CLI {
		/**
		 * Synchronisiert Patch Notes von Steam.
		 *
		 * ## OPTIONS
		 *
		 * [--force]
		 * : Auch unveränderte Einträge neu schreiben.
		 *
		 * ## EXAMPLES
		 *
		 *     wp warleek sync-patchnotes
		 *     wp warleek sync-patchnotes --force
		 *
		 * @subcommand sync-patchnotes
		 */
		public function sync_patchnotes( $args, $assoc ) {
			$r = warleek_sync_patchnotes( ! empty( $assoc['force'] ) );
			WP_CLI::log( sprintf( 'created: %d updated: %d skipped: %d', $r['created'], $r['updated'], $r['skipped'] ) );
			if ( $r['error'] ) { WP_CLI::error( $r['error'] ); }
			WP_CLI::success( 'Patch Notes synchronisiert.' );
		}
	}
	WP_CLI::add_command( 'warleek', 'Warleek_CLI' );
}


/** Überschriften so verschieben, dass die erste Ebene im Inhalt h2 ist (Titel ist h1). */
function warleek_normalize_headings( $html ) {
	if ( ! preg_match( '#<h([2-6])\b#i', $html, $m ) ) { return $html; }
	$first = (int) $m[1]; // erste Überschrift im Inhalt → h2, Rest relativ dazu (min. h2)
	if ( 2 === $first ) { return $html; }
	$shift = $first - 2;
	return preg_replace_callback( '#<(/?)h([2-6])\b#i', function ( $x ) use ( $shift ) {
		return '<' . $x[1] . 'h' . max( 2, (int) $x[2] - $shift );
	}, $html );
}
