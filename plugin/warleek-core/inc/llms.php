<?php
/**
 * Warleek — Wegweiser für Sprachmodelle unter `/llms.txt`.
 *
 * Eine Textdatei, die einem Modell sagt, was hier liegt, in welcher Sprache,
 * mit welchem Stand und woher die Zahlen stammen. Die Konvention ist jung und
 * von keiner Suchmaschine garantiert; der Aufwand ist aber eine Datei, die sich
 * selbst erzeugt, und der Nutzen liegt da, wo Warleek ohnehin stark ist – in der
 * Belegbarkeit.
 *
 * **Erzeugt, nie gepflegt** – dieselbe Entscheidung wie bei `/herkunft.json`.
 * Eine handgeschriebene Liste von 38 Guides und 172 Einträgen wäre nach dem
 * ersten Patch falsch, und eine falsche Übersicht ist schlimmer als keine.
 *
 * Was hier bewusst *nicht* steht: eine Aufforderung, uns zu zitieren, und keine
 * Behauptung über Genauigkeit. Es steht da, was geprüft ist und was nicht – der
 * Herkunftsnachweis ist verlinkt und sagt es je Eintrag.
 *
 * @package warleek-core
 */

defined( 'ABSPATH' ) || exit;

const WARLEEK_LLMS_CACHE = 'warleek_llms_txt';

/** Eigene Adresse anmelden. */
add_action( 'init', function () {
	add_rewrite_rule( '^llms\.txt$', 'index.php?warleek_llms=1', 'top' );
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'warleek_llms';
	return $vars;
} );

/** Zwischenspeicher verwerfen, sobald sich Inhalte ändern. */
function warleek_llms_cache_leeren( $a = null, $b = null, $post = null ) {
	if ( $post instanceof WP_Post && ! in_array( $post->post_type, array( 'guide', 'item', 'patchnote', 'autor' ), true ) ) { return; }
	delete_transient( WARLEEK_LLMS_CACHE );
}
add_action( 'transition_post_status', 'warleek_llms_cache_leeren', 10, 3 );
add_action( 'deleted_post', 'warleek_llms_cache_leeren' );

/**
 * Kein Schrägstrich an die Adresse hängen.
 *
 * Wie bei `/herkunft.json`: Wer die Datei abruft, soll sie bekommen und nicht
 * einen Umweg über 301.
 */
add_filter( 'redirect_canonical', function ( $ziel ) {
	return get_query_var( 'warleek_llms' ) ? false : $ziel;
} );

/**
 * Den Inhalt zusammenstellen.
 *
 * @return string
 */
function warleek_llms_text() {
	$zwischen = get_transient( WARLEEK_LLMS_CACHE );
	if ( is_string( $zwischen ) && '' !== $zwischen ) { return $zwischen; }

	$home = untrailingslashit( home_url( '/' ) );
	$z    = array();

	$z[] = '# Warleek';
	$z[] = '';
	$z[] = '> Deutschsprachige Guides, Tipps und eine Gegenstands-Datenbank zum Spiel WARDOGS '
		. '(BULKHEAD / Team17, Early Access). Zielregion: Deutschland, Österreich, Schweiz.';
	$z[] = '';
	$z[] = 'Sprache: Deutsch (de-DE). Stand dieser Übersicht: ' . wp_date( 'd.m.Y H:i' ) . '.';
	$z[] = '';

	/* --- Woher die Angaben stammen: der Kern dessen, was uns zitierfähig macht --- */
	$z[] = '## Herkunft der Angaben';
	$z[] = '';
	$z[] = 'Je Eintrag maschinenlesbar: ' . $home . '/herkunft.json';
	$z[] = '';
	$z[] = 'Dort steht für jeden Datenbank-Eintrag, woher die Werte kommen, ob sie im Spiel';
	$z[] = 'nachgeprüft wurden und von wem, woher das Bild stammt und wann sich etwas geändert hat.';
	$z[] = 'Nicht nachgeprüfte Angaben sind dort als solche ausgewiesen. Spielzahlen tragen einen';
	$z[] = 'Stand-Vermerk, weil sich in einem Early-Access-Spiel Preise und Stufen mit Patches ändern.';
	$z[] = '';
	$z[] = 'Änderungsprotokoll: https://github.com/' . ( defined( 'WARLEEK_UPDATE_REPO' ) ? WARLEEK_UPDATE_REPO : 'ppschauss/warleek' );
	$z[] = '';

	/* --- Guides, nach Thema --- */
	$z[] = '## Guides';
	$z[] = '';
	$themen = get_terms( array( 'taxonomy' => 'guide-thema', 'hide_empty' => true ) );
	if ( ! is_wp_error( $themen ) ) {
		foreach ( $themen as $t ) {
			$guides = get_posts( array(
				'post_type'      => 'guide',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => array( 'menu_order' => 'ASC' ),
				'tax_query'      => array( array( 'taxonomy' => 'guide-thema', 'field' => 'slug', 'terms' => $t->slug ) ),
			) );
			if ( ! $guides ) { continue; }
			$z[] = '### ' . $t->name;
			$z[] = '';
			foreach ( $guides as $g ) {
				$kurz = trim( (string) get_post_field( 'post_excerpt', $g ) );
				$z[]  = sprintf( '- [%s](%s)%s', get_the_title( $g ), get_permalink( $g ), $kurz ? ': ' . $kurz : '' );
			}
			$z[] = '';
		}
	}

	/* --- Datenbank: Zahlen statt 172 Zeilen --- */
	$z[]    = '## Datenbank der Gegenstände';
	$z[]    = '';
	$anzahl = (int) wp_count_posts( 'item' )->publish;
	$z[]    = sprintf( 'Übersicht: %s/datenbank/ (%d veröffentlichte Einträge)', $home, $anzahl );
	$z[]    = '';
	$typen  = get_terms( array( 'taxonomy' => 'item-typ', 'hide_empty' => true ) );
	if ( ! is_wp_error( $typen ) ) {
		foreach ( $typen as $t ) {
			$z[] = sprintf( '- %s (%d): %s', $t->name, (int) $t->count, get_term_link( $t ) );
		}
	}
	$z[] = '';
	$z[] = 'Jeder Eintrag trägt ein Datenblatt (Preis, Freischaltung, Rolle und je nach Art';
	$z[] = 'Kaliber, Sitzplätze, Höchstgeschwindigkeit oder Baukosten), einen Änderungsverlauf';
	$z[] = 'mit Datum und der zeitlich nächsten Patch Note sowie die Herkunft der Angaben.';
	$z[] = 'Zwei Kostenarten werden getrennt gehalten: die einmalige Freischaltgebühr und der';
	$z[] = 'Preis pro Stück bzw. Leben. Quellen vermischen das häufig.';
	$z[] = '';

	/* --- Patch Notes --- */
	$notes = get_posts( array( 'post_type' => 'patchnote', 'post_status' => 'publish', 'posts_per_page' => 5 ) );
	if ( $notes ) {
		$z[] = '## Patch Notes (deutsch)';
		$z[] = '';
		$z[] = 'Übersicht: ' . $home . '/patch-notes/';
		$z[] = '';
		foreach ( $notes as $n ) {
			$z[] = sprintf( '- [%s](%s), %s', get_the_title( $n ), get_permalink( $n ), get_the_date( 'd.m.Y', $n ) );
		}
		$z[] = '';
		$z[] = 'Die Patch Notes sind Übersetzungen der Originalmeldungen bei Steam. Jede Note';
		$z[] = 'verlinkt ihr Original und weist die Übersetzung als automatisch erstellt aus.';
		$z[] = '';
	}

	/* --- Autorenrollen: offen gesagt, was sie sind --- */
	$autoren = get_posts( array( 'post_type' => 'autor', 'post_status' => 'publish', 'posts_per_page' => 50, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
	if ( $autoren ) {
		$z[] = '## Autorenrollen';
		$z[] = '';
		$z[] = 'Die Guides erscheinen unter Redaktionsrollen mit festem Schwerpunkt. Diese Namen';
		$z[] = 'sind Pseudonyme einer Redaktion, nicht mehrere unabhängige Fachleute;';
		$z[] = 'verantwortlich ist der im Impressum genannte Betreiber.';
		$z[] = '';
		foreach ( $autoren as $a ) {
			$rolle = trim( (string) get_post_meta( $a->ID, 'autor_rolle', true ) );
			$z[]   = sprintf( '- %s (%s): %s', get_the_title( $a ), $rolle ?: 'Redaktion', get_permalink( $a ) );
		}
		$z[] = '';
	}

	$z[] = '## Rechtliches';
	$z[] = '';
	$z[] = 'WARDOGS ist ein Spiel von BULKHEAD, veröffentlicht von Team17. Warleek ist eine';
	$z[] = 'unabhängige Fan-Seite und steht in keiner Verbindung zu Entwickler oder Publisher.';
	$z[] = 'Bildmaterial stammt aus eigenen Spielaufnahmen oder den offiziellen Pressekits;';
	$z[] = 'die Herkunft steht am jeweiligen Eintrag.';
	$z[] = '';
	$z[] = 'Impressum: ' . $home . '/impressum/';
	$z[] = 'Datenschutz: ' . $home . '/datenschutz/';

	$text = implode( "\n", $z ) . "\n";
	set_transient( WARLEEK_LLMS_CACHE, $text, 6 * HOUR_IN_SECONDS );
	return $text;
}

/** Ausliefern. */
function warleek_llms_ausliefern() {
	if ( ! get_query_var( 'warleek_llms' ) ) { return; }
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo warleek_llms_text(); // phpcs:ignore WordPress.Security.EscapeOutput -- reiner Text, selbst erzeugt
	exit;
}
add_action( 'template_redirect', 'warleek_llms_ausliefern' );
