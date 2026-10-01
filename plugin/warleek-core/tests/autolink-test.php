<?php
/**
 * Test für die automatische Verlinkung der Datenbank-Einträge.
 *
 * Braucht WordPress (Optionen, Filter):
 *   ./manage.sh wp eval-file wp-content/plugins/warleek-core/tests/autolink-test.php
 */

$fehler = 0;
function pruef( $name, $ist, $soll ) {
	global $fehler;
	if ( $ist === $soll ) { return; }
	$fehler++;
	echo "FEHLER: $name\n  ist : " . var_export( $ist, true ) . "\n  soll: " . var_export( $soll, true ) . "\n";
}
function enthaelt( $name, $heu, $nadel ) {
	global $fehler;
	if ( str_contains( $heu, $nadel ) ) { return; }
	$fehler++;
	echo "FEHLER: $name\n  fehlt: $nadel\n  in   : $heu\n";
}
function fehlt( $name, $heu, $nadel ) {
	global $fehler;
	if ( ! str_contains( $heu, $nadel ) ) { return; }
	$fehler++;
	echo "FEHLER: $name\n  darf nicht vorkommen: $nadel\n  in : $heu\n";
}

/* Fester Begriffssatz statt der echten Datenbank – der Test soll die Ersetzung
   prüfen, nicht den Veröffentlichungsstand. */
$index = array(
	'ural defender' => array( 'titel' => 'Ural Defender', 'url' => '/datenbank/wardogs-ural-defender/', 'id' => 901, 'begriff' => 'Ural Defender' ),
	'ak-74'         => array( 'titel' => 'AK-74',         'url' => '/datenbank/wardogs-ak-74/',         'id' => 902, 'begriff' => 'AK-74' ),
	'ural'          => array( 'titel' => 'URAL',          'url' => '/datenbank/wardogs-ural/',          'id' => 903, 'begriff' => 'URAL' ),
	'tor'           => array( 'titel' => 'Tor',           'url' => '/datenbank/wardogs-tor/',           'id' => 904, 'begriff' => 'Tor' ),
	'm4'            => array( 'titel' => 'M4',            'url' => '/datenbank/wardogs-m4/',            'id' => 905, 'begriff' => 'M4' ),
);
uksort( $index, function ( $a, $b ) { return mb_strlen( $b ) <=> mb_strlen( $a ) ?: strcmp( $a, $b ); } );
add_filter( 'warleek_autolink_index', function () use ( $index ) { return $index; } );

$alt = get_option( 'warleek_autolink' );
update_option( 'warleek_autolink', array( 'enabled' => true, 'types' => array( 'guide' ), 'max' => 8, 'aus' => array() ), false );

/* 1 — einfacher Treffer mit Titel-Attribut */
$h = warleek_autolink_html( '<p>Die AK-74 ist solide.</p>' );
enthaelt( 'Link gesetzt', $h, '<a class="wl-dblink" href="/datenbank/wardogs-ak-74/" title="Wardogs AK-74">AK-74</a>' );

/* 2 — nur die erste Fundstelle je Eintrag */
$h = warleek_autolink_html( '<p>AK-74 hier, AK-74 da, AK-74 überall.</p>' );
pruef( 'nur ein Link je Eintrag', substr_count( $h, 'wl-dblink' ), 1 );

/* 3 — längerer Begriff gewinnt */
$h = warleek_autolink_html( '<p>Der Ural Defender bringt Nachschub.</p>' );
enthaelt( 'längster Begriff zuerst', $h, 'title="Wardogs Ural Defender">Ural Defender</a>' );
fehlt( 'kein Teiltreffer', $h, 'wardogs-ural/' );

/* 4 — vorhandene Links bleiben unangetastet */
$h = warleek_autolink_html( '<p><a href="/x/">AK-74</a> steht schon im Link.</p>' );
pruef( 'kein Link im Link', substr_count( $h, 'wl-dblink' ), 0 );

/* 5 — Überschriften und Code bleiben frei */
$h = warleek_autolink_html( '<h2>AK-74 im Titel</h2><p><code>AK-74</code> im Code.</p>' );
pruef( 'Überschrift und Code frei', substr_count( $h, 'wl-dblink' ), 0 );

/* 6 — kleingeschrieben wird nicht verlinkt */
$h = warleek_autolink_html( '<p>Er ging durch das tor nach draußen.</p>' );
pruef( 'kleingeschrieben bleibt Text', substr_count( $h, 'wl-dblink' ), 0 );
$h = warleek_autolink_html( '<p>Das Tor hält zwei Treffer aus.</p>' );
pruef( 'großgeschrieben wird verlinkt', substr_count( $h, 'wl-dblink' ), 1 );

/* 7 — Wortgrenzen: keine Treffer in zusammengesetzten Wörtern */
$h = warleek_autolink_html( '<p>Der Motor und das M4A1 bleiben außen vor.</p>' );
pruef( 'Wortgrenzen beachtet', substr_count( $h, 'wl-dblink' ), 0 );

/* 8 — nichts in Attributen */
$h = warleek_autolink_html( '<p><img src="/x.webp" alt="AK-74 im Alt-Text"></p>' );
pruef( 'Attribute bleiben unberührt', substr_count( $h, 'wl-dblink' ), 0 );

/* 9 — Deckel je Beitrag */
update_option( 'warleek_autolink', array( 'enabled' => true, 'types' => array( 'guide' ), 'max' => 2, 'aus' => array() ), false );
$h = warleek_autolink_html( '<p>AK-74, Ural Defender, Tor und M4.</p>' );
pruef( 'Deckel greift', substr_count( $h, 'wl-dblink' ), 2 );

/* 10 — abgeschaltete Einträge */
update_option( 'warleek_autolink', array( 'enabled' => true, 'types' => array( 'guide' ), 'max' => 8, 'aus' => array( 902 ) ), false );
$h = warleek_autolink_html( '<p>Die AK-74 und das Tor.</p>' );
fehlt( 'ausgeschlossener Eintrag', $h, 'wardogs-ak-74' );
enthaelt( 'anderer Eintrag weiterhin', $h, 'wardogs-tor' );

/* 11 — kein Selbstlink */
update_option( 'warleek_autolink', array( 'enabled' => true, 'types' => array( 'guide' ), 'max' => 8, 'aus' => array() ), false );
$h = warleek_autolink_html( '<p>Die AK-74 im eigenen Eintrag.</p>', 902 );
pruef( 'kein Selbstlink', substr_count( $h, 'wl-dblink' ), 0 );

/* 12 — Text ohne Treffer bleibt Zeichen für Zeichen gleich */
$roh = '<p>Ein Absatz ganz ohne Gegenstände.</p>';
pruef( 'unveränderter Text', warleek_autolink_html( $roh ), $roh );

if ( null === $alt ) { delete_option( 'warleek_autolink' ); } else { update_option( 'warleek_autolink', $alt, false ); }

echo $fehler ? "\n$fehler Fehler\n" : "OK\n";
