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
/**
 * Höchstzahl der Stücke je Patch Note.
 *
 * Bei der voreingestellten Stückgröße sind das rund 160.000 Zeichen – mehr als
 * jede bisherige Ankündigung. Der Deckel ist kein Qualitätsurteil, sondern
 * verhindert, dass ein einziges Dokument das Budget eines Laufs auffrisst.
 */
const WARLEEK_TRANSLATE_MAX_TEILE = 8;

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
 * @param int    $max_tokens Obergrenze der Antwortlänge.
 * @return array|WP_Error Dekodierte Antwort nach Schema.
 */
function warleek_anthropic_request( $system, $user, array $schema, $max_tokens = 16000 ) {
	$key = warleek_anthropic_key();
	if ( ! $key ) { return new WP_Error( 'warleek_no_key', 'Kein Anthropic-API-Schlüssel hinterlegt.' ); }

	$body = array(
		'model'         => (string) warleek_opt( 'translate_model', 'claude-opus-5' ),
		'max_tokens'    => max( 4000, min( 32000, (int) $max_tokens ) ),
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
 * Lange Patch Notes in übersetzbare Stücke schneiden.
 *
 * Geschnitten wird **nur zwischen Elementen der obersten Ebene**. Damit bleibt
 * jedes Stück für sich gültiges HTML mit ausgeglichenen Tags – Voraussetzung
 * dafür, dass die Plausibilitätsprüfung (gleiche Bilder, Links, Struktur) je
 * Stück überhaupt greifen kann.
 *
 * Ein einzelnes Element, das allein größer als die Stückgröße ist, wird
 * **nicht** aufgebrochen: Ein Schnitt mitten in einer Liste müsste das
 * umgebende Tag doppeln, und nach dem Zusammensetzen stünde im Dokument ein
 * `<ul>` mehr als im Original. Lieber ein zu großes Stück, dessen
 * Antwortbudget mitwächst, als eine veränderte Struktur.
 *
 * @param string $html Englisches HTML.
 * @param int    $max  Stückgröße in Zeichen.
 * @return array<int,string> Mindestens ein Stück; aneinandergehängt wieder das Original.
 */
function warleek_html_teile( $html, $max ) {
	$html = (string) $html;
	$max  = max( 2000, (int) $max );
	if ( strlen( $html ) <= $max ) { return array( $html ); }

	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML(
		'<?xml encoding="UTF-8"><div id="wl-teile">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();
	$root = $doc->getElementById( 'wl-teile' );
	if ( ! $root ) { return array( $html ); }   // Nicht lesbar? Dann unverändert weitergeben.

	$teile = array();
	$akt   = '';
	foreach ( $root->childNodes as $node ) {
		$stueck = (string) $doc->saveHTML( $node );
		if ( '' === trim( $stueck ) ) { $akt .= $stueck; continue; }
		if ( '' !== $akt && strlen( $akt ) + strlen( $stueck ) > $max ) {
			$teile[] = $akt;
			$akt     = '';
		}
		$akt .= $stueck;
	}
	if ( '' !== trim( $akt ) ) { $teile[] = $akt; }
	return $teile ? $teile : array( $html );
}

/**
 * Gliederung eines Dokuments: die Abschnittsüberschriften in ihrer Reihenfolge.
 *
 * Geht beim stückweisen Übersetzen in den **ersten** Aufruf mit, damit die
 * Kurzfassung die ganze Patch Note zusammenfasst und nicht nur das erste Stück,
 * das sie zufällig gesehen hat.
 *
 * @param string $html
 * @return array<int,string>
 */
function warleek_html_gliederung( $html ) {
	$out = array();
	// Nur h2 und h3: Bei der Season-02-Note ergaben h1–h4 über hundert Zeilen,
	// darunter jede einzelne Waffenbezeichnung. Eine Gliederung, die alles
	// aufzählt, gliedert nichts.
	if ( preg_match_all( '#<h[23][^>]*>(.*?)</h[23]>#is', (string) $html, $m ) ) {
		foreach ( $m[1] as $roh ) {
			$titel = trim( html_entity_decode( wp_strip_all_tags( $roh ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' !== $titel ) { $out[] = $titel; }
		}
	}
	return $out;
}

/**
 * Übersetzt ein Stück Patch-Note-HTML.
 *
 * @param string $title_en Englischer Titel.
 * @param string $html_en  Englisches HTML (ein Stück oder das ganze Dokument).
 * @param array  $ctx      Optional `teil`, `teile`, `gliederung` für die Stückelung.
 * @return array{title:string,summary:array,html:string,model:string,usage:array}|WP_Error
 */
function warleek_translate_html( $title_en, $html_en, array $ctx = array() ) {
	$system = 'Du übersetzt offizielle Patch Notes des Spiels WARDOGS aus dem Englischen ins Deutsche für eine Fan-Guide-Seite.

Regeln:
- Übersetze ausschließlich Textinhalte. Alle HTML-Tags, Attribute und URLs bleiben Zeichen für Zeichen unverändert und in derselben Reihenfolge.
- Etablierte Spielbegriffe bleiben englisch: FOB, Loadout, Spawn, Squad, Hotfix, Patch, Build, Ammo, Fuel, Mechanical, Hot Zone, Early Access. Eigennamen von Waffen, Fahrzeugen, Orten und Modi bleiben ebenfalls unverändert.
- Ton: sachlich und direkt, „du"-Ansprache wie in Spielemagazinen. Keine Werbesprache, nichts hinzufügen, nichts weglassen.
- Zahlen, Prozentwerte, Zeitangaben und Tastenbelegungen exakt übernehmen.
- Fasse zusätzlich das Wichtigste in drei bis fünf kurzen deutschen Stichpunkten zusammen: was sich für Spieler tatsächlich ändert. Keine Meta-Sätze wie „Dieser Patch enthält".';

	// Bei einem Stück bleibt Anweisung und Schema **genau** wie zuvor – sonst
	// würde eine Prompt-Änderung alle bereits übersetzten Notes entwerten und
	// einen neuen Lauf kostenpflichtig wiederholen lassen.
	$teil  = max( 1, (int) ( $ctx['teil'] ?? 1 ) );
	$anzahl = max( 1, (int) ( $ctx['teile'] ?? 1 ) );
	$meta  = ( 1 === $teil );   // Titel und Kurzfassung entstehen nur einmal.

	if ( $anzahl > 1 ) {
		$system .= "\n\nDieses Dokument wird in " . $anzahl . ' Stücken übersetzt, du siehst Stück ' . $teil
			. '. Übersetze genau diesen Ausschnitt: keine Einleitung, kein Schlusssatz, nichts ergänzen, was außerhalb liegt.'
			. ' Der Ausschnitt kann mitten in einem Abschnitt beginnen oder enden – das ist richtig so.';
		if ( $meta ) {
			$system .= "\n" . 'Die Gliederung des ganzen Dokuments steht unter <GLIEDERUNG>. Die Kurzfassung fasst die **ganze** Patch Note zusammen, nicht nur dieses Stück.';
		}
	}

	$user = "<TITEL>\n" . $title_en . "\n</TITEL>\n<HTML>\n" . $html_en . "\n</HTML>";
	if ( $meta && $anzahl > 1 && ! empty( $ctx['gliederung'] ) ) {
		$user .= "\n<GLIEDERUNG>\n- " . implode( "\n- ", (array) $ctx['gliederung'] ) . "\n</GLIEDERUNG>";
	}

	$eigenschaften = array();
	$pflicht       = array();
	if ( $meta ) {
		$eigenschaften['title_de'] = array( 'type' => 'string', 'description' => 'Der übersetzte Titel.' );
		$eigenschaften['summary']  = array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Drei bis fünf Stichpunkte.' );
		$pflicht                   = array( 'title_de', 'summary' );
	}
	$eigenschaften['html_de'] = array( 'type' => 'string', 'description' => 'Das übersetzte HTML mit unveränderten Tags und URLs.' );
	$pflicht[]                = 'html_de';

	$schema = array(
		'type'                 => 'object',
		'additionalProperties' => false,
		'required'             => $pflicht,
		'properties'           => $eigenschaften,
	);

	// Deutsch ist länger als Englisch, und das JSON-Format kostet zusätzlich
	// Zeichen. Die Vorgabe von 16.000 bleibt die Untergrenze.
	$out = warleek_anthropic_request( $system, $user, $schema, max( 16000, (int) ( strlen( $html_en ) / 2 ) ) );
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
		'title'   => $meta ? ( trim( wp_strip_all_tags( (string) ( $out['title_de'] ?? $title_en ) ) ) ?: $title_en ) : '',
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
 * Lange Notes werden in Stücke geschnitten und stückweise übersetzt – die
 * Season-02-Note hatte 45.438 Zeichen und blieb vorher komplett englisch.
 * Gespeichert wird nur, wenn **alle** Stücke durchgelaufen sind.
 *
 * @param string   $title_en
 * @param string   $html_en
 * @param int      $existing Vorhandene Beitrags-ID (0 = neu).
 * @param bool     $retranslate Zwischenspeicher übergehen.
 * @return array{title:string,html:string,summary:array,hash:string,model:string,teile:int}|null|WP_Error
 */
function warleek_maybe_translate( $title_en, $html_en, $existing = 0, $retranslate = false ) {
	if ( ! warleek_translate_enabled() ) { return null; }

	$model = (string) warleek_opt( 'translate_model', 'claude-opus-5' );
	$hash  = md5( WARLEEK_TRANSLATE_REV . '|' . $model . '|' . $title_en . '|' . $html_en );

	// Schon übersetzt und unverändert? Dann kostet dieser Sync nichts.
	if ( $existing && ! $retranslate && get_post_meta( $existing, '_warleek_tr_hash', true ) === $hash ) {
		return null;
	}
	$max   = max( 2000, (int) warleek_opt( 'translate_max_chars', 20000 ) );
	$teile = warleek_html_teile( $html_en, $max );
	$anzahl = count( $teile );
	if ( $anzahl > WARLEEK_TRANSLATE_MAX_TEILE ) {
		return new WP_Error( 'warleek_tr_toolong', sprintf(
			'Patch Note zu lang (%d Zeichen ergeben %d Stücke, erlaubt sind %d) – bleibt englisch.',
			strlen( $html_en ), $anzahl, WARLEEK_TRANSLATE_MAX_TEILE
		) );
	}
	if ( ! warleek_translate_budget_left() ) {
		return new WP_Error( 'warleek_tr_budget', 'Übersetzungsbudget für diesen Lauf aufgebraucht – der nächste Lauf macht weiter.' );
	}

	// Eine angefangene Note wird fertig übersetzt, auch wenn ihre Stücke das
	// Budget überziehen: Eine halb deutsche Patch Note wäre schlechter als eine
	// englische, und zwischenspeichern lässt sich nur das vollständige Ergebnis.
	$gliederung = $anzahl > 1 ? warleek_html_gliederung( $html_en ) : array();
	$gesamt     = array( 'title' => '', 'summary' => array(), 'html' => '', 'model' => '', 'usage' => array(), 'teile' => $anzahl );

	foreach ( $teile as $i => $stueck ) {
		warleek_translate_budget_left( 1 );
		$res = warleek_translate_html( $title_en, $stueck, array(
			'teil'       => $i + 1,
			'teile'      => $anzahl,
			'gliederung' => $gliederung,
		) );
		if ( is_wp_error( $res ) ) {
			if ( $anzahl < 2 ) { return $res; }
			return new WP_Error( $res->get_error_code(), sprintf( 'Stück %d von %d: %s', $i + 1, $anzahl, $res->get_error_message() ) );
		}
		$gesamt['html'] .= ( '' === $gesamt['html'] ? '' : "\n" ) . $res['html'];
		if ( '' === $gesamt['title'] && '' !== (string) $res['title'] ) { $gesamt['title'] = $res['title']; }
		if ( ! $gesamt['summary'] && ! empty( $res['summary'] ) ) { $gesamt['summary'] = $res['summary']; }
		if ( '' !== (string) $res['model'] ) { $gesamt['model'] = $res['model']; }
		foreach ( (array) $res['usage'] as $k => $v ) {
			if ( is_int( $v ) ) { $gesamt['usage'][ $k ] = (int) ( $gesamt['usage'][ $k ] ?? 0 ) + $v; }
		}
	}

	if ( '' === $gesamt['title'] ) { $gesamt['title'] = $title_en; }
	$gesamt['hash'] = $hash;
	return $gesamt;
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
