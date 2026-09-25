<?php
/**
 * Deinstallation: entfernt nur die Einstellungen und Installationsmarker des Plugins.
 * Seiten, Guides, Patch Notes und Medien bleiben erhalten – sie gehören der Redaktion,
 * nicht dem Plugin.
 *
 * @package warleek-core
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

foreach ( array( 'warleek_options', 'warleek_install_log', 'warleek_installed_version', 'warleek_media_map', 'warleek_page_ids', 'warleek_nav_main_id', 'warleek_nav_footer_id', 'warleek_og_default_id', 'warleek_sync_last_run', 'warleek_sync_last_error', 'warleek_steam_img_map' ) as $opt ) {
	delete_option( $opt );
}
delete_transient( 'warleek_sync_notice' );
$ts = wp_next_scheduled( 'warleek_sync_patchnotes' );
if ( $ts ) { wp_unschedule_event( $ts, 'warleek_sync_patchnotes' ); }
