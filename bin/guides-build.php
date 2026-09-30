<?php
/**
 * Warleek — Guides aus Markdown in die Inhalts-JSON bauen.
 *
 * Quelle sind die Dateien in `content-src/guides/*.md`. Sie werden mit demselben
 * Konverter übersetzt, den auch der Import im Backend benutzt (`inc/markdown.php`),
 * und in `plugin/warleek-core/content/_content/guides.json` eingemischt – gleicher
 * Slug ersetzt, alles andere bleibt stehen.
 *
 * Bilder: Steht im Kopfblock `image:` und liegt `content/img/<image>.webp`, wird
 * daraus ein Eintrag in `content/MANIFEST.json` mit dem Alt-Text aus `image_alt:`.
 *
 * Aufruf:  php bin/guides-build.php [--dry-run]
 * Danach:  ./manage.sh seed   (spielt die Inhalte in die Dev-Instanz)
 */

$root = dirname( __DIR__ );
require $root . '/plugin/warleek-core/inc/markdown.php';

$dry      = in_array( '--dry-run', $argv, true );
$src      = $root . '/content-src/guides';
$json     = $root . '/plugin/warleek-core/content/_content/guides.json';
$manifest = $root . '/plugin/warleek-core/content/MANIFEST.json';
$imgdir   = $root . '/plugin/warleek-core/content/img';

$existing = json_decode( (string) file_get_contents( $json ), true );
if ( ! is_array( $existing ) ) { fwrite( STDERR, "guides.json nicht lesbar\n" ); exit( 1 ); }
$mani = json_decode( (string) file_get_contents( $manifest ), true );
if ( ! is_array( $mani ) ) { fwrite( STDERR, "MANIFEST.json nicht lesbar\n" ); exit( 1 ); }

$by_slug = array();
foreach ( $existing as $g ) { $by_slug[ $g['slug'] ] = $g; }

$files = glob( $src . '/*.md' );
sort( $files );
$neu = 0; $upd = 0; $warn = 0;

foreach ( $files as $file ) {
	$fm   = warleek_md_front_matter( (string) file_get_contents( $file ) );
	$meta = $fm['meta'];
	$slug = trim( (string) ( $meta['slug'] ?? '' ) );
	if ( '' === $slug ) { fwrite( STDERR, "! kein slug: " . basename( $file ) . "\n" ); $warn++; continue; }

	$guide = array(
		'slug'    => $slug,
		'title'   => (string) ( $meta['title'] ?? '' ),
		'thema'   => (string) ( $meta['thema'] ?? 'einsteiger' ),
		'image'   => (string) ( $meta['image'] ?? '' ),
		'order'   => (int) ( $meta['order'] ?? 0 ),
		'excerpt' => (string) ( $meta['excerpt'] ?? '' ),
		'seo'     => array(
			'title'       => (string) ( $meta['seo_title'] ?? '' ),
			'description' => (string) ( $meta['seo_description'] ?? ( $meta['excerpt'] ?? '' ) ),
		),
		'html'    => "\n" . warleek_md_to_html( $fm['body'] ) . "\n",
		'quellen' => array_values( array_filter( (array) ( $meta['quellen'] ?? array() ) ) ),
		'geprueft'=> (string) ( $meta['geprueft'] ?? '' ),
	);

	foreach ( array( 'title', 'excerpt' ) as $pflicht ) {
		if ( '' === $guide[ $pflicht ] ) { fwrite( STDERR, "! $slug: $pflicht fehlt\n" ); $warn++; }
	}

	// Bilder aus dem Fließtext: `![Alt](schluessel)` – Datei muss in content-src/guides/img/ liegen.
	if ( preg_match_all( '#<img src="([^"/:.]+)" alt="([^"]*)">#', $guide['html'], $inline, PREG_SET_ORDER ) ) {
		foreach ( $inline as $hit ) {
			$key   = $hit[1];
			$alt   = $hit[2];
			$datei = '';
			foreach ( array( 'webp', 'png', 'jpg', 'jpeg' ) as $endung ) {
				if ( file_exists( "$src/img/$key.$endung" ) ) { $datei = "$key.$endung"; break; }
			}
			if ( ! $datei ) {
				fwrite( STDERR, "! $slug: Textbild fehlt (content-src/guides/img/$key.webp)\n" ); $warn++; continue;
			}
			if ( '' === trim( $alt ) ) { fwrite( STDERR, "! $slug: Textbild $key ohne Alt-Text\n" ); $warn++; }
			if ( ! $dry ) { copy( "$src/img/$datei", "$imgdir/$datei" ); }
			$mani[ $datei ] = array( 'alt' => $alt, 'use' => $key, 'size' => filesize( "$src/img/$datei" ) );
		}
	}

	// Titelbild ins Medien-Manifest eintragen, wenn die Datei vorhanden ist.
	if ( $guide['image'] ) {
		$datei = $guide['image'] . '.webp';
		$pfad  = $imgdir . '/' . $datei;
		if ( ! file_exists( $pfad ) ) {
			fwrite( STDERR, "! $slug: Bild fehlt ($datei)\n" ); $warn++;
		} else {
			$alt = trim( (string) ( $meta['image_alt'] ?? '' ) );
			if ( '' === $alt ) { fwrite( STDERR, "! $slug: image_alt fehlt\n" ); $warn++; $alt = $guide['title']; }
			$mani[ $datei ] = array( 'alt' => $alt, 'use' => $guide['image'], 'size' => filesize( $pfad ) );
		}
	}

	if ( isset( $by_slug[ $slug ] ) ) { $upd++; } else { $neu++; }
	$by_slug[ $slug ] = $guide;
	printf( "%-34s %-11s order %-3d %s\n", $slug, $guide['thema'], $guide['order'], basename( $file ) );
}

$out = array_values( $by_slug );
usort( $out, function ( $a, $b ) {
	return ( (int) $a['order'] <=> (int) $b['order'] ) ?: strcmp( $a['slug'], $b['slug'] );
} );

echo "\n$neu neu, $upd aktualisiert, " . count( $out ) . " Guides gesamt, $warn Warnung(en).\n";

if ( $dry ) { echo "Trockenlauf – nichts geschrieben.\n"; exit( $warn ? 1 : 0 ); }

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
file_put_contents( $json, json_encode( $out, $flags ) . "\n" );
file_put_contents( $manifest, json_encode( $mani, $flags ) . "\n" );
echo "geschrieben: content/_content/guides.json, content/MANIFEST.json\n";
exit( $warn ? 1 : 0 );
