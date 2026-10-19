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
	$res = wp_remote_get( $url, array( 'timeout' => 15, 'user-agent' => 'Warleek/' . WARLEEK_CORE_VERSION . ' (+https://warleek.de)' ) );
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

	// Deutsche Fassung holen. Schlägt das fehl, bleibt die englische stehen –
	// eine misslungene Übersetzung darf den Sync nie scheitern lassen.
	$title_en   = trim( (string) ( $item['title'] ?? '' ) );
	$html_en    = $html;
	$translated = function_exists( 'warleek_maybe_translate' ) ? warleek_maybe_translate( $title_en, $html_en, $existing, $force ) : null;
	$tr_error   = '';
	if ( is_wp_error( $translated ) ) {
		$tr_error   = $translated->get_error_message();
		$translated = null;
	}
	if ( $translated ) {
		$html = $translated['html'];
	}
	$plain = warleek_patchnote_klartext( $html );
	$title = trim( (string) ( $item['title'] ?? '' ) );
	if ( $title && 0 === strcasecmp( mb_substr( $plain, 0, mb_strlen( $title ) ), $title ) ) { $plain = trim( mb_substr( $plain, mb_strlen( $title ) ) ); }
	$data  = array(
		'post_type'     => 'patchnote',
		'post_status'   => 'publish',
		'post_title'    => sanitize_text_field( $translated['title'] ?? ( $item['title'] ?? 'Patch Note' ) ),
		'post_content'  => $html,
		'post_excerpt'  => warleek_patchnote_auszug( $plain, $translated['summary'] ?? array() ),
		'post_date'     => wp_date( 'Y-m-d H:i:s', $ts ),
		'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $ts ),
		'meta_input'    => array(
			'steam_gid'          => $gid,
			'steam_url'          => esc_url_raw( (string) ( $item['url'] ?? '' ) ),
			'steam_published_at' => $ts,
			'_warleek_hash'      => $hash,
			'_warleek_src_title' => $title_en,
			'_warleek_src_html'  => $html_en,
		),
	);
	if ( $existing ) {
		$data['ID'] = $existing;
		$id = wp_update_post( wp_slash( $data ), true );
		if ( is_wp_error( $id ) ) { return $id; }
		warleek_store_translation( $existing, $translated, $tr_error );
		warleek_localize_remote_images( $existing );
		return array( 'id' => $existing, 'action' => 'updated' );
	}
	$id = wp_insert_post( wp_slash( $data ), true );
	if ( is_wp_error( $id ) ) { return $id; }
	warleek_store_translation( (int) $id, $translated, $tr_error );
	warleek_localize_remote_images( (int) $id );
	return array( 'id' => (int) $id, 'action' => 'created' );
}

/** HTML einer Patch Note als Klartext, Blockgrenzen werden zu Leerzeichen. */
function warleek_patchnote_klartext( $html ) {
	$text = preg_replace( '#</(p|li|h[1-6]|blockquote|tr)>#i', ' ', (string) $html );
	return html_entity_decode( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) ), ENT_QUOTES, 'UTF-8' );
}

/**
 * Auszug einer Patch Note.
 *
 * Steht eine deutsche Kurzfassung bereit, trägt **sie** den Auszug: Ihr erster
 * Stichpunkt ist ein ganzer Satz, während der abgeschnittene Fließtext mit
 * „WARDOGS SEASON 2 Trailer …" anfängt.
 *
 * Wichtiger noch ist der Fall, der die Season-02-Note getroffen hat: Der
 * Auszug entstand beim ersten Sync aus dem damals **englischen** Inhalt. Die
 * Übersetzung kam später über die CLI und schrieb Titel und Inhalt neu, den
 * Auszug aber nicht – und Rank Math baut die Meta-Beschreibung daraus. Auf der
 * Seite stand deutscher Text, in der Beschreibung „Season 2 drops on October
 * 15th". Deshalb wird der Auszug überall dort neu gesetzt, wo übersetzt wird.
 *
 * @param string $plain   Klartext der deutschen Fassung.
 * @param array  $summary Stichpunkte der Kurzfassung, falls vorhanden.
 * @return string
 */
function warleek_patchnote_auszug( $plain, $summary = array() ) {
	$kurz = '';
	foreach ( (array) $summary as $line ) {
		$line = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $line ) ) );
		if ( '' !== $line ) { $kurz = $line; break; }
	}
	$text = $kurz ?: trim( preg_replace( '/\s+/', ' ', (string) $plain ) );
	if ( mb_strlen( $text ) <= 160 ) { return $text; }
	$text    = mb_substr( $text, 0, 160 );
	$schnitt = mb_strrpos( $text, ' ' );
	return rtrim( ( $schnitt && $schnitt > 100 ) ? mb_substr( $text, 0, $schnitt ) : $text, " ,;:–-" ) . '…';
}

/**
 * Einmalige Reparatur bereits gespeicherter Patch Notes.
 *
 * Drei Fehler steckten im ausgelieferten Text der Season-02-Note und ließen
 * sich nicht durch einen neuen Sync beheben, weil der Fingerabdruck gleich
 * geblieben wäre – und ein `--force` würde die Übersetzung erneut bezahlen:
 *
 * 1. `@@WLB188@@` stand sichtbar im Text. Der Block dahinter ist nicht mehr zu
 *    retten (das rohe BBCode legen wir nicht ab), also fällt die Marke weg –
 *    ein leeres Zitat ist besser als ein sichtbares Stück Technik. Neue Syncs
 *    lösen den Platzhalter richtig auf, siehe `inc/bbcode.php`.
 * 2. Markdown-Fluchtzeichen aus der Übersetzung („Kodiak \[M249]").
 * 3. Der Auszug war beim ersten Sync englisch und blieb es nach der späteren
 *    Übersetzung – Rank Math baut daraus die Meta-Beschreibung.
 *
 * Läuft einmal und merkt sich das in `warleek_patchnote_fix_v1`.
 */
function warleek_patchnote_reparatur() {
	if ( get_option( 'warleek_patchnote_fix_v1' ) ) { return; }

	$posts   = get_posts( array( 'post_type' => 'patchnote', 'post_status' => 'any', 'posts_per_page' => -1 ) );
	$geaendert = 0;

	foreach ( $posts as $post ) {
		$inhalt = warleek_patchnote_putzen( $post->post_content );
		$quelle = get_post_meta( $post->ID, '_warleek_src_html', true );
		$neu    = array();

		if ( $inhalt !== $post->post_content ) { $neu['post_content'] = $inhalt; }

		// Auszug nur dann neu, wenn eine deutsche Fassung vorliegt. Ohne
		// Übersetzung ist der englische Auszug die einzige Wahrheit, die da ist.
		if ( get_post_meta( $post->ID, '_warleek_tr_hash', true ) ) {
			$auszug = warleek_patchnote_auszug(
				warleek_patchnote_klartext( $inhalt ),
				(array) get_post_meta( $post->ID, '_warleek_summary', true )
			);
			if ( $auszug && $auszug !== $post->post_excerpt ) { $neu['post_excerpt'] = $auszug; }
		}

		if ( $neu ) {
			$neu['ID'] = $post->ID;
			wp_update_post( wp_slash( $neu ) );
			$geaendert++;
		}
		if ( is_string( $quelle ) && '' !== $quelle ) {
			$sauber = warleek_patchnote_putzen( $quelle );
			if ( $sauber !== $quelle ) { update_post_meta( $post->ID, '_warleek_src_html', $sauber ); }
		}
	}

	update_option( 'warleek_patchnote_fix_v1', $geaendert ?: '1' );
	if ( $geaendert && function_exists( 'warleek_log' ) ) {
		warleek_log( sprintf( 'Patch Notes nachgebessert: %d.', $geaendert ) );
	}
}
add_action( 'admin_init', 'warleek_patchnote_reparatur' );

/** Platzhalter-Reste, Fluchtzeichen und die davon leer gebliebenen Blöcke entfernen. */
function warleek_patchnote_putzen( $html ) {
	$html = preg_replace( '/@@WLB\d+@@/', '', (string) $html );
	if ( function_exists( 'warleek_md_escape_weg' ) ) {
		$html = warleek_md_escape_weg( $html );
	}
	// Was ohne den Platzhalter nur noch eine leere Hülle ist, fliegt mit.
	$html = preg_replace( '#<p[^>]*>\s*</p>#i', '', $html );
	$html = preg_replace( '#<blockquote[^>]*>\s*</blockquote>#i', '', $html );
	return $html;
}

/** Übersetzungsergebnis bzw. Fehler an der Patch Note ablegen. */
function warleek_store_translation( $post_id, $translated, $error = '' ) {
	if ( $translated ) {
		update_post_meta( $post_id, '_warleek_tr_hash', $translated['hash'] );
		update_post_meta( $post_id, '_warleek_summary', $translated['summary'] );
		update_post_meta( $post_id, '_warleek_tr_model', $translated['model'] );
		delete_post_meta( $post_id, '_warleek_tr_error' );
		return;
	}
	if ( $error ) { update_post_meta( $post_id, '_warleek_tr_error', $error ); }
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
		// 'full' statt 'large': die Screenshots laufen über die volle Inhaltsbreite (80vw),
		// mit srcset wählt der Browser trotzdem die passende Größe.
		$tag    = wp_get_attachment_image( $att_id, 'full', false, array( 'loading' => 'lazy', 'alt' => '', 'decoding' => 'async', 'sizes' => '(max-width: 781px) 95vw, 80vw' ) );
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


/**
 * Überschriften so verschieben, dass die erste Ebene im Inhalt h2 ist (der Titel ist h1).
 * Steam-Ankündigungen beginnen oft mit [h1] oder [h3] – das würde die Gliederung brechen.
 */
function warleek_normalize_headings( $html ) {
	if ( ! preg_match( '#<h([2-6])\b#i', $html, $m ) ) { return $html; }
	$first = (int) $m[1];
	if ( 2 === $first ) { return $html; }
	$shift = $first - 2;
	return preg_replace_callback( '#<(/?)h([2-6])\b#i', function ( $x ) use ( $shift ) {
		return '<' . $x[1] . 'h' . max( 2, (int) $x[2] - $shift );
	}, $html );
}
