<?php
/**
 * Title: Drei Stufen (Community · Team · Clan)
 * Slug: warleek/three-tiers
 * Categories: warleek
 */
if ( ! function_exists( 'warleek_build_tiers' ) ) { return; }
echo warleek_build_tiers( array(
	array( 'eyebrow' => 'Stufe 1 · offen', 'title' => 'Community', 'text' => 'Für alle, die Wardogs auf Deutsch spielen wollen. Kein Tag, keine Pflicht.', 'items' => array( 'Discord, WhatsApp, Telegram', 'Mitspieler finden', 'Guides & Patch Notes' ), 'url' => '/community/wardogs-community/', 'cta' => 'Zur Community' ),
	array( 'eyebrow' => 'Stufe 2 · Feierabend', 'title' => 'Team', 'text' => 'Feste Spielabende mit Leuten, die du kennst. Squad statt Zufalls-Lobby.', 'items' => array( 'Feste Abende', 'Squad-Rollen', 'Kein Leistungsdruck' ), 'url' => '/community/wardogs-team/', 'cta' => 'Zum Team' ),
	array( 'eyebrow' => 'Stufe 3 · Clan', 'title' => 'Clan', 'text' => 'Der feste Kern mit Tag, Struktur und Anspruch – für alle, die mehr wollen.', 'items' => array( 'Clan-Tag', 'Taktik & Training', 'Bewerbung' ), 'url' => '/community/wardogs-clan/', 'cta' => 'Zum Clan' ),
) );
