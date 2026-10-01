<?php
/**
 * Warleek — Inhaltstyp „Item": Waffen, Fahrzeuge, Emplacements, Bauwerke.
 *
 * Bewusst kein Nachbau fremder Datenbanken: Der Wert entsteht dadurch, dass
 * jeder Eintrag auf Deutsch steht, auf die Guides zeigt, die ihn einordnen,
 * und offen sagt, woher seine Zahlen kommen und ob sie im Spiel nachgeprüft sind.
 *
 * Die Einträge werden **terminiert** angelegt (post_status `future`) und von
 * WordPress selbst nach und nach veröffentlicht – siehe `warleek_item_termine()`.
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Felder je Eintrag: Schlüssel => [Beschriftung, Einheit/Format]. */
function warleek_item_felder() {
	return array(
		'preis'         => array( 'Preis', 'dollar' ),
		'freischaltung' => array( 'Freischaltung', 'text' ),
		'freischaltkosten' => array( 'Freischaltgebühr (einmalig)', 'dollar' ),
		'kaliber'       => array( 'Kaliber', 'text' ),
		'sitze'         => array( 'Sitzplätze', 'zahl' ),
		'tempo'         => array( 'Höchstgeschwindigkeit', 'kmh' ),
		'baukosten'     => array( 'Baukosten', 'build' ),
		'rolle'         => array( 'Rolle', 'text' ),
		'hinweis'       => array( 'Hinweis', 'text' ),
	);
}

function warleek_register_item() {
	register_post_type( 'item', array(
		'labels' => array(
			'name'          => 'Datenbank',
			'singular_name' => 'Eintrag',
			'add_new_item'  => 'Neuer Datenbank-Eintrag',
			'edit_item'     => 'Eintrag bearbeiten',
			'search_items'  => 'Einträge durchsuchen',
			'not_found'     => 'Keine Einträge gefunden.',
		),
		'public'        => true,
		'show_in_rest'  => true,
		'menu_icon'     => 'dashicons-database',
		'menu_position' => 26,
		'has_archive'   => 'datenbank',
		'rewrite'       => array( 'slug' => 'datenbank', 'with_front' => false ),
		'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'custom-fields' ),
		'taxonomies'    => array( 'item-typ' ),
	) );

	register_taxonomy( 'item-typ', 'item', array(
		'labels'            => array( 'name' => 'Kategorien', 'singular_name' => 'Kategorie' ),
		'public'            => true,
		'hierarchical'      => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
		'rewrite'           => array( 'slug' => 'datenbank-kategorie', 'with_front' => false ),
	) );

	foreach ( array_keys( warleek_item_felder() ) as $key ) {
		register_post_meta( 'item', 'item_' . $key, array(
			'type' => 'string', 'single' => true, 'show_in_rest' => true,
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
	foreach ( array( 'quellen', 'geprueft' ) as $key ) {
		register_post_meta( 'item', '_warleek_' . $key, array(
			'type' => 'string', 'single' => true, 'show_in_rest' => false,
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		) );
	}
}
add_action( 'init', 'warleek_register_item' );

/**
 * Einen Wert für die Anzeige formatieren.
 *
 * @param string $key  Feldschlüssel.
 * @param string $wert Roher Wert.
 * @return string
 */
function warleek_item_wert( $key, $wert ) {
	$wert = trim( (string) $wert );
	if ( '' === $wert ) { return ''; }
	$felder = warleek_item_felder();
	switch ( $felder[ $key ][1] ?? 'text' ) {
		case 'dollar':
			return is_numeric( $wert ) ? '$' . number_format( (float) $wert, 0, ',', '.' ) : $wert;
		case 'kmh':
			return is_numeric( $wert ) ? number_format( (float) $wert, 0, ',', '.' ) . ' km/h' : $wert;
		case 'build':
			return is_numeric( $wert ) ? number_format( (float) $wert, 0, ',', '.' ) . ' Build-Supplies' : $wert;
		default:
			return $wert;
	}
}

/** Datenblatt eines Eintrags als Tabelle. */
function warleek_item_tabelle( $post_id = 0 ) {
	$post_id = $post_id ?: get_the_ID();
	$zeilen  = '';
	foreach ( warleek_item_felder() as $key => $def ) {
		$wert = warleek_item_wert( $key, get_post_meta( $post_id, 'item_' . $key, true ) );
		if ( '' === $wert ) { continue; }
		$zeilen .= '<tr><th scope="row">' . esc_html( $def[0] ) . '</th><td>' . esc_html( $wert ) . '</td></tr>';
	}
	if ( ! $zeilen ) { return ''; }
	return '<figure class="wp-block-table wl-datenblatt"><table><caption class="screen-reader-text">Werte zu ' . esc_html( get_the_title( $post_id ) ) . '</caption><tbody>' . $zeilen . '</tbody></table></figure>';
}
add_shortcode( 'warleek_datenblatt', function ( $atts ) {
	$a = shortcode_atts( array( 'id' => 0 ), $atts, 'warleek_datenblatt' );
	return warleek_item_tabelle( (int) $a['id'] );
} );

/** Herkunftskasten, wie bei den Guides. */
add_shortcode( 'warleek_item_herkunft', function () {
	$id  = get_the_ID();
	$q   = get_post_meta( $id, '_warleek_quellen', true );
	$g   = get_post_meta( $id, '_warleek_geprueft', true );
	$arr = $q ? array_map( 'trim', explode( '|', (string) $q ) ) : array();
	return warleek_guide_herkunft( array( 'quellen' => $arr, 'geprueft' => $g, 'bild' => warleek_bildquelle( $id ) ) );
} );

/** Sortierung im Archiv: nach Kategorie, dann alphabetisch. */
function warleek_item_archive_order( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) { return; }
	if ( $q->is_post_type_archive( 'item' ) || $q->is_tax( 'item-typ' ) ) {
		$q->set( 'orderby', array( 'title' => 'ASC' ) );
		$q->set( 'posts_per_page', 60 );
	}
}
add_action( 'pre_get_posts', 'warleek_item_archive_order' );

/** Eigene Spalten in der Übersicht – ohne sie sieht man im Backend nur Titel. */
add_filter( 'manage_item_posts_columns', function ( $cols ) {
	$neu = array();
	foreach ( $cols as $k => $v ) {
		$neu[ $k ] = $v;
		if ( 'title' === $k ) {
			$neu['wl_preis']    = 'Preis';
			$neu['wl_frei']     = 'Freischaltung';
			$neu['wl_geprueft'] = 'Geprüft';
		}
	}
	return $neu;
} );
add_action( 'manage_item_posts_custom_column', function ( $col, $id ) {
	if ( 'wl_preis' === $col )    { echo esc_html( warleek_item_wert( 'preis', get_post_meta( $id, 'item_preis', true ) ) ?: '–' ); }
	if ( 'wl_frei' === $col )     { echo esc_html( get_post_meta( $id, 'item_freischaltung', true ) ?: '–' ); }
	if ( 'wl_geprueft' === $col ) {
		$g = (string) get_post_meta( $id, '_warleek_geprueft', true );
		echo ( '' === $g || 'nein' === strtolower( $g ) ) ? '—' : esc_html( $g );
	}
}, 10, 2 );

/* ---------------------------------------------------- Datenbank-Blöcke */

/**
 * Tabelle aller veröffentlichten Einträge, nach Kategorie gruppiert.
 *
 * Eine Tabelle statt Karten: Bei Werten wie Preis und Freischaltung will man
 * vergleichen, nicht blättern. Auf schmalen Bildschirmen scrollt sie waagerecht
 * in ihrem eigenen Kasten, statt die Seite zu sprengen.
 */
function warleek_render_item_tabelle( $attrs = array() ) {
	$typ = '';
	if ( is_tax( 'item-typ' ) ) { $t = get_queried_object(); $typ = $t->slug ?? ''; }
	if ( ! empty( $attrs['typ'] ) ) { $typ = sanitize_title( $attrs['typ'] ); }

	$args = array( 'post_type' => 'item', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' );
	if ( $typ ) { $args['tax_query'] = array( array( 'taxonomy' => 'item-typ', 'field' => 'slug', 'terms' => $typ ) ); }
	$q = new WP_Query( $args );
	if ( ! $q->posts ) {
		return '<p class="wl-empty">Hier ist noch nichts veröffentlicht. Die Datenbank wächst täglich – schau morgen wieder vorbei.</p>';
	}

	// Nach Kategorie gruppieren, damit die Tabelle lesbar bleibt.
	$gruppen = array();
	foreach ( $q->posts as $p ) {
		$terms = get_the_terms( $p, 'item-typ' );
		$name  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : 'Sonstiges';
		$gruppen[ $name ][] = $p;
	}
	ksort( $gruppen );

	$out = '';
	foreach ( $gruppen as $name => $posts ) {
		// Nur Spalten zeigen, die in dieser Gruppe überhaupt Werte haben.
		$spalten = array();
		foreach ( warleek_item_felder() as $key => $def ) {
			if ( 'hinweis' === $key ) { continue; }
			foreach ( $posts as $p ) {
				if ( '' !== (string) get_post_meta( $p->ID, 'item_' . $key, true ) ) { $spalten[ $key ] = $def[0]; break; }
			}
		}
		$kopf = '<tr><th scope="col">Name</th>';
		foreach ( $spalten as $label ) { $kopf .= '<th scope="col">' . esc_html( $label ) . '</th>'; }
		$kopf .= '<th scope="col">Geprüft</th></tr>';

		$zeilen = '';
		foreach ( $posts as $p ) {
			$zeilen .= '<tr><th scope="row"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></th>';
			foreach ( array_keys( $spalten ) as $key ) {
				$zeilen .= '<td>' . esc_html( warleek_item_wert( $key, get_post_meta( $p->ID, 'item_' . $key, true ) ) ?: '–' ) . '</td>';
			}
			$g = (string) get_post_meta( $p->ID, '_warleek_geprueft', true );
			$ja = ( '' !== $g && 'nein' !== strtolower( $g ) );
			$zeilen .= '<td>' . ( $ja ? '<span class="wl-ja" title="' . esc_attr( $g ) . '">ja</span>' : '<span class="wl-nein" title="Aus Community-Quellen, im Spiel nicht nachgeprüft">nein</span>' ) . '</td></tr>';
		}
		$out .= '<h2 class="wl-tabelle__titel">' . esc_html( $name ) . ' <span>' . count( $posts ) . '</span></h2>'
			. '<figure class="wp-block-table wl-tabelle"><table><thead>' . $kopf . '</thead><tbody>' . $zeilen . '</tbody></table></figure>';
	}
	wp_reset_postdata();
	return '<div class="wl-datenbank">' . $out . '</div>';
}

/** Filterleiste über der Tabelle. */
function warleek_render_item_filter() {
	$terms = get_terms( array( 'taxonomy' => 'item-typ', 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) || ! $terms ) { return ''; }
	$aktuell = is_tax( 'item-typ' ) ? ( get_queried_object()->slug ?? '' ) : '';
	$out = '<nav class="wl-chips" aria-label="Kategorien der Datenbank"><a class="wl-chip' . ( $aktuell ? '' : ' is-active' ) . '" href="' . esc_url( get_post_type_archive_link( 'item' ) ) . '">Alle</a>';
	foreach ( $terms as $t ) {
		$out .= '<a class="wl-chip' . ( $aktuell === $t->slug ? ' is-active' : '' ) . '" href="' . esc_url( get_term_link( $t ) ) . '">' . esc_html( $t->name ) . ' <span>' . (int) $t->count . '</span></a>';
	}
	return $out . '</nav>';
}

/** Kurze Liste verwandter Einträge (unter einem Eintrag). */
function warleek_render_item_liste( $attrs = array() ) {
	$args = array( 'post_type' => 'item', 'posts_per_page' => (int) ( $attrs['count'] ?? 8 ), 'orderby' => 'rand', 'post_status' => 'publish' );
	if ( ! empty( $attrs['exclude_current'] ) && is_singular( 'item' ) ) { $args['post__not_in'] = array( get_the_ID() ); }
	if ( ! empty( $attrs['typ_current'] ) && is_singular( 'item' ) ) {
		$terms = get_the_terms( get_the_ID(), 'item-typ' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'item-typ', 'field' => 'slug', 'terms' => $terms[0]->slug ) );
		}
	}
	$q = new WP_Query( $args );
	if ( ! $q->posts ) { return ''; }
	$out = '<ul class="wl-itemliste">';
	foreach ( $q->posts as $p ) {
		$preis = warleek_item_wert( 'preis', get_post_meta( $p->ID, 'item_preis', true ) );
		$out  .= '<li><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a>'
			. ( $preis ? ' <span>' . esc_html( $preis ) . '</span>' : '' ) . '</li>';
	}
	wp_reset_postdata();
	return $out . '</ul>';
}

add_action( 'init', function () {
	register_block_type( 'warleek/item-tabelle', array(
		'api_version' => 3, 'title' => 'Datenbank-Tabelle', 'category' => 'warleek', 'icon' => 'editor-table',
		'render_callback' => 'warleek_render_item_tabelle',
		'attributes' => array( 'typ' => array( 'type' => 'string', 'default' => '' ) ),
	) );
	register_block_type( 'warleek/item-filter', array(
		'api_version' => 3, 'title' => 'Datenbank-Filter', 'category' => 'warleek', 'icon' => 'filter',
		'render_callback' => 'warleek_render_item_filter',
	) );
	register_block_type( 'warleek/item-liste', array(
		'api_version' => 3, 'title' => 'Datenbank-Liste', 'category' => 'warleek', 'icon' => 'list-view',
		'render_callback' => 'warleek_render_item_liste',
		'attributes' => array(
			'count' => array( 'type' => 'number', 'default' => 8 ),
			'typ_current' => array( 'type' => 'boolean', 'default' => false ),
			'exclude_current' => array( 'type' => 'boolean', 'default' => false ),
		),
	) );
} );
add_shortcode( 'warleek_datenbank', function ( $a ) { return warleek_render_item_tabelle( (array) $a ); } );
