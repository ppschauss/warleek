<?php
/**
 * Warleek — Bilder für die Datenbank einsammeln.
 *
 * Legt Dateien aus `content-src/items/img/` ins Plugin und trägt sie ins
 * Medien-Manifest ein. Die Zuordnung läuft über den Dateinamen:
 *
 *   wardogs-ak-74.webp   → Bild für den Eintrag mit diesem Slug
 *   typ-waffe.webp       → Ersatzbild für alle Einträge der Kategorie „waffe"
 *
 * Es werden **nur** Dateien übernommen, die zu einem vorhandenen Eintrag oder
 * einer vorhandenen Kategorie passen – so fällt ein Tippfehler im Namen sofort auf,
 * statt still nichts zu bewirken.
 *
 * Aufruf:  php bin/items-media.php [--dry-run]
 * Danach:  ./manage.sh seed
 */

$root  = dirname( __DIR__ );
$dry   = in_array( '--dry-run', $argv, true );
$src   = $root . '/content-src/items/img';
$items = json_decode( (string) file_get_contents( $root . '/plugin/warleek-core/content/_content/items.json' ), true );
$mpath = $root . '/plugin/warleek-core/content/MANIFEST.json';
$mani  = json_decode( (string) file_get_contents( $mpath ), true );
$imgd  = $root . '/plugin/warleek-core/content/img';

if ( ! is_array( $items ) || ! is_array( $mani ) ) { fwrite( STDERR, "items.json oder MANIFEST.json nicht lesbar\n" ); exit( 1 ); }
if ( ! is_dir( $src ) ) { echo "Noch keine Bilder in content-src/items/img/ – nichts zu tun.\n"; exit( 0 ); }

$slugs = array();
$typen = array();
foreach ( $items as $i ) { $slugs[ $i['slug'] ] = $i['title']; $typen[ $i['typ'] ] = true; }

$ok = 0; $warn = 0;
foreach ( glob( $src . '/*.{webp,png,jpg,jpeg}', GLOB_BRACE ) as $datei ) {
	$name = pathinfo( $datei, PATHINFO_FILENAME );
	$ext  = pathinfo( $datei, PATHINFO_EXTENSION );

	if ( isset( $slugs[ $name ] ) ) {
		$key = 'item-' . $name;
		$alt = $slugs[ $name ] . ' aus WARDOGS';
	} elseif ( str_starts_with( $name, 'typ-' ) && isset( $typen[ substr( $name, 4 ) ] ) ) {
		$key = 'item-typ-' . substr( $name, 4 );
		$alt = 'Symbolbild für die Kategorie ' . substr( $name, 4 );
	} else {
		fwrite( STDERR, "! $name.$ext passt zu keinem Eintrag und keiner Kategorie – übersprungen\n" );
		$warn++;
		continue;
	}

	$ziel = $key . '.' . $ext;
	if ( ! $dry ) { copy( $datei, $imgd . '/' . $ziel ); }
	$mani[ $ziel ] = array( 'alt' => $alt, 'use' => $key, 'size' => filesize( $datei ) );
	printf( "%-34s → %s\n", basename( $datei ), $key );
	$ok++;
}

echo "\n$ok Bilder übernommen, $warn ohne Zuordnung.\n";
if ( $dry ) { echo "Trockenlauf – nichts geschrieben.\n"; exit( 0 ); }
file_put_contents( $mpath, json_encode( $mani, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo "geschrieben: content/MANIFEST.json\n";
