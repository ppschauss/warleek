<?php
/**
 * Überleben Änderungen aus dem Backend einen Seed-Lauf?
 *
 * Ändert an einem Eintrag und einer Seite SEO-Titel, Beitragstitel und einen
 * Datenblatt-Wert, lässt den Schritt laufen und vergleicht. Danach dasselbe mit
 * `--force`: dann muss der Seed gewinnen.
 *
 * Braucht WordPress und eine eingespielte Installation:
 *   ./manage.sh wp eval-file wp-content/plugins/warleek-core/tests/seed-schutz-test.php
 *
 * Der Test stellt den Ausgangszustand am Ende wieder her.
 */
$GLOBALS['wl_fehler'] = 0;
function pruef( $was, $ist, $soll ) {
	$ok = ( $ist === $soll );
	if ( ! $ok ) { $GLOBALS['wl_fehler']++; }
	printf( "%s %-46s %s\n", $ok ? '  ok ' : '  !! ', $was, $ok ? '' : "ist: \"$ist\"  soll: \"$soll\"" );
}

$item = get_posts( array( 'post_type' => 'item', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'title' ) )[0];
$seite = get_posts( array( 'post_type' => 'page', 'name' => 'about-us', 'numberposts' => 1 ) );
$seite = $seite ? $seite[0] : null;

echo "Eintrag: {$item->post_title} (#{$item->ID})\n";

// --- Im Backend ändern ---
$neuer_seo   = 'VON HAND GESETZT – nicht überschreiben';
$neuer_titel = $item->post_title . ' (redaktionell umbenannt)';
update_post_meta( $item->ID, '_warleek_seo_title', $neuer_seo );
update_post_meta( $item->ID, 'rank_math_title', $neuer_seo );
update_post_meta( $item->ID, 'item_preis', '99999' );
wp_update_post( array( 'ID' => $item->ID, 'post_title' => $neuer_titel ) );
if ( $seite ) {
	update_post_meta( $seite->ID, '_warleek_seo_desc', 'Eigene Beschreibung der Seite' );
}

// --- Seed laufen lassen, ohne force ---
echo "\n--- Seed ohne force ---\n";
$r = warleek_run_step( 'items' );
echo '  ' . $r['msg'] . "\n";
if ( $seite ) { warleek_run_step( 'pages' ); }

// --- Vergleichen ---
echo "\n--- hat es gehalten? ---\n";
pruef( 'SEO-Titel des Eintrags',   (string) get_post_meta( $item->ID, '_warleek_seo_title', true ), $neuer_seo );
pruef( 'rank_math_title',          (string) get_post_meta( $item->ID, 'rank_math_title', true ), $neuer_seo );
pruef( 'Beitragstitel',            (string) get_post_field( 'post_title', $item->ID ), $neuer_titel );
pruef( 'Datenblatt-Wert (Preis)',  (string) get_post_meta( $item->ID, 'item_preis', true ), '99999' );
if ( $seite ) { pruef( 'SEO-Beschreibung der Seite', (string) get_post_meta( $seite->ID, '_warleek_seo_desc', true ), 'Eigene Beschreibung der Seite' ); }

// --- Und mit force? Dann muss der Seed gewinnen ---
echo "\n--- Seed MIT force (soll überschreiben) ---\n";
warleek_run_step( 'items', true );
$nach = (string) get_post_meta( $item->ID, '_warleek_seo_title', true );
printf( "%s SEO-Titel nach force: %s\n", $nach !== $neuer_seo ? '  ok ' : '  !! ', $nach );
if ( $nach === $neuer_seo ) { $GLOBALS['wl_fehler']++; }
$preis = (string) get_post_meta( $item->ID, 'item_preis', true );
printf( "%s Preis nach force: %s\n", '99999' !== $preis ? '  ok ' : '  !! ', $preis ?: '(leer)' );
if ( '99999' === $preis ) { $GLOBALS['wl_fehler']++; }

echo $GLOBALS['wl_fehler'] ? "\n{$GLOBALS['wl_fehler']} Fehler\n" : "\nOK – Backend-Änderungen überleben, force überschreibt.\n";

// --- Ausgangszustand wiederherstellen ---
wp_update_post( array( 'ID' => $item->ID, 'post_title' => $item->post_title ) );
delete_post_meta( $item->ID, '_warleek_seed_titel' );
warleek_run_step( 'items', true );
if ( $seite ) { warleek_run_step( 'pages', true ); }
echo "Ausgangszustand wiederhergestellt.\n";
