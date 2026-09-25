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
