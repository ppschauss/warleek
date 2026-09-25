<?php
/**
 * Title: Seiten-Hero
 * Slug: warleek/page-hero
 * Categories: warleek
 * Description: Kompakter Hero für Unterseiten (Bild, Eyebrow, H1, Lead).
 */
if ( ! function_exists( 'warleek_build_hero' ) ) { return; }
echo warleek_build_hero( array(
	'page'    => true,
	'eyebrow' => 'Wardogs Guide',
	'h1'      => 'Seitentitel mit <em>Akzent</em>',
	'lead'    => 'Ein Satz, der erklärt, worum es auf dieser Seite geht.',
) );
