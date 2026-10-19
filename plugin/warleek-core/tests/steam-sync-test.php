<?php
// Aufruf: wp eval-file wp-content/plugins/warleek-core/tests/steam-sync-test.php
$items = json_decode( file_get_contents( __DIR__ . '/fixtures/steam-news.json' ), true )['appnews']['newsitems'];
$want  = array( '1843481262698449' => true, '1843481262693807' => false, '1843481262693332' => true, '1843481262691760' => false, '1843481262690549' => true, '1842846814456311' => false );
$fail  = 0;
foreach ( $items as $it ) {
	$got = warleek_steam_is_patchnote( $it );
	if ( isset( $want[ $it['gid'] ] ) && $got !== $want[ $it['gid'] ] ) { $fail++; echo "FAIL is_patchnote {$it['gid']} {$it['title']}\n"; }
}
$before = (int) wp_count_posts( 'patchnote' )->publish;
$a = warleek_steam_upsert_patchnote( $items[0] );
$b = warleek_steam_upsert_patchnote( $items[0] );
$after = (int) wp_count_posts( 'patchnote' )->publish;
if ( is_wp_error( $a ) || is_wp_error( $b ) ) { $fail++; echo "FAIL upsert error\n"; }
elseif ( $a['id'] !== $b['id'] ) { $fail++; echo "FAIL upsert ids differ\n"; }
elseif ( 'skipped' !== $b['action'] ) { $fail++; echo "FAIL second upsert not skipped: {$b['action']}\n"; }
elseif ( $after - $before > 1 ) { $fail++; echo "FAIL post count grew by more than one\n"; }
$c = warleek_steam_upsert_patchnote( $items[0], true );
if ( ! is_wp_error( $c ) && 'updated' !== $c['action'] ) { $fail++; echo "FAIL force should update: {$c['action']}\n"; }
$html = get_post( $a['id'] )->post_content;
if ( strpos( $html, '[' ) !== false && preg_match( '/\[(p|h1|b|list)\]/', $html ) ) { $fail++; echo "FAIL BBCode-Reste im Content\n"; }

/* Auszug: Die Kurzfassung gewinnt, weil der Fließtext einer Patch Note mit
   „WARDOGS SEASON 2 Trailer …" anfängt – und weil der Auszug, solange er aus
   dem Inhalt kam, nach einer späteren Übersetzung englisch blieb. */
$lang = str_repeat( 'Wort ', 60 );
if ( 'FOB-Spawn repariert.' !== warleek_patchnote_auszug( $lang, array( 'FOB-Spawn repariert.' ) ) ) {
	$fail++; echo "FAIL Auszug nimmt die Kurzfassung nicht\n";
}
if ( 'Zweiter Punkt' !== warleek_patchnote_auszug( $lang, array( '', '  ', 'Zweiter Punkt' ) ) ) {
	$fail++; echo "FAIL Auszug überspringt leere Stichpunkte nicht\n";
}
$ohne = warleek_patchnote_auszug( $lang, array() );
if ( mb_strlen( $ohne ) > 161 || '…' !== mb_substr( $ohne, -1 ) ) {
	$fail++; echo 'FAIL Auszug ohne Kurzfassung: ' . mb_strlen( $ohne ) . " Zeichen\n";
}
// Nicht mitten im Wort abschneiden.
if ( false !== strpos( $ohne, 'Wor…' ) ) { $fail++; echo "FAIL Auszug schneidet im Wort\n"; }
if ( 'Kurz.' !== warleek_patchnote_auszug( 'Kurz.', array() ) ) { $fail++; echo "FAIL kurzer Text wird verändert\n"; }
if ( 'A B C' !== warleek_patchnote_klartext( "<p>A</p>\n<li>B</li><h2>C</h2>" ) ) {
	$fail++; echo 'FAIL Klartext: ' . warleek_patchnote_klartext( "<p>A</p>\n<li>B</li><h2>C</h2>" ) . "\n";
}

/* Reparatur: Platzhalter-Reste und Fluchtzeichen fliegen raus, die leere Hülle mit. */
$dreck = '<p>Vorher</p><blockquote><p>@@WLB188@@</p></blockquote><li>Kodiak \[M249]</li>';
$rein  = warleek_patchnote_putzen( $dreck );
if ( '<p>Vorher</p><li>Kodiak [M249]</li>' !== $rein ) { $fail++; echo "FAIL Putzen: $rein\n"; }

wp_delete_post( $a['id'], true );
echo $fail ? "$fail FAILED\n" : "OK\n";
