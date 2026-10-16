<?php
/**
 * Reddit: Beitragstext, Subreddit-Name, und dass nichts versehentlich rausgeht.
 *
 * Aufruf: wp eval-file wp-content/plugins/warleek-core/tests/reddit-test.php
 *
 * **Dieser Test stellt keine Netzanfrage.** Gepostet wird ausschließlich über
 * `warleek_reddit_submit()` mit `$probe = false`; hier läuft alles als Probe,
 * und zur Sicherheit fängt ein `pre_http_request` jeden Aufruf ab, der es doch
 * versuchen würde.
 *
 * Der Grund für den Test: Der Beitrag zu einem Datenbank-Eintrag bestand
 * anfangs aus einem Satz, einer Frage, die denselben Satz wiederholte, und
 * einem Link – 198 solcher Beiträge, einer pro Tag, wären in einem Spiele-Sub
 * zu Recht als Spam gelesen worden.
 */
// Über $GLOBALS, weil `wp eval-file` die Datei in einem Funktionsrahmen
// ausführt – ein `global` in der Prüffunktion träfe sonst eine andere Variable.
$GLOBALS['wl_red_fehler'] = 0;
$GLOBALS['wl_red_http']   = 0;
function r_chk( $label, $cond, $extra = '' ) {
	if ( ! $cond ) { $GLOBALS['wl_red_fehler']++; printf( "FAIL %-46s %s\n", $label, $extra ); }
}

add_filter( 'pre_http_request', function () {
	$GLOBALS['wl_red_http']++;
	return new WP_Error( 'wl_test', 'Im Test darf nichts ins Netz.' );
}, 1 );

/* 1) Subreddit-Name: nur ein führendes r/ fällt weg.
      `trim( $v, " /r" )` hätte aus „rust" „ust" gemacht. */
$alt = get_option( 'warleek_options', array() );
foreach ( array(
	'WardogsDeutsch'    => 'WardogsDeutsch',
	'r/WardogsDeutsch'  => 'WardogsDeutsch',
	'/r/WardogsDeutsch' => 'WardogsDeutsch',
	'rust'              => 'rust',
	'wardogsger'        => 'wardogsger',
) as $ein => $soll ) {
	$o               = $alt;
	$o['reddit_sub'] = $ein;
	update_option( 'warleek_options', $o );
	r_chk( 'subreddit „' . $ein . '"', $soll === warleek_reddit_sub(), 'bekommen: ' . warleek_reddit_sub() );
}
update_option( 'warleek_options', $alt );

/* 2) Wortstamm – nur grob, aber in beide Richtungen gleich. */
r_chk( 'stamm: einmalige → einmalig', 'einmalig' === warleek_reddit_stamm( 'einmalige' ) );
r_chk( 'stamm: kosten bleibt lang genug', 'kost' === warleek_reddit_stamm( 'kosten' ) );
r_chk( 'stamm: rolle → roll', 'roll' === warleek_reddit_stamm( 'rolle' ) );
// Untergrenze: Bliebe weniger als vier Zeichen übrig, wird nicht geschnitten –
// sonst träfe „lage" auf „lager" und der Test wäre ein Zufallsgenerator.
r_chk( 'stamm: zu kurzes wort bleibt ganz', 'lage' === warleek_reddit_stamm( 'lage' ) );
r_chk( 'stamm: preis unverändert', 'preis' === warleek_reddit_stamm( 'preis' ) );

/* 3) Sätze, die nur das Datenblatt wiederholen, fallen weg – der Rest bleibt. */
$werte = array(
	array( 'Preis', '$1.500' ),
	array( 'Freischaltung', 'Medic 15' ),
	array( 'Freischaltgebühr (einmalig)', '$75.000' ),
	array( 'Kaliber', '9 × 19 mm' ),
	array( 'Rolle', 'Maschinenpistole' ),
);
$antwort = 'MP5 – Maschinenpistole in WARDOGS. Kaliber 9 × 19 mm.'
	. ' Preis $1.500, Freischaltung: Medic 15, einmalige Freischaltgebühr $75.000.'
	. ' Stand: Oktober 2026 · Early Access.';
$kurz = warleek_reddit_ohne_werte( $antwort, $werte );
r_chk( 'einleitung bleibt', str_contains( $kurz, 'MP5 – Maschinenpistole in WARDOGS.' ), $kurz );
r_chk( 'stand bleibt', str_contains( $kurz, 'Stand: Oktober 2026' ), $kurz );
r_chk( 'kaliber-satz fällt weg', ! str_contains( $kurz, 'Kaliber 9' ), $kurz );
r_chk( 'preis-satz fällt weg', ! str_contains( $kurz, 'Preis $1.500,' ), $kurz );
// Ein Satz, der mehr sagt als die Tabelle, bleibt – sonst verschwände bei
// Startausrüstung der einzige Hinweis darauf.
$start = warleek_reddit_ohne_werte(
	'A-91 – Sturmgewehr in WARDOGS. Von Anfang an verfügbar – ohne Freischaltung und ohne Kosten.',
	array( array( 'Freischaltung', 'Startwaffe, keine Gebühr' ), array( 'Rolle', 'Sturmgewehr' ) )
);
r_chk( 'startsatz bleibt stehen', str_contains( $start, 'Von Anfang an verfügbar' ), $start );
// Notbremse: Würde alles gestrichen, bliebe der Text ganz.
$alles = warleek_reddit_ohne_werte( 'Rolle Sturmgewehr.', array( array( 'Rolle', 'Sturmgewehr' ) ) );
r_chk( 'nie ohne einleitung', '' !== trim( $alles ), $alles );

/* 4) Titelzusatz: ohne Präposition, siehe CLAUDE.md – „über mittlerer Hammer"
      wäre falsch dekliniert, und das Geschlecht steht nirgends in den Daten. */
r_chk( 'titel mit preis und stufe',
	' – $1.500, Freischaltung: Medic 15' === warleek_reddit_item_titelzusatz( $werte ),
	warleek_reddit_item_titelzusatz( $werte ) );
r_chk( 'titel nur preis',
	' – $250' === warleek_reddit_item_titelzusatz( array( array( 'Preis', '$250' ) ) ) );
r_chk( 'titel ohne zahlen bleibt neutral',
	' – Werte und Einordnung' === warleek_reddit_item_titelzusatz( array( array( 'Rolle', 'Bauwerk' ) ) ) );
r_chk( 'baukosten zählen als kernwert',
	' – 121 Build-Supplies' === warleek_reddit_item_titelzusatz( array( array( 'Baukosten', '121 Build-Supplies' ) ) ) );

/* 5) Der echte Beitrag zu einem Datenbank-Eintrag. */
$items = get_posts( array( 'post_type' => 'item', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
$mit   = 0;
foreach ( $items as $id ) {
	if ( '' !== trim( (string) get_post_meta( $id, 'item_preis', true ) ) ) { $mit = $id; break; }
}
if ( $mit ) {
	$t = warleek_reddit_text( $mit );
	r_chk( 'eintrag: text entsteht', ! is_wp_error( $t ), is_wp_error( $t ) ? $t->get_error_message() : '' );
	if ( ! is_wp_error( $t ) ) {
		r_chk( 'eintrag: datenblatt im beitrag', str_contains( $t['text'], '| Angabe | Wert |' ) );
		r_chk( 'eintrag: trenner steht frei', str_contains( $t['text'], "|\n\n---" ) || str_contains( $t['text'], "\n\n---" ), $t['text'] );
		r_chk( 'eintrag: link am ende', str_contains( $t['text'], get_permalink( $mit ) ) );
		// Ein Eintrag bekommt keinen FAQ-Abschnitt: Seine Fragen entstehen aus
		// denselben Werten, die jetzt in der Tabelle stehen.
		$nach = substr( $t['text'], (int) strpos( $t['text'], '| Angabe' ) );
		$nach = substr( $nach, 0, (int) strpos( $nach, '---' ) );
		r_chk( 'eintrag: keine fragen nach der tabelle', ! str_contains( $nach, '?**' ), $nach );
		r_chk( 'eintrag: titel trägt den preis', str_contains( $t['titel'], '$' ), $t['titel'] );
	}
} else {
	echo "HINWEIS: kein veröffentlichter Eintrag mit Preis – Abschnitt 5 übersprungen.\n";
}

/* 6) Beim Guide bleibt das FAQ stehen – das ist geschriebener Text. */
$guides = get_posts( array( 'post_type' => 'guide', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
$gefq   = 0;
foreach ( $guides as $id ) {
	$a = warleek_aeo_extract( (string) get_post_field( 'post_content', $id, 'raw' ) );
	if ( count( (array) $a['faq'] ) >= 2 ) { $gefq = $id; break; }
}
if ( $gefq ) {
	$t = warleek_reddit_text( $gefq );
	r_chk( 'guide: fragen bleiben', ! is_wp_error( $t ) && substr_count( $t['text'], '?**' ) >= 1,
		is_wp_error( $t ) ? $t->get_error_message() : $t['text'] );
	r_chk( 'guide: kein datenblatt', ! is_wp_error( $t ) && ! str_contains( $t['text'], '| Angabe | Wert |' ) );
	r_chk( 'guide: quellenlage steht drin', ! is_wp_error( $t ) && str_contains( $t['text'], 'Langfassung mit allen Quellen' ) );
} else {
	echo "HINWEIS: kein Guide mit zwei FAQ-Paaren – Abschnitt 6 übersprungen.\n";
}

/* 7) Die Probe sendet nicht, und ohne Zugangsdaten geht gar nichts. */
$probe = warleek_reddit_submit( 'Titel', 'Text', true );
r_chk( 'probe sendet nicht', is_array( $probe ) && ! empty( $probe['probe'] ) );
r_chk( 'probe nennt den sub', is_array( $probe ) && isset( $probe['sub'] ) );
$leer = warleek_reddit_submit( '', 'Text', true );
r_chk( 'leerer titel wird abgelehnt', is_wp_error( $leer ) );

/* 8) Ein zu langer Titel wird gekürzt, nicht von Reddit abgelehnt. */
$lang = warleek_reddit_submit( str_repeat( 'Wardogs ', 60 ), 'Text', true );
r_chk( 'titel auf 300 zeichen gekürzt', is_array( $lang ) && mb_strlen( $lang['titel'] ) <= 300,
	is_array( $lang ) ? mb_strlen( $lang['titel'] ) : 'Fehler' );

/* 9) Nichts ist ins Netz gegangen. */
r_chk( 'keine netzanfrage', 0 === $GLOBALS['wl_red_http'], $GLOBALS['wl_red_http'] . ' Anfragen' );

echo $GLOBALS['wl_red_fehler'] ? $GLOBALS['wl_red_fehler'] . " FAILED\n" : "OK\n";
