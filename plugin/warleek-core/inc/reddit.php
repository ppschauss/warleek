<?php
/**
 * Warleek — aus WordPress nach Reddit posten.
 *
 * Zwei Betriebsarten:
 *
 * 1. **Neue Guides** gehen beim Veröffentlichen automatisch raus.
 * 2. **Ältere Guides und Datenbank-Einträge** tröpfeln über Wochen nach –
 *    höchstens einer pro Tag, in zufälliger, aber reproduzierbarer Reihenfolge.
 *
 * **Der Beitrag ist eine Zusammenfassung, keine Kopie.** Titel, Direktantwort,
 * bis zu drei Fragen, dann der Link. Drei Gründe: Reddit-Communities entfernen
 * Beiträge, die nur aus einem Link bestehen, ebenso wie Volltext-Dumps; eine
 * Kopie des Guides wäre doppelter Inhalt; und ein Auszug, der für sich steht,
 * ist der einzige Grund, warum jemand den Link überhaupt anklickt.
 *
 * **Anmeldung:** Reddit verlangt OAuth2. Für ein eigenes Konto reicht eine App
 * vom Typ „script" (reddit.com/prefs/apps). Benötigt werden Client-ID,
 * Client-Secret, Benutzername und Passwort. Besser als in der Datenbank stehen
 * sie als Konstanten in der `wp-config.php`:
 *
 *     define( 'WARLEEK_REDDIT_ID',     '…' );
 *     define( 'WARLEEK_REDDIT_SECRET', '…' );
 *     define( 'WARLEEK_REDDIT_USER',   '…' );
 *     define( 'WARLEEK_REDDIT_PASS',   '…' );
 *
 * **Grenzen, die eingehalten werden:** eine eigene Kennung im User-Agent (von
 * Reddit verlangt), ein Beitrag pro Lauf, Zugriffstoken zwischengespeichert,
 * und jeder Beitrag wird am Post vermerkt, damit nichts zweimal rausgeht.
 *
 * @package warleek-core
 */

defined( 'ABSPATH' ) || exit;

const WARLEEK_REDDIT_TOKEN_KEY = 'warleek_reddit_token';
const WARLEEK_REDDIT_LOG       = 'warleek_reddit_log';
const WARLEEK_REDDIT_META      = '_warleek_reddit';

/* --------------------------------------------------------- Zugangsdaten */

/** Ein Zugangsfeld: Konstante schlägt Option. */
function warleek_reddit_cred( $feld ) {
	$konst = array(
		'id'     => 'WARLEEK_REDDIT_ID',
		'secret' => 'WARLEEK_REDDIT_SECRET',
		'user'   => 'WARLEEK_REDDIT_USER',
		'pass'   => 'WARLEEK_REDDIT_PASS',
	);
	if ( isset( $konst[ $feld ] ) && defined( $konst[ $feld ] ) && constant( $konst[ $feld ] ) ) {
		return (string) constant( $konst[ $feld ] );
	}
	return trim( (string) warleek_opt( 'reddit_' . $feld ) );
}

/**
 * Subreddit-Name ohne Präfix, Schreibweise unverändert.
 *
 * Reddit behandelt Subreddit-Namen in Adressen **nicht** case-sensitiv –
 * /r/wardogsdeutsch und /r/WardogsDeutsch landen am selben Ort. Die
 * Schreibweise ist trotzdem der Anzeigename, deshalb bleibt sie, wie sie
 * eingetragen wurde.
 *
 * Entfernt wird nur ein führendes `r/` oder `/r/`. Der naheliegende Weg
 * `trim( $v, " /r" )` wäre falsch: Er schneidet die **Zeichen** `r` und `/` an
 * beiden Enden ab – aus `rust` würde `ust`, aus `wardogsger` `wardogsge`.
 *
 * @return string
 */
function warleek_reddit_sub() {
	$v = trim( (string) warleek_opt( 'reddit_sub' ), " \t/" );
	return preg_replace( '#^r/#i', '', $v );
}

/** Sind alle vier Angaben da? */
function warleek_reddit_bereit() {
	foreach ( array( 'id', 'secret', 'user', 'pass' ) as $f ) {
		if ( '' === warleek_reddit_cred( $f ) ) { return false; }
	}
	return '' !== warleek_reddit_sub();
}

/** Kennung, die Reddit verlangt: platform:id:version (by /u/name). */
function warleek_reddit_ua() {
	return sprintf( 'web:de.warleek.core:%s (by /u/%s)', WARLEEK_CORE_VERSION, warleek_reddit_cred( 'user' ) );
}

/**
 * Zugriffstoken holen – eine Stunde gültig, zwischengespeichert.
 *
 * @return string|WP_Error
 */
function warleek_reddit_token() {
	$cache = get_transient( WARLEEK_REDDIT_TOKEN_KEY );
	if ( is_string( $cache ) && '' !== $cache ) { return $cache; }
	if ( ! warleek_reddit_bereit() ) { return new WP_Error( 'warleek_reddit', 'Zugangsdaten unvollständig.' ); }

	$antwort = wp_remote_post( 'https://www.reddit.com/api/v1/access_token', array(
		'timeout' => 20,
		'headers' => array(
			'Authorization' => 'Basic ' . base64_encode( warleek_reddit_cred( 'id' ) . ':' . warleek_reddit_cred( 'secret' ) ),
			'User-Agent'    => warleek_reddit_ua(),
		),
		'body'    => array(
			'grant_type' => 'password',
			'username'   => warleek_reddit_cred( 'user' ),
			'password'   => warleek_reddit_cred( 'pass' ),
		),
	) );
	if ( is_wp_error( $antwort ) ) { return $antwort; }

	$code = (int) wp_remote_retrieve_response_code( $antwort );
	$d    = json_decode( (string) wp_remote_retrieve_body( $antwort ), true );
	if ( 200 !== $code || empty( $d['access_token'] ) ) {
		return new WP_Error( 'warleek_reddit', sprintf(
			'Anmeldung fehlgeschlagen (HTTP %d): %s',
			$code,
			is_array( $d ) ? ( $d['error'] ?? wp_json_encode( $d ) ) : 'keine Antwort'
		) );
	}

	// Etwas früher ablaufen lassen als Reddit, damit kein Lauf auf ein totes Token trifft.
	$dauer = max( 300, (int) ( $d['expires_in'] ?? 3600 ) - 120 );
	set_transient( WARLEEK_REDDIT_TOKEN_KEY, $d['access_token'], $dauer );
	return $d['access_token'];
}

/* -------------------------------------------------------------- Posten */

/**
 * Einen Textbeitrag einstellen.
 *
 * @param string $titel Beitragstitel (Reddit: höchstens 300 Zeichen).
 * @param string $text  Markdown.
 * @param bool   $probe true = nichts senden, nur zurückgeben, was gesendet würde.
 * @return array|WP_Error {url, id} bzw. bei Probe {probe: true, …}
 */
function warleek_reddit_submit( $titel, $text, $probe = false ) {
	$sub   = warleek_reddit_sub();
	$titel = trim( wp_strip_all_tags( $titel ) );
	if ( mb_strlen( $titel ) > 300 ) { $titel = mb_substr( $titel, 0, 297 ) . '…'; }
	if ( '' === $titel || '' === trim( $text ) ) { return new WP_Error( 'warleek_reddit', 'Titel oder Text leer.' ); }

	if ( $probe ) { return array( 'probe' => true, 'sub' => $sub, 'titel' => $titel, 'text' => $text ); }

	$token = warleek_reddit_token();
	if ( is_wp_error( $token ) ) { return $token; }

	$antwort = wp_remote_post( 'https://oauth.reddit.com/api/submit', array(
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
			'User-Agent'    => warleek_reddit_ua(),
		),
		'body'    => array(
			'api_type'    => 'json',
			'kind'        => 'self',
			'sr'          => $sub,
			'title'       => $titel,
			'text'        => $text,
			'sendreplies' => 'true',
			'resubmit'    => 'false',
		),
	) );
	if ( is_wp_error( $antwort ) ) { return $antwort; }

	$code = (int) wp_remote_retrieve_response_code( $antwort );
	$d    = json_decode( (string) wp_remote_retrieve_body( $antwort ), true );

	// Reddit meldet Fehler mit HTTP 200 im Rumpf – deshalb beides prüfen.
	$fehler = $d['json']['errors'] ?? array();
	if ( 200 !== $code || $fehler ) {
		$txt = $fehler ? implode( '; ', array_map( function ( $e ) { return implode( ' ', (array) $e ); }, $fehler ) ) : 'HTTP ' . $code;
		return new WP_Error( 'warleek_reddit', 'Reddit lehnt ab: ' . $txt );
	}

	return array(
		'url' => (string) ( $d['json']['data']['url'] ?? '' ),
		'id'  => (string) ( $d['json']['data']['name'] ?? '' ),
	);
}

/* ------------------------------------------------------- Text erzeugen */

/**
 * Text auf das Vergleichbare eindampfen: klein, ohne Satzzeichen, ein Leerraum.
 *
 * Dient nur dem Doppelungs-Test, nicht der Anzeige.
 *
 * @param string $s Text.
 * @return string
 */
function warleek_reddit_norm( $s ) {
	$s = wp_strip_all_tags( (string) $s );
	$s = str_replace( array( '–', '—', '„', '“', '”', '‘', '’' ), array( '-', '-', '"', '"', '"', "'", "'" ), $s );
	$s = mb_strtolower( $s, 'UTF-8' );
	$s = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $s );
	return trim( (string) $s );
}

/**
 * Datenblatt eines Eintrags als Liste aus Beschriftung und fertigem Wert.
 *
 * Nimmt dieselbe Quelle wie die Tabelle auf der Seite
 * (`warleek_item_felder()` / `warleek_item_wert()`), damit Beitrag und Seite
 * nicht auseinanderlaufen können.
 *
 * @param int $id Eintrag.
 * @return array<int, array{0:string, 1:string}>
 */
function warleek_reddit_item_werte( $id ) {
	if ( ! function_exists( 'warleek_item_felder' ) ) { return array(); }
	$out = array();
	foreach ( warleek_item_felder() as $key => $def ) {
		$wert = warleek_item_wert( $key, get_post_meta( $id, 'item_' . $key, true ) );
		if ( '' === $wert ) { continue; }
		$out[] = array( (string) $def[0], (string) $wert );
	}
	return $out;
}

/**
 * Sätze streichen, die nur wiederholen, was im Datenblatt steht.
 *
 * Die Direktantwort eines Eintrags ist aus genau den Werten gebaut, die jetzt
 * als Tabelle im Beitrag stehen (`warleek_item_aeo()`). Ungefiltert stand bei
 * der Reparaturstation „121 Build-Supplies" viermal im selben kurzen Text:
 * im Titel, im Einleitungssatz, in der Tabelle und in der Frage darunter.
 *
 * Gestrichen wird **wertgetrieben**, nicht nach Satzposition: Ein Satz fällt
 * weg, wenn sich jedes seiner inhaltstragenden Wörter in der Tabelle
 * wiederfindet. „Kaliber 5,56 × 45 mm." fällt damit weg, „Stand: Oktober 2026
 * · Early Access." und „Von Anfang an verfügbar …" bleiben stehen. Ändert
 * sich der Satzbau in der Antwort, bleibt die Regel gültig.
 *
 * @param string $text  Direktantwort.
 * @param array  $werte Ergebnis von warleek_reddit_item_werte().
 * @return string
 */
function warleek_reddit_ohne_werte( $text, array $werte ) {
	$tabelle = array();
	foreach ( $werte as $w ) {
		foreach ( explode( ' ', warleek_reddit_norm( $w[0] . ' ' . $w[1] ) ) as $t ) {
			if ( '' !== $t ) { $tabelle[ warleek_reddit_stamm( $t ) ] = 1; }
		}
	}
	if ( ! $tabelle ) { return $text; }

	$saetze = preg_split( '/(?<=\.)\s+/u', trim( (string) $text ) );
	$bleibt = array();
	foreach ( (array) $saetze as $satz ) {
		$woerter = array_filter( explode( ' ', warleek_reddit_norm( $satz ) ), function ( $t ) {
			return mb_strlen( $t ) > 1;
		} );
		$neu = false;
		foreach ( $woerter as $t ) {
			if ( ! isset( $tabelle[ warleek_reddit_stamm( $t ) ] ) ) { $neu = true; break; }
		}
		if ( $neu || ! $woerter ) { $bleibt[] = $satz; }
	}
	// Alles gestrichen hieße ein Beitrag ohne Einleitung – dann lieber doppelt.
	return $bleibt ? implode( ' ', $bleibt ) : $text;
}

/**
 * Grober Wortstamm – nur für den Doppelungs-Test.
 *
 * „einmalige" gegen „einmalig" in der Tabellenbeschriftung reichte, um einen
 * Satz stehen zu lassen, der sonst Wort für Wort schon darüberstand. Ein
 * vollwertiger Stemmer wäre dafür zu viel; abgeschnitten werden die fünf
 * häufigen Endungen, und nur, wenn genug Wort übrig bleibt.
 *
 * @param string $t Ein kleingeschriebenes Wort.
 * @return string
 */
function warleek_reddit_stamm( $t ) {
	foreach ( array( 'en', 'em', 'er', 'es', 'e' ) as $endung ) {
		if ( str_ends_with( $t, $endung ) && mb_strlen( $t ) - mb_strlen( $endung ) >= 4 ) {
			return mb_substr( $t, 0, mb_strlen( $t ) - mb_strlen( $endung ) );
		}
	}
	return $t;
}

/**
 * Was hinter den Namen eines Eintrags in den Titel gehört.
 *
 * Ein Titel, der bei 198 Beiträgen immer gleich endet, ist eine Feed-Zeile.
 * Steht eine Zahl zur Verfügung, steht sie im Titel – sie ist der Grund, den
 * Beitrag zu öffnen.
 *
 * **Ohne Präposition**, siehe CLAUDE.md: „ab Assault 3" ginge, „über mittlerer
 * Hammer" nicht, und das grammatische Geschlecht steht nirgends in den Daten.
 * Deshalb Doppelpunkt statt Vorwort.
 *
 * @param array $werte Ergebnis von warleek_reddit_item_werte().
 * @return string
 */
function warleek_reddit_item_titelzusatz( array $werte ) {
	$map = array();
	foreach ( $werte as $w ) { $map[ $w[0] ] = $w[1]; }

	$kern = $map['Preis'] ?? $map['Baukosten'] ?? '';
	$frei = $map['Freischaltung'] ?? '';

	if ( '' === $kern && '' === $frei ) { return ' – Werte und Einordnung'; }
	if ( '' === $kern ) { return ' – Freischaltung: ' . $frei; }
	if ( '' === $frei ) { return ' – ' . $kern; }
	return ' – ' . $kern . ', Freischaltung: ' . $frei;
}

/**
 * Beitragstext zu einem Beitrag der Seite.
 *
 * Aufbau: Direktantwort, bei Einträgen das Datenblatt, bis zu drei Fragen,
 * Quellenlage, Link. Bewusst kurz – siehe Kopfkommentar.
 *
 * @param int|WP_Post $post Guide oder Datenbank-Eintrag.
 * @return array{titel: string, text: string}|WP_Error
 */
function warleek_reddit_text( $post ) {
	$post = get_post( $post );
	if ( ! $post ) { return new WP_Error( 'warleek_reddit', 'Beitrag nicht gefunden.' ); }

	$ist_item = 'item' === $post->post_type;
	$aeo      = function_exists( 'warleek_aeo_extract' )
		? warleek_aeo_extract( (string) get_post_field( 'post_content', $post, 'raw' ) )
		: array( 'frage' => '', 'antwort' => '', 'faq' => array() );

	$werte = $ist_item ? warleek_reddit_item_werte( $post->ID ) : array();

	$titel = get_the_title( $post );
	if ( $ist_item ) { $titel = 'Wardogs ' . $titel . warleek_reddit_item_titelzusatz( $werte ); }

	$zeilen = array();

	if ( '' !== $aeo['antwort'] ) {
		if ( '' !== $aeo['frage'] && str_ends_with( $aeo['frage'], '?' ) ) {
			$zeilen[] = '**' . $aeo['frage'] . '**';
			$zeilen[] = '';
		}
		$zeilen[] = $werte ? warleek_reddit_ohne_werte( $aeo['antwort'], $werte ) : $aeo['antwort'];
	} else {
		$kurz = trim( (string) $post->post_excerpt );
		if ( '' === $kurz ) { $kurz = wp_trim_words( wp_strip_all_tags( $post->post_content ), 45, '…' ); }
		$zeilen[] = $kurz;
	}

	/* Das Datenblatt ist bei einem Eintrag der eigentliche Inhalt. Ohne es
	   bestand der Beitrag aus einem Satz und einem Link – auf Reddit zu Recht
	   als Spam gelesen. */
	if ( $werte ) {
		$zeilen[] = '';
		$zeilen[] = '| Angabe | Wert |';
		$zeilen[] = '|---|---|';
		foreach ( $werte as $w ) { $zeilen[] = '| ' . $w[0] . ' | ' . $w[1] . ' |'; }
	}

	/* **Beim Eintrag kein FAQ-Abschnitt.** Seine Fragen entstehen aus
	   denselben Werten, die jetzt in der Tabelle stehen
	   (`warleek_item_aeo()`) – auf der Seite ist das für Antwortmaschinen
	   gedacht, in einem kurzen Beitrag wäre es dieselbe Zahl zum vierten Mal.
	   Guides behalten ihr FAQ: das ist geschriebener Text und trägt etwas bei.

	   Was dort trotzdem schon oben steht, fliegt einzeln raus. */
	$faq = array();
	if ( ! $ist_item ) {
		$gesagt = warleek_reddit_norm( implode( ' ', $zeilen ) );
		foreach ( (array) $aeo['faq'] as $p ) {
			$a = warleek_reddit_norm( (string) ( $p['antwort'] ?? '' ) );
			if ( '' === $a || ( '' !== $gesagt && str_contains( $gesagt, $a ) ) ) { continue; }
			$faq[] = $p;
			if ( count( $faq ) >= 3 ) { break; }
		}
	}
	if ( $faq ) {
		$zeilen[] = '';
		foreach ( $faq as $p ) {
			$zeilen[] = '**' . $p['frage'] . '**';
			$zeilen[] = '';
			$zeilen[] = $p['antwort'];
			$zeilen[] = '';
		}
	}

	/* Quellenlage: der Teil, der den Beitrag von Werbung unterscheidet.
	   Die Leerzeile davor ist Pflicht: Ohne sie klebt der Trenner bei einem
	   Eintrag ohne FAQ direkt an der Tabelle und beendet sie nicht sauber. */
	if ( '' !== end( $zeilen ) ) { $zeilen[] = ''; }
	$zeilen[] = '---';
	$zeilen[] = '';
	// Achtung: Bei Datenbank-Einträgen steht hier ein String, bei Guides eine
	// Liste. `(array) ''` ergäbe `array('')` – deshalb vorher prüfen.
	$roh      = get_post_meta( $post->ID, '_warleek_quellen', true );
	$quellen  = is_array( $roh ) ? $roh : array_filter( array_map( 'trim', explode( "\n", (string) $roh ) ) );
	$quellen  = array_values( array_filter( array_map( 'strval', $quellen ) ) );
	if ( $quellen ) {
		$zeilen[] = '**Quellen:** ' . implode( ' · ', $quellen );
		$zeilen[] = '';
	}
	$geprueft = trim( (string) get_post_meta( $post->ID, '_warleek_geprueft', true ) );
	if ( '' !== $geprueft ) {
		$zeilen[] = '**Im Spiel nachgeprüft:** ' . ( str_starts_with( strtolower( $geprueft ), 'nein' ) ? 'nein' : $geprueft );
		$zeilen[] = '';
	}
	$zeilen[] = 'Langfassung mit allen Quellen und dem Änderungsverlauf: ' . get_permalink( $post );

	return array( 'titel' => $titel, 'text' => trim( implode( "\n", $zeilen ) ) );
}

/**
 * Einen Beitrag auf Reddit einstellen und das Ergebnis vermerken.
 *
 * @param int|WP_Post $post  Beitrag.
 * @param bool        $probe Trockenlauf.
 * @return array|WP_Error
 */
function warleek_reddit_poste( $post, $probe = false ) {
	$post = get_post( $post );
	if ( ! $post ) { return new WP_Error( 'warleek_reddit', 'Beitrag nicht gefunden.' ); }
	if ( ! $probe && get_post_meta( $post->ID, WARLEEK_REDDIT_META, true ) ) {
		return new WP_Error( 'warleek_reddit', 'Schon gepostet.' );
	}

	$t = warleek_reddit_text( $post );
	if ( is_wp_error( $t ) ) { return $t; }

	$res = warleek_reddit_submit( $t['titel'], $t['text'], $probe );
	if ( is_wp_error( $res ) ) {
		warleek_reddit_log( sprintf( 'FEHLER %s: %s', $post->post_name, $res->get_error_message() ) );
		return $res;
	}
	if ( $probe ) { return $res; }

	update_post_meta( $post->ID, WARLEEK_REDDIT_META, array( 'url' => $res['url'], 'zeit' => time() ) );
	warleek_reddit_log( sprintf( 'gepostet %s → %s', $post->post_name, $res['url'] ) );
	return $res;
}

/** Kurzes Protokoll, die letzten 40 Zeilen. */
function warleek_reddit_log( $zeile = null ) {
	$log = (array) get_option( WARLEEK_REDDIT_LOG, array() );
	if ( null === $zeile ) { return $log; }
	array_unshift( $log, array( 'zeit' => time(), 'text' => (string) $zeile ) );
	update_option( WARLEEK_REDDIT_LOG, array_slice( $log, 0, 40 ), false );
	return $log;
}

/* ---------------------------------------------------------- Automatik */

/** Neuer Guide veröffentlicht → sofort posten. */
function warleek_reddit_bei_veroeffentlichung( $neu, $alt, $post ) {
	if ( 'publish' !== $neu || 'publish' === $alt ) { return; }
	if ( 'guide' !== $post->post_type ) { return; }                      // Items laufen über die Warteschlange
	if ( '1' !== (string) warleek_opt( 'reddit_auto' ) ) { return; }
	if ( ! warleek_reddit_bereit() ) { return; }
	if ( get_post_meta( $post->ID, WARLEEK_REDDIT_META, true ) ) { return; }

	// Nicht im selben Request posten – ein Fehler bei Reddit darf das
	// Veröffentlichen nicht aufhalten.
	wp_schedule_single_event( time() + 60, 'warleek_reddit_einzeln', array( (int) $post->ID ) );
}
add_action( 'transition_post_status', 'warleek_reddit_bei_veroeffentlichung', 10, 3 );
add_action( 'warleek_reddit_einzeln', function ( $id ) { warleek_reddit_poste( (int) $id ); } );

/**
 * Nächsten Kandidaten für die Warteschlange bestimmen.
 *
 * Reihenfolge: zuerst ältere Guides, dann Datenbank-Einträge – und innerhalb
 * beider nach `crc32` über den Slug gemischt, damit nicht wochenlang
 * alphabetisch dasselbe Thema kommt, die Reihenfolge aber reproduzierbar
 * bleibt.
 *
 * @return WP_Post|null
 */
function warleek_reddit_naechster() {
	foreach ( array( 'guide', 'item' ) as $typ ) {
		$posts = get_posts( array(
			'post_type'      => $typ,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array( array( 'key' => WARLEEK_REDDIT_META, 'compare' => 'NOT EXISTS' ) ),
		) );
		if ( ! $posts ) { continue; }
		usort( $posts, function ( $a, $b ) {
			return crc32( 'warleek-reddit-' . $a->post_name ) <=> crc32( 'warleek-reddit-' . $b->post_name );
		} );
		return $posts[0];
	}
	return null;
}

/** Täglich höchstens einen Nachzügler posten. */
function warleek_reddit_warteschlange() {
	if ( '1' !== (string) warleek_opt( 'reddit_drip' ) || ! warleek_reddit_bereit() ) { return; }
	$p = warleek_reddit_naechster();
	if ( ! $p ) { warleek_reddit_log( 'Warteschlange leer – alles gepostet.' ); return; }
	warleek_reddit_poste( $p );
}
add_action( 'warleek_reddit_drip', 'warleek_reddit_warteschlange' );

/** Cron anmelden bzw. abmelden, je nach Schalter. */
function warleek_reddit_cron_pruefen() {
	$an = '1' === (string) warleek_opt( 'reddit_drip' ) && warleek_reddit_bereit();
	$da = wp_next_scheduled( 'warleek_reddit_drip' );
	if ( $an && ! $da ) {
		// Mitten am Tag statt nachts: Beiträge, die niemand sieht, bringen nichts.
		wp_schedule_event( strtotime( 'tomorrow 17:30' ), 'daily', 'warleek_reddit_drip' );
	} elseif ( ! $an && $da ) {
		wp_unschedule_event( $da, 'warleek_reddit_drip' );
	}
}
add_action( 'init', 'warleek_reddit_cron_pruefen' );

/** Wie viel liegt noch an? */
function warleek_reddit_stand() {
	$offen = 0;
	foreach ( array( 'guide', 'item' ) as $typ ) {
		$q = new WP_Query( array(
			'post_type'      => $typ,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => WARLEEK_REDDIT_META, 'compare' => 'NOT EXISTS' ) ),
		) );
		$offen += (int) $q->found_posts;
	}
	return array(
		'offen'   => $offen,
		'naechst' => wp_next_scheduled( 'warleek_reddit_drip' ),
		'bereit'  => warleek_reddit_bereit(),
	);
}
