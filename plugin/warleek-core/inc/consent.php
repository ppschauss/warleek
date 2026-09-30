<?php
/**
 * Warleek — Einwilligung für externe Medien.
 *
 * Die Seite selbst setzt **keine** Werbe- oder Analyse-Cookies und lädt nichts
 * von fremden Servern. Ein Einwilligungsbanner ist dafür rechtlich nicht nötig.
 * Nötig wird er in dem Moment, in dem etwas eingebettet wird, das beim Aufruf
 * eine Verbindung zu Dritten herstellt – bei uns: YouTube-Videos.
 *
 * Deshalb ist das hier kein Deko-Banner, sondern eine echte Sperre:
 * `[warleek_video]` zeigt bis zur Einwilligung nur eine Vorschau aus eigenen
 * Dateien. Erst ein Klick auf „Video laden" holt den Player von YouTube.
 *
 * Die Entscheidung liegt im localStorage, nicht in einem Cookie – sie wird
 * nirgends mitgeschickt und verlässt das Gerät nicht.
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const WARLEEK_CONSENT_KEY = 'warleek_consent';

/** Ist das Banner eingeschaltet? */
function warleek_consent_aktiv() {
	return '1' === (string) warleek_opt( 'consent_enabled' );
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

	$titel   = (string) warleek_opt( 'consent_title' );
	$text    = (string) warleek_opt( 'consent_text' );
	$ja      = (string) warleek_opt( 'consent_accept' );
	$nein    = (string) warleek_opt( 'consent_decline' );
	$ds_id   = (int) ( get_option( 'warleek_page_ids', array() )['datenschutz'] ?? 0 );
	$ds_link = $ds_id ? get_permalink( $ds_id ) : home_url( '/datenschutz/' );
	?>
	<div class="wl-consent" id="wl-consent" role="dialog" aria-modal="false" aria-labelledby="wl-consent-title" aria-describedby="wl-consent-text" hidden>
		<div class="wl-consent__inner">
			<div class="wl-consent__body">
				<p class="wl-consent__title" id="wl-consent-title"><?php echo esc_html( $titel ); ?></p>
				<p class="wl-consent__text" id="wl-consent-text"><?php echo esc_html( $text ); ?>
					<a href="<?php echo esc_url( $ds_link ); ?>">Datenschutzerklärung</a>
				</p>
			</div>
			<div class="wl-consent__actions">
				<button type="button" class="wl-btn wl-btn--ghost" data-wl-consent="deny"><?php echo esc_html( $nein ); ?></button>
				<button type="button" class="wl-btn" data-wl-consent="allow"><?php echo esc_html( $ja ); ?></button>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'warleek_consent_banner', 20 );

/**
 * Das Skript dazu – bewusst winzig und inline, damit es keine zusätzliche
 * Anfrage kostet und vor dem ersten Bild steht.
 */
function warleek_consent_script() {
	if ( ! warleek_consent_aktiv() || is_admin() ) { return; }
	$key = WARLEEK_CONSENT_KEY;
	?>
<script>
(function(){
	var KEY = <?php echo wp_json_encode( $key ); ?>;
	function lies(){ try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } }
	function schreib(wert){ try { localStorage.setItem(KEY, JSON.stringify(wert)); } catch (e) {} }

	var api = {
		/** Liegt eine Einwilligung für die Kategorie vor? */
		has: function(kat){ var s = lies(); return !!(s && s[kat || 'media']); },
		/** Entscheidung setzen und alle Platzhalter benachrichtigen. */
		set: function(erlaubt){
			schreib({ v: 1, media: !!erlaubt, ts: Date.now() });
			document.dispatchEvent(new CustomEvent('warleek-consent-change', { detail: { media: !!erlaubt } }));
			var b = document.getElementById('wl-consent'); if (b) { b.hidden = true; }
		},
		/** Banner erneut zeigen – für den Link „Einwilligung ändern". */
		reopen: function(){ var b = document.getElementById('wl-consent'); if (b) { b.hidden = false; b.querySelector('button').focus(); } }
	};
	window.warleekConsent = api;

	document.addEventListener('click', function(ev){
		var knopf = ev.target.closest('[data-wl-consent]');
		if (knopf) { ev.preventDefault(); api.set(knopf.getAttribute('data-wl-consent') === 'allow'); return; }
		if (ev.target.closest('[data-wl-consent-reopen]')) { ev.preventDefault(); api.reopen(); }
	});

	// Banner nur zeigen, wenn noch nichts entschieden wurde.
	function start(){
		if (lies() === null) { var b = document.getElementById('wl-consent'); if (b) { b.hidden = false; } }
		if (api.has('media')) { document.dispatchEvent(new CustomEvent('warleek-consent-change', { detail: { media: true } })); }
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); } else { start(); }
})();
</script>
	<?php
}
add_action( 'wp_footer', 'warleek_consent_script', 21 );

/**
 * Eingebettetes Video mit Sperre: `[warleek_video id="IHcD8c_iqD0" title="…" bild="guide-hotas-settings"]`
 *
 * Ohne Einwilligung steht dort ein Platzhalter aus eigenen Dateien mit einem
 * Knopf. Erst der Klick lädt den Player – und nur dann entsteht überhaupt eine
 * Verbindung zu YouTube.
 */
function warleek_video_shortcode( $atts ) {
	$a = shortcode_atts( array( 'id' => '', 'title' => 'Video', 'bild' => '', 'kanal' => '' ), $atts, 'warleek_video' );
	$id = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $a['id'] );
	if ( '' === $id ) { return ''; }

	$bild = '';
	if ( $a['bild'] ) {
		$map = (array) get_option( 'warleek_media_map', array() );
		if ( ! empty( $map[ $a['bild'] ] ) ) { $bild = wp_get_attachment_image_url( (int) $map[ $a['bild'] ], 'large' ); }
	}
	$quelle = 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0';
	$link   = 'https://www.youtube.com/watch?v=' . $id;

	ob_start();
	?>
	<figure class="wl-video" data-wl-video="<?php echo esc_attr( $quelle ); ?>">
		<div class="wl-video__frame"<?php echo $bild ? ' style="background-image:url(' . esc_url( $bild ) . ')"' : ''; ?>>
			<div class="wl-video__ask">
				<p><strong><?php echo esc_html( $a['title'] ); ?></strong><?php echo $a['kanal'] ? ' · ' . esc_html( $a['kanal'] ) : ''; ?></p>
				<p class="wl-video__hint">Beim Laden wird eine Verbindung zu YouTube (Google Ireland Ltd.) aufgebaut. Dabei werden deine IP-Adresse und Geräteinformationen übertragen.</p>
				<p>
					<button type="button" class="wl-btn" data-wl-video-load>Video laden</button>
					<a class="wl-btn wl-btn--ghost" href="<?php echo esc_url( $link ); ?>" rel="noopener">Bei YouTube öffnen</a>
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
		f.src = src; f.title = 'YouTube-Video'; f.loading = 'lazy'; f.allowFullscreen = true;
		f.setAttribute('allow', 'accelerometer; encrypted-media; picture-in-picture; fullscreen');
		f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
		rahmen.appendChild(f);
	}
	function alle(){ document.querySelectorAll('[data-wl-video]').forEach(laden); }

	document.addEventListener('click', function(ev){
		var k = ev.target.closest('[data-wl-video-load]'); if (!k) { return; }
		ev.preventDefault();
		if (window.warleekConsent) { window.warleekConsent.set(true); } else { laden(k.closest('[data-wl-video]')); }
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
