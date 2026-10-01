<?php
/**
 * Warleek — Datenbank-Einträge im Fließtext verlinken.
 *
 * Nennt ein Guide die „AK-74", bekommt das Wort einen Link auf den Datenbank-
 * Eintrag und ein `title="Wardogs AK-74"`.
 *
 * Die Links entstehen **beim Ausliefern**, nicht im gespeicherten Text. Das hat
 * drei Gründe, die hier festgehalten gehören, weil die naheliegende Lösung
 * (einmal durchlaufen und die Texte umschreiben) genau daran scheitert:
 *
 * 1. Der Installer erkennt an `_warleek_seed_hash`, ob ein Text von Hand
 *    bearbeitet wurde. Ein Werkzeug, das Links hineinschreibt, würde jeden
 *    Beitrag als „bearbeitet" markieren – künftige Inhalts-Updates blieben
 *    stillschweigend aus.
 * 2. Die Datenbank erscheint terminiert, drei Einträge pro Tag. Ein fest
 *    geschriebener Link auf einen noch nicht veröffentlichten Eintrag wäre ein 404.
 * 3. Umbenannte oder gelöschte Einträge hinterließen tote Links.
 *
 * Zur Laufzeit lösen sich alle drei Punkte von selbst: Verlinkt wird nur, was
 * gerade veröffentlicht ist, und Abschalten stellt den Ursprungszustand her.
 *
 * @package warleek-core
 */

defined( 'ABSPATH' ) || exit;

const WARLEEK_AUTOLINK_OPTION = 'warleek_autolink';
const WARLEEK_AUTOLINK_CACHE  = 'warleek_autolink_index';

/** Einstellungen mit Vorgaben. */
function warleek_autolink_opt() {
	$o = (array) get_option( WARLEEK_AUTOLINK_OPTION, array() );
	return array(
		'enabled' => ! isset( $o['enabled'] ) || (bool) $o['enabled'],
		'types'   => isset( $o['types'] ) ? (array) $o['types'] : array( 'guide', 'page', 'patchnote' ),
		'max'     => isset( $o['max'] ) ? max( 1, (int) $o['max'] ) : 8,
		'aus'     => isset( $o['aus'] ) ? array_map( 'intval', array_filter( (array) $o['aus'] ) ) : array(),
	);
}

/**
 * Begriffe aller veröffentlichten Einträge.
 *
 * Varianten in eckigen Klammern („Humvee [M249]") bekommen zusätzlich ihre
 * Grundform als Begriff – aber nur, wenn es dafür keinen eigenen Eintrag gibt.
 * Sonst stritten „Humvee" und „Humvee [M249]" um dasselbe Wort.
 *
 * @return array Kleinbuchstaben-Begriff => array{titel, url, id}
 */
function warleek_autolink_index() {
	$cache = get_transient( WARLEEK_AUTOLINK_CACHE );
	if ( is_array( $cache ) ) { return apply_filters( 'warleek_autolink_index', $cache ); }

	$posts = get_posts( array(
		'post_type'        => 'item',
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'orderby'          => 'title',
		'order'            => 'ASC',
		'suppress_filters' => false,
	) );

	$titel = array();
	foreach ( $posts as $p ) { $titel[ warleek_autolink_norm( $p->post_title ) ] = true; }

	$index = array();
	foreach ( $posts as $p ) {
		$url  = wp_make_link_relative( get_permalink( $p ) );
		$satz = array( $p->post_title );

		$grund = trim( (string) preg_replace( '/\s*\[[^\]]*\]\s*/', ' ', $p->post_title ) );
		if ( '' !== $grund && $grund !== $p->post_title && empty( $titel[ warleek_autolink_norm( $grund ) ] ) ) {
			$satz[] = $grund;
		}

		foreach ( $satz as $begriff ) {
			$key = warleek_autolink_norm( $begriff );
			if ( '' === $key || isset( $index[ $key ] ) ) { continue; }
			$index[ $key ] = array( 'titel' => $p->post_title, 'url' => $url, 'id' => (int) $p->ID, 'begriff' => $begriff );
		}
	}

	// Längere Begriffe zuerst: „Ural Defender" muss vor „URAL" greifen.
	uksort( $index, function ( $a, $b ) { return mb_strlen( $b ) <=> mb_strlen( $a ) ?: strcmp( $a, $b ); } );

	set_transient( WARLEEK_AUTOLINK_CACHE, $index, 12 * HOUR_IN_SECONDS );
	/**
	 * Begriffsliste der Verlinkung.
	 *
	 * @param array $index Kleinbuchstaben-Begriff => array{titel, url, id, begriff}.
	 */
	return apply_filters( 'warleek_autolink_index', $index );
}

/** Vergleichsform eines Begriffs. */
function warleek_autolink_norm( $s ) {
	return trim( mb_strtolower( (string) $s ) );
}

/** Index verwerfen, sobald sich an den Einträgen etwas ändert. */
function warleek_autolink_cache_leeren( $a = null, $b = null, $post = null ) {
	if ( $post instanceof WP_Post && 'item' !== $post->post_type ) { return; }
	delete_transient( WARLEEK_AUTOLINK_CACHE );
}
add_action( 'transition_post_status', 'warleek_autolink_cache_leeren', 10, 3 );
add_action( 'deleted_post', 'warleek_autolink_cache_leeren' );
add_action( 'warleek_autolink_gespeichert', 'warleek_autolink_cache_leeren' );

/**
 * Verlinkt die Begriffe in einem HTML-Text.
 *
 * Ersetzt ausschließlich in Textknoten – nie in Attributen, nie innerhalb
 * vorhandener Links, Code-Blöcke oder Überschriften.
 *
 * @param string $html    Inhalt.
 * @param int    $eigen   Beitrag, der gerade angezeigt wird (verlinkt sich nicht selbst).
 * @param int    $treffer Zählt die gesetzten Links (Ausgabeparameter).
 * @return string
 */
function warleek_autolink_html( $html, $eigen = 0, &$treffer = 0 ) {
	$opt   = warleek_autolink_opt();
	$index = warleek_autolink_index();
	if ( ! $index ) { return $html; }

	foreach ( $index as $key => $e ) {
		if ( in_array( (int) $e['id'], $opt['aus'], true ) ) { unset( $index[ $key ] ); continue; }
		if ( $eigen && (int) $e['id'] === (int) $eigen ) { unset( $index[ $key ] ); }
	}
	if ( ! $index ) { return $html; }

	$muster = '#(?<![\p{L}\p{N}_\-])(' . implode( '|', array_map( function ( $k ) use ( $index ) {
		return preg_quote( $index[ $k ]['begriff'], '#' );
	}, array_keys( $index ) ) ) . ')(?![\p{L}\p{N}_\-])#ui';

	$benutzt = array();
	$gesetzt = 0;
	$max     = $opt['max'];

	$teile = preg_split( '#(<[^>]*>)#', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	$tief  = 0;
	foreach ( $teile as $i => $teil ) {
		if ( '' === $teil ) { continue; }
		if ( '<' === $teil[0] ) {
			// Überschriften bleiben frei: ein Link in der Zwischenüberschrift
			// zieht den Blick weg vom Text, den er gliedern soll.
			if ( preg_match( '#^<(/?)(a|code|pre|script|style|textarea|h[1-6])\b#i', $teil, $m ) ) {
				$tief = max( 0, $tief + ( '/' === $m[1] ? -1 : 1 ) );
			}
			continue;
		}
		if ( $tief > 0 || $gesetzt >= $max ) { continue; }

		$teile[ $i ] = preg_replace_callback( $muster, function ( $m ) use ( $index, &$benutzt, &$gesetzt, $max ) {
			$key = warleek_autolink_norm( $m[1] );
			if ( $gesetzt >= $max || isset( $benutzt[ $key ] ) || ! isset( $index[ $key ] ) ) { return $m[0]; }

			// Eigennamen beginnen groß. So wird aus einem kleingeschriebenen
			// „tor" in laufendem Text kein Link auf das Bauwerk.
			$erst = mb_substr( $m[1], 0, 1 );
			if ( preg_match( '/\p{L}/u', $erst ) && mb_strtoupper( $erst ) !== $erst ) { return $m[0]; }

			$benutzt[ $key ] = true;
			$gesetzt++;
			$e = $index[ $key ];
			return sprintf(
				'<a class="wl-dblink" href="%s" title="%s">%s</a>',
				esc_url( $e['url'] ),
				esc_attr( 'Wardogs ' . $e['titel'] ),
				$m[0]
			);
		}, $teile[ $i ] );
	}

	$treffer = $gesetzt;
	return implode( '', $teile );
}

/** Filter am ausgelieferten Inhalt. */
function warleek_autolink_content( $content ) {
	// Nicht `in_the_loop()`: im Block-Theme rendert `core/post-content` außerhalb
	// der klassischen Schleife. Stattdessen prüfen, ob gerade der abgefragte
	// Beitrag gerendert wird – Karten und Auszüge bleiben so unberührt.
	if ( is_admin() || ! is_singular() || (int) get_the_ID() !== (int) get_queried_object_id() ) { return $content; }
	$opt = warleek_autolink_opt();
	if ( ! $opt['enabled'] ) { return $content; }

	$typ = get_post_type();
	if ( ! in_array( $typ, $opt['types'], true ) ) { return $content; }

	return warleek_autolink_html( $content, (int) get_the_ID() );
}
add_filter( 'the_content', 'warleek_autolink_content', 15 );

/* ===================================================================== Werkzeug */

/** Beitragstypen, die das Werkzeug anbietet. */
function warleek_autolink_typen() {
	return array(
		'guide'     => 'Guides',
		'page'      => 'Seiten',
		'patchnote' => 'Patch Notes',
		'post'      => 'Beiträge',
	);
}

/**
 * Durchsucht die Inhalte und meldet, was verlinkt würde.
 *
 * Läuft über denselben Weg wie die Ausgabe – was hier steht, ist also keine
 * Schätzung, sondern das Ergebnis.
 *
 * @return array{eintraege: array, beitraege: int, summe: int}
 */
function warleek_autolink_scan() {
	$opt   = warleek_autolink_opt();
	$index = warleek_autolink_index();
	$typen = array_values( array_intersect( $opt['types'], array_keys( warleek_autolink_typen() ) ) );
	if ( ! $typen || ! $index ) { return array( 'eintraege' => array(), 'beitraege' => 0, 'summe' => 0 ); }

	$posts = get_posts( array(
		'post_type'      => $typen,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	// Pro Eintrag zählen, nicht pro Lauf: hier interessiert, wo ein Begriff
	// überhaupt vorkommt – nicht, ob er am Deckel „Links je Beitrag" scheitert.
	$zaehler = array();
	foreach ( $index as $key => $e ) {
		$zaehler[ $e['id'] ] = array( 'titel' => $e['titel'], 'url' => $e['url'], 'id' => $e['id'], 'treffer' => 0, 'wo' => array() );
	}

	$muster = array();
	foreach ( $index as $key => $e ) {
		$muster[ $key ] = '#(?<![\p{L}\p{N}_\-])(' . preg_quote( $e['begriff'], '#' ) . ')(?![\p{L}\p{N}_\-])#ui';
	}

	$mit = 0;
	foreach ( $posts as $id ) {
		$roh = (string) get_post_field( 'post_content', $id, 'raw' );
		if ( '' === $roh ) { continue; }
		$text = wp_strip_all_tags( strip_shortcodes( $roh ) );
		$hier = 0;
		foreach ( $muster as $key => $m ) {
			$e = $index[ $key ];
			if ( (int) $e['id'] === (int) $id ) { continue; }
			$n = preg_match_all( $m, $text, $tr );
			if ( ! $n ) { continue; }
			// Kleingeschriebene Fundstellen zählen nicht – wie bei der Ausgabe.
			$n = count( array_filter( $tr[1], function ( $w ) {
				$erst = mb_substr( $w, 0, 1 );
				return ! preg_match( '/\p{L}/u', $erst ) || mb_strtoupper( $erst ) === $erst;
			} ) );
			if ( ! $n ) { continue; }
			$zaehler[ $e['id'] ]['treffer'] += $n;
			if ( count( $zaehler[ $e['id'] ]['wo'] ) < 5 ) {
				$zaehler[ $e['id'] ]['wo'][ $id ] = get_the_title( $id );
			}
			$hier += $n;
		}
		if ( $hier ) { $mit++; }
	}

	$summe = 0;
	foreach ( $zaehler as $z ) { $summe += $z['treffer']; }
	uasort( $zaehler, function ( $a, $b ) { return $b['treffer'] <=> $a['treffer'] ?: strcmp( $a['titel'], $b['titel'] ); } );

	return array( 'eintraege' => $zaehler, 'beitraege' => $mit, 'summe' => $summe );
}

/** Formular speichern. */
function warleek_autolink_speichern() {
	if ( empty( $_POST['warleek_autolink_save'] ) || ! current_user_can( 'manage_options' ) ) { return false; }
	check_admin_referer( 'warleek_autolink' );

	$typen = array_keys( warleek_autolink_typen() );
	update_option( WARLEEK_AUTOLINK_OPTION, array(
		'enabled' => ! empty( $_POST['enabled'] ),
		'types'   => array_values( array_intersect( $typen, array_map( 'sanitize_key', (array) ( $_POST['types'] ?? array() ) ) ) ),
		'max'     => max( 1, min( 100, (int) ( $_POST['max'] ?? 8 ) ) ),
		'aus'     => array_values( array_unique( array_filter( array_map( 'intval', (array) ( $_POST['aus'] ?? array() ) ) ) ) ),
	), false );
	do_action( 'warleek_autolink_gespeichert' );
	return true;
}

/** Werkzeug-Seite im Dashboard. */
function warleek_render_autolink_tab() {
	$gespeichert = warleek_autolink_speichern();
	$opt         = warleek_autolink_opt();
	$scan        = warleek_autolink_scan();
	$geplant     = (int) wp_count_posts( 'item' )->future;
	?>
	<?php if ( $gespeichert ) : ?>
		<div class="notice notice-success is-dismissible"><p>Gespeichert.</p></div>
	<?php endif; ?>

	<h2 style="margin-top:0">Was das Werkzeug tut</h2>
	<p class="description" style="max-width:62em">
		Nennt ein Text einen Gegenstand aus der Datenbank, bekommt das Wort einen Link auf den Eintrag
		und den Titel <code>Wardogs <em>Name</em></code>. Verlinkt wird die <strong>erste</strong> Fundstelle je
		Gegenstand – nie in Überschriften, nie in Code, nie innerhalb vorhandener Links, und kein Eintrag auf sich selbst.
		Kleingeschriebene Fundstellen bleiben stehen: aus „durch das tor" wird kein Link auf das Bauwerk „Tor".
	</p>
	<p class="description" style="max-width:62em">
		Die Links entstehen <strong>beim Anzeigen</strong>, nicht im gespeicherten Text. Deine Beiträge bleiben
		unverändert, Ausschalten stellt den alten Zustand her – und es kann kein Link auf einen Eintrag zeigen,
		der noch gar nicht veröffentlicht ist.
		<?php if ( $geplant ) : ?>
			Zurzeit stehen <strong><?php echo (int) $geplant; ?> Einträge</strong> noch in der Warteschlange;
			sie werden mitverlinkt, sobald sie erscheinen.
		<?php endif; ?>
	</p>

	<form method="post">
		<?php wp_nonce_field( 'warleek_autolink' ); ?>
		<input type="hidden" name="warleek_autolink_save" value="1">

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Verlinkung</th>
				<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $opt['enabled'] ); ?>> eingeschaltet</label></td>
			</tr>
			<tr>
				<th scope="row">Wo</th>
				<td>
					<?php foreach ( warleek_autolink_typen() as $slug => $label ) : ?>
						<label style="margin-right:14px"><input type="checkbox" name="types[]" value="<?php echo esc_attr( $slug ); ?>"
							<?php checked( in_array( $slug, $opt['types'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
					<?php endforeach; ?>
					<p class="description">Die Datenbank-Einträge selbst sind nie dabei – sie verlinken einander über „Weiter in der Datenbank".</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wl-al-max">Links je Beitrag</label></th>
				<td>
					<input type="number" id="wl-al-max" name="max" min="1" max="100" value="<?php echo esc_attr( $opt['max'] ); ?>" class="small-text">
					<p class="description">Deckel gegen Linkteppiche. Ein Guide, der zwanzig Waffen aufzählt, bekommt sonst zwanzig Links.</p>
				</td>
			</tr>
		</table>

		<h2>Fundstellen</h2>
		<p>
			<strong><?php echo (int) $scan['summe']; ?></strong> Fundstellen in
			<strong><?php echo (int) $scan['beitraege']; ?></strong> Beiträgen.
			Verlinkt wird davon die jeweils erste je Gegenstand und Beitrag.
		</p>

		<table class="widefat striped" style="max-width:62em">
			<thead><tr>
				<th style="width:34%">Eintrag</th>
				<th style="width:10%">Fundstellen</th>
				<th>Gefunden in</th>
				<th style="width:14%">nicht verlinken</th>
			</tr></thead>
			<tbody>
			<?php foreach ( $scan['eintraege'] as $e ) :
				$aus = in_array( (int) $e['id'], $opt['aus'], true ); ?>
				<tr<?php echo $e['treffer'] ? '' : ' style="opacity:.55"'; ?>>
					<td><a href="<?php echo esc_url( get_edit_post_link( $e['id'] ) ); ?>"><?php echo esc_html( $e['titel'] ); ?></a></td>
					<td><?php echo $e['treffer'] ? (int) $e['treffer'] : '–'; ?></td>
					<td><?php
						echo $e['wo'] ? esc_html( implode( ', ', array_slice( $e['wo'], 0, 5 ) ) ) : '<span style="color:#646970">—</span>';
						?></td>
					<td><label><input type="checkbox" name="aus[]" value="<?php echo (int) $e['id']; ?>" <?php checked( $aus ); ?>>
						<span class="screen-reader-text"><?php echo esc_html( $e['titel'] ); ?> nicht verlinken</span></label></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $scan['eintraege'] ) : ?>
				<tr><td colspan="4">Noch keine veröffentlichten Datenbank-Einträge – es gibt nichts zu verlinken.
					<?php if ( $geplant ) : ?>Die ersten <?php echo (int) $geplant; ?> sind geplant.<?php endif; ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>

		<?php submit_button( 'Speichern' ); ?>
	</form>
	<?php
}
