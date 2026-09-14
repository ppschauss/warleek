<?php
// Standalone-Test ohne WordPress: php tests/bbcode-test.php
require __DIR__ . '/../inc/bbcode.php';
$cases = [
	['[h1]Patch 1.2[/h1]', '<h2>Patch 1.2</h2>'],
	['[b]fett[/b] [i]kursiv[/i] [u]u[/u] [strike]s[/strike]', '<p><strong>fett</strong> <em>kursiv</em> <u>u</u> <s>s</s></p>'],
	["[list]\n[*]eins\n[*]zwei\n[/list]", '<ul><li>eins</li><li>zwei</li></ul>'],
	["[olist]\n[*]a\n[/olist]", '<ol><li>a</li></ol>'],
	['[url=https://x.de]Link[/url]', '<p><a href="https://x.de" rel="noopener" target="_blank">Link</a></p>'],
	['[img]{STEAM_CLAN_IMAGE}/123/abc.png[/img]', '<p><img src="https://clan.akamai.steamstatic.com/images/123/abc.png" alt="" loading="lazy"></p>'],
	['[quote]zitat[/quote]', '<blockquote>zitat</blockquote>'],
	['[code]x = 1[/code]', '<pre><code>x = 1</code></pre>'],
	['[hr][/hr]', '<hr>'],
	['[previewyoutube=AbC123;full][/previewyoutube]', '<p><a href="https://www.youtube.com/watch?v=AbC123" rel="noopener" target="_blank">Video auf YouTube</a></p>'],
	["Zeile 1\nZeile 2\n\nAbsatz 2", '<p>Zeile 1<br>Zeile 2</p><p>Absatz 2</p>'],
	['<script>alert(1)</script>[unknown]x[/unknown]', '<p>&lt;script&gt;alert(1)&lt;/script&gt;x</p>'],
	["[h2]Fixes[/h2]\n[list]\n[*][b]FOB[/b] repariert\n[/list]\nDanke!", '<h3>Fixes</h3><ul><li><strong>FOB</strong> repariert</li></ul><p>Danke!</p>'],
	// Neues Steam-Format (2026): [p], [img src=""], [*][p], [previewyoutube=""], [url=""]
	['[p][b]1.25 MILLION[/b] [/p][p][/p][p][img src="{STEAM_CLAN_IMAGE}/45973082/abc.png"][/img][/p]', '<p><strong>1.25 MILLION</strong></p><p><img src="https://clan.akamai.steamstatic.com/images/45973082/abc.png" alt="" loading="lazy"></p>'],
	['[olist][*][p][b]Alle[/b] Spieler[/p][*][p]Zwei[/p][/olist]', '<ol><li><strong>Alle</strong> Spieler</li><li>Zwei</li></ol>'],
	['[previewyoutube="CpQnZgZCIo8;full"][/previewyoutube]', '<p><a href="https://www.youtube.com/watch?v=CpQnZgZCIo8" rel="noopener" target="_blank">Video auf YouTube</a></p>'],
	['[p][url="https://x.de/a?b=1"]Link[/url][/p]', '<p><a href="https://x.de/a?b=1" rel="noopener" target="_blank">Link</a></p>'],
	['[h1][img src="{STEAM_CLAN_IMAGE}/1/a.png"][/img][/h1][h2]Exploits[/h2][list][*][p]Fixed[/p][/list]', '<h2><img src="https://clan.akamai.steamstatic.com/images/1/a.png" alt="" loading="lazy"></h2><h3>Exploits</h3><ul><li>Fixed</li></ul>'],
	['[table][tr][th]A[/th][th]B[/th][/tr][tr][td][p]1[/p][/td][td]2[/td][/tr][/table]', '<figure class="wp-block-table"><table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table></figure>'],
];
$fail = 0;
foreach ( $cases as [$in, $want] ) {
	$got = warleek_bbcode_to_html( $in );
	if ( trim( $got ) !== $want ) { $fail++; echo "FAIL\n in:   " . str_replace( "\n", '\n', $in ) . "\n want: $want\n got:  " . trim( $got ) . "\n"; }
}
echo $fail ? "$fail FAILED\n" : 'OK (' . count( $cases ) . ")\n";
exit( $fail ? 1 : 0 );
