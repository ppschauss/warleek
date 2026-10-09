<?php
/**
 * Warleek — Einwilligung für externe Medien und Reichweitenmessung.
 *
 * Die Seite selbst setzt **keine** Werbe- oder Analyse-Cookies und lädt nichts
 * von fremden Servern. Ein Einwilligungsbanner ist dafür rechtlich nicht nötig.
 * Nötig wird er in dem Moment, in dem etwas dazukommt, das Daten an Dritte gibt.
 * Dafür kennt dieses Modul zwei Kategorien:
 *
 *   media     – eingebettete Videos von YouTube und Vimeo (`[warleek_video]`)
 *   statistik – ein Analyse-Skript aus den Einstellungen
 *
 * Beides ist echt gesperrt: Vor der Einwilligung steht statt des Players nur
 * eine Vorschau vom eigenen Server, und das Analyse-Skript liegt als toter Text
 * im Quelltext (`type="text/plain"`) und wird erst danach ausgeführt.
 *
 * Die Entscheidung liegt im localStorage, nicht in einem Cookie – sie wird
 * bei keinem Seitenaufruf mitgeschickt und verlässt das Gerät nicht.
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const WARLEEK_CONSENT_KEY = 'warleek_consent';

/** Welche Kategorien sind eingeschaltet? Ohne Kategorie kein Banner. */
function warleek_consent_kategorien() {
	$k = array();
	if ( '1' === (string) warleek_opt( 'consent_media' ) ) {
		$k['media'] = 'Externe Videos (YouTube, Vimeo)';
	}
	if ( '1' === (string) warleek_opt( 'consent_stats' ) ) {
		$k['statistik'] = (string) warleek_opt( 'consent_stats_label' );
	}
	return $k;
}

/** Ist das Banner eingeschaltet und hat es überhaupt etwas zu fragen? */
function warleek_consent_aktiv() {
	return '1' === (string) warleek_opt( 'consent_enabled' ) && warleek_consent_kategorien();
}

/**
 * Banner-Markup im Fußbereich.
 *
 * Wird immer ausgeliefert und per Skript nur dann sichtbar gemacht, wenn noch
 * keine Entscheidung vorliegt. So gibt es kein Nachladen und kein Springen des
 * Layouts; ohne JavaScript bleibt es unsichtbar und die Seite voll benutzbar.
 */
function warleek_consent_banner() {
	if ( ! warleek_consent_aktiv() || is_admin() ) { return; }

	$kategorien = warleek_consent_kategorien();
	$mehrere    = count( $kategorien ) > 1;
	$ds_id      = (int) ( get_option( 'warleek_page_ids', array() )['datenschutz'] ?? 0 );
	$ds_link    = $ds_id ? get_permalink( $ds_id ) : home_url( '/datenschutz/' );
	?>
	<div class="wl-consent" id="wl-consent" role="dialog" aria-modal="false" aria-labelledby="wl-consent-title" aria-describedby="wl-consent-text" hidden>
		<div class="wl-consent__inner">
			<div class="wl-consent__body">
				<p class="wl-consent__title" id="wl-consent-title"><?php echo esc_html( warleek_opt( 'consent_title' ) ); ?></p>
				<p class="wl-consent__text" id="wl-consent-text"><?php echo esc_html( warleek_opt( 'consent_text' ) ); ?>
					<a href="<?php echo esc_url( $ds_link ); ?>">Datenschutzerklärung</a>
				</p>
				<?php if ( $mehrere ) : ?>
					<ul class="wl-consent__choices">
						<?php foreach ( $kategorien as $schluessel => $label ) : ?>
							<li><label><input type="checkbox" data-wl-consent-cat="<?php echo esc_attr( $schluessel ); ?>"> <?php echo esc_html( $label ); ?></label></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<div class="wl-consent__actions">
				<button type="button" class="wl-btn wl-btn--ghost" data-wl-consent="deny"><?php echo esc_html( warleek_opt( 'consent_decline' ) ); ?></button>
				<?php if ( $mehrere ) : ?>
					<button type="button" class="wl-btn wl-btn--ghost" data-wl-consent="selection">Auswahl speichern</button>
				<?php endif; ?>
				<button type="button" class="wl-btn" data-wl-consent="allow"><?php echo esc_html( warleek_opt( 'consent_accept' ) ); ?></button>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'warleek_consent_banner', 20 );

/**
 * Das Skript dazu – bewusst winzig und inline, damit es keine zusätzliche
 * Anfrage kostet.
 */
function warleek_consent_script() {
	if ( is_admin() ) { return; }
	$kategorien = array_keys( warleek_consent_kategorien() );
	if ( ! $kategorien ) { return; }
	?>
<script>
(function(){
	var KEY = <?php echo wp_json_encode( WARLEEK_CONSENT_KEY ); ?>;
	var KATS = <?php echo wp_json_encode( $kategorien ); ?>;
	function lies(){ try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } }

	var api = {
		/** Liegt eine Einwilligung für diese Kategorie vor? */
		has: function(kat){ var s = lies(); return !!(s && s[kat || 'media']); },
		/** Entscheidung setzen: true/false für alle, oder ein Objekt je Kategorie. */
		set: function(wahl){
			var stand = { v: 2, ts: Date.now() };
			KATS.forEach(function(k){ stand[k] = (typeof wahl === 'object' && wahl !== null) ? !!wahl[k] : !!wahl; });
			try { localStorage.setItem(KEY, JSON.stringify(stand)); } catch (e) {}
			document.dispatchEvent(new CustomEvent('warleek-consent-change', { detail: stand }));
			var b = document.getElementById('wl-consent'); if (b) { b.hidden = true; }
		},
		/** Banner erneut zeigen – für den Link „Einwilligung ändern". */
		reopen: function(){
			var b = document.getElementById('wl-consent'); if (!b) { return; }
			var stand = lies() || {};
			b.querySelectorAll('[data-wl-consent-cat]').forEach(function(f){ f.checked = !!stand[f.getAttribute('data-wl-consent-cat')]; });
			b.hidden = false; b.querySelector('button').focus();
		}
	};
	window.warleekConsent = api;

	document.addEventListener('click', function(ev){
		var knopf = ev.target.closest('[data-wl-consent]');
		if (knopf) {
			ev.preventDefault();
			var art = knopf.getAttribute('data-wl-consent');
			if (art === 'selection') {
				var wahl = {};
				document.querySelectorAll('[data-wl-consent-cat]').forEach(function(f){ wahl[f.getAttribute('data-wl-consent-cat')] = f.checked; });
				api.set(wahl);
			} else { api.set(art === 'allow'); }
			return;
		}
		if (ev.target.closest('[data-wl-consent-reopen]')) { ev.preventDefault(); api.reopen(); }
	});

	function start(){
		if (lies() === null) { var b = document.getElementById('wl-consent'); if (b) { b.hidden = false; } }
		else { document.dispatchEvent(new CustomEvent('warleek-consent-change', { detail: lies() })); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
</script>
	<?php
}
add_action( 'wp_footer', 'warleek_consent_script', 21 );

/* ---------------------------------------------------- Einmalige Umstellung */

/**
 * Statistik-Kategorie einschalten und veraltete Bannertexte ersetzen.
 *
 * Läuft genau einmal. Nötig, weil ein gespeicherter Optionswert die Vorgabe
 * schlägt: `consent_stats` stand als `0` in der Datenbank, und eine neue
 * Vorgabe in `warleek_option_fields()` hätte daran nichts geändert.
 *
 * **Texte werden nur ersetzt, wenn sie unverändert sind.** Der alte Bannertext
 * sagte „lädt nichts von fremden Servern" – das stimmt mit Google Analytics
 * nicht mehr. Eine eigene Formulierung des Betreibers wird aber nicht
 * überschrieben; stattdessen steht dann ein Hinweis im Backend.
 */
function warleek_consent_ga_umstellung() {
	if ( get_option( 'warleek_consent_ga_v1' ) ) { return; }

	$alt = array(
		'consent_title'  => 'Externe Videos erlauben?',
		'consent_text'   => 'Warleek setzt keine Werbe- oder Analyse-Cookies und lädt nichts von fremden Servern. Nur für eingebettete Videos brauchen wir deine Einwilligung – erst dann wird eine Verbindung zu YouTube oder Vimeo aufgebaut.',
		'consent_accept' => 'Externe Videos erlauben',
	);

	$opts   = (array) get_option( 'warleek_options', array() );
	$felder = warleek_option_fields();
	$eigen  = array();

	foreach ( $alt as $key => $alter_text ) {
		if ( ! isset( $opts[ $key ] ) || '' === $opts[ $key ] ) { continue; }   // leer: Vorgabe greift ohnehin
		if ( trim( (string) $opts[ $key ] ) === $alter_text ) {
			$opts[ $key ] = $felder[ $key ][2];                                  // unverändert: neue Fassung übernehmen
		} else {
			$eigen[] = $key;                                                     // eigener Text: stehen lassen
		}
	}

	$opts['consent_enabled'] = '1';
	$opts['consent_stats']   = '1';
	update_option( 'warleek_options', $opts );
	update_option( 'warleek_consent_ga_v1', $eigen ? implode( ',', $eigen ) : '1' );
}
add_action( 'admin_init', 'warleek_consent_ga_umstellung' );

/** Hinweis, wenn eigene Bannertexte stehen geblieben sind. */
add_action( 'admin_notices', function () {
	$stand = (string) get_option( 'warleek_consent_ga_v1' );
	if ( '' === $stand || '1' === $stand || ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="notice notice-warning"><p><strong>Warleek:</strong> Die Statistik-Einwilligung ist jetzt aktiv (Google Analytics). '
		. 'Diese Bannertexte sind von dir angepasst und wurden deshalb <em>nicht</em> überschrieben: <code>' . esc_html( $stand ) . '</code>. '
		. 'Bitte prüfen, ob sie noch stimmen – ein Text, der „wir laden nichts von fremden Servern" sagt, ist mit Analytics falsch.</p></div>';
} );

/* ------------------------------------------------------------- Statistik */

/**
 * Analyse-Skript einbinden – gesperrt bis zur Einwilligung.
 *
 * Der hinterlegte Block steht in einem `<template>` und wird erst ausgeführt,
 * wenn die Kategorie „statistik" freigegeben ist. Ohne Einwilligung passiert
 * nichts, auch kein Netzwerkaufruf.
 *
 * **Warum `<template>` und nicht `<script type="text/plain">`:** Ein Analyse-
 * Schnipsel besteht fast immer aus zwei Tags – externes `gtag.js` plus
 * Konfiguration. Das `</script>` des ersten beendet einen Platzhalter-Script
 * vorzeitig; der Rest des Blocks wird dann zu echten, sofort ausgeführten
 * Skripten und umgeht die Einwilligung. Ein `<template>` ist dagegen inert:
 * Skripte darin werden geparst, aber weder ausgeführt noch nachgeladen.
 */
function warleek_analytics_code() {
	if ( is_admin() ) { return; }
	$code = trim( (string) warleek_opt( 'analytics_code' ) );
	if ( '' === $code ) { return; }

	// Ohne Kategorie „statistik" gibt es keine Einwilligung – dann bleibt es aus.
	if ( ! array_key_exists( 'statistik', warleek_consent_kategorien() ) ) {
		echo "<!-- Warleek: Analyse-Code hinterlegt, aber die Kategorie „Statistik“ ist aus. Nichts geladen. -->\n";
		return;
	}
	?>
<template data-wl-consent-code="statistik"><?php echo $code; // phpcs:ignore WordPress.Security.EscapeOutput -- bewusst roher Einbindungscode aus den Einstellungen ?></template>
<script>
(function(){
	function starten(){
		document.querySelectorAll('template[data-wl-consent-code="statistik"]').forEach(function(platzhalter){
			if (platzhalter.dataset.wlAktiv) { return; }
			platzhalter.dataset.wlAktiv = '1';
			// Der Inhalt eines <template> ist inert: Skripte darin werden geparst,
			// aber nicht ausgeführt und ihr src wird nicht geladen. Zum Starten wird
			// jedes Skript neu erzeugt – ein geklontes führt nicht aus.
			platzhalter.content.childNodes.forEach(function(k){
				if (k.tagName === 'SCRIPT') {
					var s = document.createElement('script');
					[].forEach.call(k.attributes, function(a){ s.setAttribute(a.name, a.value); });
					s.text = k.text;
					document.head.appendChild(s);
				} else if (k.nodeType === 1) {
					document.head.appendChild(k.cloneNode(true));
				}
			});
		});
	}
	document.addEventListener('warleek-consent-change', function(ev){ if (ev.detail && ev.detail.statistik) { starten(); } });
	if (window.warleekConsent && window.warleekConsent.has('statistik')) { starten(); }
})();
</script>
	<?php
}
add_action( 'wp_footer', 'warleek_analytics_code', 23 );

/* ----------------------------------------------------------- Videosperre */

/**
 * Eingebettetes Video mit Sperre.
 *
 *   [warleek_video id="IHcD8c_iqD0" title="…" kanal="…" bild="asset-schluessel"]
 *   [warleek_video plattform="vimeo" id="123456789" title="…"]
 *
 * Ohne Einwilligung steht dort ein Platzhalter aus eigenen Dateien mit einem
 * Knopf. Erst der Klick lädt den Player – und nur dann entsteht überhaupt eine
 * Verbindung zum Anbieter.
 */
function warleek_video_shortcode( $atts ) {
	$a = shortcode_atts( array( 'id' => '', 'title' => 'Video', 'bild' => '', 'kanal' => '', 'plattform' => '' ), $atts, 'warleek_video' );
	$id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $a['id'] );
	if ( '' === $id ) { return ''; }

	// Ohne Angabe: rein numerische IDs sind Vimeo, alles andere YouTube.
	$plattform = strtolower( (string) $a['plattform'] );
	if ( ! in_array( $plattform, array( 'youtube', 'vimeo' ), true ) ) {
		$plattform = ctype_digit( $id ) ? 'vimeo' : 'youtube';
	}
	if ( 'vimeo' === $plattform ) {
		$quelle  = 'https://player.vimeo.com/video/' . $id . '?dnt=1';
		$link    = 'https://vimeo.com/' . $id;
		$anbieter = 'Vimeo (Vimeo.com, Inc.)';
	} else {
		$quelle  = 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0';
		$link    = 'https://www.youtube.com/watch?v=' . $id;
		$anbieter = 'YouTube (Google Ireland Ltd.)';
	}

	$bild = '';
	if ( $a['bild'] ) {
		$map = (array) get_option( 'warleek_media_map', array() );
		if ( ! empty( $map[ $a['bild'] ] ) ) { $bild = wp_get_attachment_image_url( (int) $map[ $a['bild'] ], 'large' ); }
	}

	ob_start();
	?>
	<figure class="wl-video" data-wl-video="<?php echo esc_attr( $quelle ); ?>">
		<div class="wl-video__frame"<?php echo $bild ? ' style="background-image:url(' . esc_url( $bild ) . ')"' : ''; ?>>
			<div class="wl-video__ask">
				<p><strong><?php echo esc_html( $a['title'] ); ?></strong><?php echo $a['kanal'] ? ' · ' . esc_html( $a['kanal'] ) : ''; ?></p>
				<p class="wl-video__hint">Beim Laden wird eine Verbindung zu <?php echo esc_html( $anbieter ); ?> aufgebaut. Dabei werden deine IP-Adresse und Geräteinformationen übertragen.</p>
				<p>
					<button type="button" class="wl-btn" data-wl-video-load>Video laden</button>
					<a class="wl-btn wl-btn--ghost" href="<?php echo esc_url( $link ); ?>" rel="nofollow noopener noreferrer">Beim Anbieter öffnen</a>
				</p>
			</div>
		</div>
	</figure>
	<?php
	return trim( ob_get_clean() );
}
add_shortcode( 'warleek_video', 'warleek_video_shortcode' );

/** Das Skript, das die Platzhalter austauscht – nur wenn ein Video auf der Seite steht. */
function warleek_video_script() {
	?>
<script>
(function(){
	function laden(fig){
		var src = fig.getAttribute('data-wl-video'); if (!src || fig.dataset.wlGeladen) { return; }
		fig.dataset.wlGeladen = '1';
		var rahmen = fig.querySelector('.wl-video__frame');
		rahmen.innerHTML = '';
		var f = document.createElement('iframe');
		f.src = src; f.title = 'Eingebettetes Video'; f.loading = 'lazy'; f.allowFullscreen = true;
		f.setAttribute('allow', 'accelerometer; encrypted-media; picture-in-picture; fullscreen');
		f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
		rahmen.appendChild(f);
	}
	function alle(){ document.querySelectorAll('[data-wl-video]').forEach(laden); }

	document.addEventListener('click', function(ev){
		var k = ev.target.closest('[data-wl-video-load]'); if (!k) { return; }
		ev.preventDefault();
		if (window.warleekConsent) { window.warleekConsent.set({ media: true, statistik: window.warleekConsent.has('statistik') }); }
		laden(k.closest('[data-wl-video]'));
	});
	document.addEventListener('warleek-consent-change', function(ev){ if (ev.detail && ev.detail.media) { alle(); } });
	if (window.warleekConsent && window.warleekConsent.has('media')) { alle(); }
})();
</script>
	<?php
}
add_action( 'wp_footer', function () {
	if ( is_admin() ) { return; }
	global $post;
	if ( $post && has_shortcode( (string) $post->post_content, 'warleek_video' ) ) { warleek_video_script(); }
}, 22 );

/* --------------------------------------------------------- Externe Links */

/**
 * Externe Links in Inhalten entwerten.
 *
 * Quellenangaben sollen kein Ranking weitergeben: `nofollow`. Dazu `noopener`
 * und `noreferrer`, damit die Zielseite weder auf das Ursprungsfenster zugreifen
 * noch die Herkunft mitlesen kann.
 *
 * Kein `noindex`: Das gibt es nur als Anweisung für eine **Seite**, nicht für
 * einen einzelnen Link. Wer eine eigene Seite aus dem Index halten will, nimmt
 * das Robots-Meta – bei Links ist `nofollow` das Gegenstück.
 */
function warleek_externe_links_entwerten( $content ) {
	if ( is_admin() || '1' !== (string) warleek_opt( 'external_nofollow' ) ) { return $content; }
	if ( ! str_contains( $content, '<a ' ) ) { return $content; }
	$eigen = wp_parse_url( home_url(), PHP_URL_HOST );

	return (string) preg_replace_callback(
		'#<a\s([^>]*href=["\'](https?://[^"\']+)["\'][^>]*)>#i',
		function ( $m ) use ( $eigen ) {
			$ziel = wp_parse_url( $m[2], PHP_URL_HOST );
			if ( ! $ziel || $ziel === $eigen ) { return $m[0]; }

			$attr = $m[1];
			$soll = array( 'nofollow', 'noopener', 'noreferrer' );
			if ( preg_match( '/\srel=["\']([^"\']*)["\']/i', $attr, $rel ) ) {
				$vorhanden = preg_split( '/\s+/', trim( $rel[1] ) ) ?: array();
				$neu       = implode( ' ', array_unique( array_filter( array_merge( $vorhanden, $soll ) ) ) );
				$attr      = str_replace( $rel[0], ' rel="' . $neu . '"', $attr );
			} else {
				$attr .= ' rel="' . implode( ' ', $soll ) . '"';
			}
			return '<a ' . $attr . '>';
		},
		$content
	);
}
add_filter( 'the_content', 'warleek_externe_links_entwerten', 20 );
