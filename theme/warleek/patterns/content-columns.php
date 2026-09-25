<?php
/**
 * Title: Karten-Spalten
 * Slug: warleek/content-columns
 * Categories: warleek
 */
if ( ! function_exists( 'warleek_build_columns' ) ) { return; }
echo warleek_build_columns( array(
	array( 'title' => 'FOB', 'html' => '<p>Vorgeschobene Basis bauen, versorgen, halten.</p>', 'url' => '/wardogs/fob/', 'cta' => 'FOB-Guide' ),
	array( 'title' => 'Logistik', 'html' => '<p>Paletten fahren, Geld verdienen, Team am Leben halten.</p>', 'url' => '/wardogs/logistik/', 'cta' => 'Logistik-Guide' ),
	array( 'title' => 'Gameplay', 'html' => '<p>Zone, drei Teams, Economy – so tickt Wardogs.</p>', 'url' => '/wardogs/gameplay/', 'cta' => 'Gameplay-Guide' ),
) );
