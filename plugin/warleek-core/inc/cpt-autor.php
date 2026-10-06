<?php
/**
 * Warleek — CPT „autor": die Redaktionsrollen hinter den Guides.
 *
 * Jeder Guide trägt eine Rolle als Verfasser: LogisticLeek schreibt über
 * Nachschub, MedicLeek über Rettung, TechLeek über Einstellungen. Das macht die
 * Seite lesbarer („von wem ist das?") und ist zugleich das, was Antwortmaschinen
 * zur Einordnung brauchen: Ein benannter, erreichbarer Verfasser mit einem
 * Schwerpunkt wiegt mehr als eine anonyme Organisation.
 *
 * **Die Grenze, die hier eingehalten wird.** Diese Rollen sind Pseudonyme einer
 * Redaktion, nicht mehrere unabhängige Fachleute. Deshalb:
 *
 * - Jede Autorenseite sagt selbst, dass es eine Redaktionsrolle ist. Wer das
 *   verschweigt, behauptet eine Redaktionsgröße, die es nicht gibt.
 * - `author` ist die Rolle, `reviewedBy` bleibt die reale Person aus den
 *   Optionen. Drei Pseudonyme, die sich gegenseitig „geprüft" bescheinigen,
 *   wären dieselbe kleine Unwahrheit wie ein falscher „selbst getestet"-Vermerk.
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Satz, der auf jeder Autorenseite steht. Nicht wegkonfigurierbar. */
const WARLEEK_AUTOR_HINWEIS = 'Eine Redaktionsrolle von Warleek: Unter diesem Namen erscheinen die Guides zu einem Schwerpunkt. Verantwortlich für alle Inhalte ist der im Impressum genannte Betreiber.';

function warleek_register_autor_cpt() {
	register_post_type( 'autor', array(
		'labels' => array(
			'name' => 'Autoren', 'singular_name' => 'Autor', 'add_new_item' => 'Neue Rolle anlegen',
			'edit_item' => 'Rolle bearbeiten', 'menu_name' => 'Autoren', 'all_items' => 'Alle Rollen',
			'not_found' => 'Noch keine Rollen angelegt.',
		),
		'description'   => 'Redaktionsrollen, unter denen Guides erscheinen.',
		'public'        => true,
		'show_in_rest'  => true,
		'has_archive'   => false,
		'rewrite'       => array( 'slug' => 'autor', 'with_front' => false ),
		'menu_icon'     => 'dashicons-admin-users',
		'menu_position' => 24,
		'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
	) );

	$meta = array(
		'autor_rolle'       => 'Kurzbezeichnung des Schwerpunkts, z. B. „Logistik und Nachschub"',
		'autor_schwerpunkt' => 'Themen, mit Komma getrennt – wird zu knowsAbout',
	);
	foreach ( $meta as $key => $beschreibung ) {
		register_post_meta( 'autor', $key, array(
			'type' => 'string', 'single' => true, 'show_in_rest' => true,
			'description' => $beschreibung,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}

	// Welche Rolle einen Guide verfasst hat – als Slug, damit Umbenennungen der
	// Anzeige nicht die Zuordnung zerreißen.
	register_post_meta( 'guide', '_warleek_autor', array(
		'type' => 'string', 'single' => true, 'show_in_rest' => false,
		'sanitize_callback' => 'sanitize_title',
		'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
	) );
}
add_action( 'init', 'warleek_register_autor_cpt' );

/**
 * Autorenseite zu einem Slug finden.
 *
 * @param string $slug Slug der Rolle.
 * @return WP_Post|null
 */
function warleek_autor_finden( $slug ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) { return null; }
	$treffer = get_posts( array(
		'post_type'        => 'autor',
		'name'             => $slug,
		'posts_per_page'   => 1,
		'post_status'      => 'publish',
		'suppress_filters' => false,
	) );
	return $treffer ? $treffer[0] : null;
}

/**
 * Die Rolle eines Guides, oder null.
 *
 * `$post` darf eine ID oder ein WP_Post sein – `get_post_meta()` nimmt nur die
 * ID, und ein durchgereichtes Objekt liefert stillschweigend nichts zurück.
 *
 * @param int|WP_Post $post Beitrag.
 * @return WP_Post|null
 */
function warleek_guide_autor( $post = 0 ) {
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;
	$id = $id ?: (int) get_the_ID();
	if ( ! $id ) { return null; }
	return warleek_autor_finden( (string) get_post_meta( $id, '_warleek_autor', true ) );
}

/**
 * Verfasserzeile unter dem Guide-Titel.
 *
 * @param int $post Beitrag.
 * @return string
 */
function warleek_render_autor_zeile( $post = 0 ) {
	$post  = $post ?: get_the_ID();
	$autor = warleek_guide_autor( $post );
	if ( ! $autor ) { return ''; }

	$rolle = trim( (string) get_post_meta( $autor->ID, 'autor_rolle', true ) );
	$bild  = get_the_post_thumbnail( $autor, 'thumbnail', array( 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async' ) );

	return '<p class="wl-verfasser">'
		. ( $bild ? '<span class="wl-verfasser__bild">' . $bild . '</span>' : '' )
		. '<span class="wl-verfasser__text">Von <a href="' . esc_url( get_permalink( $autor ) ) . '" rel="author">'
		. esc_html( get_the_title( $autor ) ) . '</a>'
		. ( $rolle ? ' <span class="wl-verfasser__rolle">' . esc_html( $rolle ) . '</span>' : '' )
		. '</span></p>';
}
add_shortcode( 'warleek_autor', function () { return warleek_render_autor_zeile(); } );

/**
 * Die Rolle als Person für die Auszeichnung.
 *
 * `jobTitle` trägt den Schwerpunkt, `knowsAbout` die Themen. Kein `sameAs` und
 * keine erfundene Vita: Was hier steht, steht auch auf der Autorenseite.
 *
 * @param WP_Post|null $autor Rolle.
 * @return array|null
 */
function warleek_autor_person_ld( $autor ) {
	if ( ! $autor instanceof WP_Post ) { return null; }
	$person = array(
		'@type' => 'Person',
		'name'  => get_the_title( $autor ),
		'url'   => get_permalink( $autor ),
	);
	$rolle = trim( (string) get_post_meta( $autor->ID, 'autor_rolle', true ) );
	if ( $rolle ) { $person['jobTitle'] = $rolle; }

	$themen = array_values( array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $autor->ID, 'autor_schwerpunkt', true ) ) ) ) );
	if ( $themen ) { $person['knowsAbout'] = $themen; }

	$person['worksFor'] = array( '@type' => 'Organization', 'name' => 'Warleek', 'url' => home_url( '/' ) );
	if ( has_post_thumbnail( $autor ) ) { $person['image'] = get_the_post_thumbnail_url( $autor, 'medium' ); }
	return $person;
}

/**
 * Autorenseiten bekommen den Rollen-Hinweis ans Textende gestellt.
 *
 * Als Filter statt im Inhalt gespeichert, damit er nicht wegeditiert werden kann –
 * er ist die Bedingung dafür, dass ein Pseudonym als Verfasser auftreten darf.
 */
add_filter( 'the_content', function ( $html ) {
	if ( ! is_singular( 'autor' ) || ! in_the_loop() || ! is_main_query() ) { return $html; }
	return $html . '<p class="wl-note wl-note--klein"><strong>Hinweis</strong> ' . esc_html( WARLEEK_AUTOR_HINWEIS ) . '</p>';
}, 20 );

/** Guides einer Rolle – für die Liste auf der Autorenseite. */
function warleek_render_autor_guides( $attrs = array() ) {
	$a = shortcode_atts( array( 'count' => 12 ), (array) $attrs, 'warleek_autor_guides' );
	if ( ! is_singular( 'autor' ) ) { return ''; }
	$slug = get_post_field( 'post_name', get_queried_object_id() );

	$q = new WP_Query( array(
		'post_type'      => 'guide',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $a['count'],
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'meta_query'     => array( array( 'key' => '_warleek_autor', 'value' => $slug ) ),
	) );
	if ( ! $q->have_posts() ) { return '<p>Von dieser Rolle ist noch kein Guide veröffentlicht.</p>'; }

	$out = '<ul class="wl-autorguides">';
	foreach ( $q->posts as $g ) {
		$out .= '<li><a href="' . esc_url( get_permalink( $g ) ) . '">' . esc_html( get_the_title( $g ) ) . '</a></li>';
	}
	wp_reset_postdata();
	return $out . '</ul>';
}
add_shortcode( 'warleek_autor_guides', 'warleek_render_autor_guides' );
