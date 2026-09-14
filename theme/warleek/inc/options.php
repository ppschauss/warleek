<?php
/**
 * Warleek — Optionsseite (Einstellungen → Warleek).
 * Chat-Links, Steam-AppID, Clan-Tag, Kontakt. Zugriff über warleek_opt().
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Felddefinitionen: key => [label, type, default, description]. */
function warleek_option_fields() {
	return array(
		'discord_url'   => array( 'Discord-Invite',        'url',  '',        'Vollständiger Invite-Link, z. B. https://discord.gg/xyz. Leer = Button zeigt „bald".' ),
		'whatsapp_url'  => array( 'WhatsApp-Gruppe',       'url',  '',        'Einladungslink der WhatsApp-Community/-Gruppe.' ),
		'telegram_url'  => array( 'Telegram-Gruppe',       'url',  '',        'Link zur Telegram-Gruppe, z. B. https://t.me/xyz.' ),
		'steam_url'     => array( 'Steam-Store-Link',      'url',  'https://store.steampowered.com/app/1867240/WARDOGS/', 'Wird im Organization-Schema (sameAs) und in Patch Notes verwendet.' ),
		'steam_appid'   => array( 'Steam-AppID',           'int',  '1867240', 'AppID von WARDOGS für den Patch-Notes-Sync.' ),
		'clan_tag'      => array( 'Clan-Tag',              'text', '[WLK]',   'Wird an einzelnen Stellen (z. B. Clan-Seite, Dogtag-Zeile) angezeigt.' ),
		'kontakt_email' => array( 'Kontakt-E-Mail',        'email','',        'Für Kontakt-Hinweise auf der About-us-Seite.' ),
	);
}

/**
 * Liest eine Option. Fällt auf den Feld-Default zurück, wenn leer.
 *
 * @param string $key     Feldschlüssel (siehe warleek_option_fields()).
 * @param mixed  $default Expliziter Fallback (überschreibt Feld-Default).
 * @return mixed
 */
function warleek_opt( $key, $default = null ) {
	$opts   = (array) get_option( 'warleek_options', array() );
	$fields = warleek_option_fields();
	if ( isset( $opts[ $key ] ) && '' !== $opts[ $key ] ) {
		return $opts[ $key ];
	}
	if ( null !== $default ) {
		return $default;
	}
	return isset( $fields[ $key ] ) ? $fields[ $key ][2] : '';
}

function warleek_sanitize_options( $input ) {
	$out = array();
	foreach ( warleek_option_fields() as $key => $def ) {
		$val = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
		switch ( $def[1] ) {
			case 'url':   $out[ $key ] = $val ? esc_url_raw( $val ) : ''; break;
			case 'int':   $out[ $key ] = $val ? (string) absint( $val ) : ''; break;
			case 'email': $out[ $key ] = $val ? sanitize_email( $val ) : ''; break;
			default:      $out[ $key ] = sanitize_text_field( $val );
		}
	}
	return $out;
}

function warleek_register_options() {
	register_setting( 'warleek', 'warleek_options', array(
		'type'              => 'array',
		'sanitize_callback' => 'warleek_sanitize_options',
		'default'           => array(),
	) );
	add_settings_section( 'warleek_main', 'Community & Spiel', function () {
		echo '<p>Links zu den Chat-Kanälen und Grunddaten. Leere Felder blenden den jeweiligen Button aus bzw. markieren ihn als „bald".</p>';
	}, 'warleek' );
	foreach ( warleek_option_fields() as $key => $def ) {
		add_settings_field( $key, $def[0], 'warleek_render_option_field', 'warleek', 'warleek_main', array( 'key' => $key, 'def' => $def, 'label_for' => 'warleek_' . $key ) );
	}
}
add_action( 'admin_init', 'warleek_register_options' );

function warleek_render_option_field( $args ) {
	$key  = $args['key'];
	$def  = $args['def'];
	$opts = (array) get_option( 'warleek_options', array() );
	$val  = isset( $opts[ $key ] ) ? $opts[ $key ] : '';
	$type = 'int' === $def[1] ? 'number' : ( 'url' === $def[1] ? 'url' : ( 'email' === $def[1] ? 'email' : 'text' ) );
	printf(
		'<input type="%1$s" id="warleek_%2$s" name="warleek_options[%2$s]" value="%3$s" class="regular-text" placeholder="%4$s"><p class="description">%5$s</p>',
		esc_attr( $type ), esc_attr( $key ), esc_attr( $val ), esc_attr( $def[2] ), esc_html( $def[3] )
	);
}

function warleek_options_menu() {
	add_options_page( 'Warleek', 'Warleek', 'manage_options', 'warleek', 'warleek_render_options_page' );
}
add_action( 'admin_menu', 'warleek_options_menu' );

function warleek_render_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$last_run = (int) get_option( 'warleek_sync_last_run', 0 );
	$last_err = get_option( 'warleek_sync_last_error', '' );
	?>
	<div class="wrap">
		<h1>Warleek</h1>
		<form action="options.php" method="post">
			<?php settings_fields( 'warleek' ); do_settings_sections( 'warleek' ); submit_button(); ?>
		</form>
		<hr>
		<h2>Patch-Notes-Sync</h2>
		<p>Letzter Lauf: <?php echo $last_run ? esc_html( wp_date( 'd.m.Y H:i', $last_run ) ) : '—'; ?>
			<?php if ( $last_err ) : ?> · <span style="color:#b32d2e">Fehler: <?php echo esc_html( $last_err ); ?></span><?php endif; ?></p>
		<p>Der Sync läuft stündlich per WP-Cron. Manuell: <code>wp warleek sync-patchnotes</code> oder
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=warleek&warleek_sync=1' ), 'warleek_sync' ) ); ?>">Jetzt synchronisieren</a></p>
	</div>
	<?php
}

/** Manueller Sync-Trigger über die Optionsseite. */
function warleek_handle_manual_sync() {
	if ( ! isset( $_GET['warleek_sync'] ) || ! current_user_can( 'manage_options' ) ) { return; }
	check_admin_referer( 'warleek_sync' );
	if ( function_exists( 'warleek_sync_patchnotes' ) ) {
		$r = warleek_sync_patchnotes( true );
		set_transient( 'warleek_sync_notice', $r, 60 );
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=warleek' ) );
	exit;
}
add_action( 'admin_init', 'warleek_handle_manual_sync' );

function warleek_sync_admin_notice() {
	$r = get_transient( 'warleek_sync_notice' );
	if ( $r ) {
		delete_transient( 'warleek_sync_notice' );
		$cls = empty( $r['error'] ) ? 'notice-success' : 'notice-error';
		printf( '<div class="notice %s is-dismissible"><p>Patch-Notes-Sync: %d neu, %d aktualisiert, %d übersprungen%s</p></div>',
			esc_attr( $cls ), (int) $r['created'], (int) $r['updated'], (int) $r['skipped'], $r['error'] ? ' · Fehler: ' . esc_html( $r['error'] ) : '' );
	}
	$err = get_option( 'warleek_sync_last_error', '' );
	if ( $err && ! $r ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array( 'dashboard', 'settings_page_warleek', 'edit-patchnote' ), true ) ) {
			printf( '<div class="notice notice-warning"><p>Warleek: Der letzte Steam-Sync ist fehlgeschlagen (%s). Bestehende Patch Notes bleiben unverändert.</p></div>', esc_html( $err ) );
		}
	}
}
add_action( 'admin_notices', 'warleek_sync_admin_notice' );
