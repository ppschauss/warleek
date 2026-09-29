<?php
/**
 * Warleek — Patch Notes auf Deutsch.
 *
 * Die Steam-Ankündigungen sind englisch. Nach der BBCode→HTML-Umwandlung geht der
 * Text durch die Claude-API: saubere Übersetzung plus eine Kurzfassung „Das Wichtigste
 * in Kürze". Das Original bleibt als Meta erhalten.
 *
 * Reihenfolge ist Absicht: erst HTML, dann übersetzen. So bleibt die Struktur
 * (Überschriften, Listen, Tabellen, Bilder) erhalten und lässt sich prüfen.
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const WARLEEK_ANTHROPIC_URL     = 'https://api.anthropic.com/v1/messages';
const WARLEEK_ANTHROPIC_VERSION = '2023-06-01';
/** Hash-Version: erhöhen, wenn sich Prompt oder Ausgabeformat ändern. */
const WARLEEK_TRANSLATE_REV     = 'v1';

/** API-Schlüssel: Konstante in wp-config.php hat Vorrang vor der Einstellung. */
function warleek_anthropic_key() {
	if ( defined( 'WARLEEK_ANTHROPIC_KEY' ) && WARLEEK_ANTHROPIC_KEY ) { return (string) WARLEEK_ANTHROPIC_KEY; }
	return (string) warleek_opt( 'anthropic_api_key', '' );
}

function warleek_translate_enabled() {
	return warleek_anthropic_key() && '1' === (string) warleek_opt( 'translate_enabled', '1' );
}

/** Zähler für das Übersetzungsbudget eines Laufs (verhindert 30 Aufrufe am Stück). */
function warleek_translate_budget_left( $spend = 0 ) {
	static $used = 0;
	$max = max( 0, (int) warleek_opt( 'translate_budget', 5 ) );
	if ( $spend ) { $used += $spend; }
	return max( 0, $max - $used );
}

/**
 * Ein Anfrage an die Claude-API (Messages).
 *
 * Bewusst über wp_remote_post statt über das offizielle SDK: Das Plugin soll ohne
 * Composer-Abhängigkeiten als ZIP funktionieren.
 *
 * @param string $system System-Anweisung.
 * @param string $user   Nutzerinhalt.
 * @param array  $schema JSON-Schema für die Antwort.
 * @return array|WP_Error Dekodierte Antwort nach Schema.
 */
function warleek_anthropic_request( $system, $user, array $schema ) {
	$key = warleek_anthropic_key();
	if ( ! $key ) { return new WP_Error( 'warleek_no_key', 'Kein Anthropic-API-Schlüssel hinterlegt.' ); }

	$body = array(
		'model'         => (string) warleek_opt( 'translate_model', 'claude-opus-5' ),
		'max_tokens'    => 16000,
		'output_config' => array(
			'effort' => 'low', // Übersetzen braucht keine große Denktiefe – spart Geld.
			'format' => array( 'type' => 'json_schema', 'schema' => $schema ),
		),
		'fallbacks'     => 'default', // Bei einer Ablehnung übernimmt automatisch ein anderes Modell.
		'system'        => $system,
		'messages'      => array( array( 'role' => 'user', 'content' => $user ) ),
	);

	$res = wp_remote_post( WARLEEK_ANTHROPIC_URL, array(
		'timeout' => 120,
		'headers' => array(
			'content-type'      => 'application/json',
			'x-api-key'         => $key,
			'anthropic-version' => WARLEEK_ANTHROPIC_VERSION,
			'anthropic-beta'    => 'server-side-fallback-2026-07-01',
		),
		'body'    => wp_json_encode( $body ),
	) );

	if ( is_wp_error( $res ) ) { return $res; }
	$code = (int) wp_remote_retrieve_response_code( $res );
	$raw  = wp_remote_retrieve_body( $res );
	if ( 200 !== $code ) {
		$err = json_decode( $raw, true );
		return new WP_Error( 'warleek_api_http', sprintf( 'Claude-API HTTP %d: %s', $code, $err['error']['message'] ?? mb_substr( $raw, 0, 200 ) ) );
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) { return new WP_Error( 'warleek_api_json', 'Claude-API: Antwort nicht lesbar.' ); }
	if ( 'refusal' === ( $data['stop_reason'] ?? '' ) ) { return new WP_Error( 'warleek_api_refusal', 'Claude hat die Anfrage abgelehnt.' ); }

	$text = '';
	foreach ( (array) ( $data['content'] ?? array() ) as $block ) {
		if ( 'text' === ( $block['type'] ?? '' ) ) { $text .= $block['text']; }
	}
	$parsed = json_decode( trim( $text ), true );
	if ( ! is_array( $parsed ) ) { return new WP_Error( 'warleek_api_shape', 'Claude-API: unerwartetes Antwortformat.' ); }
	$parsed['_usage'] = $data['usage'] ?? array();
	$parsed['_model'] = $data['model'] ?? '';
	return $parsed;
}

/** Zählt Tags und sammelt URLs – Grundlage der Plausibilitätsprüfung. */
function warleek_html_fingerprint( $html ) {
	$urls = array();
	if ( preg_match_all( '/(?:src|href)="([^"]+)"/i', (string) $html, $m ) ) { $urls = $m[1]; }
	sort( $urls );
	$tags = array();
	foreach ( array( 'img', 'a', 'table', 'tr', 'td', 'th', 'li', 'h2', 'h3', 'h4', 'pre' ) as $t ) {
		$tags[ $t ] = substr_count( strtolower( (string) $html ), '<' . $t );
	}
	return array( 'urls' => $urls, 'tags' => $tags );
}

/**
 * Übersetzt ein Stück Patch-Note-HTML.
 *
 * @param string $title_en Englischer Titel.
 * @param string $html_en  Englisches HTML.
 * @return array{title:string,summary:array,html:string,model:string,usage:array}|WP_Error
 */
function warleek_translate_html( $title_en, $html_en ) {
	$system = 'Du übersetzt offizielle Patch Notes des Spiels WARDOGS aus dem Englischen ins Deutsche für eine Fan-Guide-Seite.

Regeln:
- Übersetze ausschließlich Textinhalte. Alle HTML-Tags, Attribute und URLs bleiben Zeichen für Zeichen unverändert und in derselben Reihenfolge.
- Etablierte Spielbegriffe bleiben englisch: FOB, Loadout, Spawn, Squad, Hotfix, Patch, Build, Ammo, Fuel, Mechanical, Hot Zone, Early Access. Eigennamen von Waffen, Fahrzeugen, Orten und Modi bleiben ebenfalls unverändert.
- Ton: sachlich und direkt, „du"-Ansprache wie in Spielemagazinen. Keine Werbesprache, nichts hinzufügen, nichts weglassen.
- Zahlen, Prozentwerte, Zeitangaben und Tastenbelegungen exakt übernehmen.
- Fasse zusätzlich das Wichtigste in drei bis fünf kurzen deutschen Stichpunkten zusammen: was sich für Spieler tatsächlich ändert. Keine Meta-Sätze wie „Dieser Patch enthält".';

	$user = "<TITEL>\n" . $title_en . "\n</TITEL>\n<HTML>\n" . $html_en . "\n</HTML>";

	$schema = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => array( 'title_de', 'summary', 'html_de' ),
		'properties'           => array(
			'title_de' => array( 'type' => 'string', 'description' => 'Der übersetzte Titel.' ),
			'summary'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Drei bis fünf Stichpunkte.' ),
			'html_de'  => array( 'type' => 'string', 'description' => 'Das übersetzte HTML mit unveränderten Tags und URLs.' ),
		),
	);

	$out = warleek_anthropic_request( $system, $user, $schema );
	if ( is_wp_error( $out ) ) { return $out; }

	$html_de = (string) ( $out['html_de'] ?? '' );
	if ( '' === trim( $html_de ) ) { return new WP_Error( 'warleek_tr_empty', 'Übersetzung war leer.' ); }

	// Plausibilität: gleiche Bilder, Links und Strukturelemente wie im Original.
	$a = warleek_html_fingerprint( $html_en );
	$b = warleek_html_fingerprint( $html_de );
	if ( $a['urls'] !== $b['urls'] ) {
		return new WP_Error( 'warleek_tr_urls', 'Übersetzung hat Bilder oder Links verändert – verworfen.' );
	}
	foreach ( $a['tags'] as $tag => $n ) {
		if ( $b['tags'][ $tag ] !== $n ) {
			return new WP_Error( 'warleek_tr_tags', sprintf( 'Übersetzung hat die Struktur verändert (%s: %d statt %d) – verworfen.', $tag, $b['tags'][ $tag ], $n ) );
		}
	}

	$summary = array();
	foreach ( (array) ( $out['summary'] ?? array() ) as $line ) {
		$line = trim( wp_strip_all_tags( (string) $line ) );
		if ( $line ) { $summary[] = $line; }
	}

	return array(
		'title'   => trim( wp_strip_all_tags( (string) ( $out['title_de'] ?? $title_en ) ) ) ?: $title_en,
		'summary' => array_slice( $summary, 0, 5 ),
		'html'    => wp_kses_post( $html_de ),
		'model'   => (string) ( $out['_model'] ?? '' ),
		'usage'   => (array) ( $out['_usage'] ?? array() ),
	);
}

/**
 * Übersetzt eine Patch Note, sofern nötig.
 *
 * Wird aus dem Steam-Sync heraus aufgerufen. Gibt das zu speichernde Ergebnis zurück
 * oder null, wenn nichts zu tun ist (dann bleibt die englische Fassung stehen).
 *
 * @param string   $title_en
 * @param string   $html_en
 * @param int      $existing Vorhandene Beitrags-ID (0 = neu).
 * @param bool     $retranslate Zwischenspeicher übergehen.
 * @return array{title:string,html:string,summary:array,hash:string,model:string}|null|WP_Error
 */
function warleek_maybe_translate( $title_en, $html_en, $existing = 0, $retranslate = false ) {
	if ( ! warleek_translate_enabled() ) { return null; }

	$model = (string) warleek_opt( 'translate_model', 'claude-opus-5' );
	$hash  = md5( WARLEEK_TRANSLATE_REV . '|' . $model . '|' . $title_en . '|' . $html_en );

	// Schon übersetzt und unverändert? Dann kostet dieser Sync nichts.
	if ( $existing && ! $retranslate && get_post_meta( $existing, '_warleek_tr_hash', true ) === $hash ) {
		return null;
	}
	$max = max( 2000, (int) warleek_opt( 'translate_max_chars', 20000 ) );
	if ( strlen( $html_en ) > $max ) {
		return new WP_Error( 'warleek_tr_toolong', sprintf( 'Patch Note zu lang (%d Zeichen, Grenze %d) – bleibt englisch.', strlen( $html_en ), $max ) );
	}
	if ( ! warleek_translate_budget_left() ) {
		return new WP_Error( 'warleek_tr_budget', 'Übersetzungsbudget für diesen Lauf aufgebraucht – der nächste Lauf macht weiter.' );
	}

	warleek_translate_budget_left( 1 );
	$res = warleek_translate_html( $title_en, $html_en );
	if ( is_wp_error( $res ) ) { return $res; }

	$res['hash'] = $hash;
	return $res;
}

/** Kurzfassung als Kasten über dem Beitrag. */
function warleek_render_patchnote_summary( $attrs = array() ) {
	if ( ! is_singular( 'patchnote' ) ) { return ''; }
	$summary = get_post_meta( get_the_ID(), '_warleek_summary', true );
	if ( ! is_array( $summary ) || ! $summary ) { return ''; }
	$out = '<div class="wl-note wl-summary"><p><strong>Das Wichtigste in Kürze</strong></p><ul>';
	foreach ( $summary as $line ) { $out .= '<li>' . esc_html( $line ) . '</li>'; }
	return $out . '</ul></div>';
}
add_shortcode( 'warleek_summary', 'warleek_render_patchnote_summary' );

/** Englisches Original zum Aufklappen unter dem Beitrag. */
function warleek_render_patchnote_original( $attrs = array() ) {
	if ( ! is_singular( 'patchnote' ) ) { return ''; }
	$src = get_post_meta( get_the_ID(), '_warleek_src_html', true );
	if ( ! $src ) { return ''; }
	return '<div class="wl-faq wl-original"><details><summary>Englisches Original anzeigen</summary>' . wp_kses_post( $src ) . '</details></div>';
}
add_shortcode( 'warleek_original', 'warleek_render_patchnote_original' );

/** Hinweis auf die maschinelle Übersetzung – Transparenz gehört dazu. */
function warleek_translation_note( $post_id ) {
	if ( ! get_post_meta( $post_id, '_warleek_tr_hash', true ) ) { return ''; }
	return '<p class="wl-source">Diese Patch Note wurde maschinell ins Deutsche übersetzt (Claude). Im Zweifel gilt das englische Original.</p>';
}
