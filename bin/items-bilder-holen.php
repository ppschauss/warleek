<?php
/**
 * Warleek — Gegenstandsbilder von wardogshub.uk holen.
 *
 * Die Symbole dort sind **keine** Eigenleistung der Seite. Deren eigene
 * Credits-Seite (<https://wardogshub.uk/en/image-credits/>) sagt es selbst:
 *
 *   „Game interface elements are © BULKHEAD / Team17, used unchanged as
 *    reference marks … all game material belongs to its rights holders."
 *
 * Es sind also Spiel-Assets von BULKHEAD/Team17, die wardogshub.uk ebenso
 * unverändert als Referenz verwendet wie wir. Die Rechtsgrundlage ist bei uns
 * dieselbe wie dort: redaktionelle Berichterstattung über das Spiel, Bilder
 * unverändert, Rechteinhaber genannt. Jedes übernommene Bild bekommt über
 * `credits.json` die Zeile „Spiel-Asset · © BULKHEAD / Team17", die unter dem
 * Eintrag im Kasten „Woher die Angaben stammen" erscheint.
 *
 * Fair bleiben heißt hier: eine Anfrage pro Sekunde, eigene Kennung im
 * User-Agent, und die Dateien danach selbst ausliefern statt fremde Server zu
 * belasten. robots.txt der Seite erlaubt das Lesen ausdrücklich.
 *
 * Wenn BULKHEAD/Team17 oder wardogshub.uk widersprechen: `--entfernen` löscht
 * alles wieder, danach `./manage.sh seed` – die Einträge fallen auf die
 * Gruppenbilder aus dem offiziellen Pressematerial zurück.
 *
 * Aufruf:
 *   php bin/items-bilder-holen.php --dry-run   zeigt die Zuordnung
 *   php bin/items-bilder-holen.php             lädt und legt sie ab
 *   php bin/items-bilder-holen.php --entfernen löscht die übernommenen Bilder
 * Danach:
 *   php bin/items-media.php && ./manage.sh seed
 */

$root = dirname( __DIR__ );
$dry  = in_array( '--dry-run', $argv, true );
$weg  = in_array( '--entfernen', $argv, true );
$ziel = $root . '/content-src/items/img';
$ua   = 'WarleekBot/1.0 (+https://warleek.de; Kontakt info@patrickschauss.de)';
$basis = 'https://wardogshub.uk';
$credit = 'Spiel-Asset · © BULKHEAD / Team17';

$items = json_decode( (string) file_get_contents( $root . '/plugin/warleek-core/content/_content/items.json' ), true );
if ( ! is_array( $items ) ) { fwrite( STDERR, "items.json nicht lesbar\n" ); exit( 1 ); }

/* ------------------------------------------------------------ Entfernen */
if ( $weg ) {
	$n = 0;
	foreach ( $items as $i ) {
		foreach ( array( $ziel . '/' . $i['slug'] . '.webp', $ziel . '/' . $i['slug'] . '.png' ) as $d ) {
			if ( file_exists( $d ) ) { unlink( $d ); $n++; }
		}
		$p = $root . '/plugin/warleek-core/content/img/item-' . $i['slug'] . '.webp';
		if ( file_exists( $p ) ) { unlink( $p ); $n++; }
	}
	$cred = $ziel . '/credits.json';
	$c    = json_decode( (string) @file_get_contents( $cred ), true ) ?: array();
	foreach ( array_keys( $c ) as $k ) { if ( ! str_starts_with( $k, 'gruppe-' ) && ! str_starts_with( $k, 'typ-' ) ) { unset( $c[ $k ] ); } }
	file_put_contents( $cred, json_encode( $c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	echo "$n Dateien gelöscht. Jetzt: php bin/items-media.php && ./manage.sh seed\n";
	exit( 0 );
}

/* ------------------------------------------------------------ Zuordnung */
function wl_norm( $s ) {
	$s = mb_strtolower( html_entity_decode( $s, ENT_QUOTES, 'UTF-8' ) );
	$s = strtr( $s, array( 'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss' ) );
	return preg_replace( '/[^a-z0-9]/', '', $s );
}

function wl_hole( $url, $ua ) {
	$ctx = stream_context_create( array( 'http' => array( 'header' => "User-Agent: $ua\r\n", 'timeout' => 30 ) ) );
	return @file_get_contents( $url, false, $ctx );
}

// Deutsche Bezeichnungen, die drüben englisch heißen.
$hand = array(
	'wardogs-l81-moerser'     => 'l81mortar',
	'wardogs-recon-zelt'      => 'recontent',
	'wardogs-stacheldraht'    => 'barbedwire',
	'wardogs-sandsaecke'      => 'sandbagwall',
	'wardogs-bremer-wall'     => 'bremmerwall',
	'wardogs-hesco-block'     => 'hescoblocksmall',
	'wardogs-tuer'            => 'door',
	'wardogs-hesco-wand'      => 'hescowall',
	'wardogs-tor'             => 'gate',
	'wardogs-recon-turm'      => 'recontower',
	'wardogs-tankstation'     => 'refuelstation',
	'wardogs-reparaturstation'=> 'repairstation',
);

$fremd = array();
foreach ( array( 'weapons', 'vehicles', 'emplaced-weapons', 'building' ) as $k ) {
	$html = wl_hole( "$basis/en/database/$k/", $ua );
	if ( false === $html ) { fwrite( STDERR, "! Liste $k nicht erreichbar\n" ); continue; }
	if ( preg_match_all( '#src="(/assets/img/gear/[^"]+)"#', $html, $m ) ) {
		foreach ( array_unique( $m[1] ) as $pfad ) {
			$rein  = explode( '?', $pfad )[0];
			$datei = pathinfo( $rein, PATHINFO_FILENAME );
			$fremd[ wl_norm( $datei ) ] = $rein;
		}
	}
	sleep( 1 );
}
printf( "%d Symbole in den Listen gefunden\n\n", count( $fremd ) );

$plan = array();
$ohne = array();
foreach ( $items as $i ) {
	$kurz   = str_replace( 'wardogs-', '', $i['slug'] );
	$kandidaten = array();
	if ( isset( $hand[ $i['slug'] ] ) ) { $kandidaten[] = $hand[ $i['slug'] ]; }
	$kandidaten[] = wl_norm( $i['title'] );
	$kandidaten[] = wl_norm( $kurz );
	$kandidaten[] = wl_norm( explode( '[', $i['title'] )[0] );
	foreach ( $kandidaten as $k ) {
		if ( isset( $fremd[ $k ] ) ) { $plan[ $i['slug'] ] = array( 'pfad' => $fremd[ $k ], 'titel' => $i['title'] ); continue 2; }
	}
	$ohne[] = $i['title'];
}

printf( "%d von %d Einträgen zugeordnet\n", count( $plan ), count( $items ) );
if ( $ohne ) { printf( "ohne Symbol (behalten das Gruppenbild): %s\n", implode( ', ', $ohne ) ); }
if ( $dry ) {
	echo "\nTrockenlauf – nichts geladen. Ohne --dry-run erneut aufrufen.\n";
	exit( 0 );
}

/* ------------------------------------------------------------ Laden */
if ( ! is_dir( $ziel ) ) { mkdir( $ziel, 0775, true ); }
$cred = $ziel . '/credits.json';
$c    = json_decode( (string) @file_get_contents( $cred ), true ) ?: array();

$ok = 0; $fehl = 0;
foreach ( $plan as $slug => $e ) {
	$daten = wl_hole( $basis . $e['pfad'], $ua );
	if ( false === $daten || strlen( $daten ) < 200 ) { fwrite( STDERR, "! $slug nicht geladen\n" ); $fehl++; sleep( 1 ); continue; }

	$tmp = sys_get_temp_dir() . '/wl-' . $slug . '.png';
	file_put_contents( $tmp, $daten );
	$bild = @imagecreatefrompng( $tmp );
	if ( $bild ) {
		imagepalettetotruecolor( $bild );
		imagealphablending( $bild, false );
		imagesavealpha( $bild, true );
		imagewebp( $bild, $ziel . '/' . $slug . '.webp', 86 );
		imagedestroy( $bild );
		unlink( $tmp );
	} else {
		rename( $tmp, $ziel . '/' . $slug . '.png' );
	}

	$c[ $slug ] = array(
		'alt'    => $e['titel'] . ' aus WARDOGS – Symbol aus dem Spiel',
		'credit' => $credit,
	);
	$ok++;
	printf( "  %-28s %s\n", $slug, basename( $e['pfad'] ) );
	sleep( 1 );   // eine Anfrage pro Sekunde
}

file_put_contents( $cred, json_encode( $c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
printf( "\n%d Bilder übernommen, %d Fehler.\nWeiter: php bin/items-media.php && ./manage.sh seed\n", $ok, $fehl );
