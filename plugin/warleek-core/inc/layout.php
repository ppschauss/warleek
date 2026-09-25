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
