<?php
/**
 * Externe Links entwerten: nofollow für Quellen, interne Links unangetastet.
 * Aufruf: wp eval-file wp-content/plugins/warleek-core/tests/nofollow-test.php
 */
$fail = 0;
function chk_true( $label, $cond ) { global $fail; if ( ! $cond ) { $fail++; echo "FAIL $label\n"; } }
function chk( $label, $got, $want ) { global $fail; if ( trim($got) !== trim($want) ) { $fail++; echo "FAIL $label\n want: $want\n got:  " . trim($got) . "\n"; } }

$eigen = home_url( '/guides/test/' );

$a = warleek_externe_links_entwerten( '<p><a href="https://www.youtube.com/watch?v=x">Quelle</a></p>' );
chk_true( 'extern bekommt nofollow', str_contains( $a, 'nofollow' ) );
chk_true( 'extern bekommt noopener', str_contains( $a, 'noopener' ) );
chk_true( 'extern bekommt noreferrer', str_contains( $a, 'noreferrer' ) );

$b = '<p><a href="' . $eigen . '">Intern</a></p>';
chk( 'intern bleibt unangetastet', warleek_externe_links_entwerten( $b ), $b );

$c = '<p><a href="/guides/anderer/">Relativ</a></p>';
chk( 'relativ bleibt unangetastet', warleek_externe_links_entwerten( $c ), $c );

$d = warleek_externe_links_entwerten( '<a href="https://example.org/" rel="noopener" target="_blank">X</a>' );
chk_true( 'vorhandenes rel bleibt', str_contains( $d, 'noopener' ) );
chk_true( 'nofollow kommt dazu', str_contains( $d, 'nofollow' ) );
chk_true( 'rel nur einmal', 1 === substr_count( $d, 'rel=' ) );
chk_true( 'noopener nicht doppelt', 1 === substr_count( $d, 'noopener' ) );
chk_true( 'target bleibt', str_contains( $d, 'target="_blank"' ) );

$e = '<p>Kein Link hier.</p>';
chk( 'ohne link unveraendert', warleek_externe_links_entwerten( $e ), $e );

// Abschaltbar
$opts = (array) get_option( 'warleek_options', array() );
$merk = $opts;
$opts['external_nofollow'] = '0';
update_option( 'warleek_options', $opts );
$f = '<p><a href="https://example.org/">X</a></p>';
chk( 'abgeschaltet: unveraendert', warleek_externe_links_entwerten( $f ), $f );
update_option( 'warleek_options', $merk );

echo $fail ? "$fail FAILED\n" : "OK\n";
exit( $fail ? 1 : 0 );
