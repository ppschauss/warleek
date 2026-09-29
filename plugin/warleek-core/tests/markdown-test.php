<?php
// Standalone-Test ohne WordPress: php tests/markdown-test.php
require __DIR__ . '/../inc/markdown.php';

$fail = 0;
function chk( $label, $got, $want ) {
	global $fail;
	if ( trim( $got ) !== trim( $want ) ) {
		$fail++;
		echo "FAIL $label\n want: $want\n got:  " . trim( $got ) . "\n";
	}
}

/* --------------------------------------------------------- Kopfblock */
$fm = warleek_md_front_matter( "---\ntitle: \"Mit HOTAS fliegen\"\ndescription: Kurztext\ntags: [wardogs, hotas, joystick]\ndate: 2026-09-29\n---\n\n# Titel\n\nText." );
chk( 'fm-title', $fm['meta']['title'], 'Mit HOTAS fliegen' );
chk( 'fm-desc', $fm['meta']['description'], 'Kurztext' );
chk( 'fm-tags', implode( '|', $fm['meta']['tags'] ), 'wardogs|hotas|joystick' );
chk( 'fm-body', substr( $fm['body'], 0, 7 ), '# Titel' );
$fm2 = warleek_md_front_matter( "Kein Kopfblock.\n" );
chk( 'fm-none', $fm2['body'], 'Kein Kopfblock.' );

/* ------------------------------------------------------------ Inline */
chk( 'bold', warleek_md_to_html( '**fett** und *kursiv*', false ), '<p><strong>fett</strong> und <em>kursiv</em></p>' );
chk( 'code', warleek_md_to_html( 'Taste `joy.cpl` drücken', false ), '<p>Taste <code>joy.cpl</code> drücken</p>' );
chk( 'link-ext', warleek_md_to_html( '[Ziel](https://x.de)', false ), '<p><a href="https://x.de" rel="noopener" target="_blank">Ziel</a></p>' );
chk( 'link-int', warleek_md_to_html( '[Guides](/guides/)', false ), '<p><a href="/guides/">Guides</a></p>' );
chk( 'escape', warleek_md_to_html( 'a < b & c', false ), '<p>a &lt; b &amp; c</p>' );
chk( 'code-escape', warleek_md_to_html( 'Pfad `%LOCALAPPDATA%\Wardogs`', false ), '<p>Pfad <code>%LOCALAPPDATA%\Wardogs</code></p>' );

/* -------------------------------------------------------- Strukturen */
chk( 'h1-strip', warleek_md_to_html( "# Titel\n\nText." ), '<p>Text.</p>' );
chk( 'h2', warleek_md_to_html( "## Kapitel\n\nText.", false ), "<h2>Kapitel</h2>\n<p>Text.</p>" );
chk( 'h3-h4', warleek_md_to_html( "### Drei\n#### Vier", false ), "<h3>Drei</h3>\n<h4>Vier</h4>" );
chk( 'ul', warleek_md_to_html( "- eins\n- zwei", false ), '<ul><li>eins</li><li>zwei</li></ul>' );
chk( 'ol', warleek_md_to_html( "1. eins\n2. zwei", false ), '<ol><li>eins</li><li>zwei</li></ol>' );
chk( 'checklist', warleek_md_to_html( "- [ ] offen\n- [x] erledigt", false ),
	'<ul><li><span class="wl-check"></span>offen</li><li><span class="wl-check is-done"></span>erledigt</li></ul>' );
chk( 'table', warleek_md_to_html( "| Physisch | vJoy |\n|---|---|\n| X Axis | Roll |", false ),
	'<table><thead><tr><th>Physisch</th><th>vJoy</th></tr></thead><tbody><tr><td>X Axis</td><td>Roll</td></tr></tbody></table>' );
chk( 'hr', warleek_md_to_html( "Text\n\n---\n\nMehr", false ), "<p>Text</p>\n<hr>\n<p>Mehr</p>" );
chk( 'quote', warleek_md_to_html( '> Zitat', false ), '<blockquote><p>Zitat</p></blockquote>' );
chk( 'note', warleek_md_to_html( '> [!hinweis] Pass auf.', false ), '<div class="wl-note"><p><strong>Hinweis</strong> Pass auf.</p></div>' );
chk( 'warn', warleek_md_to_html( '> [!achtung] Gefahr.', false ), '<div class="wl-note wl-note--alert"><p><strong>Achtung</strong> Gefahr.</p></div>' );
chk( 'stand', warleek_md_to_html( '> [!stand] September 2026 · Early Access.', false ), '<p class="wl-stand">Stand: September 2026 · Early Access.</p>' );
chk( 'fence', warleek_md_to_html( "```\nHKEY_LOCAL\\Machine <x>\n```", false ), '<pre><code>HKEY_LOCAL\Machine &lt;x&gt;</code></pre>' );
chk( 'fence-lang', warleek_md_to_html( "```bash\nls -la\n```", false ), '<pre><code>ls -la</code></pre>' );
chk( 'fence-keeps-md', warleek_md_to_html( "```\n# kein Heading\n- keine Liste\n```", false ), "<pre><code># kein Heading\n- keine Liste</code></pre>" );
chk( 'para-join', warleek_md_to_html( "Zeile eins\nZeile zwei\n\nNeuer Absatz", false ), "<p>Zeile eins Zeile zwei</p>\n<p>Neuer Absatz</p>" );
chk( 'img', warleek_md_to_html( '![Alt Text](bild.webp)', false ), '<figure><img src="bild.webp" alt="Alt Text"></figure>' );
chk( 'img-caption', warleek_md_to_html( '![Alt](schluessel "Eine **Unterschrift**")', false ), '<figure><img src="schluessel" alt="Alt"><figcaption>Eine <strong>Unterschrift</strong></figcaption></figure>' );
chk( 'img-leere-caption', warleek_md_to_html( '![Alt](schluessel "")', false ), '<figure><img src="schluessel" alt="Alt"></figure>' );

echo $fail ? "$fail FAILED\n" : "OK\n";
exit( $fail ? 1 : 0 );
