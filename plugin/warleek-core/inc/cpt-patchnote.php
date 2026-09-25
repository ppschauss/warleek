<?php
/**
 * Warleek — CPT „patchnote" (automatisch aus Steam importiert).
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function warleek_register_patchnote_cpt() {
	register_post_type( 'patchnote', array(
		'labels' => array(
			'name' => 'Patch Notes', 'singular_name' => 'Patch Note', 'add_new_item' => 'Patch Note anlegen',
			'edit_item' => 'Patch Note bearbeiten', 'menu_name' => 'Patch Notes', 'all_items' => 'Alle Patch Notes',
			'not_found' => 'Noch keine Patch Notes importiert.',
		),
		'description'  => 'Offizielle WARDOGS-Patch-Notes, stündlich von Steam synchronisiert.',
		'public'       => true,
		'has_archive'  => 'patch-notes',
		'rewrite'      => array( 'slug' => 'patch-notes', 'with_front' => false ),
		'menu_icon'    => 'dashicons-update',
		'menu_position'=> 22,
		'show_in_rest' => true,
		'capability_type' => 'post',
		'supports'     => array( 'title', 'editor', 'excerpt' ),
	) );

	foreach ( array(
		'steam_gid'          => 'string',
		'steam_url'          => 'string',
		'steam_published_at' => 'integer',
	) as $key => $type ) {
		register_post_meta( 'patchnote', $key, array(
			'type' => $type, 'single' => true, 'show_in_rest' => true,
			'sanitize_callback' => 'integer' === $type ? 'absint' : 'sanitize_text_field',
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
}
add_action( 'init', 'warleek_register_patchnote_cpt' );

/**
 * Quellenhinweis mit Link zur Steam-Ankündigung.
 *
 * @param int $post_id
 * @return string HTML oder ''.
 */
function warleek_patchnote_source_link( $post_id ) {
	$url = get_post_meta( $post_id, 'steam_url', true );
	if ( ! $url ) { return ''; }
	return '<p class="wl-source">Quelle: offizielle <a href="' . esc_url( $url ) . '" rel="noopener nofollow" target="_blank">Steam-Ankündigung</a> des WARDOGS-Entwicklers. Warleek ist ein unabhängiges Fan-Projekt; Inhalte der Patch Notes gehören dem Entwickler.</p>';
}

/** Patch Notes im Archiv nach Steam-Veröffentlichung sortieren (= post_date, vom Sync gesetzt). */
function warleek_patchnote_archive_order( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) { return; }
	if ( $query->is_post_type_archive( 'patchnote' ) ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
		$query->set( 'posts_per_page', 12 );
	}
}
add_action( 'pre_get_posts', 'warleek_patchnote_archive_order' );
