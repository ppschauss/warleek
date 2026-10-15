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

/**
 * Wie viele Patch Notes seit der letzten Änderung am Eintrag erschienen sind.
 *
 * Das ist der ehrlichste verfügbare Hinweis auf Veralterung: Wir wissen nicht,
 * ob ein Patch genau diesen Gegenstand angefasst hat – aber wir wissen, dass
 * seitdem etwas passiert ist und niemand nachgesehen hat.
 *
 * @param int $id Beitrag.
 * @return array{anzahl:int, neueste:int}
 */
function warleek_item_patches_seither( $id ) {
	$stand = get_post_modified_time( 'Y-m-d H:i:s', false, $id );
	$q = new WP_Query( array(
		'post_type'      => 'patchnote',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'date_query'     => array( array( 'after' => $stand, 'inclusive' => false ) ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	return array( 'anzahl' => (int) $q->found_posts, 'neueste' => $q->posts ? (int) $q->posts[0] : 0 );
}

/**
 * Änderungsverlauf eines Eintrags als Abschnitt.
 *
 * Zeigt, was sich wann geändert hat – mit dem alten Wert, damit nachvollziehbar
 * bleibt, was früher galt und seit wann es das nicht mehr tut.
 */
function warleek_render_item_verlauf( $id = 0 ) {
	$id = $id ? (int) $id : (int) get_the_ID();
	if ( ! $id ) { return ''; }

	$verlauf = array_filter( (array) get_post_meta( $id, '_warleek_verlauf', true ) );
	$seither = warleek_item_patches_seither( $id );
	if ( ! $verlauf && ! $seither['anzahl'] ) { return ''; }

	$felder = warleek_item_felder();
	$out    = '<div class="wl-verlauf"><h2>Was sich geändert hat</h2>';

	if ( $seither['anzahl'] ) {
		$link = $seither['neueste'] ? get_permalink( $seither['neueste'] ) : get_post_type_archive_link( 'patchnote' );
		$out .= '<p class="wl-verlauf__warnung">' . sprintf(
			/* translators: %s: Zahl der Patch Notes */
			esc_html( _n(
				'Seit der letzten Prüfung dieses Eintrags ist %s Patch Note erschienen. Die Werte können veraltet sein.',
				'Seit der letzten Prüfung dieses Eintrags sind %s Patch Notes erschienen. Die Werte können veraltet sein.',
				$seither['anzahl'],
				'warleek'
			) ),
			'<a href="' . esc_url( $link ) . '">' . (int) $seither['anzahl'] . '</a>'
		) . '</p>';
	}

	if ( $verlauf ) {
		$zeilen = '';
		foreach ( array_reverse( $verlauf ) as $schritt ) {
			$datum = mysql2date( 'd.m.Y', $schritt['zeit'] ?? '' );
			$patch = ! empty( $schritt['patch'] ) ? get_post( (int) $schritt['patch'] ) : null;
			foreach ( (array) ( $schritt['aenderungen'] ?? array() ) as $a ) {
				$label = $felder[ $a['feld'] ][0] ?? $a['feld'];
				$alt   = '' === $a['alt'] ? '—' : warleek_item_wert( $a['feld'], $a['alt'] );
				$neu   = '' === $a['neu'] ? '—' : warleek_item_wert( $a['feld'], $a['neu'] );
				$zeilen .= '<tr><td>' . esc_html( $datum ) . '</td>'
					. '<td>' . esc_html( $label ) . '</td>'
					. '<td><s>' . esc_html( $alt ) . '</s> → <strong>' . esc_html( $neu ) . '</strong></td>'
					. '<td>' . ( $patch ? '<a href="' . esc_url( get_permalink( $patch ) ) . '">' . esc_html( get_the_title( $patch ) ) . '</a>' : '—' ) . '</td></tr>';
			}
		}
		$out .= '<figure class="wp-block-table wl-tabelle"><table><thead><tr>'
			. '<th scope="col">Datum</th><th scope="col">Feld</th><th scope="col">Änderung</th><th scope="col">Nächste Patch Note</th>'
			. '</tr></thead><tbody>' . $zeilen . '</tbody></table></figure>'
			. '<p class="wl-verlauf__fuss">Die Patch Note ist die zeitlich nächste, nicht zwingend die Ursache. Festgehalten wird, wann wir den neuen Wert eingespielt haben.</p>';
	}

	return $out . '</div>';
}
add_shortcode( 'warleek_item_verlauf', function () { return warleek_render_item_verlauf(); } );

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
		// Kurzform, voller Text als Titel: Die Begründungen sind ganze Sätze
		// („noch nicht – übernommen aus der Datenbank von …"). Ausgeschrieben
		// zogen sie jede Zeile der Liste auf Absatzhöhe auseinander.
		$g     = (string) get_post_meta( $id, '_warleek_geprueft', true );
		$stand = warleek_herkunft_pruefstand( $g );
		$kurz  = array( 'aus-quellen' => '—', 'im-spiel-beobachtet' => 'beobachtet', 'im-spiel-geprueft' => 'geprüft' );
		printf( '<span title="%s">%s</span>',
			esc_attr( $g ?: 'Im Spiel nicht nachgeprüft.' ),
			esc_html( $kurz[ $stand['status'] ] ?? '—' ) );
	}
}, 10, 2 );

/* ------------------------------------------------- Prüfstand von Hand pflegen */

/**
 * Die drei Prüfstände, wie sie im Herkunftsnachweis heißen.
 *
 * Bewusst dieselben Schlüssel wie in `warleek_herkunft_pruefstand()`: Eine
 * zweite Benennung für dieselbe Sache liefe irgendwann auseinander.
 */
function warleek_pruefstaende() {
	return array(
		'aus-quellen'         => 'nicht nachgeprüft – aus den genannten Quellen übernommen',
		'im-spiel-beobachtet' => 'im Spiel beobachtet, aber nicht systematisch vermessen',
		'im-spiel-geprueft'   => 'im Spiel am Gegenstand abgeglichen',
	);
}

/**
 * Regulärer Ausdruck für „nicht nachgeprüft".
 *
 * Wird für die Filterliste im Backend als Datenbankabfrage gebraucht. Er muss
 * zu `warleek_herkunft_pruefstand()` passen – `tests/pruefstand-test.php`
 * vergleicht beide über eine Reihe echter Werte, damit das nicht auseinanderläuft.
 */
function warleek_pruefstand_regex_offen() {
	return '^[[:space:]]*($|nein|noch nicht)';
}

/**
 * Gespeicherten Text in Auswahl plus Zusatz zerlegen.
 *
 * Das Feld `_warleek_geprueft` ist Freitext und bleibt es auch: Daraus liest der
 * Herkunftsnachweis den Prüfstand, und im Guide steht der Text wörtlich. Das
 * Formular ist nur ein Werkzeug darauf, kein zweiter Speicherort.
 *
 * @param string $roh Inhalt von `_warleek_geprueft`.
 * @return array{status:string,detail:string}
 */
function warleek_item_pruefstand_teile( $roh ) {
	$roh    = trim( (string) $roh );
	$status = function_exists( 'warleek_herkunft_pruefstand' )
		? warleek_herkunft_pruefstand( $roh )['status']
		: ( '' === $roh ? 'aus-quellen' : 'im-spiel-geprueft' );

	$detail = $roh;
	if ( 'aus-quellen' === $status ) {
		// „nein" und „nein, Preis unklar" → der Zusatz ist das, was dahinter steht.
		$detail = (string) preg_replace( '/^\s*(nein|noch nicht)[\s,;:–-]*/iu', '', $roh );
	} elseif ( 'im-spiel-beobachtet' === $status ) {
		$detail = (string) preg_replace( '/^\s*im spiel beobachtet[\s,;:–-]*/iu', '', $roh );
	}
	return array( 'status' => $status, 'detail' => trim( $detail ) );
}

/**
 * Aus Auswahl und Zusatz wieder den zu speichernden Text bauen.
 *
 * Die Schlüsselwörter sind Absicht: `warleek_herkunft_pruefstand()` erkennt den
 * Stand am Text, nicht an einem zweiten Feld. Wer „geprüft" wählt und nichts
 * dazuschreibt, bekommt das heutige Datum – ein Prüfvermerk ohne Zeitpunkt
 * behauptet mehr, als er belegt.
 *
 * @param string $status Schlüssel aus warleek_pruefstaende().
 * @param string $detail Freier Zusatz (wer, wann, wie).
 * @param string $heute  Datum für den leeren Prüfvermerk.
 * @return string
 */
function warleek_item_pruefstand_bauen( $status, $detail, $heute = '' ) {
	$detail = trim( (string) preg_replace( '/\s+/u', ' ', (string) $detail ) );
	switch ( $status ) {
		case 'im-spiel-geprueft':
			if ( '' === $detail ) { return $heute ?: 'geprüft'; }
			// Ein Zusatz wie „nein, nur Teilwerte" würde den Stand beim Zurücklesen
			// umdeuten; ein vorangestellter Stand fängt das auf, weil „nein" nur am
			// Anfang zählt. Steht dagegen „beobachtet" im Zusatz, bleibt es dabei:
			// Der Text gewinnt, und das ist die **vorsichtigere** der beiden
			// Lesarten. Deshalb zeigt das Formular darunter, was daraus gelesen wird.
			$teile = warleek_item_pruefstand_teile( $detail );
			return 'im-spiel-geprueft' === $teile['status'] ? $detail : 'Im Spiel geprüft, ' . $detail;
		case 'im-spiel-beobachtet':
			return '' === $detail ? 'Im Spiel beobachtet' : 'Im Spiel beobachtet, ' . $detail;
		default:
			return '' === $detail ? 'nein' : 'nein, ' . $detail;
	}
}

/** Quellen stehen als `a | b | c` in einem Feld – im Formular eine je Zeile. */
function warleek_item_quellen_lesen( $id ) {
	return array_values( array_filter( array_map( 'trim', explode( '|', (string) get_post_meta( $id, '_warleek_quellen', true ) ) ) ) );
}

add_action( 'add_meta_boxes_item', function ( $post ) {
	add_meta_box( 'warleek-herkunft', 'Herkunft & Prüfstand', 'warleek_item_herkunft_box', null, 'normal', 'high' );
} );

/** Das Formular. Ohne es wäre der Prüfstand nur per Code änderbar. */
function warleek_item_herkunft_box( $post ) {
	$teile   = warleek_item_pruefstand_teile( get_post_meta( $post->ID, '_warleek_geprueft', true ) );
	$quellen = warleek_item_quellen_lesen( $post->ID );
	wp_nonce_field( 'warleek_herkunft_' . $post->ID, 'warleek_herkunft_nonce' );
	?>
	<p>
		<label for="wl-pruefstand"><strong>Prüfstand</strong></label><br>
		<select name="warleek_pruefstand" id="wl-pruefstand" style="max-width:100%">
			<?php foreach ( warleek_pruefstaende() as $key => $text ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $teile['status'], $key ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="wl-pruefdetail"><strong>Wer und wann</strong></label><br>
		<input type="text" class="large-text" name="warleek_pruefdetail" id="wl-pruefdetail"
			value="<?php echo esc_attr( $teile['detail'] ); ?>"
			placeholder="<?php echo esc_attr( date_i18n( 'd.m.Y' ) . ', AdminLeek' ); ?>">
		<span class="description">Steht so auf der Seite und im Herkunftsnachweis. Bei „geprüft" ohne Angabe wird das heutige Datum eingesetzt – ein Prüfvermerk ohne Zeitpunkt behauptet mehr, als er belegt.</span>
	</p>
	<p>
		<label for="wl-quellen"><strong>Quellen</strong> – eine je Zeile</label><br>
		<textarea name="warleek_quellen" id="wl-quellen" class="large-text code" rows="4"
			placeholder="Steam-Ankündigung von BULKHEAD, 09.10.2026"><?php echo esc_textarea( implode( "\n", $quellen ) ); ?></textarea>
	</p>
	<?php
	$roh   = (string) get_post_meta( $post->ID, '_warleek_geprueft', true );
	$namen = warleek_pruefstaende();
	?>
	<p class="description">
		Gespeichert: <code><?php echo esc_html( $roh ?: '—' ); ?></code><br>
		Wird gelesen als: <strong><?php echo esc_html( $namen[ $teile['status'] ] ?? '—' ); ?></strong>.
		Der Prüfstand steckt im Text selbst, nicht in einem zweiten Feld – ein Zusatz,
		der „beobachtet" enthält, macht daraus den vorsichtigeren Stand.<br>
		Was hier geändert wird, bleibt beim nächsten Inhalts-Update stehen.
	</p>
	<?php
}

/** Speichern. Nonce, Rechte, kein Autosave – sonst leert eine Zwischenspeicherung die Felder. */
function warleek_item_herkunft_speichern( $post_id ) {
	if ( ! isset( $_POST['warleek_herkunft_nonce'] ) ) { return; }
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['warleek_herkunft_nonce'] ) ), 'warleek_herkunft_' . $post_id ) ) { return; }
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( wp_is_post_revision( $post_id ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

	$status = sanitize_key( wp_unslash( $_POST['warleek_pruefstand'] ?? 'aus-quellen' ) );
	if ( ! isset( warleek_pruefstaende()[ $status ] ) ) { $status = 'aus-quellen'; }
	$detail = sanitize_text_field( wp_unslash( $_POST['warleek_pruefdetail'] ?? '' ) );

	update_post_meta( $post_id, '_warleek_geprueft', warleek_item_pruefstand_bauen( $status, $detail, date_i18n( 'd.m.Y' ) ) );

	$quellen = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) wp_unslash( $_POST['warleek_quellen'] ?? '' ) ) ) ) );
	$quellen = array_map( 'sanitize_text_field', $quellen );
	update_post_meta( $post_id, '_warleek_quellen', implode( ' | ', $quellen ) );

	// Die Seed-Marke bleibt absichtlich unberührt: Sie hält den Stand des
	// Installers fest. Weicht der Wert davon ab, gilt der Eintrag als bearbeitet
	// und das nächste Inhalts-Update lässt ihn in Ruhe.
	if ( function_exists( 'warleek_herkunft_cache_leeren' ) ) { warleek_herkunft_cache_leeren(); }
}
add_action( 'save_post_item', 'warleek_item_herkunft_speichern' );

/**
 * Sammelbearbeitung: Prüfstand für mehrere Einträge auf einmal setzen.
 *
 * Bei knapp 200 Einträgen ist das der Weg, der tatsächlich benutzt wird – der
 * Einzeleditor ist für den Eintrag, an dem auch der Zusatz steht.
 */
add_filter( 'bulk_actions-edit-item', function ( $actions ) {
	$actions['wl_geprueft']   = 'Prüfstand: im Spiel geprüft (heute)';
	$actions['wl_beobachtet'] = 'Prüfstand: im Spiel beobachtet';
	$actions['wl_offen']      = 'Prüfstand: nicht nachgeprüft';
	return $actions;
} );

add_filter( 'handle_bulk_actions-edit-item', function ( $redirect, $doaction, $ids ) {
	$karte = array( 'wl_geprueft' => 'im-spiel-geprueft', 'wl_beobachtet' => 'im-spiel-beobachtet', 'wl_offen' => 'aus-quellen' );
	if ( empty( $karte[ $doaction ] ) ) { return $redirect; }

	// Wer prüft, steht in den Optionen. Ohne Namen bleibt es beim Datum – eine
	// erfundene Person wäre genau die Sorte Unwahrheit, gegen die der
	// Herkunftsnachweis gebaut ist.
	$name   = trim( (string) warleek_opt( 'autor_name' ) );
	$detail = 'im-spiel-geprueft' === $karte[ $doaction ]
		? trim( date_i18n( 'd.m.Y' ) . ( $name ? ', ' . $name : '' ) )
		: '';

	$n = 0;
	foreach ( (array) $ids as $id ) {
		if ( ! current_user_can( 'edit_post', $id ) ) { continue; }
		update_post_meta( (int) $id, '_warleek_geprueft', warleek_item_pruefstand_bauen( $karte[ $doaction ], $detail, date_i18n( 'd.m.Y' ) ) );
		$n++;
	}
	if ( function_exists( 'warleek_herkunft_cache_leeren' ) ) { warleek_herkunft_cache_leeren(); }
	return add_query_arg( 'wl_pruefstand_gesetzt', $n, $redirect );
}, 10, 3 );

add_action( 'admin_notices', function () {
	$n = isset( $_GET['wl_pruefstand_gesetzt'] ) ? (int) $_GET['wl_pruefstand_gesetzt'] : 0;
	if ( $n > 0 ) {
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( _n( 'Prüfstand bei %d Eintrag geändert.', 'Prüfstand bei %d Einträgen geändert.', $n ), $n ) ) );
	}
} );

/** Filter über der Liste: zeigt, was noch offen ist. */
add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'item' !== $post_type ) { return; }
	$aktiv = isset( $_GET['wl_pruefstand'] ) ? sanitize_key( $_GET['wl_pruefstand'] ) : '';
	echo '<label class="screen-reader-text" for="wl-filter-pruefstand">Nach Prüfstand filtern</label>';
	echo '<select name="wl_pruefstand" id="wl-filter-pruefstand">';
	echo '<option value="">Prüfstand: alle</option>';
	echo '<option value="offen"' . selected( $aktiv, 'offen', false ) . '>nicht nachgeprüft</option>';
	echo '<option value="geprueft"' . selected( $aktiv, 'geprueft', false ) . '>geprüft oder beobachtet</option>';
	echo '</select>';
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'item' !== $q->get( 'post_type' ) ) { return; }
	$wahl = isset( $_GET['wl_pruefstand'] ) ? sanitize_key( $_GET['wl_pruefstand'] ) : '';
	if ( ! in_array( $wahl, array( 'offen', 'geprueft' ), true ) ) { return; }

	// Gefragt wird am Freitext selbst, nicht an einem zweiten Feld: Ein
	// abgeleitetes Statusfeld wäre eine zweite Wahrheit, die irgendwann abweicht.
	if ( 'offen' === $wahl ) {
		$q->set( 'meta_query', array(
			'relation' => 'OR',
			array( 'key' => '_warleek_geprueft', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_warleek_geprueft', 'value' => warleek_pruefstand_regex_offen(), 'compare' => 'REGEXP' ),
		) );
	} else {
		$q->set( 'meta_query', array(
			array( 'key' => '_warleek_geprueft', 'value' => warleek_pruefstand_regex_offen(), 'compare' => 'NOT REGEXP' ),
		) );
	}
} );

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
			$zeilen .= '<tr><th scope="row"><a href="' . esc_url( get_permalink( $p ) ) . '" title="' . warleek_item_linktitel( $p ) . '">' . esc_html( get_the_title( $p ) ) . '</a></th>';
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

/**
 * Titel-Attribut für einen Link auf einen Eintrag.
 *
 * Dieselbe Form wie bei der automatischen Verlinkung im Fließtext, damit ein
 * Link auf „AK-74" überall dasselbe verspricht.
 */
function warleek_item_linktitel( $post ) {
	return esc_attr( 'Wardogs ' . get_the_title( $post ) );
}

/** Filterleiste über der Tabelle. */
function warleek_render_item_filter() {
	$terms = get_terms( array( 'taxonomy' => 'item-typ', 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) || ! $terms ) { return ''; }
	$aktuell = is_tax( 'item-typ' ) ? ( get_queried_object()->slug ?? '' ) : '';
	$out = '<nav class="wl-chips" aria-label="Kategorien der Datenbank"><a class="wl-chip' . ( $aktuell ? '' : ' is-active' ) . '" href="' . esc_url( get_post_type_archive_link( 'item' ) ) . '" title="Alle Einträge der Wardogs-Datenbank">Alle</a>';
	foreach ( $terms as $t ) {
		$out .= '<a class="wl-chip' . ( $aktuell === $t->slug ? ' is-active' : '' ) . '" href="' . esc_url( get_term_link( $t ) ) . '" title="' . esc_attr( 'Wardogs ' . $t->name ) . '">' . esc_html( $t->name ) . ' <span>' . (int) $t->count . '</span></a>';
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
		// Zweite Zeile: Preis, sonst Baukosten, sonst die Rolle. Jede Karte soll
		// etwas sagen – ein Bauwerk hat keinen Preis, aber Baukosten.
		$wert = warleek_item_wert( 'preis', get_post_meta( $p->ID, 'item_preis', true ) );
		if ( '' === $wert ) { $wert = warleek_item_wert( 'baukosten', get_post_meta( $p->ID, 'item_baukosten', true ) ); }
		if ( '' === $wert ) {
			$frei = (string) get_post_meta( $p->ID, 'item_freischaltung', true );
			// Startwaffen haben weder Preis noch Baukosten – „kostenlos" sagt mehr
			// als die Rolle, die ohnehin eine Zeile tiefer im Datenblatt steht.
			$wert = str_contains( strtolower( $frei ), 'startwaffe' ) ? 'kostenlos' : (string) get_post_meta( $p->ID, 'item_rolle', true );
		}

		// Alt-Text leer: Der Titel steht direkt darunter, sonst liest ein
		// Screenreader denselben Namen zweimal vor.
		$bild = get_the_post_thumbnail( $p, 'medium', array( 'alt' => '', 'loading' => warleek_lazy(), 'decoding' => 'async' ) );

		$out .= '<li class="wl-itemkarte">'
			. '<a class="wl-itemkarte__link" href="' . esc_url( get_permalink( $p ) ) . '" title="' . warleek_item_linktitel( $p ) . '">'
			. ( $bild ? '<span class="wl-itemkarte__bild">' . $bild . '</span>' : '' )
			. '<span class="wl-itemkarte__text">'
			. '<span class="wl-itemkarte__titel">' . esc_html( get_the_title( $p ) ) . '</span>'
			. ( '' !== $wert ? '<span class="wl-itemkarte__wert">' . esc_html( $wert ) . '</span>' : '' )
			. '</span></a></li>';
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
