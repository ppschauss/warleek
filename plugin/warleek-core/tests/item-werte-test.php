<?php
/*
 * Platzhalter im Fließtext – Standalone-Test ohne WordPress:
 *     docker run --rm -v $PWD/plugin/warleek-core:/c php:8.3-cli php /c/tests/item-werte-test.php
 *
 * Geprüft wird das, was auf der Seite schiefgehen kann: ein Platzhalter, der
 * stehen bleibt; ein Wert, der anders formatiert ist als im Datenblatt; eine
 * Differenz, die mit dem Vorzeichen kämpft. Die Stubs bilden nur nach, was
 * `warleek_werte_einsetzen()` wirklich anfasst.
 */
define( 'ABSPATH', __DIR__ );
define( 'OBJECT', 'OBJECT' );

$GLOBALS['wl_meta'] = array(
	5 => array( 'item_preis' => '350', 'item_rolle' => 'Kleiner Rucksack' ),
	7 => array( 'item_preis' => '650', 'item_rolle' => 'Mittlerer Rucksack' ),
	9 => array( 'item_preis' => '',    'item_rolle' => 'Ohne Preis' ),
);
$GLOBALS['wl_slugs'] = array(
	'wardogs-scout-backpack' => 5,
	'wardogs-field-backpack' => 7,
	'wardogs-ohne-preis'     => 9,
);
$GLOBALS['wl_titel'] = array( 5 => 'Scout-Rucksack', 7 => 'Field-Rucksack', 9 => 'Namenlos' );
$GLOBALS['wl_hier']  = 5;
$GLOBALS['wl_typ']   = 'item';

function warleek_item_felder() {
	return array(
		'preis' => array( 'Preis', 'dollar' ), 'freischaltung' => array( 'Freischaltung', 'text' ),
		'freischaltkosten' => array( 'Freischaltgebühr (einmalig)', 'dollar' ), 'kaliber' => array( 'Kaliber', 'text' ),
		'sitze' => array( 'Sitzplätze', 'zahl' ), 'tempo' => array( 'Höchstgeschwindigkeit', 'kmh' ),
		'baukosten' => array( 'Baukosten', 'build' ), 'rolle' => array( 'Rolle', 'text' ),
		'hinweis' => array( 'Hinweis', 'text' ),
	);
}
function warleek_item_wert( $key, $wert ) {
	$wert = trim( (string) $wert );
	if ( '' === $wert ) { return ''; }
	switch ( warleek_item_felder()[ $key ][1] ?? 'text' ) {
		case 'dollar': return is_numeric( $wert ) ? '$' . number_format( (float) $wert, 0, ',', '.' ) : $wert;
		case 'kmh':    return is_numeric( $wert ) ? number_format( (float) $wert, 0, ',', '.' ) . ' km/h' : $wert;
		case 'build':  return is_numeric( $wert ) ? number_format( (float) $wert, 0, ',', '.' ) . ' Build-Supplies' : $wert;
		default:       return $wert;
	}
}
function get_the_ID()            { return $GLOBALS['wl_hier']; }
function get_post_type( $id )    { return $GLOBALS['wl_typ']; }
function get_the_title( $id )    { return $GLOBALS['wl_titel'][ $id ] ?? ''; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['wl_meta'][ $id ][ $key ] ?? ''; }
function get_page_by_path( $slug, $out = null, $typ = null ) {
	$id = $GLOBALS['wl_slugs'][ $slug ] ?? 0;
	return $id ? (object) array( 'ID' => $id ) : null;
}
function esc_html( $v )  { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function mysql2date( $f, $d ) { return '03.12.2026'; }
function add_filter() {}
function add_shortcode() {}
function shortcode_atts( $a ) { return $a; }

require __DIR__ . '/../inc/item-werte.php';

$fail = 0;
function chk( $was, $ist, $soll ) {
	global $fail;
	if ( $ist === $soll ) { return; }
	$fail++;
	echo "FAIL  $was\n  ist:  " . var_export( $ist, true ) . "\n  soll: " . var_export( $soll, true ) . "\n";
}

chk( 'eigener Preis',        warleek_werte_einsetzen( '{preis}' ), '$350' );
chk( 'fremder Preis',        warleek_werte_einsetzen( '{preis:wardogs-field-backpack}' ), '$650' );
chk( 'Differenz',            warleek_werte_einsetzen( '{aufpreis:wardogs-field-backpack}' ), '$300' );
chk( 'Differenz ohne Vorzeichen, egal welche Richtung',
     warleek_werte_einsetzen( '{aufpreis:wardogs-scout-backpack}', 7 ), '$300' );
chk( 'Titel',                warleek_werte_einsetzen( '{titel:wardogs-field-backpack}' ), 'Field-Rucksack' );
chk( 'leeres Feld wird zum Strich', warleek_werte_einsetzen( '{kaliber}' ), '–' );
chk( 'unbekannter Platzhalter bleibt stehen', warleek_werte_einsetzen( '{foo}' ), '{foo}' );
chk( 'unbekannter Eintrag bleibt stehen',
     warleek_werte_einsetzen( '{preis:gibt-es-nicht}' ), '{preis:gibt-es-nicht}' );
chk( 'Differenz ohne Preis auf einer Seite',
     warleek_werte_einsetzen( '{aufpreis:wardogs-ohne-preis}' ), '–' );
chk( 'Text ohne Klammern bleibt unberührt',
     warleek_werte_einsetzen( 'Kostet nichts.' ), 'Kostet nichts.' );
chk( 'mehrere in einem Satz',
     warleek_werte_einsetzen( 'Hier {preis}, dort {preis:wardogs-field-backpack}.' ),
     'Hier $350, dort $650.' );

/*
 * Ein nackter Platzhalter in einem Guide hat keinen Bezug: Dort muss der Slug
 * dabeistehen, sonst stünde auf jeder Seite der Preis irgendeines Eintrags.
 */
$GLOBALS['wl_typ'] = 'guide';
chk( 'nackter Platzhalter nur auf einem Eintrag', warleek_werte_einsetzen( '{preis}' ), '{preis}' );
chk( 'mit Slug auch im Guide', warleek_werte_einsetzen( '{preis:wardogs-field-backpack}' ), '$650' );
$GLOBALS['wl_typ'] = 'item';

/* Formatierung muss die des Datenblatts sein – sonst steht im Text $350 und
   in der Tabelle 350. */
chk( 'gleiche Formatierung wie das Datenblatt',
     warleek_werte_einsetzen( '{preis}' ), warleek_item_wert( 'preis', '350' ) );

echo $fail ? "$fail FAILED\n" : "OK\n";
exit( $fail ? 1 : 0 );
