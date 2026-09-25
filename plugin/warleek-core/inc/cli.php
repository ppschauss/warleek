<?php
/**
 * Warleek — WP-CLI-Befehle.
 *   wp warleek install [--force]        Inhalte, Medien, Menüs, SEO-Plugin, Patch Notes
 *   wp warleek sync-patchnotes [--force]
 *   wp warleek status
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { return; }

/**
 * Warleek-Verwaltung.
 */
class Warleek_CLI {

	/**
	 * Installiert Inhalte, Medien, Menüs und das SEO-Plugin.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Bestehende Seitentexte überschreiben.
	 *
	 * [--skip-plugins]
	 * : Rank Math nicht installieren.
	 *
	 * ## EXAMPLES
	 *
	 *     wp warleek install
	 *     wp warleek install --force
	 */
	public function install( $args, $assoc ) {
		$force = ! empty( $assoc['force'] );
		foreach ( warleek_install_steps() as $slug => $step ) {
			if ( 'plugins' === $slug && ! empty( $assoc['skip-plugins'] ) ) { WP_CLI::log( '– ' . $step['label'] . ': übersprungen' ); continue; }
			$res = warleek_run_step( $slug, $force );
			$msg = ( $res['ok'] ? '✓ ' : '✕ ' ) . $step['label'] . ': ' . ( $res['msg'] ?? '' );
			if ( $res['ok'] ) { WP_CLI::log( $msg ); } else { WP_CLI::warning( $msg ); }
		}
		WP_CLI::success( 'Installation abgeschlossen. Chat-Links setzen: wp option patch update warleek_options discord_url \'https://discord.gg/…\'' );
	}

	/**
	 * Holt die Patch Notes von Steam.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Auch unveränderte Einträge neu schreiben.
	 *
	 * @subcommand sync-patchnotes
	 */
	public function sync_patchnotes( $args, $assoc ) {
		$r = warleek_sync_patchnotes( ! empty( $assoc['force'] ) );
		WP_CLI::log( sprintf( 'created: %d updated: %d skipped: %d', $r['created'], $r['updated'], $r['skipped'] ) );
		if ( $r['error'] ) { WP_CLI::error( $r['error'] ); }
		WP_CLI::success( 'Patch Notes synchronisiert.' );
	}

	/**
	 * Zeigt den Installationsstatus.
	 */
	public function status( $args, $assoc ) {
		$rows = array();
		foreach ( warleek_install_status() as $s ) {
			$rows[] = array( 'Punkt' => $s['label'], 'OK' => $s['ok'] ? 'ja' : 'nein', 'Detail' => $s['detail'] );
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'Punkt', 'OK', 'Detail' ) );
	}
}
WP_CLI::add_command( 'warleek', 'Warleek_CLI' );
