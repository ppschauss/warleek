<?php
/**
 * Title: Hero (Startseite)
 * Slug: warleek/hero
 * Categories: warleek
 * Description: Großer Hero mit Bild, Eyebrow, H1, Lead, Buttons und Dogtag-Zeile.
 */
if ( ! function_exists( 'warleek_build_hero' ) ) { return; }
echo warleek_build_hero( array(
	'home'    => true,
	'eyebrow' => 'Wardogs Guides · Tipps · Patch Notes · Deutsch',
	'h1'      => 'Wardogs verstehen, statt <em>raten</em>',
	'lead'    => 'Deutsche Guides zu FOB, Logistik, Gameplay und Technik – geschrieben von Leuten, die die Fehler schon gemacht haben.',
	'buttons' => array( array( 'label' => 'Zu den Guides', 'url' => '/guides/' ), array( 'label' => 'Das Spiel erklärt', 'url' => '/wardogs/', 'ghost' => true ) ),
	'tags'    => array( 'Warleek // Guides // DACH' ),
) );
