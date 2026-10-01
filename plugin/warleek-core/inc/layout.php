<?php
/**
 * Warleek — Layout-Breiten.
 * Wird immer geladen, auch neben einem älteren Theme.
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Alte Inhalte von festen Pixelbreiten befreien.
 *
 * Bis Theme 1.0.0 schrieb der Seed `contentSize: 760px` fest in die Gruppen-Blöcke.
 * Diese Angabe schlägt die globalen Layout-Breiten, der Text bliebe also für immer
 * auf 760 px – auch wenn theme.json oder CSS etwas anderes sagen. Beim Rendern
 * entfernen wir sie aus unseren eigenen Sektionen, damit vorhandene Seiten die
 * aktuelle Breite übernehmen, ohne dass jemand alle Seiten neu anlegen muss.
 * Eigene Breiten, die eine Redakteurin im Editor bewusst setzt, bleiben unberührt –
 * betroffen sind nur Blöcke mit unseren Klassen wl-section / wl-hero__inner.
 */
function warleek_unpin_legacy_widths( $parsed ) {
	if ( 'core/group' !== ( $parsed['blockName'] ?? '' ) || empty( $parsed['attrs']['layout'] ) ) { return $parsed; }
	$cls = $parsed['attrs']['className'] ?? '';
	if ( ! str_contains( $cls, 'wl-section' ) && ! str_contains( $cls, 'wl-hero__inner' ) ) { return $parsed; }
	unset( $parsed['attrs']['layout']['contentSize'], $parsed['attrs']['layout']['wideSize'] );
	return $parsed;
}
add_filter( 'render_block_data', 'warleek_unpin_legacy_widths' );

/**
 * 301-Weiterleitungen für Seiten, die es nicht mehr gibt.
 *
 * Die Map füllt `warleek_step_retire()`. Greift nur im 404-Fall, damit echte
 * Inhalte niemals umgeleitet werden.
 */
function warleek_serve_redirects() {
	if ( is_admin() || wp_doing_ajax() || is_robots() ) { return; }
	$map = (array) get_option( 'warleek_redirects', array() );
	if ( ! $map ) { return; }
	$path = trailingslashit( strtok( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '?' ) );
	// Unterverzeichnis-Installationen: Basispfad abziehen.
	$base = trailingslashit( wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/' );
	if ( '/' !== $base && str_starts_with( $path, $base ) ) { $path = '/' . substr( $path, strlen( $base ) ); }
	$target = $map[ $path ] ?? '';
	if ( ! $target || trailingslashit( $target ) === $path ) { return; }
	wp_safe_redirect( home_url( $target ), 301 );
	exit;
}
add_action( 'template_redirect', 'warleek_serve_redirects', 1 );

/**
 * Aktuellen Menüpunkt markieren.
 *
 * Der Navigations-Block vergibt `current-menu-item` nur für Einträge, die auf
 * einen Beitrag oder eine Seite zeigen. Unsere Menüpunkte sind eigene Links
 * („kind": „custom"), also markiert WordPress nichts – im Menü war nie zu sehen,
 * wo man gerade ist. Das holen wir hier nach: Adresse vergleichen, Klasse setzen,
 * `aria-current` mitgeben, damit auch Screenreader es wissen.
 *
 * @param string $html  Gerendertes Block-Markup.
 * @param array  $block Block-Daten.
 * @return string
 */
function warleek_nav_mark_current( $html, $block ) {
	if ( is_admin() || '' === trim( $html ) ) { return $html; }
	$url = $block['attrs']['url'] ?? '';
	if ( '' === $url ) { return $html; }

	$jetzt = trailingslashit( wp_parse_url( add_query_arg( array() ), PHP_URL_PATH ) ?: '/' );
	$ziel  = trailingslashit( wp_parse_url( $url, PHP_URL_PATH ) ?: '/' );
	// Die Startseite darf nicht bei jedem Aufruf mitleuchten.
	if ( '/' === $ziel ) { return $html; }

	if ( $jetzt === $ziel ) {
		$klasse = 'current-menu-item';
		$aria   = ' aria-current="page"';
	} elseif ( 'core/navigation-submenu' === ( $block['blockName'] ?? '' ) && str_starts_with( $jetzt, $ziel ) ) {
		// „Übergeordnet" gilt nur für aufklappbare Punkte. Sonst würde bei
		// /wardogs/technik/ auch der Untermenü-Eintrag „Überblick" (/wardogs/)
		// mitleuchten – zwei Markierungen, von denen eine falsch ist.
		$klasse = 'current-menu-ancestor';
		$aria   = '';
	} else {
		return $html;
	}

	// Klasse an das äußere <li>, aria-current an den Link darin.
	// Der Filter kann für verschachtelte Blöcke mehrfach laufen – nicht doppelt setzen.
	if ( str_contains( $html, $klasse ) ) { return $html; }
	$html = preg_replace( '/class="(wp-block-navigation-item[^"]*)"/', 'class="$1 ' . $klasse . '"', $html, 1 );
	if ( ! str_contains( $html, 'aria-current' ) ) {
		$html = preg_replace( '/<a\s/', '<a' . $aria . ' ', $html, 1 );
	}
	return $html;
}
add_filter( 'render_block_core/navigation-link', 'warleek_nav_mark_current', 10, 2 );
add_filter( 'render_block_core/navigation-submenu', 'warleek_nav_mark_current', 10, 2 );

/* ------------------------------------------------------------ Bildquelle */
/**
 * Quellenangabe des Beitragsbildes. Leer, wenn das Bild keine braucht
 * (eigenes Material); gesetzt bei fremdem, etwa offiziellem Pressematerial.
 *
 * @param int $post_id Beitrag, Standard: der aktuelle.
 * @return string
 */
function warleek_bildquelle( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : (int) get_the_ID();
	$thumb   = $post_id ? get_post_thumbnail_id( $post_id ) : 0;
	if ( ! $thumb ) { return ''; }
	return trim( (string) get_post_meta( $thumb, '_warleek_credit', true ) );
}

add_shortcode( 'warleek_bildquelle', function () {
	$c = warleek_bildquelle();
	return $c ? '<p class="wl-bildquelle"><strong>Bild:</strong> ' . esc_html( $c ) . '</p>' : '';
} );
