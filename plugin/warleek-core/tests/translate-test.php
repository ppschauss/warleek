<?php
/**
 * Aufruf: wp eval-file wp-content/plugins/warleek-core/tests/translate-test.php
 * Kein echter API-Aufruf: die HTTP-Schicht wird abgefangen. Kostet nichts.
 */
$fail = 0;
function t_chk( $label, $cond, $extra = '' ) {
	global $fail;
	if ( ! $cond ) { $fail++; echo "FAIL $label $extra\n"; }
}

$html_en = '<h2>Fixes</h2><ul><li>Fixed the <strong>FOB</strong> spawn</li></ul><p><img src="https://clan.akamai.steamstatic.com/images/1/a.png" alt="" loading="lazy"></p><p>See <a href="https://x.de/a" rel="noopener" target="_blank">notes</a>.</p>';
$html_de = '<h2>Korrekturen</h2><ul><li>Der <strong>FOB</strong>-Spawn wurde repariert</li></ul><p><img src="https://clan.akamai.steamstatic.com/images/1/a.png" alt="" loading="lazy"></p><p>Siehe <a href="https://x.de/a" rel="noopener" target="_blank">Notes</a>.</p>';

/* Antwort der API nachbilden */
$GLOBALS['t_payload'] = array( 'title_de' => 'Wartung & Patch 0.11', 'summary' => array( 'FOB-Spawn repariert' ), 'html_de' => $html_de );
$GLOBALS['t_calls']   = 0;
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'api.anthropic.com' ) ) { return $pre; }
	$GLOBALS['t_calls']++;
	if ( ! empty( $GLOBALS['t_http_error'] ) ) { return new WP_Error( 'http', 'Netzwerk weg' ); }
	return array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode( array(
			'model' => 'claude-opus-5', 'stop_reason' => 'end_turn', 'usage' => array( 'input_tokens' => 10, 'output_tokens' => 20 ),
			'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $GLOBALS['t_payload'] ) ) ),
		) ),
	);
}, 10, 3 );

/* Einstellungen für den Test setzen */
$opts = (array) get_option( 'warleek_options', array() );
$back = $opts;
$opts['anthropic_api_key'] = 'sk-ant-test';
$opts['translate_enabled'] = '1';
$opts['translate_budget']  = '5';
update_option( 'warleek_options', $opts );

/* 1) Übersetzung inkl. Prüfungen */
$res = warleek_translate_html( 'Scheduled Maintenance', $html_en );
t_chk( 'übersetzt', ! is_wp_error( $res ), is_wp_error( $res ) ? $res->get_error_message() : '' );
t_chk( 'titel', ! is_wp_error( $res ) && 'Wartung & Patch 0.11' === $res['title'] );
t_chk( 'kurzfassung', ! is_wp_error( $res ) && 1 === count( $res['summary'] ) );
t_chk( 'html deutsch', ! is_wp_error( $res ) && false !== strpos( $res['html'], 'Korrekturen' ) );

/* 2) Kaputte Übersetzung: Bild fehlt → muss abgelehnt werden */
$GLOBALS['t_payload']['html_de'] = str_replace( '<img src="https://clan.akamai.steamstatic.com/images/1/a.png" alt="" loading="lazy">', '', $html_de );
$bad = warleek_translate_html( 'X', $html_en );
t_chk( 'bild-verlust erkannt', is_wp_error( $bad ) && 'warleek_tr_urls' === $bad->get_error_code(), is_wp_error( $bad ) ? $bad->get_error_code() : 'kein Fehler' );

/* 3) Struktur verändert (Listenpunkt weg) */
$GLOBALS['t_payload']['html_de'] = str_replace( '<li>Der <strong>FOB</strong>-Spawn wurde repariert</li>', '', $html_de );
$bad2 = warleek_translate_html( 'X', $html_en );
t_chk( 'struktur-verlust erkannt', is_wp_error( $bad2 ), is_wp_error( $bad2 ) ? '' : 'kein Fehler' );
$GLOBALS['t_payload']['html_de'] = $html_de;

/* 4) Zwischenspeicher: zweiter Aufruf mit gleichem Original kostet nichts */
$p = wp_insert_post( array( 'post_type' => 'patchnote', 'post_title' => 'T', 'post_content' => $html_en, 'post_status' => 'publish' ) );
$r1 = warleek_maybe_translate( 'T', $html_en, $p );
t_chk( 'erste übersetzung', is_array( $r1 ) );
warleek_store_translation( $p, $r1 );
$before = $GLOBALS['t_calls'];
$r2 = warleek_maybe_translate( 'T', $html_en, $p );
t_chk( 'zwischenspeicher greift', null === $r2 && $GLOBALS['t_calls'] === $before, 'Aufrufe: ' . $GLOBALS['t_calls'] . ' statt ' . $before );

/* 5) Budget-Deckel */
$opts['translate_budget'] = '0'; update_option( 'warleek_options', $opts );
$blocked = warleek_maybe_translate( 'Y', '<p>Other</p>', 0 );
t_chk( 'budget deckelt', is_wp_error( $blocked ) && 'warleek_tr_budget' === $blocked->get_error_code() );
$opts['translate_budget'] = '5'; update_option( 'warleek_options', $opts );

/* 6) Längengrenze (Untergrenze der Einstellung ist 2000 Zeichen) */
$opts['translate_max_chars'] = '2000'; update_option( 'warleek_options', $opts );
$riesig = '<p>' . str_repeat( 'Lorem ipsum dolor sit amet. ', 200 ) . '</p>';
$long = warleek_maybe_translate( 'Y', $riesig, 0 );
t_chk( 'längengrenze greift', is_wp_error( $long ) && 'warleek_tr_toolong' === $long->get_error_code() );
$opts['translate_max_chars'] = '20000'; update_option( 'warleek_options', $opts );

/* 7) API-Fehler: englische Fassung bleibt */
$GLOBALS['t_http_error'] = true;
$err = warleek_maybe_translate( 'Z', '<p>Hello</p>', 0 );
t_chk( 'api-fehler wird gemeldet', is_wp_error( $err ) );
$GLOBALS['t_http_error'] = false;

/* 8) Ohne Schlüssel passiert nichts */
$opts['anthropic_api_key'] = ''; update_option( 'warleek_options', $opts );
t_chk( 'ohne schlüssel aus', null === warleek_maybe_translate( 'A', '<p>B</p>', 0 ) );

wp_delete_post( $p, true );
update_option( 'warleek_options', $back );
echo $fail ? "$fail FAILED\n" : "OK\n";
