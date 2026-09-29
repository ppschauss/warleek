<?php
/**
 * Title: Karten-Spalten
 * Slug: warleek/content-columns
 * Categories: warleek
 */
if ( ! function_exists( 'warleek_build_columns' ) ) { return; }
echo warleek_build_columns( array(
	array( 'title' => 'FOB', 'html' => '<p>Vorgeschobene Basis bauen, versorgen und halten.</p>', 'url' => '/wardogs/fob/', 'cta' => 'Zum FOB-Thema' ),
	array( 'title' => 'Logistik', 'html' => '<p>Paletten fahren, Routen wählen, Cash verdienen.</p>', 'url' => '/wardogs/logistik/', 'cta' => 'Zur Logistik' ),
	array( 'title' => 'Technik', 'html' => '<p>Steuerung, HOTAS und Einstellungen, die etwas ändern.</p>', 'url' => '/wardogs/technik/', 'cta' => 'Zur Technik' ),
) );
