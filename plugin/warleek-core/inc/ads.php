<?php
/**
 * Warleek — Google AdSense.
 *
 * Zwei Teile: das Skript im Kopfbereich und die Anzeigenplätze im Guide.
 *
 * Das Meta-Tag zur Bestätigung der Domain steht **nicht** hier, sondern fest
 * verdrahtet in `theme/warleek/functions.php`. Begründung dort.
 *
 * **Warum das Skript ohne Einwilligung lädt.** Für die Auslieferung an Nutzer
 * im EWR und in Großbritannien verlangt Google seit Januar 2024 eine von
 * Google zertifizierte CMP. Wir benutzen dafür Googles eigene „Privacy &
 * messaging"-Nachricht – und die wird von genau diesem Skript ausgeliefert.
 * Das Skript erst nach einer Einwilligung zu laden wäre ein Zirkelschluss:
 * Ohne Skript keine Abfrage, ohne Abfrage keine Einwilligung.
 *
 * Was daraus folgt und in der Datenschutzerklärung so stehen muss: Beim ersten
 * Aufruf entsteht eine Verbindung zu Google, bevor der Nutzer entschieden hat.
 * Anzeigen werden dabei **nicht** ausgeliefert – der Consent Mode steht per
 * Vorgabe auf `denied` (siehe `warleek_consent_mode_defaults()`), und Googles
 * CMP hebt ihn erst nach der Zustimmung an.
 *
 * **Die Anzeigen stehen an festen Plätzen, nicht per Auto Ads.** Auto Ads
 * schiebt Anzeigen auch in den Antwortkasten und zwischen die FAQ-Fragen. Das
 * JSON-LD entsteht zwar aus dem gespeicherten Inhalt und bliebe unberührt, der
 * sichtbare Text aber nicht – und eine Antwort, die von einer Anzeige
 * durchschnitten wird, ist für Leser wie für Antwortmaschinen schlechter.
 *
 * **Eingefügt wird beim Ausliefern, nicht beim Einspielen.** Stünden die
 * Anzeigenblöcke im gespeicherten Inhalt, lägen sie im JSON-LD, im
 * Reddit-Beitrag, im Herkunftsnachweis und in jeder Sicherung. Ein Filter auf
 * `the_content` hält sie dort heraus, wo sie nicht hingehören.
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Kennung des AdSense-Kontos, auf Form geprüft. Leer = nichts einbinden.
 *
 * **Ohne zweites Argument aufrufen.** `warleek_opt()` behandelt einen
 * ausdrücklich übergebenen Fallback als Vorrang vor der Vorgabe aus
 * `warleek_option_fields()` – ein `warleek_opt( 'ads_client', '' )` liefert
 * also immer '' und kommt an der hinterlegten Kennung nie vorbei. Genau daran
 * ist das Meta-Tag bis 2.37.2 hängen geblieben: Version eingespielt, Kennung
 * als Vorgabe vorhanden, Ausgabe trotzdem leer.
 */
function warleek_ads_client() {
	$v = trim( (string) warleek_opt( 'ads_client' ) );
	return preg_match( '/^ca-pub-\d{10,20}$/', $v ) ? $v : '';
}

/** Anzeigen eingeschaltet und eine brauchbare Kennung hinterlegt? */
function warleek_ads_aktiv() {
	return '1' === (string) warleek_opt( 'ads_enabled' ) && '' !== warleek_ads_client();
}

/**
 * Stellen, an denen Anzeigen erscheinen dürfen.
 *
 * Bewusst eng: Nur einzelne Guides und Patch Notes. Auf Übersichtsseiten,
 * im Impressum und in der Datenschutzerklärung steht nichts – eine Anzeige
 * neben dem Text, der die Anzeige erklärt, ist albern.
 */
function warleek_ads_erlaubt() {
	if ( is_admin() || is_feed() || is_embed() || is_404() ) { return false; }
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return false; }
	return warleek_ads_aktiv();
}

/**
 * Das AdSense-Skript im Kopfbereich.
 *
 * Steht bewusst **nach** `warleek_consent_mode_defaults()` (Priorität 1), damit
 * der Zustimmungsstand gesetzt ist, bevor Google das Skript auswertet.
 */
function warleek_ads_script() {
	if ( ! warleek_ads_erlaubt() ) { return; }
	printf(
		'<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=%s" crossorigin="anonymous"></script>' . "\n",
		esc_attr( warleek_ads_client() )
	);
}
add_action( 'wp_head', 'warleek_ads_script', 5 );

/**
 * Ein Anzeigenplatz.
 *
 * Die Beschriftung „Anzeige" ist keine Höflichkeit, sondern Pflicht: Werbung
 * muss in Deutschland klar vom redaktionellen Inhalt getrennt und als solche
 * erkennbar sein (Trennungsgebot, § 6 TMG und UWG). Deshalb steht sie im
 * Markup und nicht nur im Stylesheet – ein ausgefallenes CSS darf sie nicht
 * verschwinden lassen.
 *
 * Ohne hinterlegte Platz-Kennung wird nichts ausgegeben. Ein `<ins>` ohne
 * `data-ad-slot` füllt Google nicht, hinterlässt aber eine leere Fläche.
 *
 * @param string $slot  Platz-Kennung aus dem AdSense-Konto.
 * @param string $klasse Zusätzliche CSS-Klasse.
 * @return string HTML oder ''.
 */
function warleek_ads_einheit( $slot, $klasse = '' ) {
	$slot = preg_replace( '/\D/', '', (string) $slot );
	if ( '' === $slot ) { return ''; }

	return '<aside class="wl-anzeige ' . esc_attr( $klasse ) . '" aria-label="Anzeige">'
		. '<span class="wl-anzeige__label">Anzeige</span>'
		. '<ins class="adsbygoogle" style="display:block" data-ad-client="' . esc_attr( warleek_ads_client() ) . '"'
		. ' data-ad-slot="' . esc_attr( $slot ) . '" data-ad-format="auto" data-full-width-responsive="true"></ins>'
		. '<script>(adsbygoogle = window.adsbygoogle || []).push({});</script>'
		. '</aside>';
}

/**
 * Einfügepunkte im gerenderten Guide finden.
 *
 * Zwei Plätze, beide vor einer `<h2>`: einer nach den ersten beiden
 * Abschnitten, einer vor den häufigen Fragen. Vor einer Überschrift zu
 * schneiden ist die einzige Stelle, an der ein Einschub nichts zerreißt –
 * mitten in einem Absatz, einer Tabelle oder einer Liste stünde die Anzeige
 * im Satz.
 *
 * **Niemals in den FAQ-Abschnitt hinein.** Dessen Antworten enden laut
 * `inc/aeo.php` an der nächsten Überschrift oder am nächsten Kasten; eine
 * Anzeige zwischen zwei Fragen würde im sichtbaren Text eine Antwort
 * abschneiden. Deshalb steht der zweite Platz **vor** der FAQ-Überschrift.
 *
 * @param string $html Gerenderter Inhalt.
 * @return array<int,int> Byte-Positionen, aufsteigend.
 */
function warleek_ads_punkte( $html ) {
	if ( ! preg_match_all( '/<h2\b/i', $html, $m, PREG_OFFSET_CAPTURE ) ) { return array(); }
	$h2 = array_column( $m[0], 1 );

	$punkte = array();

	// Platz 1: vor der dritten Überschrift – also nach zwei gelesenen Abschnitten.
	if ( isset( $h2[2] ) ) { $punkte[] = $h2[2]; }

	// Platz 2: vor „Häufige Fragen". Die Überschrift kann eine Zahl oder einen
	// Anker tragen, deshalb über den Text statt über die Position gesucht.
	if ( preg_match( '/<h2\b[^>]*>\s*(?:Häufige Fragen|FAQ|Fragen und Antworten)\s*<\/h2>/ui', $html, $f, PREG_OFFSET_CAPTURE ) ) {
		$punkte[] = $f[0][1];
	}

	$punkte = array_values( array_unique( $punkte ) );
	sort( $punkte );

	// Zwei Anzeigen direkt hintereinander sind eine Anzeigenwand. Liegt der
	// zweite Platz zu dicht am ersten, fällt er weg.
	if ( 2 === count( $punkte ) && $punkte[1] - $punkte[0] < 1200 ) { array_pop( $punkte ); }

	return $punkte;
}

/**
 * Anzeigen in den Guide einsetzen.
 *
 * Von hinten nach vorn, damit die vorher ermittelten Positionen gültig
 * bleiben – ein Einschub vorn verschiebt sonst alles dahinter.
 */
function warleek_ads_in_inhalt( $content ) {
	if ( ! warleek_ads_erlaubt() || ! is_singular( array( 'guide', 'patchnote' ) ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$einheiten = array(
		warleek_ads_einheit( (string) warleek_opt( 'ads_slot_text' ), 'wl-anzeige--text' ),
		warleek_ads_einheit( (string) warleek_opt( 'ads_slot_faq' ), 'wl-anzeige--faq' ),
	);
	$einheiten = array_values( array_filter( $einheiten ) );
	if ( ! $einheiten ) { return $content; }

	$punkte = warleek_ads_punkte( $content );
	$anzahl = min( count( $punkte ), count( $einheiten ) );
	if ( ! $anzahl ) { return $content; }

	for ( $i = $anzahl - 1; $i >= 0; $i-- ) {
		$content = substr_replace( $content, $einheiten[ $i ], $punkte[ $i ], 0 );
	}
	return $content;
}
add_filter( 'the_content', 'warleek_ads_in_inhalt', 20 );

/* ------------------------------------------------------------------ ads.txt */
/**
 * Kennung für die `/ads.txt` – fest verdrahtet, aus demselben Grund wie das
 * Bestätigungs-Tag im Theme: Google liest diese Datei fortlaufend, und eine
 * Kennung, die von Optionswert, Schalter und Vorrangregel abhängt, kann
 * unbemerkt verschwinden. Verschwindet sie, meldet AdSense „Nicht gefunden"
 * und Werbeplätze verlieren an Wert. Eine Zeichenkette im Quelltext kann das
 * nicht. Ändert sich das Konto, ändert sich hier und im Theme je eine Zeile.
 */
const WARLEEK_ADS_PUB = 'pub-5566154579483105';

/**
 * Inhalt der `/ads.txt`.
 *
 * Eine Zeile nach IAB-Spezifikation: Werbesystem, Konto-Kennung ohne das
 * `ca-`-Präfix, Direktvermarktung, Zertifizierungs-ID von Google. Die ID
 * `f08c47fec0942fa0` ist Googles eigene und in jeder AdSense-`ads.txt`
 * dieselbe – sie bezeichnet nicht uns, sondern Google.
 */
function warleek_ads_txt_inhalt() {
	return 'google.com, ' . WARLEEK_ADS_PUB . ', DIRECT, f08c47fec0942fa0' . "\n";
}

/**
 * Eigene Adresse anmelden.
 *
 * **Warum eine Rewrite-Regel und kein `init`-Hook auf `REQUEST_URI`.** Die
 * erste Fassung (bis 2.37.1) prüfte in `init` den angefragten Pfad und gab den
 * Text direkt aus. Auf All-Inkl landete der Aufruf trotzdem im WordPress-404.
 * `/herkunft.json` wird seit Monaten verlässlich ausgeliefert, und zwar über
 * Rewrite-Regel, Query-Var und `template_redirect`. Dieser Weg ist erprobt,
 * also nehmen wir ihn auch hier, statt den alten zu reparieren.
 *
 * Eine echte Datei im Web-Verzeichnis käme ohnehin zuerst – der Webserver
 * reicht die Anfrage dann gar nicht erst an WordPress weiter.
 */
function warleek_ads_txt_rewrite() {
	add_rewrite_rule( '^ads\.txt$', 'index.php?warleek_ads_txt=1', 'top' );
}
add_action( 'init', 'warleek_ads_txt_rewrite' );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'warleek_ads_txt';
	return $vars;
} );

/**
 * Kein Schrägstrich ans Ende.
 *
 * WordPress hängt Adressen ohne Dateiendung einen Schrägstrich an und leitet
 * dafür um. Bei `/ads.txt` wäre das falsch: Googles Prüfer folgt zwar
 * Weiterleitungen, aber `/ads.txt/` ist keine gültige Adresse mehr.
 */
add_filter( 'redirect_canonical', function ( $ziel ) {
	return get_query_var( 'warleek_ads_txt' ) ? false : $ziel;
} );

/** Ausliefern. */
function warleek_ads_txt_ausliefern() {
	if ( ! get_query_var( 'warleek_ads_txt' ) ) { return; }
	nocache_headers();
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo warleek_ads_txt_inhalt();
	exit;
}
add_action( 'template_redirect', 'warleek_ads_txt_ausliefern' );
