<?php
/**
 * Title: Discord-Aufruf
 * Slug: warleek/chat-cta
 * Categories: warleek
 * Description: Kurzer Kasten mit dem Discord-Button – für Rückfragen zu Guides.
 */
if ( ! function_exists( 'warleek_build_cta' ) ) { return; }
echo warleek_build_cta( array(
	'eyebrow' => 'Fragen?',
	'h2'      => 'Was im Guide fehlt, klären wir im Discord',
	'text'    => 'Rückfragen, Korrekturen, Themenwünsche – und Leute für den Abend.',
) );
?>
<!-- wp:group {"className":"wl-cta__chat","layout":{"type":"default"}} -->
<div class="wp-block-group wl-cta__chat"><!-- wp:warleek/chat-buttons {"layout":"row"} /--></div>
<!-- /wp:group -->
