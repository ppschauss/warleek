<?php
/**
 * Warleek — Markdown → HTML.
 *
 * Deckt bewusst nur die Teilmenge ab, die unsere Guides nutzen: Überschriften,
 * Absätze, fett/kursiv/Code, Links, Bilder, Listen (auch Checklisten), Tabellen,
 * Zitate, Trenner und eingezäunte Code-Blöcke. Kein vollständiges CommonMark.
 * Reine Funktionen ohne WordPress-Abhängigkeit – testbar mit
 * `php tests/markdown-test.php`.
 *
 * @package warleek-core
 */
if ( ! defined( 'WARLEEK_MD_LOADED' ) ) { define( 'WARLEEK_MD_LOADED', true ); }

/**
 * Trennt den Kopfblock (zwischen zwei `---`-Zeilen) vom Text.
 *
 * Absichtlich kein YAML-Parser: `key: value` pro Zeile, Anführungszeichen optional,
 * `[a, b, c]` wird zur Liste. Verschachtelung wird nicht unterstützt.
 *
 * @param string $text Dateiinhalt.
 * @return array{meta: array, body: string}
 */
function warleek_md_front_matter( $text ) {
	$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
	$meta = array();
	if ( ! preg_match( '/\A---\n(.*?)\n---\n?/s', $text, $m ) ) {
		return array( 'meta' => $meta, 'body' => ltrim( $text, "\n" ) );
	}
	foreach ( explode( "\n", $m[1] ) as $line ) {
		if ( ! preg_match( '/^([A-Za-z0-9_-]+)\s*:\s*(.*)$/', trim( $line ), $kv ) ) { continue; }
		$key = strtolower( $kv[1] );
		$val = trim( $kv[2] );
		if ( ( str_starts_with( $val, '"' ) && str_ends_with( $val, '"' ) ) || ( str_starts_with( $val, "'" ) && str_ends_with( $val, "'" ) ) ) {
			$val = substr( $val, 1, -1 );
		}
		if ( str_starts_with( $val, '[' ) && str_ends_with( $val, ']' ) ) {
			$val = array_values( array_filter( array_map( function ( $x ) { return trim( $x, " \t\"'" ); }, explode( ',', substr( $val, 1, -1 ) ) ) ) );
		}
		$meta[ $key ] = $val;
	}
	return array( 'meta' => $meta, 'body' => ltrim( substr( $text, strlen( $m[0] ) ), "\n" ) );
}

/**
 * Wandelt Markdown in das HTML um, das `warleek_html_to_blocks()` erwartet.
 *
 * @param string $md            Markdown ohne Kopfblock.
 * @param bool   $strip_first_h1 Erste H1 entfernen (sie ist der Beitragstitel).
 * @return string
 */
function warleek_md_to_html( $md, $strip_first_h1 = true ) {
	$md = str_replace( array( "\r\n", "\r" ), "\n", (string) $md );

	// 1) Code-Blöcke sichern, damit darin nichts umgewandelt wird.
	$blocks = array();
	$md = preg_replace_callback( '/^```[a-z0-9]*\n(.*?)^```[ \t]*$/ms', function ( $m ) use ( &$blocks ) {
		$blocks[] = '<pre><code>' . htmlspecialchars( rtrim( $m[1] ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</code></pre>';
		return "\n@@WLMD" . ( count( $blocks ) - 1 ) . "@@\n";
	}, $md );

	$out   = array();
	$lines = explode( "\n", $md );
	$para  = array();
	$n     = count( $lines );
	$first_h1_done = ! $strip_first_h1;

	$flush = function () use ( &$out, &$para ) {
		if ( $para ) { $out[] = '<p>' . warleek_md_inline( implode( ' ', $para ) ) . '</p>'; $para = array(); }
	};

	for ( $i = 0; $i < $n; $i++ ) {
		$line = rtrim( $lines[ $i ] );
		$t    = trim( $line );

		if ( '' === $t ) { $flush(); continue; }

		// Gesicherter Code-Block
		if ( preg_match( '/^@@WLMD(\d+)@@$/', $t, $m ) ) { $flush(); $out[] = $blocks[ (int) $m[1] ]; continue; }

		// Überschriften
		if ( preg_match( '/^(#{1,6})\s+(.*)$/', $t, $m ) ) {
			$flush();
			$level = strlen( $m[1] );
			if ( 1 === $level && ! $first_h1_done ) { $first_h1_done = true; continue; } // Titel überspringen
			$level = max( 2, min( 4, 1 === $level ? 2 : $level ) );                       // im Inhalt beginnt h2
			$out[] = '<h' . $level . '>' . warleek_md_inline( $m[2] ) . '</h' . $level . '>';
			continue;
		}

		// Trenner
		if ( preg_match( '/^(---|\*\*\*|___)$/', $t ) ) { $flush(); $out[] = '<hr>'; continue; }

		// Tabelle: Kopfzeile + Trennzeile
		if ( str_starts_with( $t, '|' ) && $i + 1 < $n && preg_match( '/^\|[\s:|-]+\|$/', trim( $lines[ $i + 1 ] ) ) ) {
			$flush();
			$head = warleek_md_table_row( $t );
			$rows = array();
			$i += 2;
			while ( $i < $n && str_starts_with( trim( $lines[ $i ] ), '|' ) ) { $rows[] = warleek_md_table_row( trim( $lines[ $i ] ) ); $i++; }
			$i--;
			$html = '<table><thead><tr>';
			foreach ( $head as $cell ) { $html .= '<th>' . warleek_md_inline( $cell ) . '</th>'; }
			$html .= '</tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$html .= '<tr>';
				foreach ( $row as $cell ) { $html .= '<td>' . warleek_md_inline( $cell ) . '</td>'; }
				$html .= '</tr>';
			}
			$out[] = $html . '</tbody></table>';
			continue;
		}

		// Listen (auch Checklisten und nummeriert)
		if ( preg_match( '/^([-*+]|\d+\.)\s+(.*)$/', $t ) ) {
			$flush();
			$ordered = (bool) preg_match( '/^\d+\.\s/', $t );
			$items   = array();
			while ( $i < $n ) {
				$lt = trim( $lines[ $i ] );
				if ( preg_match( '/^([-*+]|\d+\.)\s+(.*)$/', $lt, $im ) ) {
					$item = $im[2];
					// Checkliste: `- [ ]` / `- [x]`
					if ( preg_match( '/^\[( |x|X)\]\s*(.*)$/', $item, $cm ) ) {
						$item = '<span class="wl-check' . ( ' ' === $cm[1] ? '' : ' is-done' ) . '"></span>' . warleek_md_inline( $cm[2] );
						$items[] = $item;
					} else {
						$items[] = warleek_md_inline( $item );
					}
					$i++;
					continue;
				}
				// Fortsetzungszeile einer Aufzählung
				if ( '' !== $lt && $items && ! preg_match( '/^(#{1,6}\s|\||```|@@WLMD)/', $lt ) && str_starts_with( $lines[ $i ], '  ' ) ) {
					$items[ count( $items ) - 1 ] .= ' ' . warleek_md_inline( $lt );
					$i++;
					continue;
				}
				break;
			}
			$i--;
			$tag   = $ordered ? 'ol' : 'ul';
			$html  = '<' . $tag . '>';
			foreach ( $items as $it ) { $html .= '<li>' . $it . '</li>'; }
			$out[] = $html . '</' . $tag . '>';
			continue;
		}

		// Zitat / Hinweis: `> [!hinweis] Text` wird zum wl-note-Kasten
		if ( str_starts_with( $t, '>' ) ) {
			$flush();
			$buf = array();
			while ( $i < $n && str_starts_with( trim( $lines[ $i ] ), '>' ) ) {
				$buf[] = trim( ltrim( trim( $lines[ $i ] ), '>' ) );
				$i++;
			}
			$i--;
			$text = trim( implode( ' ', array_filter( $buf ) ) );
			if ( preg_match( '/^\[!(hinweis|note|tipp|tip|achtung|warning)\]\s*(.*)$/i', $text, $nm ) ) {
				$label = in_array( strtolower( $nm[1] ), array( 'achtung', 'warning' ), true ) ? 'Achtung' : 'Hinweis';
				$cls   = 'Achtung' === $label ? 'wl-note wl-note--alert' : 'wl-note';
				$out[] = '<div class="' . $cls . '"><p><strong>' . $label . '</strong> ' . warleek_md_inline( $nm[2] ) . '</p></div>';
			} else {
				$out[] = '<blockquote><p>' . warleek_md_inline( $text ) . '</p></blockquote>';
			}
			continue;
		}

		// Bild als eigener Absatz
		if ( preg_match( '/^!\[([^\]]*)\]\(([^)\s]+)\)$/', $t, $m ) ) {
			$flush();
			$out[] = '<figure><img src="' . htmlspecialchars( $m[2], ENT_QUOTES, 'UTF-8' ) . '" alt="' . htmlspecialchars( $m[1], ENT_QUOTES, 'UTF-8' ) . '"></figure>';
			continue;
		}

		$para[] = $t;
	}
	$flush();

	return implode( "\n", $out );
}

/** Zerlegt eine Tabellenzeile in Zellen. */
function warleek_md_table_row( $line ) {
	$line = trim( $line, "| \t" );
	return array_map( 'trim', explode( '|', $line ) );
}

/** Inline-Auszeichnungen: Code, fett, kursiv, Links, Bilder. */
function warleek_md_inline( $text ) {
	// Inline-Code zuerst sichern.
	$codes = array();
	$text = preg_replace_callback( '/`([^`]+)`/', function ( $m ) use ( &$codes ) {
		$codes[] = '<code>' . htmlspecialchars( $m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</code>';
		return '@@WLC' . ( count( $codes ) - 1 ) . '@@';
	}, (string) $text );

	$text = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

	$text = preg_replace_callback( '/!\[([^\]]*)\]\(([^)\s]+)\)/', function ( $m ) {
		return '<img src="' . $m[2] . '" alt="' . $m[1] . '">';
	}, $text );
	$text = preg_replace_callback( '/\[([^\]]+)\]\(([^)\s]+)\)/', function ( $m ) {
		$ext = (bool) preg_match( '#^https?://#i', html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) );
		return '<a href="' . $m[2] . '"' . ( $ext ? ' rel="noopener" target="_blank"' : '' ) . '>' . $m[1] . '</a>';
	}, $text );
	$text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
	$text = preg_replace( '/(?<![\w*])\*([^*\n]+)\*(?![\w*])/', '<em>$1</em>', $text );

	foreach ( $codes as $idx => $code ) { $text = str_replace( '@@WLC' . $idx . '@@', $code, $text ); }
	return $text;
}
