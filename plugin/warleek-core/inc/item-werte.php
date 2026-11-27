<?php
/**
 * Warleek — Werte im Fließtext.
 *
 * **Ein Wert steht an einer Stelle.** Das Datenblatt eines Eintrags liest seine
 * Zahlen aus den Meta-Feldern (`item_preis`, `item_freischaltung` …); der
 * Fließtext daneben trug sie bis 2.40.0 ausgeschrieben. Nach einem Patch stand
 * damit in der Tabelle der neue Preis und zwei Absätze darüber der alte – und
 * niemand merkte es, weil beide Stellen für sich stimmig aussahen. Genau so
 * ist in `wardogs-helikopter-fliegen` der Season-2-Preis der AH-6M über eine
 * ganze Season stehen geblieben.
 *
 * Seitdem stehen im Text Platzhalter, die beim **Ausliefern** aufgelöst werden:
 *
 *     Der Scout-Rucksack kostet {preis} …                 (eigener Eintrag)
 *     … gegenüber dem Field für {preis:wardogs-field-backpack}
 *     … der {titel:wardogs-z20-lakota} fasst {sitze:wardogs-z20-lakota} Plätze
 *
 * Aufgelöst wird gegen dieselben Meta-Felder, aus denen das Datenblatt liest,
 * und formatiert mit derselben Funktion (`warleek_item_wert()`). Tabelle und
 * Text können deshalb nicht mehr auseinanderlaufen.
 *
 * **Warum beim Ausliefern und nicht beim Einspielen.** Ein Wert ändert sich im
 * Backend, nicht in der JSON-Datei – dort wird er erst beim nächsten Release
 * nachgezogen. Würden die Platzhalter beim Einspielen ersetzt, wäre jeder Text
 * wieder so alt wie das letzte Update. So genügt eine Zahl im Datenblatt, und
 * jeder Satz auf jeder Seite, der sie nennt, ist in derselben Sekunde richtig.
 *
 * Ein `{preis}` ohne Eintrag bleibt stehen; ein leerer Wert wird zu „–“.
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Platzhalter, die aufgelöst werden. Alles andere in geschweiften Klammern bleibt. */
function warleek_werte_muster() {
	$felder = implode( '|', array_map( 'preg_quote', array_keys( warleek_item_felder() ) ) );
	return '/\{(' . $felder . '|titel|aufpreis)(?::([a-z0-9][a-z0-9-]*))?\}/';
}

/**
 * Beitrags-ID zu einem Eintrags-Slug, einmal je Aufruf nachgeschlagen.
 *
 * Ein Guide, der zehn Preise nennt, würde sonst zehn Abfragen auslösen –
 * und die Datenbankseiten verlinken sich untereinander reichlich.
 */
function warleek_item_id_fuer_slug( $slug ) {
	static $bekannt = array();
	if ( array_key_exists( $slug, $bekannt ) ) { return $bekannt[ $slug ]; }
	$post               = get_page_by_path( $slug, OBJECT, 'item' );
	$bekannt[ $slug ]   = $post ? (int) $post->ID : 0;
	return $bekannt[ $slug ];
}

/**
 * Platzhalter in einem Text durch die Werte aus der Datenbank ersetzen.
 *
 * @param string $text    Beliebiger Text mit Platzhaltern.
 * @param int    $post_id Eintrag für Platzhalter ohne Slug. 0 = aktueller Beitrag.
 * @return string
 */
function warleek_werte_einsetzen( $text, $post_id = 0 ) {
	$text = (string) $text;
	if ( false === strpos( $text, '{' ) ) { return $text; }

	return (string) preg_replace_callback(
		warleek_werte_muster(),
		function ( $m ) use ( $post_id ) {
			$feld = $m[1];
			$slug = $m[2] ?? '';

			if ( '' !== $slug ) {
				$id = warleek_item_id_fuer_slug( $slug );
			} else {
				$id = $post_id ?: (int) get_the_ID();
				// Ein nackter Platzhalter gilt nur auf einem Eintrag. In einem
				// Guide hätte er keinen Bezug – dort muss der Slug dabeistehen.
				if ( $id && 'item' !== get_post_type( $id ) ) { $id = 0; }
			}
			if ( ! $id ) { return $m[0]; }

			if ( 'titel' === $feld ) { return get_the_title( $id ); }

			/*
			 * `{aufpreis:slug}` – der Unterschied zwischen diesem Eintrag und
			 * dem genannten. Ein Satz wie „der Aufpreis beträgt rund $300“ ist
			 * aus zwei Preisen gerechnet und veraltet deshalb doppelt so
			 * schnell wie jeder einzelne von ihnen. Der Betrag steht ohne
			 * Vorzeichen: Welche Richtung gemeint ist, sagt der Satz.
			 */
			if ( 'aufpreis' === $feld ) {
				$eigen = $post_id ?: (int) get_the_ID();
				/*
				 * Bauwerke haben keinen Preis, sondern Baukosten in
				 * Build-Supplies. Beide Seiten müssen dieselbe Einheit tragen –
				 * eine Differenz aus Dollar und Supplies wäre eine Zahl ohne
				 * Bedeutung. Deshalb erst Preis gegen Preis, sonst Baukosten
				 * gegen Baukosten, sonst nichts.
				 */
				foreach ( array( 'preis', 'baukosten' ) as $art ) {
					$hier = (float) get_post_meta( $eigen, 'item_' . $art, true );
					$dort = (float) get_post_meta( $id, 'item_' . $art, true );
					if ( $hier && $dort ) {
						return warleek_item_wert( $art, (string) abs( $dort - $hier ) );
					}
				}
				return '–';
			}

			$wert = warleek_item_wert( $feld, get_post_meta( $id, 'item_' . $feld, true ) );
			return '' === $wert ? '–' : $wert;
		},
		$text
	);
}

/**
 * Beim Ausliefern einsetzen.
 *
 * Priorität 4: vor der Kurzfassung der Patch Notes (5) und lange vor den
 * Anzeigenplätzen (20). Shortcodes laufen später und sehen die fertigen Werte.
 */
add_filter( 'the_content', function ( $inhalt ) {
	return warleek_werte_einsetzen( $inhalt );
}, 4 );

add_filter( 'get_the_excerpt', function ( $auszug, $post = null ) {
	return warleek_werte_einsetzen( $auszug, $post ? (int) $post->ID : 0 );
}, 4, 2 );

/**
 * Wann zuletzt ein Wert dieses Eintrags geändert wurde.
 *
 * Quelle ist `_warleek_verlauf` – dieselbe Liste, aus der „Was sich geändert
 * hat“ entsteht. `post_modified` wäre das falsche Maß: Es springt auch, wenn
 * jemand einen Tippfehler im Text behebt.
 *
 * @param int $id Beitrag.
 * @return string MySQL-Zeitstempel oder ''.
 */
function warleek_item_zuletzt_geaendert( $id ) {
	$verlauf = array_filter( (array) get_post_meta( $id, '_warleek_verlauf', true ), 'is_array' );
	if ( ! $verlauf ) { return ''; }
	$letzter = end( $verlauf );
	return (string) ( $letzter['zeit'] ?? '' );
}

/**
 * Stand eines Eintrags und, wenn es einen gibt, das Aktualisierungsdatum.
 *
 * Steht als `[warleek_item_stand]` im Beitrag statt als ausgeschriebener Satz.
 * Grund ist derselbe wie bei den Platzhaltern: Der Satz „Stand: Oktober 2026“
 * war beim Einspielen richtig und blieb es nicht.
 */
function warleek_item_stand_block( $id = 0 ) {
	$id = $id ?: (int) get_the_ID();
	if ( ! $id ) { return ''; }

	$quellen = (string) get_post_meta( $id, '_warleek_quellen', true );
	$stand   = 'Early Access';
	foreach ( array(
		'Season 03 Patch Notes' => 'November 2026 (Season 3)',
		'Season 02 Patch Notes' => 'Oktober 2026 (Season 2)',
	) as $nadel => $bezeichnung ) {
		if ( false !== strpos( $quellen, $nadel ) ) { $stand = $bezeichnung; break; }
	}
	if ( 'Early Access' === $stand ) {
		$stand = (string) ( get_post_meta( $id, '_warleek_stand', true ) ?: 'September 2026' );
	}

	$out = '<p class="wl-stand">Stand: ' . esc_html( $stand ) . ' · Early Access – Werte ändern sich mit Patches.';

	$geaendert = warleek_item_zuletzt_geaendert( $id );
	if ( $geaendert ) {
		$out .= ' <span class="wl-stand__neu">Zuletzt korrigiert am '
			. esc_html( mysql2date( 'd.m.Y', $geaendert ) ) . '</span>';
	}
	return $out . '</p>';
}
add_shortcode( 'warleek_item_stand', function ( $atts ) {
	$a = shortcode_atts( array( 'id' => 0 ), $atts, 'warleek_item_stand' );
	return warleek_item_stand_block( (int) $a['id'] );
} );
