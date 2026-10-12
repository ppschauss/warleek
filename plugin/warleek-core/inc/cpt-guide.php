<?php
/**
 * Warleek — CPT „guide" + Taxonomie „guide-thema".
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function warleek_register_guide_cpt() {
	register_post_type( 'guide', array(
		'labels' => array(
			'name' => 'Guides', 'singular_name' => 'Guide', 'add_new_item' => 'Neuen Guide anlegen',
			'edit_item' => 'Guide bearbeiten', 'menu_name' => 'Guides', 'all_items' => 'Alle Guides',
			'search_items' => 'Guides durchsuchen', 'not_found' => 'Keine Guides gefunden',
		),
		'description'  => 'Wardogs-Guides und Tipps (Einsteiger, FOB, Logistik, Gameplay, Equipment).',
		'public'       => true,
		'has_archive'  => 'guides',
		'rewrite'      => array( 'slug' => 'guides', 'with_front' => false ),
		'menu_icon'    => 'dashicons-book',
		'menu_position'=> 21,
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
	) );

	register_taxonomy( 'guide-thema', 'guide', array(
		'labels' => array( 'name' => 'Themen', 'singular_name' => 'Thema', 'add_new_item' => 'Neues Thema', 'menu_name' => 'Themen' ),
		'public'       => true,
		'hierarchical' => true,
		'show_in_rest' => true,
		'show_admin_column' => true,
		'rewrite'      => array( 'slug' => 'thema', 'with_front' => false ),
	) );
}
add_action( 'init', 'warleek_register_guide_cpt' );

/** Standard-Themen (werden vom Seed angelegt, hier als Referenz für Blöcke/Filter). */
function warleek_guide_themen() {
	return array(
		'einsteiger' => 'Einsteiger',
		'fob'        => 'FOB',
		'logistik'   => 'Logistik',
		'gameplay'   => 'Gameplay',
		'equipment'  => 'Equipment',
	);
}

/**
 * Guide-Archiv und Themen-Archive einheitlich sortieren.
 *
 * Die Angabe `perPage` im Template greift nicht, weil die Abfrage die
 * Hauptabfrage erbt – deshalb hier.
 *
 * 20 Guides je Seite: Bei 12 lagen bereits über die Hälfte auf Seite zwei.
 */
function warleek_guide_archive_order( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) { return; }
	$archiv = $query->is_post_type_archive( 'guide' );
	$thema  = $query->is_tax( 'guide-thema' );
	if ( ! $archiv && ! $thema ) { return; }

	// Zwei verschiedene Fragen, zwei verschiedene Sortierungen:
	//
	// Die Bibliothek unter /guides/ beantwortet „was ist neu?" – also das
	// jüngste zuerst. Eine Themenseite beantwortet „wie fange ich an?" – dort
	// gilt die redaktionelle Reihenfolge aus `menu_order` (einsteiger 1–9,
	// gameplay 10–19 …), die vom Einfachen zum Schwierigen führt. Nach Datum
	// sortiert stünde dort der Satire-Guide vor „Deine erste Runde".
	if ( $archiv ) {
		$query->set( 'orderby', array( 'date' => 'DESC' ) );
	} else {
		$query->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
	}
	$query->set( 'posts_per_page', 20 );
}
add_action( 'pre_get_posts', 'warleek_guide_archive_order' );

/** Guides in der Seitensuche mitführen (Standard wäre nur `post`). */
function warleek_include_guides_in_search( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) { return; }
	if ( ! empty( $_GET['post_type'] ) ) { return; }
	$query->set( 'post_type', array( 'guide', 'page', 'patchnote', 'post' ) );
}
add_action( 'pre_get_posts', 'warleek_include_guides_in_search' );

/**
 * Grobe Lesezeit in Minuten (200 Wörter pro Minute).
 *
 * @param int|WP_Post|null $post
 * @return int Minuten, mindestens 1.
 */
function warleek_reading_time( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) { return 1; }
	$words = str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );
	return max( 1, (int) ceil( $words / 200 ) );
}

/** Lesezeit als Shortcode für Templates: [warleek_lesezeit] */
add_shortcode( 'warleek_lesezeit', function () {
	return is_singular() ? esc_html( warleek_reading_time() . ' Min. Lesezeit' ) : '';
} );
