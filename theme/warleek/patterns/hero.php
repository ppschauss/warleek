<?php
/**
 * Title: Hero (Startseite)
 * Slug: warleek/hero
 * Categories: warleek
 * Description: Großer Hero mit Bild/Video, Eyebrow, H1, Lead, Buttons und Dogtag-Zeile.
 */
echo warleek_build_hero( array(
	'home'    => true,
	'eyebrow' => 'Wardogs Community · Deutschland · Österreich · Schweiz',
	'h1'      => 'Deine Wardogs <em>Community</em> im deutschsprachigen Raum',
	'lead'    => 'Community, Feierabend-Team und Clan für WARDOGS – 100 Spieler, drei Teams, eine Zone. Wir spielen zusammen, nicht nebeneinander.',
	'buttons' => array( array( 'label' => 'Discord beitreten', 'url' => '/wardogs-discord/' ), array( 'label' => 'Community kennenlernen', 'url' => '/community/', 'ghost' => true ) ),
	'tags'    => array( 'Warleek // DACH // seit 2026' ),
) );
