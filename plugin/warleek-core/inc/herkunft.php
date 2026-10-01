<?php
/**
 * Warleek — maschinenlesbarer Herkunftsnachweis unter `/herkunft.json`.
 *
 * Für jeden veröffentlichten Datenbank-Eintrag: woher die Zahlen stammen, ob
 * sie im Spiel nachgeprüft wurden und von wem, woher das Bild kommt, wann der
 * Eintrag zuletzt geändert wurde.
 *
 * Zwei Entwurfsentscheidungen, die den Unterschied machen:
 *
 * 1. **Erzeugt, nie gepflegt.** Das Dokument entsteht bei jedem Abruf aus den
 *    Daten, die ohnehin an den Beiträgen hängen. Es kann deshalb nicht
 *    veralten – und ein veralteter Herkunftsnachweis wäre schlimmer als keiner,
 *    weil er Genauigkeit behauptet, die er nicht hat.
 * 2. **Keine Selbstbescheinigung.** Hier steht nirgends „verifiziert". Es steht
 *    da, *was* geprüft wurde, *wann* und *von wem* – und bei allem anderen
 *    steht, dass es aus Quellen übernommen ist. Ein Nachweis, den man sich
 *    selbst ausstellt, ist nur dann etwas wert, wenn er auch das Unfertige nennt.
 *
 * Das fälschungssichere Protokoll liegt woanders: im öffentlichen Git-Repo.
 * Jede Wertänderung ist dort ein Commit mit Zeitstempel und Begründung. Dieses
 * Dokument verweist darauf, statt eine eigene Signatur zu erfinden.
 *
 * @package warleek-core
 */

defined( 'ABSPATH' ) || exit;

const WARLEEK_HERKUNFT_CACHE = 'warleek_herkunft_json';

/** Eigene Adresse anmelden. */
function warleek_herkunft_rewrite() {
	add_rewrite_rule( '^herkunft\.json$', 'index.php?warleek_herkunft=1', 'top' );
}
add_action( 'init', 'warleek_herkunft_rewrite' );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'warleek_herkunft';
	return $vars;
} );

/** Zwischenspeicher verwerfen, sobald sich ein Eintrag ändert. */
function warleek_herkunft_cache_leeren( $a = null, $b = null, $post = null ) {
	if ( $post instanceof WP_Post && 'item' !== $post->post_type ) { return; }
	delete_transient( WARLEEK_HERKUNFT_CACHE );
}
add_action( 'transition_post_status', 'warleek_herkunft_cache_leeren', 10, 3 );
add_action( 'deleted_post', 'warleek_herkunft_cache_leeren' );

/**
 * Prüfstand eines Eintrags in maschinenlesbarer Form.
 *
 * @param string $roh Inhalt von `_warleek_geprueft`.
 * @return array{status: string, text: string}
 */
function warleek_herkunft_pruefstand( $roh ) {
	$roh = trim( (string) $roh );
	$tief = strtolower( $roh );
	if ( '' === $roh || str_starts_with( $tief, 'nein' ) || str_starts_with( $tief, 'noch nicht' ) ) {
		return array(
			'status' => 'aus-quellen',
			'text'   => '' === $roh ? 'Im Spiel nicht nachgeprüft.' : $roh,
		);
	}
	if ( str_contains( $tief, 'beobachtet' ) ) {
		return array( 'status' => 'im-spiel-beobachtet', 'text' => $roh );
	}
	return array( 'status' => 'im-spiel-geprueft', 'text' => $roh );
}

/**
 * Das Dokument aufbauen.
 *
 * @return array
 */
function warleek_herkunft_daten() {
	$zwischen = get_transient( WARLEEK_HERKUNFT_CACHE );
	if ( is_array( $zwischen ) ) { return $zwischen; }

	$posts = get_posts( array(
		'post_type'      => 'item',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );

	$eintraege = array();
	$zaehler   = array( 'im-spiel-geprueft' => 0, 'im-spiel-beobachtet' => 0, 'aus-quellen' => 0 );

	foreach ( $posts as $p ) {
		$pruef   = warleek_herkunft_pruefstand( get_post_meta( $p->ID, '_warleek_geprueft', true ) );
		$quellen = array_values( array_filter( array_map( 'trim', explode( '|', (string) get_post_meta( $p->ID, '_warleek_quellen', true ) ) ) ) );
		$zaehler[ $pruef['status'] ] = ( $zaehler[ $pruef['status'] ] ?? 0 ) + 1;

		$werte = array();
		if ( function_exists( 'warleek_item_felder' ) ) {
			foreach ( warleek_item_felder() as $key => $def ) {
				$roh = (string) get_post_meta( $p->ID, 'item_' . $key, true );
				if ( '' !== $roh ) { $werte[ $def[0] ] = warleek_item_wert( $key, $roh ); }
			}
		}

		$eintrag = array(
			'@type'            => 'Dataset',
			'name'             => get_the_title( $p ),
			'url'              => get_permalink( $p ),
			'dateModified'     => get_the_modified_date( 'c', $p ),
			'variableMeasured' => $werte,
			'creativeWorkStatus' => $pruef['text'],
			'wardogs:pruefstand' => $pruef['status'],
		);
		if ( $quellen ) {
			$eintrag['citation'] = array_map( function ( $q ) {
				return filter_var( $q, FILTER_VALIDATE_URL )
					? array( '@type' => 'CreativeWork', 'url' => $q )
					: array( '@type' => 'CreativeWork', 'name' => $q );
			}, $quellen );
		}
		if ( 'aus-quellen' !== $pruef['status'] && function_exists( 'warleek_seo_person_ld' ) ) {
			$person = warleek_seo_person_ld();
			if ( $person ) { $eintrag['reviewedBy'] = $person; }
		}
		$bild = get_post_thumbnail_id( $p );
		if ( $bild ) {
			$credit = trim( (string) get_post_meta( $bild, '_warleek_credit', true ) );
			$eintrag['image'] = array_filter( array(
				'@type'      => 'ImageObject',
				'contentUrl' => wp_get_attachment_image_url( $bild, 'large' ),
				'creditText' => $credit ?: null,
			) );
		}
		$eintraege[] = $eintrag;
	}

	$daten = array(
		'@context' => array(
			'https://schema.org',
			array( 'wardogs' => 'https://warleek.de/ns#' ),
		),
		'@type'       => 'Dataset',
		'@id'         => home_url( '/herkunft.json' ),
		'name'        => 'Warleek – Herkunftsnachweis der Wardogs-Datenbank',
		'description' => 'Für jeden veröffentlichten Eintrag: Quellen, Prüfstand, Bildherkunft und Änderungsdatum. Erzeugt bei jedem Abruf aus den Daten der Website, nicht von Hand gepflegt.',
		'inLanguage'  => 'de-DE',
		'url'         => get_post_type_archive_link( 'item' ),
		'dateCreated' => gmdate( 'c' ),
		'creator'     => array( '@type' => 'Organization', 'name' => 'Warleek', 'url' => home_url( '/' ) ),
		'isBasedOn'   => array(
			'@type'       => 'SoftwareSourceCode',
			'name'        => 'Quellcode und Änderungsprotokoll',
			'codeRepository' => 'https://github.com/ppschauss/warleek',
			'description' => 'Jede Änderung an einem Wert ist dort ein Commit mit Zeitstempel und Begründung – öffentlich nachprüfbar.',
		),
		'wardogs:methode' => array(
			'im-spiel-geprueft'   => 'Wert im Spiel am Gegenstand abgeglichen; Person und Datum stehen am Eintrag.',
			'im-spiel-beobachtet' => 'Im Spiel beobachtet, aber nicht systematisch vermessen.',
			'aus-quellen'         => 'Aus den genannten Quellen übernommen, im Spiel nicht nachgeprüft.',
		),
		'wardogs:hinweis' => 'Early Access: Zahlen ändern sich mit Patches. Ein Eintrag gilt für den Stand, der unter dateModified steht.',
		'wardogs:bilanz'  => $zaehler,
		'size'            => count( $eintraege ) . ' Einträge',
		'hasPart'         => $eintraege,
	);
	$person = function_exists( 'warleek_seo_person_ld' ) ? warleek_seo_person_ld() : false;
	if ( $person ) { $daten['contributor'] = $person; }

	set_transient( WARLEEK_HERKUNFT_CACHE, $daten, HOUR_IN_SECONDS );
	return $daten;
}

/**
 * Kein Schrägstrich am Ende.
 *
 * WordPress hängt an Adressen ohne Dateiendung einen Schrägstrich an und leitet
 * dafür um. Bei `/herkunft.json` ist das falsch: Wer die Datei abruft, soll sie
 * bekommen, nicht einen Umweg über 301.
 */
add_filter( 'redirect_canonical', function ( $ziel ) {
	return get_query_var( 'warleek_herkunft' ) ? false : $ziel;
} );

/** Ausliefern. */
function warleek_herkunft_ausliefern() {
	if ( ! get_query_var( 'warleek_herkunft' ) ) { return; }
	nocache_headers();
	header( 'Content-Type: application/ld+json; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	echo wp_json_encode( warleek_herkunft_daten(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	exit;
}
add_action( 'template_redirect', 'warleek_herkunft_ausliefern' );

/** Im Kopf jeder Seite auffindbar machen. */
add_action( 'wp_head', function () {
	printf(
		'<link rel="alternate" type="application/ld+json" href="%s" title="Herkunftsnachweis der Datenbank">' . "\n",
		esc_url( home_url( '/herkunft.json' ) )
	);
}, 5 );
