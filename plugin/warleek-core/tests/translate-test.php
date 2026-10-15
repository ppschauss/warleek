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
$GLOBALS['t_kaputt']  = 0;   // so viele der nächsten Antworten bekommen einen Listenpunkt zu viel
$GLOBALS['t_kaputt_ueber'] = 0;   // jede Antwort verfälschen, deren Ausschnitt länger ist als …
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

		// Strukturfehler nachstellen: ein Listenpunkt zu viel – genau der Fall,
		// an dem die Season-02-Note gescheitert ist (li: 101 statt 96).
		$verfaelschen = false;
		if ( $GLOBALS['t_kaputt'] > 0 ) { $GLOBALS['t_kaputt']--; $verfaelschen = true; }
		if ( $GLOBALS['t_kaputt_ueber'] && strlen( $m[1] ) > $GLOBALS['t_kaputt_ueber'] ) { $verfaelschen = true; }
		if ( $verfaelschen ) { $nutzlast['html_de'] .= '<ul><li>einer zu viel</li></ul>'; }
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
t_chk( 'elementzahlen in der anweisung', 1 === preg_match( '/Der Ausschnitt enthält [^.]*<h2>/u', $GLOBALS['t_system'] ), mb_substr( $GLOBALS['t_system'], -400 ) );

/* 6d) Deckel: mehr Stücke als erlaubt → bleibt englisch */
$opts['translate_max_chars'] = '2000'; update_option( 'warleek_options', $opts );
$zu_lang = '';
for ( $i = 1; $i <= 40; $i++ ) { $zu_lang .= '<p>' . str_repeat( 'Lorem ipsum dolor sit amet. ', 30 ) . '</p>'; }
$deckel = warleek_maybe_translate( 'Y', $zu_lang, 0 );
t_chk( 'stückdeckel greift', is_wp_error( $deckel ) && 'warleek_tr_toolong' === $deckel->get_error_code(), is_wp_error( $deckel ) ? $deckel->get_error_code() : 'kein Fehler' );
$GLOBALS['t_spiegel'] = false;
$opts['translate_max_chars'] = '20000'; update_option( 'warleek_options', $opts );

/* 6e–6h) Nachbesserung, Teilen, Zwischenspeicher.
   Der Stückspeicher liegt in Transients; er muss zwischen Durchläufen weg,
   sonst zählt der zweite Testlauf andere Aufrufe als der erste. */
function wl_tr_cache_leeren() {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_warleek\\_tr\\_%' OR option_name LIKE '\\_transient\\_timeout\\_warleek\\_tr\\_%'" );
	wp_cache_flush();
}
wl_tr_cache_leeren();
$GLOBALS['t_spiegel'] = true;
$opts['translate_budget'] = '200'; $opts['translate_max_chars'] = '20000'; update_option( 'warleek_options', $opts );

/* 6e) Erster Versuch verändert die Struktur, der zweite sitzt */
$GLOBALS['t_kaputt'] = 1;
$vorher = $GLOBALS['t_calls'];
$nach   = warleek_maybe_translate( 'Retry', '<p>' . str_repeat( 'Alpha beta gamma. ', 40 ) . '</p>', 0 );
t_chk( 'nachbesserung gelingt', is_array( $nach ), is_wp_error( $nach ) ? $nach->get_error_message() : '' );
t_chk( 'nachbesserung kostet einen aufruf extra', 2 === $GLOBALS['t_calls'] - $vorher, 'Aufrufe: ' . ( $GLOBALS['t_calls'] - $vorher ) );
t_chk( 'tadel steht in der anweisung', false !== strpos( $GLOBALS['t_system'], 'voriger Versuch wurde verworfen' ) );
t_chk( 'tadel nennt die abweichung', 1 === preg_match( '/li: \d+ statt \d+/u', $GLOBALS['t_system'] ) );
$GLOBALS['t_kaputt'] = 0;

/* 6f) Hartnäckig: große Ausschnitte scheitern, kleine gelingen → Stück wird geteilt */
wl_tr_cache_leeren();
$GLOBALS['t_kaputt_ueber'] = 1200;
$opts['translate_max_chars'] = '4000'; update_option( 'warleek_options', $opts );
$acht = '';
for ( $i = 1; $i <= 8; $i++ ) { $acht .= '<h2>S' . $i . '</h2><p>' . str_repeat( 'Delta epsilon. ', 25 ) . '</p>'; }
$vorher = $GLOBALS['t_calls'];
$geteilt = warleek_maybe_translate( 'Teilen', $acht, 0 );
t_chk( 'geteiltes stück gelingt', is_array( $geteilt ), is_wp_error( $geteilt ) ? $geteilt->get_error_message() : '' );
t_chk( 'mehr stücke als am anfang', is_array( $geteilt ) && (int) $geteilt['teile'] > 1, is_array( $geteilt ) ? $geteilt['teile'] . ' Stücke' : '' );
t_chk( 'text vollständig nach dem teilen', is_array( $geteilt ) && 8 === substr_count( $geteilt['html'], '<h2' ), is_array( $geteilt ) ? substr_count( $geteilt['html'], '<h2' ) . ' Überschriften' : '' );
t_chk( 'teilen bleibt im aufruf-deckel', $GLOBALS['t_calls'] - $vorher <= warleek_translate_aufruf_deckel( 1 ), 'Aufrufe: ' . ( $GLOBALS['t_calls'] - $vorher ) . ', Deckel ' . warleek_translate_aufruf_deckel( 1 ) );
t_chk( 'deckel wächst mit den stücken', 10 === warleek_translate_aufruf_deckel( 1 ) && 20 === warleek_translate_aufruf_deckel( 3 ) && 32 === warleek_translate_aufruf_deckel( 8 ), warleek_translate_aufruf_deckel( 1 ) . '/' . warleek_translate_aufruf_deckel( 3 ) . '/' . warleek_translate_aufruf_deckel( 8 ) );

/* 6g) Unteilbar und hartnäckig: nach zwei Versuchen ist Schluss */
wl_tr_cache_leeren();
$ein_hart = '<p>' . str_repeat( 'Zeta eta theta. ', 120 ) . '</p>';
$vorher   = $GLOBALS['t_calls'];
$hart     = warleek_maybe_translate( 'Hart', $ein_hart, 0 );
t_chk( 'unteilbares stück gibt auf', is_wp_error( $hart ) && 'warleek_tr_tags' === $hart->get_error_code(), is_wp_error( $hart ) ? $hart->get_error_code() : 'kein Fehler' );
t_chk( 'genau zwei versuche', 2 === $GLOBALS['t_calls'] - $vorher, 'Aufrufe: ' . ( $GLOBALS['t_calls'] - $vorher ) );

/* 6h) Der Stückspeicher: Ein gescheitertes letztes Stück darf die bezahlten
   Stücke davor nicht entwerten. */
wl_tr_cache_leeren();
$opts['translate_max_chars'] = '2000'; update_option( 'warleek_options', $opts );
$gemischt = '<p>' . str_repeat( 'Iota kappa. ', 50 ) . '</p><p>' . str_repeat( 'Lambda my. ', 250 ) . '</p>';
$teile_g  = warleek_html_teile( $gemischt, 2000 );
t_chk( 'prüfaufbau: zwei stücke', 2 === count( $teile_g ), count( $teile_g ) . ' Stücke' );
$vorher = $GLOBALS['t_calls'];
$lauf1  = warleek_maybe_translate( 'Speicher', $gemischt, 0 );
$kosten1 = $GLOBALS['t_calls'] - $vorher;
t_chk( 'erster lauf scheitert am letzten stück', is_wp_error( $lauf1 ) && false !== strpos( $lauf1->get_error_message(), 'Stück 2 von 2' ), is_wp_error( $lauf1 ) ? $lauf1->get_error_message() : 'kein Fehler' );
$vorher2 = $GLOBALS['t_calls'];
$lauf2   = warleek_maybe_translate( 'Speicher', $gemischt, 0 );
$kosten2 = $GLOBALS['t_calls'] - $vorher2;
t_chk( 'zweiter lauf zahlt das erste stück nicht erneut', $kosten2 < $kosten1, $kosten1 . ' dann ' . $kosten2 . ' Aufrufe' );
t_chk( 'zweiter lauf scheitert weiterhin', is_wp_error( $lauf2 ) );
$GLOBALS['t_kaputt_ueber'] = 0;
wl_tr_cache_leeren();
$GLOBALS['t_spiegel'] = false;
// Budget bleibt hoch: Der Zähler des Laufs summiert alle Aufrufe dieser Datei,
// ein knappes Budget würde Test 7 den Budget- statt den HTTP-Fehler liefern.
$opts['translate_max_chars'] = '20000'; $opts['translate_budget'] = '200'; update_option( 'warleek_options', $opts );

/* 7) API-Fehler: englische Fassung bleibt */
$GLOBALS['t_http_error'] = true;
$err = warleek_maybe_translate( 'Z', '<p>Hello</p>', 0 );
t_chk( 'api-fehler wird gemeldet', is_wp_error( $err ) );
$GLOBALS['t_http_error'] = false;

/* 8) Ohne Schlüssel passiert nichts */
$opts['anthropic_api_key'] = ''; update_option( 'warleek_options', $opts );
t_chk( 'ohne schlüssel aus', null === warleek_maybe_translate( 'A', '<p>B</p>', 0 ) );

wl_tr_cache_leeren();
wp_delete_post( $p, true );
update_option( 'warleek_options', $back );
echo $GLOBALS['wl_tr_fail'] ? $GLOBALS['wl_tr_fail'] . " FAILED\n" : "OK\n";
