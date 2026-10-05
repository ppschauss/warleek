<?php
/**
 * Hält der Änderungsverlauf fest, was sich an einem Eintrag ändert?
 *
 * Simuliert einen Patch, der den Preis eines Gegenstands anhebt: Werte
 * schreiben, Verlauf prüfen, Ausgangszustand wiederherstellen.
 *
 *   ./manage.sh wp eval-file wp-content/plugins/warleek-core/tests/item-verlauf-test.php
 */

$GLOBALS['wl_fehler'] = 0;
function wlv_pruef( $was, $bedingung, $detail = '' ) {
	if ( ! $bedingung ) { $GLOBALS['wl_fehler']++; }
	printf( "%s %-50s %s\n", $bedingung ? '  ok ' : '  !! ', $was, $detail );
}

$posts = get_posts( array( 'post_type' => 'item', 'post_status' => array( 'publish', 'future' ), 'numberposts' => 1, 'orderby' => 'title' ) );
if ( ! $posts ) { echo "Keine Einträge – Test übersprungen.\n"; return; }
$p  = $posts[0];
$id = $p->ID;
echo "Eintrag: {$p->post_title} (#$id)\n\n";

$vorher_verlauf = get_post_meta( $id, '_warleek_verlauf', true );
$vorher_preis   = (string) get_post_meta( $id, 'item_preis', true );

/* --- 1. Erstes Anlegen darf nichts protokollieren --- */
delete_post_meta( $id, '_warleek_verlauf' );
$n = warleek_item_verlauf_merken( $id, array( 'preis' => '', 'rolle' => '' ), array( 'preis' => '600', 'rolle' => 'Sturmgewehr' ) );
wlv_pruef( 'frisch angelegt: nichts protokolliert', 0 === $n && ! get_post_meta( $id, '_warleek_verlauf', true ) );

/* --- 2. Geänderter Wert wird festgehalten --- */
$n = warleek_item_verlauf_merken( $id, array( 'preis' => '600' ), array( 'preis' => '750' ) );
$v = (array) get_post_meta( $id, '_warleek_verlauf', true );
wlv_pruef( 'Änderung protokolliert', 1 === $n && 1 === count( $v ) );
$a = $v ? ( $v[0]['aenderungen'][0] ?? array() ) : array();
wlv_pruef( 'alter Wert erhalten', ( $a['alt'] ?? '' ) === '600', 'alt=' . ( $a['alt'] ?? '–' ) );
wlv_pruef( 'neuer Wert erhalten', ( $a['neu'] ?? '' ) === '750', 'neu=' . ( $a['neu'] ?? '–' ) );
wlv_pruef( 'Zeitstempel gesetzt', ! empty( $v[0]['zeit'] ) );

/* --- 3. Unveränderte Werte erzeugen keinen Eintrag --- */
$n = warleek_item_verlauf_merken( $id, array( 'preis' => '750' ), array( 'preis' => '750' ) );
wlv_pruef( 'gleicher Wert: kein Eintrag', 0 === $n && 1 === count( (array) get_post_meta( $id, '_warleek_verlauf', true ) ) );

/* --- 4. Mehrere Felder in einem Schritt --- */
warleek_item_verlauf_merken( $id, array( 'preis' => '750', 'rolle' => 'Sturmgewehr' ), array( 'preis' => '800', 'rolle' => 'Karabiner' ) );
$v = (array) get_post_meta( $id, '_warleek_verlauf', true );
wlv_pruef( 'zwei Felder in einem Schritt', 2 === count( end( $v )['aenderungen'] ?? array() ) );

/* --- 5. Die Anzeige erzeugt etwas Lesbares --- */
$html = warleek_render_item_verlauf( $id );
wlv_pruef( 'Abschnitt wird gerendert', str_contains( $html, 'wl-verlauf' ) );
wlv_pruef( 'alter Wert steht durchgestrichen drin', str_contains( $html, '<s>' ) );

/* --- 6. Deckel bei 20 Schritten --- */
for ( $i = 0; $i < 25; $i++ ) {
	warleek_item_verlauf_merken( $id, array( 'preis' => (string) ( 1000 + $i ) ), array( 'preis' => (string) ( 1001 + $i ) ) );
}
$v = (array) get_post_meta( $id, '_warleek_verlauf', true );
wlv_pruef( 'Deckel bei 20 Schritten', 20 === count( $v ), count( $v ) . ' Schritte' );

/* --- Aufräumen --- */
if ( $vorher_verlauf ) { update_post_meta( $id, '_warleek_verlauf', $vorher_verlauf ); } else { delete_post_meta( $id, '_warleek_verlauf' ); }
if ( '' !== $vorher_preis ) { update_post_meta( $id, 'item_preis', $vorher_preis ); }

echo $GLOBALS['wl_fehler'] ? "\n{$GLOBALS['wl_fehler']} Fehler\n" : "\nOK\n";
