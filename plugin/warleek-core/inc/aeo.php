<?php
/**
 * Warleek — Antwort-Optimierung (AEO): Direktantwort, FAQ und deren Auszeichnung.
 *
 * Eine Antwortmaschine (Featured Snippet, Sprachsuche, AI Overview) sucht keinen
 * gut geschriebenen Einstieg, sondern einen Satz, der die Frage beantwortet.
 * Unsere Guides führten bislang mit einem Teaser — „Blau, Rot oder Grün?" —, also
 * mit Neugier statt mit Auskunft. Darum trägt jeder Guide jetzt oben einen
 * Antwortkasten und optional einen Abschnitt „Häufige Fragen".
 *
 * **Die eine Entwurfsentscheidung, auf die es ankommt:** Das JSON-LD wird aus dem
 * *gerenderten Inhalt* abgeleitet, nicht aus getrennt gespeicherten Feldern.
 *
 * Grund: Google wertet strukturierte Daten, die nicht dem sichtbaren Text
 * entsprechen, als Verstoß — und genau das passiert zwangsläufig, wenn Schema und
 * Text zwei Quellen haben. Ändert jemand den Antwortkasten im Backend, ändert sich
 * hier automatisch das Schema mit. Auseinanderlaufen ist technisch unmöglich.
 *
 * Reine Funktionen ohne WordPress-Abhängigkeit — testbar mit
 * `php tests/aeo-test.php`.
 *
 * @package warleek-core
 */
if ( ! defined( 'WARLEEK_AEO_LOADED' ) ) { define( 'WARLEEK_AEO_LOADED', true ); }

/** Wortfenster für eine Direktantwort. Darunter trägt sie nichts, darüber wird sie abgeschnitten. */
const WARLEEK_AEO_WORT_MIN = 25;
const WARLEEK_AEO_WORT_MAX = 60;

/**
 * Antwortkasten als HTML, so wie Build und Installer ihn erzeugen.
 *
 * Die Frage steht als Überschrift, weil eine Überschrift, die der Suchanfrage
 * entspricht, das Stück ist, auf das Antwortmaschinen zugreifen. Ein `<strong>`
 * täte es optisch auch, wäre aber strukturell stumm.
 *
 * @param string $frage   Frage, endet auf ein Fragezeichen.
 * @param string $antwort Antwort in 25–60 Wörtern.
 * @return string Leerstring, wenn eines von beidem fehlt.
 */
function warleek_aeo_antwort_block( $frage, $antwort ) {
	$frage   = trim( (string) $frage );
	$antwort = trim( (string) $antwort );
	if ( '' === $frage || '' === $antwort ) { return ''; }
	return '<div class="wl-antwort"><h2>' . $frage . '</h2><p>' . $antwort . '</p></div>';
}

/** Wörter zählen — für die Längenprüfung im Build. Läuft auch ohne WordPress. */
function warleek_aeo_wortzahl( $text ) {
	$text = trim( preg_replace( '/\s+/u', ' ', strip_tags( (string) $text ) ) );
	return '' === $text ? 0 : count( explode( ' ', $text ) );
}

/**
 * Direktantwort und FAQ aus dem Beitragsinhalt lesen.
 *
 * Arbeitet auf dem rohen `post_content` mit Block-Kommentaren: Die Kommentare
 * sind für den HTML-Parser unsichtbar, die Klassen im Markup bleiben erhalten
 * (`warleek_b_heading()` und `warleek_b_group()` schreiben sie mit).
 *
 * @param string $content Beitragsinhalt.
 * @return array{frage: string, antwort: string, faq: array<int, array{frage: string, antwort: string}>}
 */
function warleek_aeo_extract( $content ) {
	$leer = array( 'frage' => '', 'antwort' => '', 'faq' => array() );
	$content = (string) $content;
	if ( '' === trim( $content ) ) { return $leer; }

	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML(
		'<?xml encoding="UTF-8"><div id="wl-aeo">' . $content . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	libxml_clear_errors();
	$root = $doc->getElementById( 'wl-aeo' );
	if ( ! $root ) { return $leer; }

	$xp  = new DOMXPath( $doc );
	$hat = function ( $klasse ) {
		return "contains(concat(' ', normalize-space(@class), ' '), ' " . $klasse . " ')";
	};

	/* --- Direktantwort: Überschrift + Absätze im Kasten --- */
	$frage   = '';
	$antwort = '';
	$kasten  = $xp->query( './/*[' . $hat( 'wl-antwort' ) . '][1]', $root );
	if ( $kasten->length ) {
		$k = $kasten->item( 0 );
		$h = $xp->query( './/h2|.//h3|.//h4', $k );
		if ( $h->length ) { $frage = warleek_aeo_text( $h->item( 0 ) ); }
		$teile = array();
		foreach ( $xp->query( './/p', $k ) as $p ) {
			$t = warleek_aeo_text( $p );
			if ( '' !== $t ) { $teile[] = $t; }
		}
		$antwort = implode( ' ', $teile );
	}

	/* --- Häufige Fragen: jede Überschrift mit wl-faq-frage plus das, was folgt --- */
	$faq = array();
	foreach ( $xp->query( './/*[' . $hat( 'wl-faq-frage' ) . ']', $root ) as $kopf ) {
		$f = warleek_aeo_text( $kopf );
		$a = warleek_aeo_folgetext( $kopf );
		if ( '' !== $f && '' !== $a ) { $faq[] = array( 'frage' => $f, 'antwort' => $a ); }
	}

	return array( 'frage' => $frage, 'antwort' => $antwort, 'faq' => $faq );
}

/** Reiner Text eines Knotens, Leerraum normalisiert. */
function warleek_aeo_text( DOMNode $node ) {
	return trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
}

/**
 * Text zwischen einer FAQ-Überschrift und dem Ende der Antwort.
 *
 * Eine Antwort besteht ausschließlich aus Absätzen und Listen. Alles andere –
 * eine Überschrift, ein Kasten, eine Tabelle, ein Shortcode – beendet sie.
 *
 * Diese Strenge ist nötig, nicht vorsichtig: Mit „bis zur nächsten Überschrift“
 * als einziger Grenze zog die **letzte** Antwort einer Seite alles hinter sich
 * her, was danach kam. Im Guide war das der komplette Herkunftskasten, beim
 * Datenbank-Eintrag die Shortcodes `[warleek_item_verlauf]` und
 * `[warleek_item_herkunft]` – beides stand so im ausgelieferten FAQPage.
 *
 * Listen werden mit Semikolon verbunden: `acceptedAnswer.text` ist ein Textfeld,
 * und ohne Trennzeichen klebten die Punkte aneinander.
 *
 * @param DOMNode $kopf Die Überschrift der Frage.
 * @return string
 */
function warleek_aeo_folgetext( DOMNode $kopf ) {
	$teile = array();
	for ( $n = $kopf->nextSibling; $n; $n = $n->nextSibling ) {
		if ( XML_ELEMENT_NODE !== $n->nodeType ) {
			// Ein nackter Shortcode steht als Textknoten zwischen Block-Kommentaren.
			if ( XML_TEXT_NODE === $n->nodeType && preg_match( '/\[[a-z_]+[^\]]*\]/i', $n->textContent ) ) { break; }
			continue;
		}
		$tag = strtolower( $n->nodeName );
		if ( ! in_array( $tag, array( 'p', 'ul', 'ol' ), true ) ) { break; }
		// Ein Absatz, der nur aus Shortcodes besteht, gehört nicht zur Antwort.
		// Mehrzahl: Der Installer legt `[warleek_item_verlauf]` und
		// `[warleek_item_herkunft]` in **einen** Absatz, getrennt durch Umbruch.
		if ( preg_match( '/^(?:\s*\[[a-z_]+[^\]]*\])+\s*$/i', $n->textContent ) ) { break; }
		if ( in_array( $tag, array( 'ul', 'ol' ), true ) ) {
			$li = array();
			foreach ( $n->childNodes as $c ) {
				if ( XML_ELEMENT_NODE === $c->nodeType && 'li' === strtolower( $c->nodeName ) ) {
					$t = warleek_aeo_text( $c );
					if ( '' !== $t ) { $li[] = rtrim( $t, '.' ); }
				}
			}
			if ( $li ) { $teile[] = implode( '; ', $li ) . '.'; }
			continue;
		}
		$t = warleek_aeo_text( $n );
		if ( '' !== $t ) { $teile[] = $t; }
	}
	return trim( implode( ' ', $teile ) );
}

/**
 * FAQPage-Auszeichnung aus den gelesenen Paaren.
 *
 * Die Direktantwort ist der erste Eintrag, wenn es sie gibt — sie steht sichtbar
 * auf der Seite und ist damit eine zulässige FAQ-Antwort.
 *
 * Was das bringt und was nicht: FAQ-Rich-Results zeigt Google seit August 2023
 * nur noch Behörden- und Gesundheitsseiten. Sterne in der Suche sind damit nicht
 * zu holen. Der Nutzen liegt bei den Antwortmaschinen, die den Text auslesen,
 * und beim Featured Snippet, das aus dem *sichtbaren* Frage-Antwort-Paar entsteht.
 *
 * @param array  $aeo Ergebnis von `warleek_aeo_extract()`.
 * @param string $url Adresse der Seite.
 * @return array|null Null, wenn es nichts auszuzeichnen gibt.
 */
function warleek_aeo_faq_ld( array $aeo, $url = '' ) {
	$paare = array();
	// Die Überschrift des Antwortkastens kommt nur mit, wenn sie wirklich eine
	// Frage ist. Bei Datenbank-Einträgen lautet sie „X in Wardogs: Preis, …“ und
	// spiegelt damit die Suchanfrage – als `Question` ausgezeichnet wäre sie falsch.
	if ( ! empty( $aeo['frage'] ) && ! empty( $aeo['antwort'] ) && str_ends_with( $aeo['frage'], '?' ) ) {
		$paare[] = array( 'frage' => $aeo['frage'], 'antwort' => $aeo['antwort'] );
	}
	foreach ( (array) ( $aeo['faq'] ?? array() ) as $p ) {
		if ( ! is_array( $p ) || empty( $p['frage'] ) || empty( $p['antwort'] ) ) { continue; }
		$paare[] = $p;
	}
	if ( ! $paare ) { return null; }

	$fragen = array();
	foreach ( $paare as $p ) {
		$fragen[] = array(
			'@type'          => 'Question',
			'name'           => $p['frage'],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $p['antwort'] ),
		);
	}
	$ld = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'inLanguage' => 'de-DE', 'mainEntity' => $fragen );
	if ( $url ) { $ld['@id'] = $url . '#faq'; $ld['url'] = $url; }
	return $ld;
}

/**
 * Sprachausgabe: genau der Antwortkasten, nichts sonst.
 *
 * Ein Assistent, der die halbe Seite vorliest, ist nutzlos. Googles
 * Unterstützung für `speakable` ist auf Nachrichten beschränkt, der Aufwand
 * aber zwei Zeilen — und andere Vorleser halten sich ebenfalls daran.
 *
 * @return array
 */
function warleek_aeo_speakable() {
	return array( '@type' => 'SpeakableSpecification', 'cssSelector' => array( '.wl-antwort' ) );
}
