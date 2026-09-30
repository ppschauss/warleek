<?php
/**
 * Warleek — Farbwelt und Hell/Dunkel für Besucherinnen und Besucher.
 *
 * Zwei unabhängige Achsen, beide im localStorage:
 *   `warleek_mode`  auto | light | dark   (auto folgt der Systemeinstellung)
 *   `warleek_theme` warleek | monochrom | valkyra | lonestar | manticore | pastell
 *
 * Gesetzt wird beides als Attribut am <html>-Element, und zwar von einem
 * winzigen Skript **im Kopfbereich** – noch bevor etwas gezeichnet wird.
 * Sonst blitzt beim Laden kurz die Standardfarbe auf.
 *
 * Gestaltet wird das Ganze im Theme (`assets/css/themes.css`); hier stehen nur
 * die Auswahl, die Beschriftungen und das Merken.
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Die Farbwelten: Schlüssel => [Name, Beschreibung fürs Vorlesen]. */
function warleek_themes() {
	return array(
		'warleek'   => array( 'Warleek', 'Lauchgrün, die Standardfarbe der Seite' ),
		'monochrom' => array( 'Monochrom', 'ohne Farbakzent, nur Grautöne' ),
		'valkyra'   => array( 'Valkyra', 'Rot, nach der roten Fraktion' ),
		'lonestar'  => array( 'Lonestar', 'Blau, nach der blauen Fraktion' ),
		'manticore' => array( 'Manticore', 'Grün, nach der grünen Fraktion' ),
		'pastell'   => array( 'Pastell', 'Rosa und Violett' ),
	);
}

/** Die Modi: Schlüssel => Name. */
function warleek_modes() {
	return array(
		'auto'  => 'System',
		'light' => 'Hell',
		'dark'  => 'Dunkel',
	);
}

/**
 * Setzt die Attribute, bevor die Seite gezeichnet wird.
 *
 * Bewusst inline und ganz oben: Eine zusätzliche Datei würde erst nach dem
 * ersten Bild geladen, und genau dann sieht man das Umspringen.
 */
function warleek_appearance_head() {
	if ( is_admin() ) { return; }
	?>
<script>
(function(){
	var h = document.documentElement;
	h.classList.add('wl-js');
	try {
		var m = localStorage.getItem('warleek_mode');
		var t = localStorage.getItem('warleek_theme');
		if (m === 'light' || m === 'dark') { h.setAttribute('data-wl-mode', m); }
		if (t && /^[a-z]+$/.test(t)) { h.setAttribute('data-wl-theme', t); }
	} catch (e) {}
})();
</script>
	<?php
}
add_action( 'wp_head', 'warleek_appearance_head', 0 );

/**
 * Der Umschalter. Als Shortcode `[warleek_appearance]` überall platzierbar.
 *
 * Ohne JavaScript werden die Felder ausgeblendet – sie könnten dann nichts
 * bewirken, und ein toter Schalter ist schlimmer als keiner.
 */
function warleek_appearance_shortcode( $atts ) {
	$a = shortcode_atts( array( 'titel' => 'Darstellung' ), $atts, 'warleek_appearance' );
	ob_start();
	?>
	<div class="wl-appearance" data-wl-appearance>
		<fieldset>
			<legend>Modus</legend>
			<div class="wl-appearance__row">
				<?php foreach ( warleek_modes() as $key => $name ) : ?>
					<label class="wl-appearance__opt">
						<input type="radio" name="wl-mode" value="<?php echo esc_attr( $key ); ?>">
						<span><?php echo esc_html( $name ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<fieldset>
			<legend>Farbe</legend>
			<div class="wl-appearance__row">
				<?php foreach ( warleek_themes() as $key => $def ) : ?>
					<label class="wl-appearance__opt" title="<?php echo esc_attr( $def[1] ); ?>">
						<input type="radio" name="wl-theme" value="<?php echo esc_attr( $key ); ?>">
						<span class="wl-appearance__dot wl-appearance__dot--<?php echo esc_attr( $key ); ?>" aria-hidden="true"></span>
						<span><?php echo esc_html( $def[0] ); ?></span>
						<span class="screen-reader-text"> – <?php echo esc_html( $def[1] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
	</div>
	<script>
	(function(){
		var box = document.currentScript.previousElementSibling;
		if (!box) { return; }
		var h = document.documentElement;
		function lies(k, standard){ try { return localStorage.getItem(k) || standard; } catch (e) { return standard; } }
		function setze(k, v){ try { localStorage.setItem(k, v); } catch (e) {} }

		var modus = lies('warleek_mode', 'auto');
		var farbe = lies('warleek_theme', 'warleek');
		box.querySelectorAll('input[name="wl-mode"]').forEach(function(f){ f.checked = (f.value === modus); });
		box.querySelectorAll('input[name="wl-theme"]').forEach(function(f){ f.checked = (f.value === farbe); });

		box.addEventListener('change', function(ev){
			var f = ev.target;
			if (f.name === 'wl-mode') {
				setze('warleek_mode', f.value);
				if (f.value === 'auto') { h.removeAttribute('data-wl-mode'); } else { h.setAttribute('data-wl-mode', f.value); }
			} else if (f.name === 'wl-theme') {
				setze('warleek_theme', f.value);
				h.setAttribute('data-wl-theme', f.value);
			}
		});
	})();
	</script>
	<?php
	return trim( ob_get_clean() );
}
add_shortcode( 'warleek_appearance', 'warleek_appearance_shortcode' );

/**
 * Block `warleek/appearance` – damit der Umschalter im Editor platzierbar ist
 * und nicht als Shortcode-Text in einer Vorlage steht.
 */
function warleek_register_appearance_block() {
	register_block_type( 'warleek/appearance', array(
		'api_version'     => 3,
		'title'           => 'Darstellung umschalten',
		'category'        => 'warleek',
		'icon'            => 'art',
		'description'     => 'Hell/Dunkel und Farbwelt zur Auswahl für Besucher.',
		'render_callback' => function ( $attrs ) { return warleek_appearance_shortcode( (array) $attrs ); },
		'attributes'      => array( 'titel' => array( 'type' => 'string', 'default' => 'Darstellung' ) ),
		'supports'        => array( 'html' => false ),
	) );
}
add_action( 'init', 'warleek_register_appearance_block' );
