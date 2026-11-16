<?php
// Standalone-Test ohne WordPress: php tests/ads-test.php
define( 'ABSPATH', __DIR__ );

$GLOBALS['wl_opt'] = array(
	'ads_enabled'   => '1',
	'ads_client'    => 'ca-pub-5566154579483105',
	'ads_slot_text' => '1234567890',
	'ads_slot_faq'  => '9876543210',
);
function warleek_opt( $k, $d = '' ) { return $GLOBALS['wl_opt'][ $k ] ?? $d; }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function add_action() {}
function add_filter() {}
function is_admin() { return false; }
function is_feed() { return false; }
function is_embed() { return false; }
function is_404() { return false; }

require __DIR__ . '/../inc/ads.php';

$fail = 0;
function chk( $label, $got, $want ) {
	global $fail;
	if ( $got !== $want ) {
		$fail++;
		echo "FAIL $label\n want: " . var_export( $want, true ) . "\n got:  " . var_export( $got, true ) . "\n";
	}
}

/* ------------------------------------------------------------- Kennung */
chk( 'kennung gültig', warleek_ads_client(), 'ca-pub-5566154579483105' );
$GLOBALS['wl_opt']['ads_client'] = 'pub-5566154579483105';          // falsches Präfix
chk( 'kennung ohne ca- wird verworfen', warleek_ads_client(), '' );
chk( 'ohne Kennung nicht aktiv', warleek_ads_aktiv(), false );
$GLOBALS['wl_opt']['ads_client'] = 'ca-pub-55661545794831051234567890';   // zu lang
chk( 'überlange Kennung wird verworfen', warleek_ads_client(), '' );
$GLOBALS['wl_opt']['ads_client'] = 'ca-pub-5566154579483105';
chk( 'mit Kennung aktiv', warleek_ads_aktiv(), true );
$GLOBALS['wl_opt']['ads_enabled'] = '0';
chk( 'ausgeschaltet schlägt Kennung', warleek_ads_aktiv(), false );
$GLOBALS['wl_opt']['ads_enabled'] = '1';

/* ------------------------------------------------------------ Einheit */
chk( 'ohne Platz-Kennung nichts', warleek_ads_einheit( '' ), '' );
chk( 'nichtnumerische Kennung nichts', warleek_ads_einheit( 'abc' ), '' );
$e = warleek_ads_einheit( '12-34 56', 'wl-x' );
chk( 'Ziffern werden herausgelöst', (bool) strpos( $e, 'data-ad-slot="123456"' ), true );
// Die Beschriftung ist Pflicht und muss im Markup stehen, nicht im Stylesheet.
chk( 'Beschriftung im Markup', (bool) strpos( $e, '>Anzeige</span>' ), true );
chk( 'als aside ausgezeichnet', str_starts_with( $e, '<aside class="wl-anzeige wl-x"' ), true );
chk( 'Konto-Kennung im Markup', (bool) strpos( $e, 'data-ad-client="ca-pub-5566154579483105"' ), true );

/* ----------------------------------------------------------- Punkte */
$h2 = function ( $t ) { return "<h2>$t</h2>" . str_repeat( '<p>Fülltext zur Längenprüfung.</p>', 30 ); };

// Fünf Abschnitte, dazu häufige Fragen: zwei Plätze erwartet.
$lang = $h2( 'Eins' ) . $h2( 'Zwei' ) . $h2( 'Drei' ) . $h2( 'Vier' ) . '<h2>Häufige Fragen</h2><h3>Warum?</h3><p>Darum.</p>';
$p    = warleek_ads_punkte( $lang );
chk( 'zwei Plätze im langen Guide', count( $p ), 2 );
chk( 'erster Platz vor der dritten Überschrift', substr( $lang, $p[0], 13 ), '<h2>Drei</h2>' );
chk( 'zweiter Platz vor den häufigen Fragen', substr( $lang, $p[1], 24 ), '<h2>Häufige Fragen</h2>' );
chk( 'Plätze aufsteigend', $p[0] < $p[1], true );

// Die Anzeige darf nie *in* den FAQ-Abschnitt rutschen – dort schneidet sie
// laut inc/aeo.php eine Antwort ab.
$faq_start = strpos( $lang, '<h2>Häufige Fragen</h2>' );
chk( 'kein Platz innerhalb der FAQ', $p[1] <= $faq_start, true );

// Kurzer Guide: dritte Überschrift und FAQ liegen dicht beieinander, dann
// fällt der zweite Platz weg – zwei Anzeigen hintereinander sind eine Wand.
$kurz = '<h2>A</h2><p>x</p><h2>B</h2><p>x</p><h2>C</h2><p>x</p><h2>Häufige Fragen</h2>';
chk( 'dichte Plätze werden zu einem', count( warleek_ads_punkte( $kurz ) ), 1 );

// Zu wenig Überschriften: nur die FAQ bleibt.
$duenn = '<h2>Nur eine</h2>' . str_repeat( '<p>x</p>', 50 ) . '<h2>FAQ</h2>';
chk( 'ohne dritte Überschrift nur die FAQ', count( warleek_ads_punkte( $duenn ) ), 1 );

chk( 'ganz ohne Überschriften kein Platz', warleek_ads_punkte( '<p>nur Text</p>' ), array() );

// Überschrift mit Attributen muss trotzdem gefunden werden. Der dritte
// Abschnitt ist hier bewusst lang, sonst greift der Mindestabstand und der
// zweite Platz fiele weg – geprüft werden soll aber das Erkennen.
$weit = $h2( 'Eins' ) . $h2( 'Zwei' ) . '<h2>Drei</h2>' . str_repeat( '<p>Fülltext zur Längenprüfung.</p>', 60 )
	. '<h2 id="faq" class="wp-block-heading">Häufige Fragen</h2>';
$pa   = warleek_ads_punkte( $weit );
chk( 'zwei Plätze bei weitem Abstand', count( $pa ), 2 );
chk( 'FAQ-Überschrift mit Attributen erkannt', substr( $weit, $pa[1], 8 ), '<h2 id="' );

/* ------------------------------------------------------------ ads.txt */
chk( 'ads.txt-Zeile', warleek_ads_txt_inhalt(), "google.com, pub-5566154579483105, DIRECT, f08c47fec0942fa0\n" );

// Regression: Die Datei hing zuerst an `ads_enabled`. AdSense prueft damit
// aber die Inhaberschaft, und zwar bevor man Anzeigen ueberhaupt einschalten
// kann - die Pruefung scheiterte deshalb zwangslaeufig.
$GLOBALS['wl_opt']['ads_enabled'] = '0';
chk( 'ads.txt auch bei ausgeschalteten Anzeigen', warleek_ads_txt_inhalt(), "google.com, pub-5566154579483105, DIRECT, f08c47fec0942fa0\n" );
$GLOBALS['wl_opt']['ads_enabled'] = '1';

$GLOBALS['wl_opt']['ads_client'] = '';
chk( 'ohne Kennung keine ads.txt', warleek_ads_txt_inhalt(), '' );
$GLOBALS['wl_opt']['ads_client'] = 'ca-pub-5566154579483105';

echo $fail ? "$fail FAILED\n" : "OK\n";
exit( $fail ? 1 : 0 );
