<?php
/**
 * Aufruf: wp eval-file wp-content/plugins/warleek-core/tests/translate-test.php
 * Kein echter API-Aufruf: die HTTP-Schicht wird abgefangen. Kostet nichts.
 */
// Zähler über $GLOBALS: `wp eval-file` führt die Datei in einem Funktionsrahmen
// aus, ein `global $fail` in der Prüffunktion träfe deshalb nicht dieselbe
// Variable wie das `$fail` hier – die Schlussmeldung hätte immer „OK" gelautet.
$GLOBALS['wl_tr_fail'] = 0;
function t_chk( $label, $cond, $extra = '' ) {
	if ( ! $cond ) { $GLOBALS['wl_tr_fail']++; echo "FAIL $label $extra\n"; }
}

$html_en = '<h2>Fixes</h2><ul><li>Fixed the <strong>FOB</strong> spawn</li></ul><p><img src="https://clan.akamai.steamstatic.com/images/1/a.png" alt="" loading="lazy"></p><p>See <a href="https://x.de/a" rel="noopener" target="_blank">notes</a>.</p>';
$html_de = '<h2>Korrekturen</h2><ul><li>Der <strong>FOB</strong>-Spawn wurde repariert</li></ul><p><img src="https://clan.akamai.steamstatic.com/images/1/a.png" alt="" loading="lazy"></p><p>Siehe <a href="https://x.de/a" rel="noopener" target="_blank">Notes</a>.</p>';

/* Antwort der API nachbilden */
$GLOBALS['t_payload'] = array( 'title_de' => 'Wartung & Patch 0.11', 'summary' => array( 'FOB-Spawn repariert' ), 'html_de' => $html_de );
$GLOBALS['t_calls']   = 0;
$GLOBALS['t_spiegel'] = false;
$GLOBALS['t_system']  = '';
$GLOBALS['t_pflicht'] = array();
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'api.anthropic.com' ) ) { return $pre; }
	$GLOBALS['t_calls']++;
	if ( ! empty( $GLOBALS['t_http_error'] ) ) { return new WP_Error( 'http', 'Netzwerk weg' ); }

	$nutzlast = $GLOBALS['t_payload'];
	$gesendet = json_decode( (string) ( $args['body'] ?? '' ), true );
	$inhalt   = (string) ( $gesendet['messages'][0]['content'] ?? '' );
	$GLOBALS['t_system'] = (string) ( $gesendet['system'] ?? '' );
	$GLOBALS['t_pflicht'] = (array) ( $gesendet['output_config']['format']['schema']['required'] ?? array() );

	// Spiegel: Das gesendete HTML kommt als „Übersetzung" zurück. Damit prüft der
	// Test, dass Stücke vollständig und in der richtigen Reihenfolge wieder
	// zusammenwachsen – mit einer festen Antwort wäre genau das unsichtbar.
	if ( ! empty( $GLOBALS['t_spiegel'] ) && preg_match( '#<HTML>\n(.*)\n</HTML>#s', $inhalt, $m ) ) {
		$nutzlast['html_de'] = $m[1];
	}
	foreach ( array( 'title_de', 'summary' ) as $feld ) {
		if ( ! in_array( $feld, $GLOBALS['t_pflicht'], true ) ) { unset( $nutzlast[ $feld ] ); }
	}

	return array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode( array(
			'model' => 'claude-opus-5', 'stop_reason' => 'end_turn', 'usage' => array( 'input_tokens' => 10, 'output_tokens' => 20 ),
			'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $nutzlast ) ) ),
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

/* 6) Stückelung: Schnitt nur zwischen Elementen oberster Ebene */
$lang = '';
for ( $i = 1; $i <= 12; $i++ ) {
	$lang .= '<h2>Section ' . $i . '</h2><p>' . str_repeat( 'Lorem ipsum dolor sit amet. ', 30 ) . '</p>';
}
$st = warleek_html_teile( $lang, 2000 );
t_chk( 'in stücke geschnitten', count( $st ) > 3, 'Stücke: ' . count( $st ) );
t_chk( 'stücke ergeben das original', implode( '', $st ) === $lang );
foreach ( $st as $n => $stueck ) {
	t_chk( 'stück ' . ( $n + 1 ) . ' beginnt mit einem tag', '<' === substr( ltrim( $stueck ), 0, 1 ) );
	t_chk( 'stück ' . ( $n + 1 ) . ' hat ausgeglichene absätze', substr_count( $stueck, '<p' ) === substr_count( $stueck, '</p>' ) );
}
t_chk( 'kurzes html bleibt ein stück', array( '<p>Kurz</p>' ) === warleek_html_teile( '<p>Kurz</p>', 2000 ) );
// Ein einzelnes übergroßes Element wird nicht aufgebrochen – sonst stünde nach
// dem Zusammensetzen ein Tag mehr im Dokument als im Original.
$ein_block = '<p>' . str_repeat( 'Lorem ipsum dolor sit amet. ', 200 ) . '</p>';
t_chk( 'übergroßer einzelblock bleibt ganz', 1 === count( warleek_html_teile( $ein_block, 2000 ) ) );

/* 6b) Gliederung für die Kurzfassung */
$gl = warleek_html_gliederung( '<h2>Weapons</h2><p>x</p><h3>AK-74</h3><h4>Damage</h4><h2>Vehicles</h2>' );
t_chk( 'gliederung gelesen', array( 'Weapons', 'AK-74', 'Vehicles' ) === $gl, implode( ' | ', $gl ) );

/* 6b2) HTML-Entitäten: Der DOM-Umlauf normalisiert sie (&#039; → ') – erlaubt,
   solange Struktur, Bilder und Links gleich bleiben. Genau das prüft die
   Plausibilitätsprüfung je Stück, nicht Byte-Gleichheit. */
$mit_ent = '';
for ( $i = 1; $i <= 12; $i++ ) { $mit_ent .= '<h2>S' . $i . '</h2><p>' . str_repeat( 'It&#039;s a test &amp; more. ', 30 ) . '</p>'; }
$st_ent = warleek_html_teile( $mit_ent, 2000 );
$fp_a   = warleek_html_fingerprint( $mit_ent );
$fp_b   = warleek_html_fingerprint( implode( '', $st_ent ) );
t_chk( 'entitäten: struktur bleibt', $fp_a === $fp_b );
t_chk( 'entitäten: text bleibt', 360 === substr_count( implode( '', $st_ent ), 'test' ), substr_count( implode( '', $st_ent ), 'test' ) . ' Fundstellen' );

/* 6c) Stückweise Übersetzung läuft durch und wächst wieder zusammen */
// Budget hochsetzen: Jedes Stück ist ein Aufruf, und der Zähler des Laufs läuft
// über die ganze Testdatei weiter.
$GLOBALS['t_spiegel'] = true;
$opts['translate_max_chars'] = '2000'; $opts['translate_budget'] = '50'; update_option( 'warleek_options', $opts );
$vorher = $GLOBALS['t_calls'];
$mehr   = warleek_maybe_translate( 'Season 02 Patch Notes', $lang, 0 );
t_chk( 'lange note übersetzt', is_array( $mehr ), is_wp_error( $mehr ) ? $mehr->get_error_message() : '' );
t_chk( 'ein aufruf je stück', is_array( $mehr ) && $GLOBALS['t_calls'] - $vorher === (int) $mehr['teile'], 'Aufrufe: ' . ( $GLOBALS['t_calls'] - $vorher ) );
t_chk( 'text vollständig', is_array( $mehr ) && substr_count( $mehr['html'], '<h2' ) === 12, is_array( $mehr ) ? substr_count( $mehr['html'], '<h2' ) . ' Überschriften' : '' );
t_chk( 'titel aus dem ersten stück', is_array( $mehr ) && 'Wartung & Patch 0.11' === $mehr['title'] );
t_chk( 'eine kurzfassung, nicht je stück', is_array( $mehr ) && 1 === count( $mehr['summary'] ) );
t_chk( 'folgestücke ohne titel im schema', array( 'html_de' ) === $GLOBALS['t_pflicht'], implode( ',', $GLOBALS['t_pflicht'] ) );
t_chk( 'modell weiß vom ausschnitt', false !== strpos( $GLOBALS['t_system'], 'Stücken übersetzt' ) );

/* 6d) Deckel: mehr Stücke als erlaubt → bleibt englisch */
$opts['translate_max_chars'] = '2000'; update_option( 'warleek_options', $opts );
$zu_lang = '';
for ( $i = 1; $i <= 40; $i++ ) { $zu_lang .= '<p>' . str_repeat( 'Lorem ipsum dolor sit amet. ', 30 ) . '</p>'; }
$deckel = warleek_maybe_translate( 'Y', $zu_lang, 0 );
t_chk( 'stückdeckel greift', is_wp_error( $deckel ) && 'warleek_tr_toolong' === $deckel->get_error_code(), is_wp_error( $deckel ) ? $deckel->get_error_code() : 'kein Fehler' );
$GLOBALS['t_spiegel'] = false;
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
echo $GLOBALS['wl_tr_fail'] ? $GLOBALS['wl_tr_fail'] . " FAILED\n" : "OK\n";
