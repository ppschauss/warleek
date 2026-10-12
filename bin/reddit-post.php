#!/usr/bin/env php
<?php
/**
 * Warleek — aus einem Guide einen Reddit-fertigen Beitrag machen.
 *
 * Reddit kann Markdown, aber nicht unseres: Keine `> [!hinweis]`-Kästen, keine
 * relativen Links, keine Tabellen-Ausrichtung mit Doppelpunkten. Dieses Skript
 * übersetzt die Quelldatei und hängt den Quellenblock an, den jeder Beitrag
 * tragen sollte.
 *
 * **Die wichtigste Zeile steht ganz unten in der Ausgabe:** ein Hinweis, dass
 * der Beitrag für sich stehen muss. Ein Reddit-Post, der nur aus einem Link
 * besteht, wird in den meisten Spiele-Subs entfernt – und zu Recht. Der Link
 * gehört ans Ende als Vertiefung, nicht an den Anfang als Zweck.
 *
 * Aufruf:  php bin/reddit-post.php <slug> [--kurz]
 *          php bin/reddit-post.php --liste
 *
 * `--kurz` gibt nur Titel, Direktantwort und FAQ aus – die Fassung für einen
 * Kommentar unter einer fremden Frage.
 */

$root = dirname( __DIR__ );
require $root . '/plugin/warleek-core/inc/markdown.php';

const BASIS = 'https://www.warleek.de';

$args  = array_slice( $argv, 1 );
$kurz  = in_array( '--kurz', $args, true );
$liste = in_array( '--liste', $args, true );
$slug  = '';
foreach ( $args as $a ) { if ( ! str_starts_with( $a, '--' ) ) { $slug = $a; break; } }

$dateien = glob( $root . '/content-src/guides/*.md' );
sort( $dateien );

if ( $liste || '' === $slug ) {
	echo "Vorhandene Guides:\n";
	foreach ( $dateien as $f ) {
		$fm = warleek_md_front_matter( (string) file_get_contents( $f ) );
		printf( "  %-34s %s\n", $fm['meta']['slug'] ?? '?', mb_substr( $fm['meta']['title'] ?? '', 0, 56 ) );
	}
	echo "\nAufruf: php bin/reddit-post.php <slug> [--kurz]\n";
	exit( 0 );
}

$treffer = null;
foreach ( $dateien as $f ) {
	$fm = warleek_md_front_matter( (string) file_get_contents( $f ) );
	if ( ( $fm['meta']['slug'] ?? '' ) === $slug ) { $treffer = $fm; break; }
}
if ( ! $treffer ) { fwrite( STDERR, "Kein Guide mit dem Slug „$slug“. Liste: php bin/reddit-post.php --liste\n" ); exit( 1 ); }

$meta = $treffer['meta'];
$body = $treffer['body'];

/**
 * Unsere Markdown-Eigenheiten nach Reddit übersetzen.
 */
function wl_reddit_body( $md ) {
	$zeilen = explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", $md ) );
	$out    = array();

	foreach ( $zeilen as $z ) {
		$t = rtrim( $z );

		// Stand-Zeile → kursiver Einzeiler
		if ( preg_match( '/^>\s*\[!stand\]\s*(.*)$/i', $t, $m ) ) {
			$out[] = '*Stand: ' . trim( $m[1] ) . '*';
			continue;
		}
		// Hinweis-/Achtung-Kasten → Zitat mit Präfix
		if ( preg_match( '/^>\s*\[!(hinweis|note|tipp|tip)\]\s*(.*)$/i', $t, $m ) ) {
			$out[] = '> **Hinweis:** ' . trim( $m[2] );
			continue;
		}
		if ( preg_match( '/^>\s*\[!(achtung|warning)\]\s*(.*)$/i', $t, $m ) ) {
			$out[] = '> **Achtung:** ' . trim( $m[2] );
			continue;
		}
		// Bild-Assets fliegen raus – die Schlüssel sagen auf Reddit niemandem etwas
		if ( preg_match( '/^!\[[^\]]*\]\([^)]*\)\s*$/', $t ) ) { continue; }

		// Interne Links absolut machen
		$t = preg_replace( '#\]\((/[^)]*)\)#', '](' . BASIS . '$1)', $t );

		// Tabellen-Trennzeile ohne Ausrichtungs-Doppelpunkte
		if ( preg_match( '/^\|[\s:|-]+\|$/', $t ) ) { $t = str_replace( ':', '', $t ); }

		$out[] = $t;
	}

	$text = implode( "\n", $out );
	$text = preg_replace( "/\n{3,}/", "\n\n", $text );
	return trim( $text );
}

/** FAQ-Abschnitt aus dem Text lösen. */
function wl_reddit_faq( $md ) {
	if ( ! preg_match( '/\n## (?:Häufige Fragen|FAQ|Fragen und Antworten)\s*\n(.*)$/su', $md, $m ) ) { return ''; }
	return trim( $m[1] );
}

$faq  = wl_reddit_faq( $body );
$rest = preg_replace( '/\n## (?:Häufige Fragen|FAQ|Fragen und Antworten)\s*\n.*$/su', '', $body );
$url  = BASIS . '/guides/' . $slug . '/';

echo "════════ TITEL ════════\n";
echo trim( (string) ( $meta['title'] ?? '' ) ) . "\n\n";

echo "════════ TEXT ════════\n";

if ( ! empty( $meta['frage'] ) && ! empty( $meta['antwort'] ) ) {
	echo '**' . trim( $meta['frage'] ) . "**\n\n" . trim( $meta['antwort'] ) . "\n\n---\n\n";
}

if ( $kurz ) {
	if ( $faq ) { echo wl_reddit_body( $faq ) . "\n\n"; }
} else {
	echo wl_reddit_body( $rest ) . "\n\n";
	if ( $faq ) { echo "## Häufige Fragen\n\n" . wl_reddit_body( $faq ) . "\n\n"; }
}

/* ---- Quellenblock: das, was den Beitrag von Werbung unterscheidet ---- */
echo "---\n\n";
$q = array_values( array_filter( (array) ( $meta['quellen'] ?? array() ) ) );
if ( $q ) {
	echo "**Quellen:** " . implode( ' · ', array_map( 'trim', $q ) ) . "\n\n";
}
$g = trim( (string) ( $meta['geprueft'] ?? '' ) );
if ( '' !== $g ) {
	$nein = str_starts_with( strtolower( $g ), 'nein' );
	echo '**Im Spiel nachgeprüft:** ' . ( $nein ? 'nein' : $g ) . "\n\n";
}
echo "Langfassung mit allen Quellen: $url\n";

/* ---- Hinweise für den Menschen, nicht für den Beitrag ---- */
$woerter = str_word_count( strip_tags( $rest ), 0, 'äöüÄÖÜß' );
fwrite( STDERR, "\n──────── Vor dem Posten ────────\n" );
fwrite( STDERR, sprintf( "Länge: rund %d Wörter%s\n", $woerter, $woerter > 900 ? '  (für Reddit lang – überleg eine Kurzfassung)' : '' ) );
fwrite( STDERR, "Der Beitrag muss **für sich stehen**. Ein Post, der nur aus einem Link besteht,\n" );
fwrite( STDERR, "fliegt in den meisten Spiele-Subs raus – der Link gehört ans Ende als Vertiefung.\n" );
if ( str_starts_with( strtolower( (string) ( $meta['geprueft'] ?? 'nein' ) ), 'nein' ) ) {
	fwrite( STDERR, "ACHTUNG: Dieser Guide ist nicht im Spiel nachgeprüft. Schreib das dazu,\n" );
	fwrite( STDERR, "bevor jemand anders es dir vorhält.\n" );
}
