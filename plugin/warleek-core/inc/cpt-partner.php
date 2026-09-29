<?php
/**
 * Warleek — CPT „partner": befreundete Communities, Clans und Kanäle.
 *
 * Partner bekommen bewusst keine eigenen Unterseiten (`publicly_queryable = false`) –
 * sie erscheinen als Karten auf der Partner-Seite und verlinken nach außen.
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function warleek_register_partner_cpt() {
	register_post_type( 'partner', array(
		'labels' => array(
			'name' => 'Partner', 'singular_name' => 'Partner', 'add_new_item' => 'Neuen Partner anlegen',
			'edit_item' => 'Partner bearbeiten', 'menu_name' => 'Partner', 'all_items' => 'Alle Partner',
			'not_found' => 'Noch keine Partner angelegt.',
		),
		'description'         => 'Befreundete Communities, Clans und Kanäle, die auf der Partner-Seite erscheinen.',
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'has_archive'         => false,
		'rewrite'             => false,
		'menu_icon'           => 'dashicons-groups',
		'menu_position'       => 23,
		'supports'            => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
	) );

	$meta = array(
		'partner_url'      => array( 'string', 'esc_url_raw' ),
		'partner_platform' => array( 'string', 'sanitize_text_field' ),
		'partner_tag'      => array( 'string', 'sanitize_text_field' ),
		'partner_featured' => array( 'boolean', 'rest_sanitize_boolean' ),
	);
	foreach ( $meta as $key => $def ) {
		register_post_meta( 'partner', $key, array(
			'type' => $def[0], 'single' => true, 'show_in_rest' => true,
			'sanitize_callback' => $def[1],
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
}
add_action( 'init', 'warleek_register_partner_cpt' );

/** Bekannte Plattformen für das Auswahlfeld und die Karten-Chips. */
function warleek_partner_platforms() {
	return array(
		'discord' => 'Discord',
		'youtube' => 'YouTube',
		'twitch'  => 'Twitch',
		'steam'   => 'Steam-Gruppe',
		'website' => 'Website',
	);
}

/* ----------------------------------------------------- Felder im Backend */
function warleek_partner_meta_box() {
	add_meta_box( 'warleek_partner', 'Partner-Details', 'warleek_partner_meta_box_html', 'partner', 'side', 'high' );
}
add_action( 'add_meta_boxes', 'warleek_partner_meta_box' );

function warleek_partner_meta_box_html( $post ) {
	wp_nonce_field( 'warleek_partner_meta', 'warleek_partner_nonce' );
	$url      = get_post_meta( $post->ID, 'partner_url', true );
	$platform = get_post_meta( $post->ID, 'partner_platform', true );
	$tag      = get_post_meta( $post->ID, 'partner_tag', true );
	$featured = (bool) get_post_meta( $post->ID, 'partner_featured', true );
	echo '<p><label style="display:block;font-weight:600">Link zum Partner</label>'
		. '<input type="url" name="partner_url" value="' . esc_attr( $url ) . '" style="width:100%" placeholder="https://discord.gg/…"></p>';
	echo '<p><label style="display:block;font-weight:600">Plattform</label><select name="partner_platform" style="width:100%">';
	foreach ( warleek_partner_platforms() as $k => $label ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $platform, $k, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select></p>';
	echo '<p><label style="display:block;font-weight:600">Kurz-Label</label>'
		. '<input type="text" name="partner_tag" value="' . esc_attr( $tag ) . '" style="width:100%" placeholder="z. B. Wardogs Clan"></p>';
	echo '<p><label><input type="checkbox" name="partner_featured" value="1"' . checked( $featured, true, false ) . '> Hervorheben</label></p>';
	echo '<p class="description">Die Reihenfolge steuerst du über „Seitenattribute → Reihenfolge".</p>';
}

function warleek_partner_save_meta( $post_id ) {
	if ( ! isset( $_POST['warleek_partner_nonce'] ) || ! wp_verify_nonce( $_POST['warleek_partner_nonce'], 'warleek_partner_meta' ) ) { return; }
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	update_post_meta( $post_id, 'partner_url', isset( $_POST['partner_url'] ) ? esc_url_raw( wp_unslash( $_POST['partner_url'] ) ) : '' );
	update_post_meta( $post_id, 'partner_platform', isset( $_POST['partner_platform'] ) ? sanitize_text_field( wp_unslash( $_POST['partner_platform'] ) ) : '' );
	update_post_meta( $post_id, 'partner_tag', isset( $_POST['partner_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['partner_tag'] ) ) : '' );
	update_post_meta( $post_id, 'partner_featured', ! empty( $_POST['partner_featured'] ) );
}
add_action( 'save_post_partner', 'warleek_partner_save_meta' );
