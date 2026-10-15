<?php
/**
 * Prüfstand: Formular ↔ Freitext, und der Filter in der Liste.
 *
 * Aufruf: wp eval-file wp-content/plugins/warleek-core/tests/pruefstand-test.php
 *
 * Zwei Dinge, die auseinanderlaufen können und es nicht dürfen:
 *  1. Auswahl + Zusatz → Text → zurück zu Auswahl + Zusatz (Formular-Umlauf).
 *  2. Der reguläre Ausdruck der Listenfilterung und `warleek_herkunft_pruefstand()`
 *     müssen dieselben Werte als „offen" einstufen. Der Filter fragt die
 *     Datenbank, die Anzeige liest PHP – zwei Wege zu derselben Frage.
 */
// Über $GLOBALS, weil `wp eval-file` die Datei in einem Funktionsrahmen
// ausführt: Ein `global $fehler` in der Prüffunktion und ein `$fehler` hier
// wären zwei verschiedene Variablen – der Test hätte am Ende immer „OK"
// gemeldet, auch mit Fehlern darüber.
$GLOBALS['wl_pruef_fehler'] = 0;
function p_chk( $label, $cond, $extra = '' ) {
	if ( ! $cond ) { $GLOBALS['wl_pruef_fehler']++; printf( "FAIL %-44s %s\n", $label, $extra ); }
}

/* 1) Umlauf durch das Formular */
$faelle = array(
	array( 'aus-quellen',         '' ),
	array( 'aus-quellen',         'Preis unklar' ),
	array( 'im-spiel-beobachtet', '' ),
	array( 'im-spiel-beobachtet', '14.10.2026' ),
	array( 'im-spiel-geprueft',   '15.10.2026, AdminLeek' ),
	array( 'im-spiel-geprueft',   'Preis am Händler abgelesen' ),
);
foreach ( $faelle as $f ) {
	$text = warleek_item_pruefstand_bauen( $f[0], $f[1], '15.10.2026' );
	$zur  = warleek_item_pruefstand_teile( $text );
	p_chk( 'umlauf status: ' . $f[0] . ' / ' . $f[1], $zur['status'] === $f[0], "gespeichert: \"$text\" → {$zur['status']}" );
	p_chk( 'umlauf zusatz: ' . $f[0] . ' / ' . $f[1], $zur['detail'] === $f[1], "gespeichert: \"$text\" → \"{$zur['detail']}\"" );
}

/* 2) „geprüft" ohne Angabe bekommt ein Datum – ein Prüfvermerk ohne Zeitpunkt
      behauptet mehr, als er belegt. */
p_chk( 'geprüft ohne zusatz bekommt datum', '15.10.2026' === warleek_item_pruefstand_bauen( 'im-spiel-geprueft', '', '15.10.2026' ) );

/* 3) Ein Zusatz, der den Stand umdeuten würde, wird entschärft */
$heikel = warleek_item_pruefstand_bauen( 'im-spiel-geprueft', 'nein, nur Teilwerte', '15.10.2026' );
p_chk( 'heikler zusatz bleibt geprüft', 'im-spiel-geprueft' === warleek_herkunft_pruefstand( $heikel )['status'], $heikel );
// Ein Zusatz, der „beobachtet" enthält, setzt den Stand herab – Absicht: Der
// Text gewinnt, und „beobachtet" ist die vorsichtigere Aussage. Das Formular
// zeigt diese Lesart unter dem Feld an, damit sie nicht überrascht.
$heikel2 = warleek_item_pruefstand_bauen( 'im-spiel-geprueft', 'im Spiel beobachtet und gemessen', '15.10.2026' );
p_chk( 'zusatz mit „beobachtet" setzt herab', 'im-spiel-beobachtet' === warleek_herkunft_pruefstand( $heikel2 )['status'], $heikel2 );

/* 4) Filter-Regex und Anzeige sind sich einig */
$proben = array(
	'', 'nein', 'nein – angekündigt für Season 2', 'noch nicht geprüft', '  nein',
	'15.10.2026, AdminLeek', 'Im Spiel beobachtet, 14.10.2026', 'Preis am Händler abgelesen',
);
$regex = '/' . str_replace( '[[:space:]]', '\s', warleek_pruefstand_regex_offen() ) . '/i';
foreach ( $proben as $wert ) {
	$offen_php   = 'aus-quellen' === warleek_herkunft_pruefstand( $wert )['status'];
	$offen_regex = (bool) preg_match( $regex, $wert );
	p_chk( 'filter einig über: "' . $wert . '"', $offen_php === $offen_regex, $offen_php ? 'PHP: offen' : 'PHP: geprüft' );
}

/* 5) Die Auswahlliste deckt genau die Stände des Herkunftsnachweises ab */
p_chk( 'auswahl deckt alle stände', array( 'aus-quellen', 'im-spiel-beobachtet', 'im-spiel-geprueft' ) === array_keys( warleek_pruefstaende() ) );

echo $GLOBALS['wl_pruef_fehler'] ? $GLOBALS['wl_pruef_fehler'] . " FAILED\n" : "OK\n";
