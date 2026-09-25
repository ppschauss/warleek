<?php
/**
 * Title: Zahlen-Leiste
 * Slug: warleek/stats
 * Categories: warleek
 */
if ( ! function_exists( 'warleek_build_stats' ) ) { return; }
echo warleek_build_stats( array(
	array( 'num' => '100', 'label' => 'Spieler pro Match' ),
	array( 'num' => '3', 'label' => 'Teams' ),
	array( 'num' => 'DACH', 'label' => 'deutschsprachig' ),
	array( 'num' => '24/7', 'label' => 'Discord' ),
) );
