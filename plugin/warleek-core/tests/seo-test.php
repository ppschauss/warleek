<?php
/**
 * SEO-Muster: Patch-Note-Titel mit Datum, Kürzung, Versionsnummer, Brotkrumen.
 * Aufruf: wp eval-file wp-content/plugins/warleek-core/tests/seo-test.php
 */

$fail = 0;
function chk( $label, $got, $want ) {
	global $fail;
	if ( trim( (string) $got ) !== trim( (string) $want ) ) {
		$fail++;
		echo "FAIL $label\n want: $want\n got:  " . trim( (string) $got ) . "\n";
	}
}
function chk_true( $label, $cond ) {
	global $fail;
	if ( ! $cond ) { $fail++; echo "FAIL $label\n"; }
}

/* Versionsnummer aus Titeln ziehen */
chk( 'version 3-teilig', warleek_seo_patch_version( 'WARDOGS | Update 0.1.2 (Minor Patch)' ), '0.1.2' );
chk( 'version 2-teilig', warleek_seo_patch_version( 'SCHEDULED MAINTENANCE & PATCH 0.11' ), '0.11' );
chk( 'ohne version', warleek_seo_patch_version( 'Launch Stability Hotfix #1' ), '' );
chk( 'keine jahreszahl', warleek_seo_patch_version( 'Season 1 Changelog' ), '' );

/* Kürzung auf Suchergebnis-Länge */
$lang = str_repeat( 'Wardogs Guide auf Deutsch ', 20 );
$kurz = warleek_seo_kuerzen( $lang );
chk_true( 'kuerzen <= 158', mb_strlen( $kurz ) <= 158 );
chk_true( 'kuerzen endet mit auslassung', str_ends_with( $kurz, '…' ) );
chk_true( 'kuerzen schneidet an wortgrenze', ! str_contains( mb_substr( $kurz, -8, 6 ), '  ' ) );
chk( 'kurzer text bleibt', warleek_seo_kuerzen( 'Kurz und gut.' ), 'Kurz und gut.' );
chk( 'mehrfache leerzeichen weg', warleek_seo_kuerzen( "A   B\n C" ), 'A B C' );

/* Titel und Beschreibung einer Patch Note */
$pn = get_posts( array( 'post_type' => 'patchnote', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
if ( ! $pn ) { echo "ÜBERSPRUNGEN – keine Patch Notes vorhanden\n"; exit( 0 ); }
$p     = $pn[0];
$datum = get_the_date( 'd.m.Y', $p );

$titel = warleek_seo_patch_title( $p );
chk_true( 'titel nennt das datum', str_contains( $titel, $datum ) );
chk_true( 'titel nennt die sprache', str_contains( $titel, 'Deutsch' ) );
chk_true( 'titel nennt das spiel', str_starts_with( $titel, 'Wardogs' ) );
chk_true( 'titel passt in die suche', mb_strlen( $titel . ' | Warleek' ) <= 66 );

$desc = warleek_seo_patch_desc( $p );
chk_true( 'desc nennt das datum', str_contains( $desc, $datum ) );
chk_true( 'desc nennt die laender', str_contains( $desc, 'Österreich' ) && str_contains( $desc, 'Schweiz' ) );
chk_true( 'desc nicht zu lang', mb_strlen( $desc ) <= 165 );
chk_true( 'desc anders formuliert als titel', mb_substr( $desc, 0, 20 ) !== mb_substr( $titel, 0, 20 ) );

/* Ohne deutsche Kurzfassung darf kein englischer Text in die Beschreibung geraten */
$hatte = get_post_meta( $p->ID, '_warleek_summary', true );
delete_post_meta( $p->ID, '_warleek_summary' );
$ohne = warleek_seo_patch_desc( $p );
chk_true( 'ohne uebersetzung rein deutsch', ! preg_match( '/\b(the|and|will|update is)\b/i', $ohne ) );
if ( $hatte ) { update_post_meta( $p->ID, '_warleek_summary', $hatte ); }

/* Datum der jüngsten Patch Note für den Archivtitel */
chk( 'archivdatum', warleek_seo_latest_patch_date(), $datum );

/* Das Spiel als Schema-Objekt */
$spiel = warleek_seo_game_ld();
chk( 'spiel-typ', $spiel['@type'], 'VideoGame' );
chk( 'spiel-name', $spiel['name'], 'WARDOGS' );
chk_true( 'spiel verweist auf steam', str_contains( $spiel['sameAs'], 'store.steampowered.com' ) );

echo $fail ? "$fail FAILED\n" : "OK\n";
exit( $fail ? 1 : 0 );
