<?php
/**
 * Warleek — WP-CLI-Befehle.
 *   wp warleek install [--force] [--steps=media,items]  Inhalte, Medien, Menüs, SEO-Plugin, Patch Notes
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
class Warleek_Core_CLI {

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
	 * [--steps=<liste>]
	 * : Nur diese Schritte, mit Komma getrennt. Ohne Angabe laufen alle.
	 *   Verfügbar: plugins, media, pages, guides, retire, items, partners, nav, patchnotes, translate
	 *
	 * ## EXAMPLES
	 *
	 *     wp warleek install
	 *     wp warleek install --force
	 *     wp warleek install --steps=media,items
	 *     wp warleek install --steps=guides --force
	 */
	public function install( $args, $assoc ) {
		$force = ! empty( $assoc['force'] );
		$alle  = warleek_install_steps();
		$nur   = array();
		if ( ! empty( $assoc['steps'] ) ) {
			$nur = array_filter( array_map( 'trim', explode( ',', (string) $assoc['steps'] ) ) );
			$unbekannt = array_diff( $nur, array_keys( $alle ) );
			if ( $unbekannt ) {
				WP_CLI::error( 'Unbekannte Schritte: ' . implode( ', ', $unbekannt ) . ' – verfügbar: ' . implode( ', ', array_keys( $alle ) ) );
			}
		}
		foreach ( $alle as $slug => $step ) {
			if ( $nur && ! in_array( $slug, $nur, true ) ) { continue; }
			if ( 'plugins' === $slug && ! empty( $assoc['skip-plugins'] ) ) { WP_CLI::log( '– ' . $step['label'] . ': übersprungen' ); continue; }
			$res = warleek_run_step( $slug, $force );
			$msg = ( $res['ok'] ? '✓ ' : '✕ ' ) . $step['label'] . ': ' . ( $res['msg'] ?? '' );
			if ( $res['ok'] ) { WP_CLI::log( $msg ); } else { WP_CLI::warning( $msg ); }
		}
		if ( $nur ) { WP_CLI::success( sprintf( '%d Schritt(e) ausgeführt.', count( $nur ) ) ); return; }
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
	 * Übersetzt Patch Notes ins Deutsche (Claude-API).
	 *
	 * ## OPTIONS
	 *
	 * [--id=<id>]
	 * : Nur diese Patch Note.
	 *
	 * [--limit=<n>]
	 * : Höchstens so viele übersetzen (Standard: das eingestellte Budget).
	 *
	 * [--retranslate]
	 * : Auch bereits übersetzte Notes neu übersetzen (kostet erneut).
	 *
	 * [--dry-run]
	 * : Nur zeigen, was übersetzt würde.
	 *
	 * @subcommand translate-patchnotes
	 */
	public function translate_patchnotes( $args, $assoc ) {
		if ( ! warleek_translate_enabled() ) {
			WP_CLI::error( 'Übersetzung ist aus oder es fehlt der API-Schlüssel (Einstellungen → Warleek).' );
		}
		$dry   = ! empty( $assoc['dry-run'] );
		$retr  = ! empty( $assoc['retranslate'] );
		$limit = isset( $assoc['limit'] ) ? (int) $assoc['limit'] : (int) warleek_opt( 'translate_budget', 5 );

		$q = array( 'post_type' => 'patchnote', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' );
		if ( ! empty( $assoc['id'] ) ) { $q['p'] = (int) $assoc['id']; }
		$posts = get_posts( $q );

		$done = 0; $skipped = 0; $failed = 0;
		foreach ( $posts as $post ) {
			if ( $done >= $limit ) { break; }
			$src_html  = get_post_meta( $post->ID, '_warleek_src_html', true );
			$src_title = get_post_meta( $post->ID, '_warleek_src_title', true );
			if ( ! $src_html ) {
				WP_CLI::log( '– ' . $post->post_title . ': kein englisches Original hinterlegt (erst neu synchronisieren)' );
				$skipped++;
				continue;
			}
			if ( ! $retr && get_post_meta( $post->ID, '_warleek_tr_hash', true ) ) {
				$skipped++;
				continue;
			}
			if ( $dry ) { WP_CLI::log( '→ würde übersetzen: ' . $post->post_title ); $done++; continue; }

			$res = warleek_maybe_translate( $src_title ?: $post->post_title, $src_html, $post->ID, $retr );
			if ( is_wp_error( $res ) ) { WP_CLI::warning( $post->post_title . ': ' . $res->get_error_message() ); $failed++; continue; }
			if ( ! $res ) { $skipped++; continue; }
			wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_title' => $res['title'], 'post_content' => $res['html'] ) ) );
			warleek_store_translation( $post->ID, $res );
			WP_CLI::log( '✓ ' . $res['title'] );
			$done++;
		}
		WP_CLI::success( sprintf( '%d übersetzt, %d übersprungen, %d fehlgeschlagen.', $done, $skipped, $failed ) );
	}

	/**
	 * Importiert Guides aus Markdown-Dateien.
	 *
	 * ## OPTIONS
	 *
	 * <pfad>
	 * : Eine .md-Datei, ein Ordner oder ein ZIP.
	 *
	 * [--dry-run]
	 * : Nur zeigen, was passieren würde.
	 *
	 * [--force]
	 * : Auch von Hand bearbeitete Guides überschreiben.
	 *
	 * [--thema=<slug>]
	 * : Thema, wenn der Kopfblock keines nennt (Standard: einsteiger).
	 *
	 * [--status=<status>]
	 * : `draft`, um die Guides als Entwurf anzulegen.
	 *
	 * ## EXAMPLES
	 *
	 *     wp warleek import-guides guides/ --dry-run
	 *     wp warleek import-guides neuer-guide.md --thema=technik
	 *
	 * @subcommand import-guides
	 */
	public function import_guides( $args, $assoc ) {
		$path = $args[0] ?? '';
		if ( ! $path || ( ! file_exists( $path ) && ! is_dir( $path ) ) ) { WP_CLI::error( 'Pfad nicht gefunden: ' . $path ); }

		$res = warleek_import_guides( $path, array(
			'dry_run' => ! empty( $assoc['dry-run'] ),
			'force'   => ! empty( $assoc['force'] ),
			'thema'   => $assoc['thema'] ?? 'einsteiger',
			'status'  => $assoc['status'] ?? '',
		) );

		$rows = array();
		foreach ( $res['rows'] as $r ) {
			if ( ! empty( $r['error'] ) ) {
				$rows[] = array( 'Datei' => $r['file'], 'Slug' => '—', 'Thema' => '—', 'Aktion' => 'FEHLER', 'Wörter' => '—', 'Hinweise' => $r['error'] );
				continue;
			}
			$rows[] = array(
				'Datei' => $r['file'], 'Slug' => $r['slug'], 'Thema' => $r['thema'],
				'Aktion' => $r['action'], 'Wörter' => $r['words'],
				'Hinweise' => $r['warnings'] ? implode( ' · ', $r['warnings'] ) : '',
			);
		}
		if ( $rows ) { WP_CLI\Utils\format_items( 'table', $rows, array( 'Datei', 'Slug', 'Thema', 'Aktion', 'Wörter', 'Hinweise' ) ); }
		warleek_import_cleanup( $res['tmp'] );

		if ( ! empty( $assoc['dry-run'] ) ) { WP_CLI::success( sprintf( 'Trockenlauf: %d Guides bereit, %d Probleme.', $res['ok'], $res['failed'] ) ); return; }
		if ( $res['failed'] ) { WP_CLI::warning( sprintf( '%d Dateien konnten nicht gelesen werden.', $res['failed'] ) ); }
		WP_CLI::success( sprintf( '%d Guides importiert.', $res['ok'] ) );
	}

	/**
	 * Zeigt den Installationsstatus.
	 */
	/**
	 * Auf Reddit posten.
	 *
	 * ## OPTIONEN
	 *
	 * [<slug>]
	 * : Guide oder Datenbank-Eintrag. Ohne Angabe wird der nächste aus der
	 *   Warteschlange genommen.
	 *
	 * [--probe]
	 * : Nichts senden, nur zeigen, was rausginge.
	 *
	 * [--stand]
	 * : Nur den Stand der Warteschlange ausgeben.
	 *
	 * ## BEISPIELE
	 *
	 *     wp warleek reddit --stand
	 *     wp warleek reddit wardogs-munition --probe
	 *     wp warleek reddit
	 */
	public function reddit( $args, $assoc ) {
		$probe = ! empty( $assoc['probe'] );

		if ( ! empty( $assoc['stand'] ) ) {
			$st = warleek_reddit_stand();
			WP_CLI::log( sprintf( 'Zugangsdaten: %s', $st['bereit'] ? 'vollständig' : 'UNVOLLSTÄNDIG' ) );
			WP_CLI::log( sprintf( 'Noch nicht gepostet: %d Beiträge', $st['offen'] ) );
			WP_CLI::log( sprintf( 'Nächster Lauf: %s', $st['naechst'] ? wp_date( 'd.m.Y H:i', $st['naechst'] ) : 'nicht geplant' ) );
			foreach ( array_slice( warleek_reddit_log(), 0, 8 ) as $z ) {
				WP_CLI::log( sprintf( '  %s  %s', wp_date( 'd.m. H:i', $z['zeit'] ), $z['text'] ) );
			}
			return;
		}

		$slug = $args[0] ?? '';
		if ( $slug ) {
			$post = null;
			foreach ( array( 'guide', 'item' ) as $t ) {
				$f = get_posts( array( 'post_type' => $t, 'name' => $slug, 'posts_per_page' => 1, 'post_status' => 'publish' ) );
				if ( $f ) { $post = $f[0]; break; }
			}
			if ( ! $post ) { WP_CLI::error( "Kein veröffentlichter Beitrag mit dem Slug „$slug“." ); }
		} else {
			$post = warleek_reddit_naechster();
			if ( ! $post ) { WP_CLI::success( 'Warteschlange leer – alles gepostet.' ); return; }
		}

		$res = warleek_reddit_poste( $post, $probe );
		if ( is_wp_error( $res ) ) { WP_CLI::error( $res->get_error_message() ); }

		if ( $probe ) {
			WP_CLI::log( str_repeat( '─', 60 ) );
			WP_CLI::log( 'r/' . $res['sub'] . '  —  ' . $res['titel'] );
			WP_CLI::log( str_repeat( '─', 60 ) );
			WP_CLI::log( $res['text'] );
			WP_CLI::log( str_repeat( '─', 60 ) );
			WP_CLI::success( 'Trockenlauf – nichts gesendet.' );
			return;
		}
		WP_CLI::success( 'Gepostet: ' . $res['url'] );
	}

	public function status( $args, $assoc ) {
		$rows = array();
		foreach ( warleek_install_status() as $s ) {
			$rows[] = array( 'Punkt' => $s['label'], 'OK' => $s['ok'] ? 'ja' : 'nein', 'Detail' => $s['detail'] );
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'Punkt', 'OK', 'Detail' ) );
	}
}
if ( ! class_exists( 'Warleek_CLI' ) ) { WP_CLI::add_command( 'warleek', 'Warleek_Core_CLI' ); }
