<?php
/**
 * Warleek — Block-Markup-Builder.
 * Werden von Patterns (Beispieltexte) und vom Seed (echte Inhalte) genutzt, damit das
 * Markup nur an einer Stelle liegt. Ergebnis sind ausschließlich Core-Blöcke + Warleek-Blöcke,
 * d. h. alles bleibt im Block-Editor bearbeitbar.
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** JSON für Block-Kommentare (ohne Slash-Escaping). */
function warleek_battr( array $attrs ) {
	return $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';
}

/* ------------------------------------------------------------ Primitives */
function warleek_b_paragraph( $html, $class = '' ) {
	$a = $class ? array( 'className' => $class ) : array();
	return '<!-- wp:paragraph' . warleek_battr( $a ) . ' --><p' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>' . $html . '</p><!-- /wp:paragraph -->' . "\n";
}
function warleek_b_heading( $html, $level = 2, $class = '' ) {
	$a = array();
	if ( 2 !== $level ) { $a['level'] = $level; }
	if ( $class ) { $a['className'] = $class; }
	$cls = trim( 'wp-block-heading ' . $class );
	return '<!-- wp:heading' . warleek_battr( $a ) . ' --><h' . $level . ' class="' . esc_attr( $cls ) . '">' . $html . '</h' . $level . '><!-- /wp:heading -->' . "\n";
}
function warleek_b_list( array $items, $ordered = false ) {
	$a   = $ordered ? array( 'ordered' => true ) : array();
	$tag = $ordered ? 'ol' : 'ul';
	$out = '<!-- wp:list' . warleek_battr( $a ) . ' --><' . $tag . ' class="wp-block-list">';
	foreach ( $items as $it ) { $out .= '<!-- wp:list-item --><li>' . $it . '</li><!-- /wp:list-item -->'; }
	return $out . '</' . $tag . '><!-- /wp:list -->' . "\n";
}
function warleek_b_buttons( array $buttons, $justify = '' ) {
	$a = array( 'layout' => array( 'type' => 'flex', 'flexWrap' => 'wrap' ) );
	if ( $justify ) { $a['layout']['justifyContent'] = $justify; }
	$out = '<!-- wp:buttons' . warleek_battr( $a ) . ' --><div class="wp-block-buttons">';
	foreach ( $buttons as $b ) {
		$ghost = ! empty( $b['ghost'] );
		$ba    = $ghost ? array( 'className' => 'is-style-ghost' ) : array();
		$out  .= '<!-- wp:button' . warleek_battr( $ba ) . ' --><div class="wp-block-button' . ( $ghost ? ' is-style-ghost' : '' ) . '"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $b['url'] ) . '">' . esc_html( $b['label'] ) . '</a></div><!-- /wp:button -->';
	}
	return $out . '</div><!-- /wp:buttons -->' . "\n";
}
/** Gruppe (optional alignfull) mit constrained Layout. */
function warleek_b_group( $inner, array $opts = array() ) {
	$class = $opts['class'] ?? '';
	$a     = array();
	if ( ! empty( $opts['tag'] ) ) { $a['tagName'] = $opts['tag']; }
	if ( ! empty( $opts['full'] ) ) { $a['align'] = 'full'; }
	elseif ( ! empty( $opts['wide_align'] ) ) { $a['align'] = 'wide'; }
	if ( $class ) { $a['className'] = $class; }
	$a['layout'] = array( 'type' => $opts['layout'] ?? 'constrained' );
	// Keine festen Pixelbreiten ins Markup schreiben: sonst überstimmt der Block die
	// globalen Layout-Breiten (theme.json / CSS-Variablen) und der Text klebt für immer
	// auf der Breite, die beim Anlegen galt.
	if ( ! empty( $opts['content'] ) ) { $a['layout']['contentSize'] = $opts['content']; }
	if ( ! empty( $opts['wide'] ) ) { $a['layout']['wideSize'] = $opts['wide']; }
	$tag = $opts['tag'] ?? 'div';
	$cls = trim( 'wp-block-group ' . ( ! empty( $opts['full'] ) ? 'alignfull ' : ( ! empty( $opts['wide_align'] ) ? 'alignwide ' : '' ) ) . $class );
	return '<!-- wp:group' . warleek_battr( $a ) . ' --><' . $tag . ' class="' . esc_attr( $cls ) . '">' . "\n" . $inner . '</' . $tag . '><!-- /wp:group -->' . "\n";
}
function warleek_b_image( array $img, $class = '', $size = 'large' ) {
	if ( empty( $img['url'] ) ) { return ''; }
	$a = array( 'id' => (int) ( $img['id'] ?? 0 ), 'sizeSlug' => $size, 'linkDestination' => 'none' );
	if ( $class ) { $a['className'] = $class; }
	if ( empty( $a['id'] ) ) { unset( $a['id'] ); }
	$cls     = trim( 'wp-block-image size-' . $size . ' ' . $class );
	$caption = trim( (string) ( $img['caption'] ?? '' ) );
	$fig     = $caption ? '<figcaption class="wp-element-caption">' . wp_kses_post( $caption ) . '</figcaption>' : '';
	return '<!-- wp:image' . warleek_battr( $a ) . ' --><figure class="' . esc_attr( $cls ) . '"><img src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( $img['alt'] ?? '' ) . '"' . ( ! empty( $a['id'] ) ? ' class="wp-image-' . $a['id'] . '"' : '' ) . '/>' . $fig . '</figure><!-- /wp:image -->' . "\n";
}
function warleek_b_columns( array $columns, $class = '', $wide = true ) {
	$a = array();
	if ( $wide ) { $a['align'] = 'wide'; }
	if ( $class ) { $a['className'] = $class; }
	$out = '<!-- wp:columns' . warleek_battr( $a ) . ' --><div class="' . esc_attr( trim( 'wp-block-columns ' . ( $wide ? 'alignwide ' : '' ) . $class ) ) . '">';
	foreach ( $columns as $c ) {
		$ca = array();
		if ( is_array( $c ) ) { $ca = $c['attrs'] ?? array(); $c = $c['inner']; }
		$out .= '<!-- wp:column' . warleek_battr( $ca ) . ' --><div class="wp-block-column' . ( ! empty( $ca['className'] ) ? ' ' . esc_attr( $ca['className'] ) : '' ) . '">' . $c . '</div><!-- /wp:column -->';
	}
	return $out . '</div><!-- /wp:columns -->' . "\n";
}
function warleek_b_shortcode( $sc ) {
	return '<!-- wp:shortcode -->' . $sc . '<!-- /wp:shortcode -->' . "\n";
}
function warleek_b_block( $name, array $attrs = array() ) {
	return '<!-- wp:warleek/' . $name . warleek_battr( $attrs ) . ' /-->' . "\n";
}

/* ------------------------------------------------------- HTML → Blöcke */
/**
 * Wandelt einfaches redaktionelles HTML (h2/h3/h4, p, ul/ol, table, blockquote, div.wl-note,
 * figure/img) in Core-Blöcke um. Inline-Markup (a, strong, em, code, br) bleibt erhalten.
 */
/**
 * Bild-Schlüssel im HTML durch echte Mediathek-Adressen ersetzen.
 *
 * Bilder im Fließtext eines Guides stehen in der Markdown-Quelle als
 * `![Alt-Text](guide-fob-layout)` – also als **Asset-Schlüssel** ohne Pfad und
 * ohne Endung. Erst hier, wo die Mediathek bekannt ist, wird daraus eine URL.
 * Ein unbekannter Schlüssel fliegt samt `figure` heraus: lieber eine Lücke als
 * ein kaputtes Bild im Text.
 *
 * Adressen mit Schrägstrich, Protokoll oder Dateiendung bleiben unangetastet –
 * wer im Markdown bewusst eine fertige URL schreibt, bekommt sie auch.
 *
 * @param string $html HTML aus dem Markdown-Konverter.
 * @param array  $map  Asset-Schlüssel => Anhang-ID.
 * @return string
 */
function warleek_resolve_asset_src( $html, array $map ) {
	if ( ! str_contains( $html, '<img' ) ) { return $html; }

	return (string) preg_replace_callback(
		'#<figure>\s*<img src="([^"]+)" alt="([^"]*)">\s*((?:<figcaption>.*?</figcaption>)?)\s*</figure>#s',
		function ( $m ) use ( $map ) {
			$key = $m[1];
			// Fertige Adresse? Dann nichts anfassen.
			if ( preg_match( '#[/:.]#', $key ) ) { return $m[0]; }
			if ( empty( $map[ $key ] ) ) {
				if ( function_exists( 'warleek_log' ) ) { warleek_log( "Bild fehlt in der Mediathek: $key" ); }
				return '';
			}
			$id  = (int) $map[ $key ];
			$url = function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $id ) : '';
			if ( ! $url ) { return ''; }
			return '<figure><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $m[2] ) . '" data-id="' . $id . '">' . $m[3] . '</figure>';
		},
		$html
	);
}

function warleek_html_to_blocks( $html ) {
	if ( '' === trim( $html ) ) { return ''; }
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8"><div id="wl-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$root = $doc->getElementById( 'wl-root' );
	if ( ! $root ) { return warleek_b_paragraph( $html ); }
	$out = '';
	foreach ( $root->childNodes as $node ) {
		$out .= warleek_node_to_block( $node, $doc );
	}
	return $out;
}
function warleek_inner_html( DOMNode $node, DOMDocument $doc ) {
	$h = '';
	foreach ( $node->childNodes as $c ) { $h .= $doc->saveHTML( $c ); }
	return trim( $h );
}
function warleek_node_to_block( DOMNode $node, DOMDocument $doc ) {
	if ( XML_TEXT_NODE === $node->nodeType ) {
		$t = trim( $node->textContent );
		return $t ? warleek_b_paragraph( esc_html( $t ) ) : '';
	}
	if ( XML_ELEMENT_NODE !== $node->nodeType ) { return ''; }
	$tag   = strtolower( $node->nodeName );
	$class = $node->getAttribute( 'class' );
	switch ( $tag ) {
		case 'h2': case 'h3': case 'h4':
			return warleek_b_heading( warleek_inner_html( $node, $doc ), (int) substr( $tag, 1 ), $class );
		case 'p':
			// Ein Absatz, der nur einen Shortcode enthält, wird ein Shortcode-Block –
			// sonst stünde am Ende eine <figure> in einem <p> und das Markup wäre kaputt.
			if ( 'wl-shortcode' === $class ) {
				return '<!-- wp:shortcode -->' . $node->textContent . '<!-- /wp:shortcode -->' . "\n";
			}
			return warleek_b_paragraph( warleek_inner_html( $node, $doc ), $class );
		case 'ul': case 'ol':
			$items = array();
			foreach ( $node->childNodes as $li ) { if ( 'li' === strtolower( $li->nodeName ) ) { $items[] = warleek_inner_html( $li, $doc ); } }
			return warleek_b_list( $items, 'ol' === $tag );
		case 'blockquote':
			$inner = '';
			foreach ( $node->childNodes as $c ) { $inner .= warleek_node_to_block( $c, $doc ); }
			if ( ! $inner ) { $inner = warleek_b_paragraph( warleek_inner_html( $node, $doc ) ); }
			return '<!-- wp:quote --><blockquote class="wp-block-quote">' . $inner . '</blockquote><!-- /wp:quote -->' . "\n";
		case 'table':
			return '<!-- wp:table --><figure class="wp-block-table">' . $doc->saveHTML( $node ) . '</figure><!-- /wp:table -->' . "\n";
		case 'div':
			$inner = '';
			foreach ( $node->childNodes as $c ) { $inner .= warleek_node_to_block( $c, $doc ); }
			return warleek_b_group( $inner, array( 'class' => $class ) );
		case 'figure':
			$img = $node->getElementsByTagName( 'img' )->item( 0 );
			if ( $img ) {
				$cap  = $node->getElementsByTagName( 'figcaption' )->item( 0 );
				return warleek_b_image( array(
					'url'     => $img->getAttribute( 'src' ),
					'alt'     => $img->getAttribute( 'alt' ),
					'id'      => (int) $img->getAttribute( 'data-id' ),
					'caption' => $cap ? warleek_inner_html( $cap, $doc ) : '',
				), $class );
			}
			return '';
		case 'pre':
			// Code-Block: Inhalt bleibt unangetastet, damit Pfade und Registry-Schlüssel stimmen.
			$code = $node->getElementsByTagName( 'code' )->item( 0 );
			$text = $code ? $code->textContent : $node->textContent;
			return '<!-- wp:code --><pre class="wp-block-code"><code>' . esc_html( $text ) . '</code></pre><!-- /wp:code -->' . "\n";
		case 'hr':
			return '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->' . "\n";
		default:
			return warleek_b_paragraph( warleek_inner_html( $node, $doc ), $class );
	}
}

/* ------------------------------------------------------------- Sections */
/**
 * Hero. $a: eyebrow, h1 (HTML, <em> für Lauch-Akzent), lead, buttons[], tags[], image{id,url,alt}, video{id,url,poster}, home(bool), page(bool)
 */
function warleek_build_hero( array $a ) {
	$media = '';
	if ( ! empty( $a['image']['url'] ) ) { $media .= warleek_b_image( $a['image'], 'wl-hero__media', 'full' ); }
	if ( ! empty( $a['video']['url'] ) ) {
		$va = array( 'id' => (int) ( $a['video']['id'] ?? 0 ), 'autoplay' => true, 'loop' => true, 'muted' => true, 'playsInline' => true, 'className' => 'wl-hero__video' );
		if ( ! empty( $a['video']['poster'] ) ) { $va['poster'] = $a['video']['poster']; }
		if ( empty( $va['id'] ) ) { unset( $va['id'] ); }
		$media .= '<!-- wp:video' . warleek_battr( $va ) . ' --><figure class="wp-block-video wl-hero__video"><video autoplay loop muted playsinline' . ( ! empty( $va['poster'] ) ? ' poster="' . esc_url( $va['poster'] ) . '"' : '' ) . ' src="' . esc_url( $a['video']['url'] ) . '"></video></figure><!-- /wp:video -->' . "\n";
	}
	$inner = '';
	if ( ! empty( $a['eyebrow'] ) ) { $inner .= warleek_b_paragraph( esc_html( $a['eyebrow'] ), 'wl-eyebrow' ); }
	$inner .= warleek_b_heading( $a['h1'], 1 );
	if ( ! empty( $a['lead'] ) ) { $inner .= warleek_b_paragraph( $a['lead'], 'wl-lead' ); }
	if ( ! empty( $a['buttons'] ) ) { $inner .= warleek_b_buttons( $a['buttons'] ); }
	if ( ! empty( $a['tags'] ) ) {
		$tags = '';
		foreach ( $a['tags'] as $t ) { $tags .= warleek_b_paragraph( esc_html( $t ), 'wl-dogtag' ); }
		$inner .= warleek_b_group( $tags, array( 'class' => 'wl-hero__meta', 'layout' => 'flex' ) );
	}
	$cls = 'wl-hero' . ( ! empty( $a['home'] ) ? ' wl-hero--home' : '' ) . ( ! empty( $a['page'] ) ? ' wl-hero--page' : '' );
	return warleek_b_group( $media . warleek_b_group( $inner, array( 'class' => 'wl-hero__inner' ) ), array( 'class' => $cls, 'full' => true, 'layout' => 'default' ) );
}

/** Allgemeiner CTA. $a: h2, text, buttons[] */
function warleek_build_cta( array $a ) {
	$inner  = ! empty( $a['eyebrow'] ) ? warleek_b_paragraph( esc_html( $a['eyebrow'] ), 'wl-eyebrow' ) : '';
	$inner .= warleek_b_heading( $a['h2'], 2 );
	if ( ! empty( $a['text'] ) ) { $inner .= warleek_b_paragraph( $a['text'], 'wl-lead' ); }
	if ( ! empty( $a['buttons'] ) ) { $inner .= warleek_b_buttons( $a['buttons'], 'center' ); }
	return warleek_b_group( $inner, array( 'class' => 'wl-cta', 'layout' => 'constrained' ) );
}

/** FAQ als Details-Blöcke. $items: [{q, a}] */
function warleek_build_faq( array $items, $heading = 'Häufige Fragen' ) {
	$out = $heading ? warleek_b_heading( esc_html( $heading ), 2 ) : '';
	$det = '';
	foreach ( $items as $it ) {
		$det .= '<!-- wp:details --><details class="wp-block-details"><summary>' . esc_html( $it['q'] ) . '</summary>' . warleek_b_paragraph( $it['a'] ) . '</details><!-- /wp:details -->' . "\n";
	}
	return $out . warleek_b_group( $det, array( 'class' => 'wl-faq', 'layout' => 'default' ) );
}

/** Stats. $stats: [{num, label}] — num darf Suffix enthalten (z. B. "100+"). */
function warleek_build_stats( array $stats ) {
	$out = '';
	foreach ( $stats as $s ) {
		$out .= warleek_b_group(
			warleek_b_paragraph( esc_html( $s['num'] ), 'wl-stat__num' ) . warleek_b_paragraph( esc_html( $s['label'] ), 'wl-stat__label' ),
			array( 'class' => 'wl-stat', 'layout' => 'default' )
		);
	}
	return warleek_b_group( $out, array( 'class' => 'wl-stats', 'layout' => 'default', 'wide_align' => true ) );
}

/** Karten-Spalten. $items: [{title, html, image{id,url,alt}, url}] */
function warleek_build_columns( array $items ) {
	$cols = array();
	foreach ( $items as $it ) {
		$inner = '';
		if ( ! empty( $it['image']['url'] ) ) { $inner .= warleek_b_image( $it['image'], '', 'medium_large' ); }
		$title = ! empty( $it['url'] ) ? '<a href="' . esc_url( $it['url'] ) . '">' . esc_html( $it['title'] ) . '</a>' : esc_html( $it['title'] );
		$inner .= warleek_b_heading( $title, 3 );
		$inner .= warleek_html_to_blocks( $it['html'] ?? '' );
		if ( ! empty( $it['url'] ) && ! empty( $it['cta'] ) ) { $inner .= warleek_b_paragraph( '<a class="wl-more" href="' . esc_url( $it['url'] ) . '">' . esc_html( $it['cta'] ) . ' →</a>' ); }
		$cols[] = $inner;
	}
	return warleek_b_columns( $cols, 'wl-columns' );
}

/** Sektion mit Kopf (eyebrow, h2, text) und beliebigem Inhalt. */
function warleek_build_section( $inner, array $o = array() ) {
	$head = '';
	if ( ! empty( $o['eyebrow'] ) ) { $head .= warleek_b_paragraph( esc_html( $o['eyebrow'] ), 'wl-eyebrow' ); }
	if ( ! empty( $o['h2'] ) ) { $head .= warleek_b_heading( $o['h2'], 2 ); }
	if ( ! empty( $o['text'] ) ) { $head .= warleek_b_paragraph( $o['text'], 'wl-lead' ); }
	if ( $head ) { $head = warleek_b_group( $head, array( 'class' => 'wl-section__head', 'layout' => 'default' ) ); }
	$cls = 'wl-section' . ( ! empty( $o['surface'] ) ? ' wl-section--surface' : '' ) . ( ! empty( $o['class'] ) ? ' ' . $o['class'] : '' );
	return warleek_b_group( $head . $inner, array( 'class' => $cls, 'full' => true ) );
}
