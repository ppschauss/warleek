<?php
// Standalone-Test ohne WordPress: php tests/aeo-test.php
require __DIR__ . '/../inc/aeo.php';
require __DIR__ . '/../inc/markdown.php';

$fail = 0;
function chk( $label, $got, $want ) {
	global $fail;
	if ( trim( (string) $got ) !== trim( (string) $want ) ) {
		$fail++;
		echo "FAIL $label\n want: $want\n got:  " . trim( (string) $got ) . "\n";
	}
}

/* ------------------------------------------------------ Antwortkasten */
chk(
	'block',
	warleek_aeo_antwort_block( 'Was kostet eine Palette?', 'Rund $400.' ),
	'<div class="wl-antwort"><h2>Was kostet eine Palette?</h2><p>Rund $400.</p></div>'
);
chk( 'block-leer', warleek_aeo_antwort_block( '', 'Antwort' ), '' );
chk( 'block-leer2', warleek_aeo_antwort_block( 'Frage?', '' ), '' );

/* --------------------------------------------------------- Wortzahl */
chk( 'wortzahl', warleek_aeo_wortzahl( 'eins zwei   drei' ), 3 );
chk( 'wortzahl-html', warleek_aeo_wortzahl( '<p>eins <strong>zwei</strong></p>' ), 2 );
chk( 'wortzahl-leer', warleek_aeo_wortzahl( '   ' ), 0 );

/* ------------------------------------------- Extraktion: Direktantwort */
$c = '<div class="wl-antwort"><h2>Wie verdient man Geld?</h2><p>Mit Paletten.</p></div><p>Fließtext.</p>';
$a = warleek_aeo_extract( $c );
chk( 'x-frage', $a['frage'], 'Wie verdient man Geld?' );
chk( 'x-antwort', $a['antwort'], 'Mit Paletten.' );
chk( 'x-faq-leer', count( $a['faq'] ), 0 );

// Nach der Block-Umwandlung steht die Klasse am Gruppen-Block, die Überschrift
// trägt zusätzlich wp-block-heading. Genau so liegt es in der Datenbank.
$blockmarkup = '<!-- wp:group {"className":"wl-antwort","layout":{"type":"constrained"}} -->'
	. '<div class="wp-block-group wl-antwort">'
	. '<!-- wp:heading --><h2 class="wp-block-heading">Was kostet der Ural?</h2><!-- /wp:heading -->'
	. '<!-- wp:paragraph --><p>Rund $5.000 pro Stück.</p><!-- /wp:paragraph -->'
	. '</div><!-- /wp:group -->';
$b = warleek_aeo_extract( $blockmarkup );
chk( 'x-block-frage', $b['frage'], 'Was kostet der Ural?' );
chk( 'x-block-antwort', $b['antwort'], 'Rund $5.000 pro Stück.' );

chk( 'x-leer', warleek_aeo_extract( '' )['frage'], '' );
chk( 'x-ohne-kasten', warleek_aeo_extract( '<p>Nur Text</p>' )['antwort'], '' );

/* -------------------------------------------------- Extraktion: FAQ */
$faqhtml = '<h2 class="wl-faq-titel">Häufige Fragen</h2>'
	. '<h3 class="wl-faq-frage">Was kostet eine Palette?</h3><p>Rund $400 beim Händler.</p>'
	. '<h3 class="wl-faq-frage">Wie viele passen auf den Ural?</h3><p>Zwei.</p><p>Mehr nicht.</p>'
	. '<h2>Anderer Abschnitt</h2><p>Gehört nicht dazu.</p>';
$f = warleek_aeo_extract( $faqhtml );
chk( 'faq-anzahl', count( $f['faq'] ), 2 );
chk( 'faq-1-frage', $f['faq'][0]['frage'], 'Was kostet eine Palette?' );
chk( 'faq-1-antwort', $f['faq'][0]['antwort'], 'Rund $400 beim Händler.' );
chk( 'faq-2-mehrere-absaetze', $f['faq'][1]['antwort'], 'Zwei. Mehr nicht.' );

// Die nächste Überschrift beendet die Antwort – sonst wanderte der halbe
// Guide in ein acceptedAnswer.
chk( 'faq-stoppt-bei-h2', (int) str_contains( $f['faq'][1]['antwort'], 'Gehört nicht' ), 0 );

// Listen in einer Antwort: Semikolon statt aneinandergeklebter Sätze.
$liste = '<h3 class="wl-faq-frage">Welche Reihenfolge?</h3><ul><li>Fernglas</li><li>Fahrzeug</li></ul>';
chk( 'faq-liste', warleek_aeo_extract( $liste )['faq'][0]['antwort'], 'Fernglas; Fahrzeug.' );

// Eine Frage ohne Antwort darf nicht als leeres Paar durchrutschen.
$ohne = '<h3 class="wl-faq-frage">Frage ohne Antwort?</h3><h3 class="wl-faq-frage">Zweite?</h3><p>Da.</p>';
$o    = warleek_aeo_extract( $ohne );
chk( 'faq-ohne-antwort', count( $o['faq'] ), 1 );
chk( 'faq-ohne-antwort-welche', $o['faq'][0]['frage'], 'Zweite?' );

/* ------- Die Antwort endet, wo die Antwort endet (Regression) -------
   Mit „bis zur nächsten Überschrift“ als einziger Grenze zog die letzte
   Antwort einer Seite alles hinter sich her. Beides stand live im FAQPage. */

// Guide: hinter der letzten Antwort folgt der Herkunftskasten als <div>.
$mit_kasten = '<h3 class="wl-faq-frage">Welche Route nimmt man?</h3><p>Nicht die kürzeste.</p>'
	. '<div class="wl-note"><p>Woher die Angaben stammen</p><p>Öffentliche Community-Guides</p></div>';
chk( 'folge-stoppt-bei-div', warleek_aeo_extract( $mit_kasten )['faq'][0]['antwort'], 'Nicht die kürzeste.' );

// Datenbank-Eintrag: hinter der letzten Antwort folgen Shortcodes.
$mit_sc = '<h3 class="wl-faq-frage">Welche Rolle hat X?</h3><p>Sofortwirkung.</p>'
	. '<p>[warleek_item_verlauf]</p><p>[warleek_item_herkunft]</p>';
chk( 'folge-stoppt-bei-shortcode', warleek_aeo_extract( $mit_sc )['faq'][0]['antwort'], 'Sofortwirkung.' );

// Zwei Shortcodes in EINEM Absatz – so legt der Installer sie an.
$sc_zwei = '<h3 class="wl-faq-frage">Welche Rolle hat X?</h3><p>Sofortwirkung.</p>'
	. "<p>[warleek_item_verlauf]\n[warleek_item_herkunft]</p>";
chk( 'folge-stoppt-bei-zwei-shortcodes', warleek_aeo_extract( $sc_zwei )['faq'][0]['antwort'], 'Sofortwirkung.' );

// Auch als nackter Textknoten zwischen Block-Kommentaren.
$roh_sc = '<h3 class="wl-faq-frage">Und hier?</h3><p>Antwort.</p>'
	. '<!-- wp:shortcode -->[warleek_item_herkunft]<!-- /wp:shortcode -->';
chk( 'folge-stoppt-bei-rohem-shortcode', warleek_aeo_extract( $roh_sc )['faq'][0]['antwort'], 'Antwort.' );

// Eine Tabelle beendet die Antwort ebenfalls – sie ist kein Antwortsatz.
$mit_tab = '<h3 class="wl-faq-frage">Was kostet es?</h3><p>Unterschiedlich.</p><table><tr><td>Ural</td></tr></table>';
chk( 'folge-stoppt-bei-tabelle', warleek_aeo_extract( $mit_tab )['faq'][0]['antwort'], 'Unterschiedlich.' );

// Mehrere Absätze gehören weiterhin dazu.
$zwei = '<h3 class="wl-faq-frage">Warum?</h3><p>Erster Satz.</p><p>Zweiter Satz.</p><div>Ende</div>';
chk( 'folge-nimmt-mehrere-absaetze', warleek_aeo_extract( $zwei )['faq'][0]['antwort'], 'Erster Satz. Zweiter Satz.' );

/* ------------------------------------------------------------ JSON-LD */
$ld = warleek_aeo_faq_ld( $a, 'https://warleek.de/guides/geld/' );
chk( 'ld-typ', $ld['@type'], 'FAQPage' );
chk( 'ld-anzahl', count( $ld['mainEntity'] ), 1 );
chk( 'ld-frage', $ld['mainEntity'][0]['name'], 'Wie verdient man Geld?' );
chk( 'ld-antwort', $ld['mainEntity'][0]['acceptedAnswer']['text'], 'Mit Paletten.' );
chk( 'ld-id', $ld['@id'], 'https://warleek.de/guides/geld/#faq' );

// Direktantwort plus FAQ: die Direktantwort führt die Liste.
$beides = warleek_aeo_extract( $c . $faqhtml );
$ld2    = warleek_aeo_faq_ld( $beides );
chk( 'ld-beides-anzahl', count( $ld2['mainEntity'] ), 3 );
chk( 'ld-beides-erste', $ld2['mainEntity'][0]['name'], 'Wie verdient man Geld?' );

chk( 'ld-nichts', warleek_aeo_faq_ld( array( 'frage' => '', 'antwort' => '', 'faq' => array() ) ), null );
// Kaputtes Meta darf nicht in die Auszeichnung wandern.
chk( 'ld-muell', warleek_aeo_faq_ld( array( 'frage' => '', 'antwort' => '', 'faq' => array( 'string', array( 'frage' => 'A?' ) ) ) ), null );

// Keine Frage im Kasten (so sehen die Datenbank-Einträge aus): nicht als Question.
$keine = warleek_aeo_extract( '<div class="wl-antwort"><h2>Ural in Wardogs: Preis und Freischaltung</h2><p>Rund $5.000.</p></div>' );
chk( 'ld-keine-frage', warleek_aeo_faq_ld( $keine ), null );
chk( 'ld-keine-frage-extrahiert', $keine['antwort'], 'Rund $5.000.' );
// Mit FAQ darunter bleibt die Nicht-Frage draußen, die echten Fragen kommen mit.
$gemischt = warleek_aeo_extract(
	'<div class="wl-antwort"><h2>Ural in Wardogs: Preis</h2><p>Rund $5.000.</p></div>'
	. '<h3 class="wl-faq-frage">Was kostet Ural in Wardogs?</h3><p>Rund $5.000.</p>'
);
$ld3 = warleek_aeo_faq_ld( $gemischt );
chk( 'ld-gemischt-anzahl', count( $ld3['mainEntity'] ), 1 );
chk( 'ld-gemischt-name', $ld3['mainEntity'][0]['name'], 'Was kostet Ural in Wardogs?' );

chk( 'speakable', warleek_aeo_speakable()['cssSelector'][0], '.wl-antwort' );

/* ------------------------------------ Markdown erzeugt die FAQ-Klassen */
$md = "## Häufige Fragen\n\n### Was kostet eine Palette?\n\nRund \$400.\n\n## Weiter\n\n### Keine Frage hier\n\nText.";
$h  = warleek_md_to_html( $md, false );
chk( 'md-faq-titel', (int) str_contains( $h, '<h2 class="wl-faq-titel">' ), 1 );
chk( 'md-faq-frage', (int) str_contains( $h, '<h3 class="wl-faq-frage">Was kostet eine Palette?</h3>' ), 1 );
// Nach dem nächsten h2 ist der FAQ-Abschnitt zu Ende.
chk( 'md-faq-endet', (int) str_contains( $h, '<h3>Keine Frage hier</h3>' ), 1 );

// Und der ganze Weg: Markdown → HTML → Extraktion.
$durch = warleek_aeo_extract( warleek_md_to_html( $md, false ) );
chk( 'md-durchgang', $durch['faq'][0]['antwort'], 'Rund $400.' );
chk( 'md-durchgang-anzahl', count( $durch['faq'] ), 1 );

echo $fail ? "$fail FAILED\n" : "OK\n";
exit( $fail ? 1 : 0 );
