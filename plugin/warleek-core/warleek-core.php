<?php
/**
 * Plugin Name:       Warleek Core
 * Plugin URI:        https://warleek.de
 * Description:       Funktionskern und Installer der Warleek-Website: Guides & Patch Notes (automatisch von Steam), Chat-Optionen, eigene Blöcke, SEO-Metas – plus ein Klick-Installer für alle Inhalte, Bilder und das benötigte SEO-Plugin.
 * Version:           2.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Warleek
 * Author URI:        https://warleek.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       warleek
 *
 * @package warleek-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WARLEEK_CORE_VERSION', '2.0.0' );
define( 'WARLEEK_CORE_FILE', __FILE__ );
define( 'WARLEEK_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WARLEEK_CORE_URI', plugin_dir_url( __FILE__ ) );
define( 'WARLEEK_CONTENT_DIR', WARLEEK_CORE_DIR . 'content/' );

/**
 * Älteres Warleek-Theme erkennen.
 *
 * Bis Version 0.1.x brachte das Theme die Funktionsmodule selbst mit (theme/warleek/inc/).
 * Lädt das Plugin dieselben Funktionen, bricht PHP mit „Cannot redeclare“ ab. Deshalb prüfen
 * wir auf der Festplatte, ob das aktive Theme noch eigene Module hat, und überlassen ihm dann
 * den Funktionsteil – das Plugin steuert nur den Installer bei und bittet um ein Theme-Update.
 *
 * @return bool
 */
function warleek_core_theme_has_legacy_modules() {
	foreach ( array( get_template_directory(), get_stylesheet_directory() ) as $dir ) {
		if ( $dir && file_exists( $dir . '/inc/options.php' ) ) { return true; }
	}
	return false;
}

$warleek_legacy = warleek_core_theme_has_legacy_modules();
define( 'WARLEEK_CORE_LEGACY_THEME', $warleek_legacy );

/* Nur wenn kein altes Theme die Konstante selbst setzt – sonst gäbe es eine PHP-Warnung. */
if ( ! $warleek_legacy && ! defined( 'WARLEEK_VERSION' ) ) { define( 'WARLEEK_VERSION', WARLEEK_CORE_VERSION ); }

$warleek_modules = $warleek_legacy
	? array( 'layout', 'markdown', 'import-guides', 'installer' )                                                                          // Funktionen kommen aus dem alten Theme
	: array( 'options', 'bbcode', 'cpt-guide', 'cpt-patchnote', 'cpt-partner', 'steam-sync', 'translate', 'builders', 'blocks', 'seo', 'privacy', 'layout', 'markdown', 'import-guides', 'installer', 'cli' );

foreach ( $warleek_modules as $warleek_module ) {
	$warleek_path = WARLEEK_CORE_DIR . 'inc/' . $warleek_module . '.php';
	if ( file_exists( $warleek_path ) ) { require_once $warleek_path; }
}
unset( $warleek_module, $warleek_path, $warleek_modules, $warleek_legacy );

/** Hinweis, solange ein altes Theme die Funktionsmodule mitbringt. */
function warleek_core_legacy_notice() {
	if ( ! WARLEEK_CORE_LEGACY_THEME || ! current_user_can( 'manage_options' ) ) { return; }
	echo '<div class="notice notice-warning"><p><strong>Warleek Core:</strong> Das aktive Theme bringt noch eigene Funktionsmodule mit (Version 0.1.x). '
		. 'Das Plugin hält sich deshalb zurück und stellt nur den Installer bereit. '
		. 'Bitte das Theme aktualisieren (<em>warleek-theme.zip</em> ab Version 1.0.0 unter Design → Themes → Theme hochladen), '
		. 'danach übernimmt das Plugin Guides, Patch Notes, Einstellungen und SEO.</p></div>';
}
add_action( 'admin_notices', 'warleek_core_legacy_notice' );

/* ----------------------------------------------------- Aktivierung */
/**
 * Beim Aktivieren: CPTs registrieren, Permalinks neu schreiben, Sync-Cron anlegen.
 * Die Inhalte werden NICHT automatisch installiert – das macht der Installer auf Klick,
 * damit niemand ungefragt 30 Beiträge und 30 Medien in seine Installation bekommt.
 */
function warleek_core_activate() {
	if ( function_exists( 'warleek_register_guide_cpt' ) ) { warleek_register_guide_cpt(); }
	if ( function_exists( 'warleek_register_patchnote_cpt' ) ) { warleek_register_patchnote_cpt(); }
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'warleek_sync_patchnotes' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'warleek_sync_patchnotes' );
	}
	set_transient( 'warleek_activation_redirect', 1, 60 );
}
register_activation_hook( __FILE__, 'warleek_core_activate' );

function warleek_core_deactivate() {
	$ts = wp_next_scheduled( 'warleek_sync_patchnotes' );
	if ( $ts ) { wp_unschedule_event( $ts, 'warleek_sync_patchnotes' ); }
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'warleek_core_deactivate' );

/** Nach dem Aktivieren direkt auf die Installationsseite. */
function warleek_core_activation_redirect() {
	if ( ! get_transient( 'warleek_activation_redirect' ) ) { return; }
	delete_transient( 'warleek_activation_redirect' );
	if ( isset( $_GET['activate-multi'] ) || ! current_user_can( 'manage_options' ) ) { return; }
	wp_safe_redirect( admin_url( 'admin.php?page=warleek' ) );
	exit;
}
add_action( 'admin_init', 'warleek_core_activation_redirect' );

/** Hinweis, wenn das Warleek-Theme nicht aktiv ist (Blöcke/Patterns sehen dann anders aus). */
function warleek_core_theme_notice() {
	if ( 'warleek' === get_template() || ! current_user_can( 'manage_options' ) ) { return; }
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'toplevel_page_warleek' ), true ) ) { return; }
	echo '<div class="notice notice-warning"><p><strong>Warleek Core:</strong> Das Theme <em>Warleek</em> ist nicht aktiv. Das Plugin funktioniert trotzdem, die Seiten sehen aber erst mit dem Theme richtig aus. <a href="' . esc_url( admin_url( 'themes.php' ) ) . '">Themes öffnen</a></p></div>';
}
add_action( 'admin_notices', 'warleek_core_theme_notice' );
