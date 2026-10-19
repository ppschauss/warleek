<?php
/**
 * Warleek — Steam-BBCode → HTML.
 * Reine Funktion ohne WP-Abhängigkeit (Tests: php tests/bbcode-test.php).
 * Unterstützt h1–h3, b, i, u, strike, list/olist, url, img, quote, code, hr, previewyoutube.
 * Unbekannte Tags werden entfernt, HTML im Rohtext wird escaped.
 *
 * @package warleek
 */
if ( defined( 'ABSPATH' ) && ! defined( 'WARLEEK_BBCODE_LOADED' ) ) { define( 'WARLEEK_BBCODE_LOADED', true ); }

const WARLEEK_STEAM_CLAN_IMAGE = 'https://clan.akamai.steamstatic.com/images';

/**
 * @param string $bb Roh-BBCode aus der Steam-News-API.
 * @return string HTML.
 */
function warleek_bbcode_to_html( $bb ) {
	$text   = str_replace( array( "\r\n", "\r" ), "\n", (string) $bb );
	$text   = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	$blocks = array();
	$stash  = function ( $html ) use ( &$blocks ) {
		$blocks[] = $html;
		return "\n@@WLB" . ( count( $blocks ) - 1 ) . "@@\n";
	};

	// 1) Code-Blöcke zuerst sichern (kein Inline-Parsing darin).
	$text = preg_replace_callback( '/\[code\](.*?)\[\/code\]/is', function ( $m ) use ( $stash ) {
		return $stash( '<pre><code>' . trim( $m[1] ) . '</code></pre>' );
	}, $text );

	// 1b) Markdown-Fluchtzeichen: Steam liefert sie im Rohtext mit
	// („TCX-SPR \\[7.62x39mm]", „Kodiak \\[M249]" – 27 Stellen in der
	// Season-02-Note). In HTML bedeutet der Backslash nichts, also fällt er
	// weg. Code-Blöcke sind oben schon gesichert und bleiben unberührt.
	$text = warleek_md_escape_weg( $text );

	// 2) Inline-Tags.
	$text = preg_replace( '/\[b\](.*?)\[\/b\]/is', '<strong>$1</strong>', $text );
	$text = preg_replace( '/\[i\](.*?)\[\/i\]/is', '<em>$1</em>', $text );
	$text = preg_replace( '/\[u\](.*?)\[\/u\]/is', '<u>$1</u>', $text );
	$text = preg_replace( '/\[strike\](.*?)\[\/strike\]/is', '<s>$1</s>', $text );
	$text = preg_replace( '/\[spoiler\](.*?)\[\/spoiler\]/is', '<span class="wl-spoiler">$1</span>', $text );
	$text = preg_replace_callback( '/\[url=(?:&quot;)?([^\]&]+)(?:&quot;)?\](.*?)\[\/url\]/is', function ( $m ) {
		$href = warleek_bbcode_url( $m[1] );
		return $href ? '<a href="' . $href . '" rel="noopener" target="_blank">' . $m[2] . '</a>' : $m[2];
	}, $text );
	$text = preg_replace_callback( '/\[url\](.*?)\[\/url\]/is', function ( $m ) {
		$href = warleek_bbcode_url( $m[1] );
		return $href ? '<a href="' . $href . '" rel="noopener" target="_blank">' . $m[1] . '</a>' : $m[1];
	}, $text );
	$text = preg_replace_callback( '/\[img\s+src=&quot;([^&]+)&quot;[^\]]*\](.*?)\[\/img\]/is', function ( $m ) {
		$src = warleek_bbcode_url( $m[1] );
		return $src ? '<img src="' . $src . '" alt="" loading="lazy">' : '';
	}, $text );
	$text = preg_replace_callback( '/\[dynamiclink\s+href=&quot;([^&]+)&quot;[^\]]*\](.*?)\[\/dynamiclink\]/is', function ( $m ) {
		$href = warleek_bbcode_url( $m[1] );
		return $href ? '<a href="' . $href . '" rel="noopener" target="_blank">' . ( trim( $m[2] ) ?: $href ) . '</a>' : '';
	}, $text );
	$text = preg_replace_callback( '/\[img\](.*?)\[\/img\]/is', function ( $m ) {
		$src = warleek_bbcode_url( $m[1] );
		return $src ? '<img src="' . $src . '" alt="" loading="lazy">' : '';
	}, $text );
	$text = preg_replace_callback( '/\[previewyoutube=(?:&quot;)?([A-Za-z0-9_-]+)(?:;[^\]&]*)?(?:&quot;)?\](.*?)\[\/previewyoutube\]/is', function ( $m ) {
		return '<a href="https://www.youtube.com/watch?v=' . $m[1] . '" rel="noopener" target="_blank">Video auf YouTube</a>';
	}, $text );

	// 3) Block-Tags (werden als Platzhalter gesichert).
	$text = preg_replace_callback( '/\[h([123])\](.*?)\[\/h\1\]/is', function ( $m ) use ( $stash ) {
		$lvl = (int) $m[1] + 1;
		return $stash( '<h' . $lvl . '>' . trim( $m[2] ) . '</h' . $lvl . '>' );
	}, $text );
	$text = preg_replace_callback( '/\[(list|olist)\](.*?)\[\/\1\]/is', function ( $m ) use ( $stash ) {
		$tag   = 'olist' === strtolower( $m[1] ) ? 'ol' : 'ul';
		$items = preg_split( '/\[\*\]/', preg_replace( '/\[\/\*\]/', '', $m[2] ) );
		$lis   = '';
		foreach ( $items as $it ) {
			$it = trim( preg_replace( '/\[\/?p\]/i', "\n", $it ) );
			$it = preg_replace( "/\n{2,}/", "\n", $it );
			if ( '' !== $it ) { $lis .= '<li>' . str_replace( "\n", '<br>', $it ) . '</li>'; }
		}
		return $stash( '<' . $tag . '>' . $lis . '</' . $tag . '>' );
	}, $text );
	$text = preg_replace_callback( '/\[table(?:\s[^\]]*)?\](.*?)\[\/table\]/is', function ( $m ) use ( $stash ) {
		$t = preg_replace( array( '/\[tr(?:\s[^\]]*)?\]/i', '/\[\/tr\]/i', '/\[th(?:\s[^\]]*)?\]/i', '/\[\/th\]/i', '/\[td(?:\s[^\]]*)?\]/i', '/\[\/td\]/i', '/\[\/?p\]/i' ),
			array( '<tr>', '</tr>', '<th>', '</th>', '<td>', '</td>', '' ), $m[1] );
		return $stash( '<figure class="wp-block-table"><table>' . preg_replace( '/\s*\n\s*/', '', $t ) . '</table></figure>' );
	}, $text );
	$text = preg_replace_callback( '/\[p\](.*?)\[\/p\]/is', function ( $m ) use ( $stash ) {
		$inner = trim( $m[1] );
		if ( '' === $inner ) { return "\n"; }
		if ( preg_match( '/^@@WLB\d+@@$/', $inner ) ) { return "\n" . $inner . "\n"; }
		return $stash( '<p>' . str_replace( "\n", '<br>', $inner ) . '</p>' );
	}, $text );
	$text = preg_replace_callback( '/\[quote(?:=[^\]]*)?\](.*?)\[\/quote\]/is', function ( $m ) use ( $stash ) {
		// Zeilenumbrüche neben einem Platzhalter gehören zur Technik, nicht zum
		// Zitat: Als <br> stehen sie später zwischen zwei Absätzen im Zitatblock.
		$inner = preg_replace( '/\n*(@@WLB\d+@@)\n*/', '$1', trim( $m[1] ) );
		return $stash( '<blockquote>' . str_replace( "\n", '<br>', $inner ) . '</blockquote>' );
	}, $text );
	$text = preg_replace_callback( '/\[hr\](?:\[\/hr\])?/i', function () use ( $stash ) {
		return $stash( '<hr>' );
	}, $text );

	// 4) Unbekannte Tags entfernen (z. B. [table], [tr], [td], [noparse], [dynamiclink]).
	$text = preg_replace( '/\[\/?[a-z0-9]+(?:[=\s][^\]]*)?\]/i', '', $text );

	// 5) Absätze bauen: Platzhalter-Zeilen → Block, Textzeilen → <p> mit <br>.
	$out    = '';
	$para   = array();
	$flush  = function () use ( &$out, &$para ) {
		if ( $para ) { $out .= '<p>' . implode( '<br>', $para ) . '</p>'; $para = array(); }
	};
	foreach ( explode( "\n", $text ) as $line ) {
		$t = trim( $line );
		if ( '' === $t ) { $flush(); continue; }
		if ( preg_match( '/^@@WLB(\d+)@@$/', $t, $m ) ) { $flush(); $out .= $blocks[ (int) $m[1] ]; continue; }
		$para[] = $t;
	}
	$flush();

	$out = warleek_bbcode_platzhalter_loesen( $out, $blocks );

	if ( function_exists( 'wp_kses_post' ) ) {
		$out = wp_kses_post( $out );
	}
	return $out;
}

/**
 * Löst Platzhalter auf, die in einem anderen Block gelandet sind.
 *
 * Schritt 5 ersetzt nur Zeilen, die **genau** ein Platzhalter sind. Ein
 * `[quote]` mit einem `[p]` darin hatte dessen Platzhalter aber schon
 * eingeschlossen, bevor die Zeilen überhaupt gebaut wurden – und
 * `@@WLB188@@` stand danach sichtbar in der Season-02-Note, nicht nur im Text,
 * sondern auch in der Meta-Beschreibung und im JSON-LD.
 *
 * Der Deckel von acht Durchläufen begrenzt die Verschachtelung; was danach
 * noch dasteht, zeigt auf keinen Block und fällt weg – ein leerer Zitatblock
 * ist besser als ein sichtbares Stück Technik.
 *
 * @param string $html
 * @param array  $blocks
 * @return string
 */
function warleek_bbcode_platzhalter_loesen( $html, array $blocks ) {
	for ( $i = 0; $i < 8 && false !== strpos( $html, '@@WLB' ); $i++ ) {
		$html = preg_replace_callback( '/@@WLB(\\d+)@@/', function ( $m ) use ( $blocks ) {
			return isset( $blocks[ (int) $m[1] ] ) ? $blocks[ (int) $m[1] ] : '';
		}, $html );
	}
	return preg_replace( '/@@WLB\\d+@@/', '', $html );
}

/**
 * Entfernt Markdown-Fluchtzeichen aus Text, der als HTML weiterverwendet wird.
 *
 * Betrifft nur die Zeichen, die Markdown überhaupt maskiert; ein `\\U` wie in
 * einem Windows-Pfad bleibt stehen. `<pre>` und `<code>` bleiben unberührt –
 * dort kann der Backslash gemeint sein.
 *
 * @param string $text
 * @return string
 */
function warleek_md_escape_weg( $text ) {
	$teile = preg_split( '#(<(?:pre|code)\\b[^>]*>.*?</(?:pre|code)>)#is', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( (array) $teile as $i => $t ) {
		if ( 1 === $i % 2 ) { continue; }
		$teile[ $i ] = preg_replace( '/\\\\([\\[\\]*_#`~|(){}+.!$-])/', '$1', $t );
	}
	return implode( '', $teile );
}

/** Erlaubt nur http(s)-URLs, löst Steam-Platzhalter auf, gibt attribut-sicheren String zurück. */
function warleek_bbcode_url( $raw ) {
	$u = trim( html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' ) );
	$u = str_replace( '{STEAM_CLAN_IMAGE}', WARLEEK_STEAM_CLAN_IMAGE, $u );
	$u = str_replace( '{STEAM_CLAN_LOC_IMAGE}', WARLEEK_STEAM_CLAN_IMAGE, $u );
	if ( ! preg_match( '#^https?://#i', $u ) ) { return ''; }
	return htmlspecialchars( $u, ENT_QUOTES, 'UTF-8' );
}
