<?php
/**
 * Warleek — Bilder für die Datenbank einsammeln.
 *
 * Legt Dateien aus `content-src/items/img/` ins Plugin und trägt sie ins
 * Medien-Manifest ein. Die Zuordnung läuft über den Dateinamen:
 *
 *   wardogs-ak-74.webp     → Bild für den Eintrag mit diesem Slug
 *   gruppe-sturmgewehr.webp → Ersatzbild für alle Einträge dieser Gruppe
 *   typ-waffe.webp         → letzter Rückfall für die Kategorie „waffe"
 *
 * Eine optionale `credits.json` im selben Ordner setzt pro Datei (Schlüssel = Dateiname
 * ohne Endung) einen eigenen `alt`-Text und eine `credit`-Zeile – die Bildquelle, die
 * unter dem Eintrag ausgewiesen wird. Ohne Quelle kein fremdes Bild.
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
$cred  = file_exists( $src . '/credits.json' ) ? json_decode( (string) file_get_contents( $src . '/credits.json' ), true ) : array();
if ( ! is_array( $cred ) ) { fwrite( STDERR, "credits.json ist kein gültiges JSON\n" ); exit( 1 ); }

if ( ! is_array( $items ) || ! is_array( $mani ) ) { fwrite( STDERR, "items.json oder MANIFEST.json nicht lesbar\n" ); exit( 1 ); }
if ( ! is_dir( $src ) ) { echo "Noch keine Bilder in content-src/items/img/ – nichts zu tun.\n"; exit( 0 ); }

$slugs   = array();
$typen   = array();
$gruppen = array();
foreach ( $items as $i ) {
	$slugs[ $i['slug'] ] = $i['title'];
	$typen[ $i['typ'] ] = true;
	if ( ! empty( $i['gruppe'] ) ) { $gruppen[ $i['gruppe'] ] = true; }
}

$ok = 0; $warn = 0;
foreach ( glob( $src . '/*.{webp,png,jpg,jpeg}', GLOB_BRACE ) as $datei ) {
	$name = pathinfo( $datei, PATHINFO_FILENAME );
	$ext  = pathinfo( $datei, PATHINFO_EXTENSION );

	if ( isset( $slugs[ $name ] ) ) {
		$key = 'item-' . $name;
		$alt = $slugs[ $name ] . ' aus WARDOGS';
	} elseif ( str_starts_with( $name, 'gruppe-' ) && isset( $gruppen[ substr( $name, 7 ) ] ) ) {
		$key = 'item-gruppe-' . substr( $name, 7 );
		$alt = 'Symbolbild für die Gruppe ' . substr( $name, 7 );
	} elseif ( str_starts_with( $name, 'typ-' ) && isset( $typen[ substr( $name, 4 ) ] ) ) {
		$key = 'item-typ-' . substr( $name, 4 );
		$alt = 'Symbolbild für die Kategorie ' . substr( $name, 4 );
	} else {
		fwrite( STDERR, "! $name.$ext passt zu keinem Eintrag, keiner Gruppe und keiner Kategorie – übersprungen\n" );
		$warn++;
		continue;
	}

	$info = isset( $cred[ $name ] ) && is_array( $cred[ $name ] ) ? $cred[ $name ] : array();
	if ( ! empty( $info['alt'] ) ) { $alt = (string) $info['alt']; }

	$ziel  = $key . '.' . $ext;
	if ( ! $dry ) { copy( $datei, $imgd . '/' . $ziel ); }
	$eintrag = array( 'alt' => $alt, 'use' => $key, 'size' => filesize( $datei ) );
	if ( ! empty( $info['credit'] ) ) { $eintrag['credit'] = (string) $info['credit']; }
	$mani[ $ziel ] = $eintrag;
	printf( "%-34s → %-26s %s\n", basename( $datei ), $key, empty( $info['credit'] ) ? '(ohne Quellenangabe)' : $info['credit'] );
	$ok++;
}

echo "\n$ok Bilder übernommen, $warn ohne Zuordnung.\n";
if ( $dry ) { echo "Trockenlauf – nichts geschrieben.\n"; exit( 0 ); }
file_put_contents( $mpath, json_encode( $mani, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
echo "geschrieben: content/MANIFEST.json\n";
