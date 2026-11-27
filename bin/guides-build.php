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
 * Mit `--blog` liest dasselbe Skript `content-src/blog/*.md` und schreibt nach
 * `content/_content/blog.json`. Die Prüfungen sind dieselben – SEO-Längen,
 * Direktantwort, FAQ, Bilder –, denn ein Blogbeitrag ist kein schlechterer Text
 * als ein Guide. Nur die Pflichtfelder unterscheiden sich: ein Guide gehört zu
 * einem Thema und hat eine Ordnung, ein Beitrag hat ein Datum.
 *
 * Aufruf:  php bin/guides-build.php [--blog] [--dry-run]
 * Danach:  ./manage.sh seed   (spielt die Inhalte in die Dev-Instanz)
 */

$root = dirname( __DIR__ );
require $root . '/plugin/warleek-core/inc/markdown.php';
require $root . '/plugin/warleek-core/inc/aeo.php';

$dry      = in_array( '--dry-run', $argv, true );
$blog     = in_array( '--blog', $argv, true );
$art      = $blog ? 'blog' : 'guides';
$src      = $root . '/content-src/' . $art;
$json     = $root . '/plugin/warleek-core/content/_content/' . $art . '.json';
$manifest = $root . '/plugin/warleek-core/content/MANIFEST.json';
$imgdir   = $root . '/plugin/warleek-core/content/img';

$existing = file_exists( $json ) ? json_decode( (string) file_get_contents( $json ), true ) : array();
if ( ! is_array( $existing ) ) { fwrite( STDERR, basename( $json ) . " nicht lesbar\n" ); exit( 1 ); }
$mani = json_decode( (string) file_get_contents( $manifest ), true );
if ( ! is_array( $mani ) ) { fwrite( STDERR, "MANIFEST.json nicht lesbar\n" ); exit( 1 ); }

$by_slug = array();
foreach ( $existing as $g ) { $by_slug[ $g['slug'] ] = $g; }

// Slugs und Felder der Datenbank, um Querverweise und Platzhalter zu prüfen.
$items_slugs = array();
$items_datei = $root . '/plugin/warleek-core/content/_content/items.json';
if ( file_exists( $items_datei ) ) {
	foreach ( (array) json_decode( (string) file_get_contents( $items_datei ), true ) as $e ) {
		if ( ! empty( $e['slug'] ) ) { $items_slugs[ $e['slug'] ] = (array) ( $e['felder'] ?? array() ); }
	}
}

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
		'thema'   => (string) ( $meta['thema'] ?? ( $blog ? 'blog' : 'einsteiger' ) ),
		'image'   => (string) ( $meta['image'] ?? '' ),
		// Optionales Veröffentlichungsdatum (Y-m-d oder Y-m-d H:i). Liegt es in der
		// Zukunft, legt der Installer den Guide terminiert an statt sofort sichtbar.
		'datum'   => trim( (string) ( $meta['veroeffentlichen'] ?? '' ) ),
		'order'   => (int) ( $meta['order'] ?? 0 ),
		'excerpt' => (string) ( $meta['excerpt'] ?? '' ),
		'seo'     => array(
			'title'       => (string) ( $meta['seo_title'] ?? '' ),
			'description' => (string) ( $meta['seo_description'] ?? ( $meta['excerpt'] ?? '' ) ),
		),
		'html'    => "\n" . warleek_md_to_html( $fm['body'] ) . "\n",
		'quellen' => array_values( array_filter( (array) ( $meta['quellen'] ?? array() ) ) ),
		'geprueft'=> (string) ( $meta['geprueft'] ?? '' ),
		'autor'   => trim( (string) ( $meta['autor'] ?? '' ) ),
	);

	// Direktantwort: `frage:` + `antwort:` werden zum Kasten ganz oben im Text.
	// Er steht vor dem Fließtext, weil Antwortmaschinen die ersten Absätze lesen –
	// und dort stand bisher ein Teaser, also eine Frage statt einer Auskunft.
	$frage   = trim( (string) ( $meta['frage'] ?? '' ) );
	$antwort = trim( (string) ( $meta['antwort'] ?? '' ) );
	if ( '' !== $frage || '' !== $antwort ) {
		if ( '' === $frage || '' === $antwort ) {
			fwrite( STDERR, "! $slug: frage und antwort gehören zusammen, eines fehlt\n" ); $warn++;
		} else {
			if ( ! str_ends_with( $frage, '?' ) ) {
				fwrite( STDERR, "! $slug: frage endet nicht auf ein Fragezeichen\n" ); $warn++;
			}
			$wz = warleek_aeo_wortzahl( $antwort );
			if ( $wz < WARLEEK_AEO_WORT_MIN || $wz > WARLEEK_AEO_WORT_MAX ) {
				fwrite( STDERR, sprintf(
					"! %s: antwort hat %d Wörter (sinnvoll %d–%d)\n",
					$slug, $wz, WARLEEK_AEO_WORT_MIN, WARLEEK_AEO_WORT_MAX
				) );
				$warn++;
			}
			$guide['html'] = "\n" . warleek_aeo_antwort_block(
				warleek_md_inline( $frage ),
				warleek_md_inline( $antwort )
			) . $guide['html'];
		}
	}

	// FAQ-Abschnitt gegenprüfen: `## Häufige Fragen` ohne `###`-Fragen darunter
	// erzeugt eine leere Auszeichnung, und eine Frage ohne Fragezeichen ist keine.
	$aeo = warleek_aeo_extract( $guide['html'] );
	if ( preg_match( '/^##\s*(Häufige Fragen|FAQ|Fragen und Antworten)\s*$/mu', $fm['body'] ) && ! $aeo['faq'] ) {
		fwrite( STDERR, "! $slug: Abschnitt „Häufige Fragen“ ohne ###-Fragen darunter\n" ); $warn++;
	}
	foreach ( $aeo['faq'] as $paar ) {
		if ( ! str_ends_with( $paar['frage'], '?' ) ) {
			fwrite( STDERR, sprintf( "! %s: FAQ-Frage ohne Fragezeichen: %s\n", $slug, $paar['frage'] ) ); $warn++;
		}
	}

	foreach ( array( 'title', 'excerpt' ) as $pflicht ) {
		if ( '' === $guide[ $pflicht ] ) { fwrite( STDERR, "! $slug: $pflicht fehlt\n" ); $warn++; }
	}

	// SEO-Längen prüfen – die Regel steht in CLAUDE.md, also soll sie auch jemand
	// durchsetzen. Zu lange Angaben kürzt Google selbst, und dann steht dort nicht
	// das, was wir ausgesucht haben.
	foreach ( array( 'seo_title' => 62, 'seo_description' => 158 ) as $feld => $grenze ) {
		$wert = trim( (string) ( $meta[ $feld ] ?? '' ) );
		if ( '' === $wert ) { continue; }
		$laenge = mb_strlen( $wert );
		if ( $laenge > $grenze ) {
			fwrite( STDERR, sprintf( "! %s: %s ist %d Zeichen lang (erlaubt %d)\n", $slug, $feld, $laenge, $grenze ) );
			$warn++;
		}
	}

	// Querverweise und Werte-Platzhalter prüfen. Beides zeigt gern ins Leere:
	// ein Slug mit Tippfehler wird zum 404, ein `{preis:…}` auf einen Eintrag,
	// den es nicht gibt, bleibt wörtlich auf der Seite stehen.
	if ( $items_slugs ) {
		if ( preg_match_all( '#href="/datenbank/([a-z0-9-]+)/"#', $guide['html'], $db, PREG_SET_ORDER ) ) {
			foreach ( $db as $hit ) {
				if ( ! isset( $items_slugs[ $hit[1] ] ) ) {
					fwrite( STDERR, "! $slug: /datenbank/{$hit[1]}/ gibt es nicht\n" ); $warn++;
				}
			}
		}
		if ( preg_match_all( '#\{(preis|freischaltung|freischaltkosten|kaliber|sitze|tempo|baukosten|rolle|hinweis|titel|aufpreis):([a-z0-9-]+)\}#', $guide['html'], $ph, PREG_SET_ORDER ) ) {
			foreach ( $ph as $hit ) {
				if ( ! isset( $items_slugs[ $hit[2] ] ) ) {
					fwrite( STDERR, "! $slug: Platzhalter {$hit[0]} – diesen Eintrag gibt es nicht\n" ); $warn++;
				} elseif ( ! in_array( $hit[1], array( 'titel', 'aufpreis' ), true )
					&& '' === trim( (string) ( $items_slugs[ $hit[2] ][ $hit[1] ] ?? '' ) ) ) {
					fwrite( STDERR, "! $slug: Platzhalter {$hit[0]} – Feld ist leer\n" ); $warn++;
				}
			}
		}
		// Ein nackter `{preis}` hat außerhalb eines Datenbank-Eintrags keinen Bezug.
		if ( preg_match( '#\{(preis|freischaltung|freischaltkosten|baukosten)\}#', $guide['html'], $nackt ) ) {
			fwrite( STDERR, "! $slug: {$nackt[0]} ohne Slug – hier gibt es keinen eigenen Eintrag\n" ); $warn++;
		}
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
	if ( $blog ) {
		$termin = $guide['datum'] ?: 'sofort';
		printf( "%-34s %-11s %-16s %s\n", $slug, $guide['thema'], $termin, basename( $file ) );
	} else {
		printf( "%-34s %-11s order %-3d %s\n", $slug, $guide['thema'], $guide['order'], basename( $file ) );
	}
}

$out = array_values( $by_slug );
usort( $out, function ( $a, $b ) use ( $blog ) {
	// Beiträge nach Termin, Guides nach Ordnung – beides so, wie sie
	// hinterher auch erscheinen.
	if ( $blog ) { return strcmp( (string) $a['datum'], (string) $b['datum'] ) ?: strcmp( $a['slug'], $b['slug'] ); }
	return ( (int) $a['order'] <=> (int) $b['order'] ) ?: strcmp( $a['slug'], $b['slug'] );
} );

printf( "\n%d neu, %d aktualisiert, %d %s gesamt, %d Warnung(en).\n",
	$neu, $upd, count( $out ), $blog ? 'Beiträge' : 'Guides', $warn );

if ( $dry ) { echo "Trockenlauf – nichts geschrieben.\n"; exit( $warn ? 1 : 0 ); }

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
file_put_contents( $json, json_encode( $out, $flags ) . "\n" );
file_put_contents( $manifest, json_encode( $mani, $flags ) . "\n" );
echo "geschrieben: content/_content/" . $art . ".json, content/MANIFEST.json\n";
exit( $warn ? 1 : 0 );
