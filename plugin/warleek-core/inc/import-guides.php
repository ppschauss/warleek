<?php
/**
 * Warleek — Guides aus Markdown importieren.
 *
 * Eine .md-Datei (oder ein ZIP mit mehreren plus Bildern) wird zu fertigen Guides:
 * Kopfblock → Metadaten, Markdown → HTML → Core-Blöcke über den bestehenden Seed-Pfad.
 * Ein erneuter Import derselben Datei aktualisiert, statt zu duplizieren.
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const WARLEEK_IMPORT_MAX_FILES = 200;
const WARLEEK_IMPORT_MAX_BYTES = 20971520; // 20 MB

/** Erlaubte Dateiendungen in einem Import-ZIP. */
function warleek_import_allowed_ext() {
	return array( 'md', 'markdown', 'jpg', 'jpeg', 'png', 'webp', 'gif' );
}

/**
 * Stichwörter, die auf ein Thema schließen lassen.
 *
 * Guides werden oft mit Blog-Tags geschrieben („hotas", „joystick"), nicht mit
 * unseren Themen-Slugs. Diese Tabelle übersetzt die gängigen Begriffe, damit ein
 * Import nicht bei jedem Guide von Hand nachjustiert werden muss.
 */
function warleek_import_thema_synonyms() {
	return array(
		'technik'    => array( 'hotas', 'joystick', 'thrustmaster', 'controller', 'gamepad', 'steuerung', 'keybinds', 'tastenbelegung', 'binds', 'vjoy', 'hardware', 'einstellungen', 'settings', 'performance', 'fps', 'grafik', 'audio', 'maus', 'tastatur' ),
		'fob'        => array( 'fob', 'basis', 'bauen', 'build', 'bunker', 'hesco', 'fortification', 'verteidigung' ),
		'logistik'   => array( 'logistik', 'logistics', 'nachschub', 'palette', 'pallet', 'supply', 'ural', 'truck', 'lkw', 'konvoi' ),
		'gameplay'   => array( 'gameplay', 'zone', 'economy', 'cash', 'geld', 'taktik', 'squad', 'kommunikation', 'funk', 'voice', 'teamplay' ),
		'equipment'  => array( 'equipment', 'loadout', 'waffen', 'weapons', 'fahrzeug', 'fahrzeuge', 'vehicles', 'helikopter', 'heli', 'ausruestung', 'ausrüstung', 'kit' ),
		'einsteiger' => array( 'einsteiger', 'anfaenger', 'anfänger', 'beginner', 'basics', 'grundlagen', 'tutorial', 'erste' ),
	);
}

/**
 * Kopfblock einer Markdown-Datei auf unser Guide-Format abbilden.
 *
 * Verträgt beide Schreibweisen: unsere Schlüssel (`thema`, `excerpt`, `order`)
 * und die verbreiteten Blog-Schlüssel (`description`, `tags`, `date`).
 *
 * @param array  $meta     Kopfblock.
 * @param string $filename Dateiname (liefert den Slug, wenn keiner gesetzt ist).
 * @param string $fallback_thema Thema, wenn keines erkannt wird.
 * @return array{guide:array,warnings:array}
 */
function warleek_import_map_meta( array $meta, $filename, $fallback_thema = 'einsteiger' ) {
	$warn  = array();
	$known = array_keys( (array) ( warleek_content_json( 'site' )['themen'] ?? array() ) );

	$title = trim( (string) ( $meta['title'] ?? '' ) );
	if ( '' === $title ) { $warn[] = 'Kein Titel im Kopfblock.'; }

	$slug = sanitize_title( (string) ( $meta['slug'] ?? '' ) );
	if ( ! $slug ) {
		// Dateiname ohne Endung; führende Zufallspräfixe wie „a1b2c3d4-" entfernen.
		$base = preg_replace( '/\.(md|markdown)$/i', '', basename( $filename ) );
		$base = preg_replace( '/^[0-9a-f]{6,}-/i', '', $base );
		$slug = sanitize_title( $base );
	}

	$thema = sanitize_title( (string) ( $meta['thema'] ?? '' ) );
	if ( ! $thema || ! in_array( $thema, $known, true ) ) {
		$tags = array_map( 'sanitize_title', (array) ( $meta['tags'] ?? array() ) );
		if ( $thema ) { array_unshift( $tags, $thema ); }

		// 1. Ein Tag trifft ein Thema direkt.
		$found = '';
		foreach ( $tags as $tag ) {
			if ( in_array( $tag, $known, true ) ) { $found = $tag; break; }
		}
		// 2. Sonst über die Synonym-Tabelle; das Thema mit den meisten Treffern gewinnt.
		if ( ! $found ) {
			$scores = array();
			foreach ( warleek_import_thema_synonyms() as $cand => $words ) {
				if ( ! in_array( $cand, $known, true ) ) { continue; }
				$hit = 0;
				foreach ( $tags as $tag ) {
					foreach ( $words as $w ) {
						if ( $tag === $w || str_contains( $tag, $w ) ) { $hit++; break; }
					}
				}
				if ( $hit ) { $scores[ $cand ] = $hit; }
			}
			if ( $scores ) { arsort( $scores ); $found = (string) array_key_first( $scores ); }
		}
		if ( ! $found && $thema ) { $warn[] = sprintf( 'Thema „%s" ist unbekannt – „%s" verwendet.', $thema, $fallback_thema ); }
		if ( $found && $thema && $found !== $thema ) { $warn[] = sprintf( 'Thema aus den Stichwörtern abgeleitet: %s.', $found ); }
		$thema = $found ?: $fallback_thema;
	}

	$excerpt = trim( (string) ( $meta['excerpt'] ?? $meta['description'] ?? '' ) );
	if ( '' === $excerpt ) { $warn[] = 'Kein Auszug – die Karte bleibt textlos.'; }

	$guide = array(
		'slug'    => $slug,
		'title'   => $title,
		'thema'   => $thema,
		'excerpt' => $excerpt,
		'order'   => (int) ( $meta['order'] ?? 0 ),
		'image'   => (string) ( $meta['image'] ?? '' ),
		'seo'     => array(
			'title'       => (string) ( $meta['seo_title'] ?? '' ),
			'description' => (string) ( $meta['seo_description'] ?? $excerpt ),
		),
	);
	if ( '' === $guide['seo']['title'] && $title ) {
		$guide['seo']['title'] = mb_substr( $title, 0, 55 ) . ' | Warleek';
	}
	return array( 'guide' => $guide, 'warnings' => $warn );
}

/**
 * Eine Markdown-Datei einlesen und in einen Guide-Datensatz verwandeln.
 *
 * @param string $path           Dateipfad.
 * @param string $fallback_thema
 * @return array{guide:array,warnings:array}|WP_Error
 */
function warleek_import_read_file( $path, $fallback_thema = 'einsteiger' ) {
	if ( ! is_readable( $path ) ) { return new WP_Error( 'warleek_import_read', 'Datei nicht lesbar: ' . basename( $path ) ); }
	$raw = file_get_contents( $path );
	if ( false === $raw || '' === trim( $raw ) ) { return new WP_Error( 'warleek_import_empty', 'Datei ist leer: ' . basename( $path ) ); }

	$fm      = warleek_md_front_matter( $raw );
	$mapped  = warleek_import_map_meta( $fm['meta'], $path, $fallback_thema );
	$guide   = $mapped['guide'];
	$guide['html'] = warleek_md_to_html( $fm['body'] );

	if ( '' === $guide['title'] ) {
		// Kein Titel im Kopfblock? Dann die erste H1 aus dem Text nehmen.
		if ( preg_match( '/^#\s+(.+)$/m', $fm['body'], $m ) ) { $guide['title'] = trim( $m[1] ); }
	}
	if ( '' === $guide['title'] ) { return new WP_Error( 'warleek_import_title', 'Kein Titel gefunden: ' . basename( $path ) ); }
	if ( '' === trim( $guide['html'] ) ) { return new WP_Error( 'warleek_import_body', 'Kein Inhalt gefunden: ' . basename( $path ) ); }

	$mapped['guide'] = $guide;
	return $mapped;
}

/** Bild aus dem Import-Ordner in die Mediathek holen (Dedupe über den Asset-Schlüssel). */
function warleek_import_media( $file, $key, $alt = '' ) {
	if ( ! file_exists( $file ) ) { return 0; }
	$q = new WP_Query( array(
		'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids',
		'meta_key' => '_warleek_asset_key', 'meta_value' => $key, 'no_found_rows' => true,
	) );
	if ( $q->posts ) { return (int) $q->posts[0]; }

	require_once ABSPATH . 'wp-admin/includes/image.php';
	$bits = wp_upload_bits( basename( $file ), null, file_get_contents( $file ) );
	if ( ! empty( $bits['error'] ) ) { return 0; }
	$ft = wp_check_filetype( $bits['file'] );
	$id = wp_insert_attachment( array(
		'post_mime_type' => $ft['type'], 'post_title' => sanitize_text_field( $alt ?: $key ), 'post_status' => 'inherit',
	), $bits['file'] );
	if ( is_wp_error( $id ) || ! $id ) { return 0; }
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $bits['file'] ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $id, '_warleek_asset_key', $key );
	return (int) $id;
}

/**
 * Bilder aus dem Fließtext eines Guides in die Mediathek holen.
 *
 * Im Markdown stehen sie als `![Alt](schluessel)`. Gesucht wird eine passende
 * Datei neben der Markdown-Datei bzw. im ZIP – auch in einem Unterordner `images/`.
 * Ist der Schlüssel bereits in der Mediathek bekannt, reicht das ebenfalls.
 *
 * @param string $html HTML des Guides.
 * @param string $dir  Verzeichnis der Quelldatei.
 * @param array  $map  Asset-Schlüssel => Anhang-ID (wird ergänzt).
 * @param bool   $dry  Trockenlauf: nichts hochladen.
 * @return array Warnungen.
 */
function warleek_import_inline_images( $html, $dir, array &$map, $dry = false ) {
	$warn = array();
	if ( ! preg_match_all( '#<img src="([^"/:.]+)" alt="([^"]*)">#', $html, $hits, PREG_SET_ORDER ) ) {
		return $warn;
	}
	foreach ( $hits as $hit ) {
		$key = $hit[1];
		if ( isset( $map[ $key ] ) ) { continue; }

		$datei = '';
		foreach ( array( '', 'images/', 'img/' ) as $unter ) {
			foreach ( array( 'webp', 'png', 'jpg', 'jpeg' ) as $endung ) {
				$kandidat = $dir . '/' . $unter . $key . '.' . $endung;
				if ( file_exists( $kandidat ) ) { $datei = $kandidat; break 2; }
			}
		}
		if ( ! $datei ) {
			$warn[] = sprintf( 'Bild „%s" fehlt – die Stelle bleibt im Text leer.', $key );
			continue;
		}
		if ( $dry ) { continue; }
		$id = warleek_import_media( $datei, $key, $hit[2] );
		if ( $id ) { $map[ $key ] = $id; } else { $warn[] = sprintf( 'Bild „%s" ließ sich nicht importieren.', $key ); }
	}
	return $warn;
}

/**
 * Importiert Guides aus einem Pfad: eine .md-Datei, ein Ordner oder ein ZIP.
 *
 * @param string $path
 * @param array  $opts dry_run, force, thema, status
 * @return array{rows:array,ok:int,failed:int,tmp:string}
 */
function warleek_import_guides( $path, array $opts = array() ) {
	$dry    = ! empty( $opts['dry_run'] );
	$force  = ! empty( $opts['force'] );
	$thema  = $opts['thema'] ?? 'einsteiger';
	$status = 'draft' === ( $opts['status'] ?? '' ) ? 'draft' : 'publish';

	$dir   = '';
	$files = array();
	$tmp   = '';

	if ( is_dir( $path ) ) {
		$dir   = untrailingslashit( $path );
		$files = glob( $dir . '/*.{md,markdown}', GLOB_BRACE ) ?: array();
	} elseif ( preg_match( '/\.zip$/i', $path ) ) {
		$tmp = warleek_import_unzip( $path );
		if ( is_wp_error( $tmp ) ) { return array( 'rows' => array( array( 'file' => basename( $path ), 'error' => $tmp->get_error_message() ) ), 'ok' => 0, 'failed' => 1, 'tmp' => '' ); }
		$dir = $tmp;
		$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			if ( preg_match( '/\.(md|markdown)$/i', $f->getFilename() ) ) { $files[] = $f->getPathname(); }
		}
	} else {
		$dir     = dirname( $path );
		$files[] = $path;
	}

	sort( $files );
	$rows = array();
	$ok   = 0;
	$fail = 0;
	$site = warleek_content_json( 'site' );
	$map  = (array) get_option( 'warleek_media_map', array() );

	foreach ( $files as $file ) {
		$read = warleek_import_read_file( $file, $thema );
		if ( is_wp_error( $read ) ) {
			$rows[] = array( 'file' => basename( $file ), 'error' => $read->get_error_message() );
			$fail++;
			continue;
		}
		$guide = $read['guide'];
		$warn  = $read['warnings'];

		$existing = get_posts( array( 'post_type' => 'guide', 'name' => $guide['slug'], 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
		$action   = $existing ? 'aktualisiert' : 'neu';

		// Bilder aus dem Fließtext zuerst – sie müssen vor dem Schreiben in der Mediathek sein.
		$warn = array_merge( $warn, warleek_import_inline_images( $guide['html'], $dir, $map, $dry ) );

		// Bild aus dem Import mitnehmen, sonst das Themenbild des Hubs.
		$img_key = '';
		if ( $guide['image'] ) {
			$img_path = $dir . '/' . ltrim( $guide['image'], '/' );
			if ( file_exists( $img_path ) ) {
				$img_key = 'guide-' . $guide['slug'];
				if ( ! $dry ) {
					$id = warleek_import_media( $img_path, $img_key, $guide['title'] );
					if ( $id ) { $map[ $img_key ] = $id; }
				}
			} elseif ( isset( $map[ $guide['image'] ] ) ) {
				$img_key = $guide['image']; // bereits bekannter Asset-Schlüssel
			} else {
				$warn[] = sprintf( 'Bild „%s" nicht gefunden – Themenbild wird verwendet.', $guide['image'] );
			}
		}
		if ( ! $img_key ) {
			$img_key = $site['themen'][ $guide['thema'] ]['image'] ?? '';
		}
		$guide['image'] = $img_key;

		$rows[] = array(
			'file' => basename( $file ), 'slug' => $guide['slug'], 'title' => $guide['title'],
			'thema' => $guide['thema'], 'action' => $action, 'image' => $img_key,
			'words' => str_word_count( wp_strip_all_tags( $guide['html'] ) ), 'warnings' => $warn,
		);

		if ( ! $dry ) {
			warleek_seed_guides( array( $guide ), $map, $site, $force );
			if ( 'draft' === $status ) {
				$p = get_posts( array( 'post_type' => 'guide', 'name' => $guide['slug'], 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
				if ( $p ) { wp_update_post( array( 'ID' => (int) $p[0], 'post_status' => 'draft' ) ); }
			}
		}
		$ok++;
	}

	if ( ! $dry ) { update_option( 'warleek_media_map', $map, false ); }
	return array( 'rows' => $rows, 'ok' => $ok, 'failed' => $fail, 'tmp' => $tmp );
}

/**
 * ZIP sicher entpacken.
 *
 * Abgewiesen werden Pfade mit „..", absolute Pfade und alles außerhalb der
 * erlaubten Endungen – ein präpariertes Archiv soll nichts überschreiben können.
 *
 * @return string|WP_Error Verzeichnis.
 */
function warleek_import_unzip( $zip_path ) {
	if ( ! class_exists( 'ZipArchive' ) ) { return new WP_Error( 'warleek_zip', 'ZipArchive steht auf diesem Server nicht zur Verfügung.' ); }
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) { return new WP_Error( 'warleek_zip_open', 'ZIP lässt sich nicht öffnen.' ); }
	if ( $zip->numFiles > WARLEEK_IMPORT_MAX_FILES ) { $zip->close(); return new WP_Error( 'warleek_zip_many', 'ZIP enthält zu viele Dateien.' ); }

	$dir = trailingslashit( get_temp_dir() ) . 'warleek-import-' . wp_generate_password( 8, false );
	if ( ! wp_mkdir_p( $dir ) ) { $zip->close(); return new WP_Error( 'warleek_zip_tmp', 'Temporäres Verzeichnis nicht anlegbar.' ); }

	$bytes   = 0;
	$allowed = warleek_import_allowed_ext();
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$stat = $zip->statIndex( $i );
		$name = $stat['name'];
		if ( str_ends_with( $name, '/' ) ) { continue; }
		if ( str_contains( $name, '..' ) || str_starts_with( $name, '/' ) || str_contains( $name, "\0" ) ) { continue; }
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed, true ) ) { continue; }
		$bytes += (int) $stat['size'];
		if ( $bytes > WARLEEK_IMPORT_MAX_BYTES ) { $zip->close(); return new WP_Error( 'warleek_zip_big', 'ZIP-Inhalt ist zu groß.' ); }

		$target = $dir . '/' . basename( $name ); // flach entpacken – Unterordner brauchen wir nicht
		$stream = $zip->getStream( $name );
		if ( ! $stream ) { continue; }
		file_put_contents( $target, stream_get_contents( $stream ) );
		fclose( $stream );
	}
	$zip->close();
	return $dir;
}

/** Temporäres Import-Verzeichnis aufräumen. */
function warleek_import_cleanup( $dir ) {
	if ( ! $dir || ! is_dir( $dir ) || ! str_contains( $dir, 'warleek-import-' ) ) { return; }
	foreach ( glob( $dir . '/*' ) ?: array() as $f ) { @unlink( $f ); }
	@rmdir( $dir );
}
