<?php
/**
 * Bilder im Fließtext: Asset-Schlüssel → Mediathek-Adresse.
 * Aufruf: wp eval-file wp-content/plugins/warleek-core/tests/inline-images-test.php
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

// Ein echtes Bild aus der Mediathek als Testfall.
$map = (array) get_option( "warleek_media_map", array() );
$key = 'guide-erste-runde';
if ( empty( $map[ $key ] ) ) { echo "ÜBERSPRUNGEN – Mediathek noch nicht befüllt (erst ./manage.sh seed)\n"; exit( 0 ); }
$id  = (int) $map[ $key ];
$url = wp_get_attachment_url( $id );

// 1. Bekannter Schlüssel wird zur Adresse und behält den Alt-Text.
$html = warleek_resolve_asset_src( '<figure><img src="' . $key . '" alt="Ein Alt-Text"></figure>', $map );
chk( 'schluessel→url', $html, '<figure><img src="' . esc_url( $url ) . '" alt="Ein Alt-Text" data-id="' . $id . '"></figure>' );

// 2. Unbekannter Schlüssel: figure fliegt raus, statt ein kaputtes Bild zu zeigen.
chk( 'unbekannt→leer', warleek_resolve_asset_src( '<figure><img src="gibt-es-nicht" alt="x"></figure>', $map ), '' );

// 3. Fertige Adressen bleiben unangetastet.
$fertig = '<figure><img src="https://example.org/bild.webp" alt="x"></figure>';
chk( 'url bleibt', warleek_resolve_asset_src( $fertig, $map ), $fertig );
$relativ = '<figure><img src="/wp-content/uploads/2026/09/x.webp" alt="x"></figure>';
chk( 'pfad bleibt', warleek_resolve_asset_src( $relativ, $map ), $relativ );

// 4. Text drumherum bleibt stehen.
$gemischt = warleek_resolve_asset_src( "<p>Davor</p>\n<figure><img src=\"$key\" alt=\"A\"></figure>\n<p>Danach</p>", $map );
chk_true( 'text bleibt', str_contains( $gemischt, '<p>Davor</p>' ) && str_contains( $gemischt, '<p>Danach</p>' ) );

// 5. Aus dem aufgelösten HTML wird ein echter Bild-Block mit ID.
$blocks = warleek_html_to_blocks( warleek_resolve_asset_src( '<figure><img src="' . $key . '" alt="A"></figure>', $map ) );
chk_true( 'wp:image', str_contains( $blocks, '<!-- wp:image' ) );
chk_true( 'id im block', str_contains( $blocks, '"id":' . $id ) );

// 6. Markdown → HTML → Auflösung: der ganze Weg.
$md   = warleek_md_to_html( '![Karte der Zone](' . $key . ')', false );
chk( 'markdown erzeugt figure', $md, '<figure><img src="' . $key . '" alt="Karte der Zone"></figure>' );
chk_true( 'ganzer weg', str_contains( warleek_resolve_asset_src( $md, $map ), 'data-id="' . $id . '"' ) );

// 7. Bildunterschrift überlebt Auflösung und Block-Bau.
$mit_cap = warleek_md_to_html( '![Alt](' . $key . ' "Eine Unterschrift")', false );
$aufgeloest = warleek_resolve_asset_src( $mit_cap, $map );
chk_true( 'caption bleibt', str_contains( $aufgeloest, '<figcaption>Eine Unterschrift</figcaption>' ) );
$bl = warleek_html_to_blocks( $aufgeloest );
chk_true( 'caption im block', str_contains( $bl, '<figcaption class="wp-element-caption">Eine Unterschrift</figcaption>' ) );

echo $fail ? "$fail FAILED\n" : "OK\n";
exit( $fail ? 1 : 0 );
