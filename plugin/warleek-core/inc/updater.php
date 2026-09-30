<?php
/**
 * Warleek — Updates über GitHub-Releases.
 *
 * Theme und Plugin melden über den Kopfzeilen-Eintrag `Update URI` eine eigene
 * Update-Quelle. WordPress ruft dafür die Filter `update_plugins_github.com` bzw.
 * `update_themes_github.com` auf und erledigt den Versionsvergleich selbst –
 * wir liefern nur die Angaben zur neuesten Veröffentlichung.
 *
 * Erwartet je Veröffentlichung eine Datei `warleek-update.json` als Anhang:
 *   { "plugin": {"version","package","requires_php","tested"},
 *     "theme":  {"version","package"},
 *     "changelog": "https://…" }
 *
 * @package warleek-core
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const WARLEEK_UPDATE_REPO     = 'ppschauss/warleek';
const WARLEEK_UPDATE_CACHE    = 'warleek_update_manifest';
const WARLEEK_UPDATE_TTL      = 6 * HOUR_IN_SECONDS;
const WARLEEK_UPDATE_TTL_FAIL = 30 * MINUTE_IN_SECONDS;

function warleek_update_manifest_url() {
	return apply_filters( 'warleek_update_manifest_url',
		'https://github.com/' . WARLEEK_UPDATE_REPO . '/releases/latest/download/warleek-update.json' );
}

/**
 * Angaben zur neuesten Veröffentlichung holen.
 *
 * Ergebnis wird zwischengespeichert; ein Fehlschlag ebenfalls (kürzer), damit eine
 * nicht erreichbare Quelle nicht bei jedem Seitenaufruf erneut angefragt wird.
 *
 * @param bool $force Zwischenspeicher übergehen.
 * @return array|false
 */
function warleek_update_manifest( $force = false ) {
	// „Erneut prüfen" auf Dashboard → Aktualisierungen leert die Zwischenspeicher von
	// WordPress, nicht unseren. Ohne diese Zeile klickt man dort und bekommt trotzdem
	// die alte Antwort, bis die sechs Stunden um sind.
	if ( ! $force && is_admin() && ! empty( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur ein Lesevorgang, WordPress prüft den Link selbst
		$force = true;
	}
	if ( ! $force ) {
		$cached = get_site_transient( WARLEEK_UPDATE_CACHE );
		if ( false !== $cached ) { return is_array( $cached ) ? $cached : false; }
	}

	$res = wp_remote_get( warleek_update_manifest_url(), array(
		'timeout' => 12,
		'headers' => array( 'accept' => 'application/json', 'user-agent' => 'Warleek/' . WARLEEK_CORE_VERSION ),
	) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		set_site_transient( WARLEEK_UPDATE_CACHE, 'fehler', WARLEEK_UPDATE_TTL_FAIL );
		return false;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $data ) || ( empty( $data['plugin'] ) && empty( $data['theme'] ) ) ) {
		set_site_transient( WARLEEK_UPDATE_CACHE, 'fehler', WARLEEK_UPDATE_TTL_FAIL );
		return false;
	}
	set_site_transient( WARLEEK_UPDATE_CACHE, $data, WARLEEK_UPDATE_TTL );
	return $data;
}

/** Die Plugin-Datei relativ zum Plugin-Verzeichnis, z. B. warleek-core/warleek-core.php. */
function warleek_plugin_basename() {
	return plugin_basename( WARLEEK_CORE_FILE );
}

/**
 * Update-Angaben für das Plugin.
 *
 * WordPress ruft diesen Filter für **jedes** Plugin mit einer github.com-Update-URI auf –
 * deshalb zuerst prüfen, ob wirklich unseres gemeint ist.
 */
function warleek_update_plugin( $update, $plugin_data, $plugin_file, $locales ) {
	if ( warleek_plugin_basename() !== $plugin_file ) { return $update; }
	$m = warleek_update_manifest();
	if ( empty( $m['plugin']['version'] ) || empty( $m['plugin']['package'] ) ) { return $update; }

	return array(
		'id'           => 'github.com/' . WARLEEK_UPDATE_REPO,
		'slug'         => 'warleek-core',
		'plugin'       => $plugin_file,
		'version'      => (string) $m['plugin']['version'],
		'url'          => $m['changelog'] ?? ( 'https://github.com/' . WARLEEK_UPDATE_REPO ),
		'package'      => (string) $m['plugin']['package'],
		'tested'       => (string) ( $m['plugin']['tested'] ?? '' ),
		'requires_php' => (string) ( $m['plugin']['requires_php'] ?? '8.1' ),
	);
}
add_filter( 'update_plugins_github.com', 'warleek_update_plugin', 10, 4 );

/** Update-Angaben für das Theme. */
function warleek_update_theme( $update, $theme_data, $theme_stylesheet, $locales ) {
	if ( 'warleek' !== $theme_stylesheet ) { return $update; }
	$m = warleek_update_manifest();
	if ( empty( $m['theme']['version'] ) || empty( $m['theme']['package'] ) ) { return $update; }

	return array(
		'id'           => 'github.com/' . WARLEEK_UPDATE_REPO,
		'theme'        => $theme_stylesheet,
		'version'      => (string) $m['theme']['version'],
		'url'          => $m['changelog'] ?? ( 'https://github.com/' . WARLEEK_UPDATE_REPO ),
		'package'      => (string) $m['theme']['package'],
		'requires'     => (string) ( $m['theme']['requires'] ?? '6.6' ),
		'requires_php' => (string) ( $m['theme']['requires_php'] ?? '8.1' ),
	);
}
add_filter( 'update_themes_github.com', 'warleek_update_theme', 10, 4 );

/** Details-Dialog im Plugin-Bereich. */
function warleek_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'warleek-core' !== $args->slug ) { return $result; }
	$m = warleek_update_manifest();
	if ( ! $m ) { return $result; }

	$notes = (string) ( $m['notes'] ?? '' );
	return (object) array(
		'name'          => 'Warleek Core',
		'slug'          => 'warleek-core',
		'version'       => (string) ( $m['plugin']['version'] ?? WARLEEK_CORE_VERSION ),
		'author'        => '<a href="https://warleek.de">Warleek</a>',
		'homepage'      => 'https://warleek.de',
		'requires'      => (string) ( $m['plugin']['requires'] ?? '6.6' ),
		'requires_php'  => (string) ( $m['plugin']['requires_php'] ?? '8.1' ),
		'tested'        => (string) ( $m['plugin']['tested'] ?? '' ),
		'download_link' => (string) ( $m['plugin']['package'] ?? '' ),
		'sections'      => array(
			'description' => 'Funktionskern und Installer der Warleek-Guide-Seite.',
			'changelog'   => $notes
				? ( function_exists( 'warleek_md_to_html' ) ? warleek_md_to_html( $notes, false ) : wpautop( esc_html( $notes ) ) )
				: '<p>Änderungen siehe <a href="' . esc_url( $m['changelog'] ?? '' ) . '">GitHub</a>.</p>',
		),
	);
}
add_filter( 'plugins_api', 'warleek_plugins_api', 10, 3 );

/**
 * Sicherheitsnetz für den Ordnernamen im Paket.
 *
 * Wir hängen fertig gebaute ZIPs an die Veröffentlichung, deren oberster Ordner
 * bereits richtig heißt. Sollte einmal ein Quell-Archiv verwendet werden, korrigiert
 * dieser Filter den Namen – sonst installierte WordPress ein zweites Plugin.
 */
function warleek_update_source_selection( $source, $remote_source, $upgrader, $hook_extra = array() ) {
	$want = '';
	if ( ! empty( $hook_extra['plugin'] ) && warleek_plugin_basename() === $hook_extra['plugin'] ) { $want = 'warleek-core'; }
	if ( ! empty( $hook_extra['theme'] ) && 'warleek' === $hook_extra['theme'] ) { $want = 'warleek'; }
	if ( ! $want ) { return $source; }

	$current = basename( untrailingslashit( $source ) );
	if ( $current === $want ) { return $source; }
	$target = trailingslashit( $remote_source ) . $want;
	if ( @rename( $source, $target ) ) { return trailingslashit( $target ); }
	return $source;
}
add_filter( 'upgrader_source_selection', 'warleek_update_source_selection', 10, 4 );

/** Nach einem Update: Zwischenspeicher leeren und auf neue Inhalte hinweisen. */
function warleek_after_upgrade( $upgrader, $extra ) {
	delete_site_transient( WARLEEK_UPDATE_CACHE );
	delete_site_transient( 'update_plugins' );
	delete_site_transient( 'update_themes' );
}
add_action( 'upgrader_process_complete', 'warleek_after_upgrade', 10, 2 );

/** Hinweis, wenn die installierten Inhalte älter sind als das Plugin. */
function warleek_content_outdated_notice() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$installed = (string) get_option( 'warleek_installed_version', '' );
	if ( ! $installed || version_compare( $installed, WARLEEK_CORE_VERSION, '>=' ) ) { return; }
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'toplevel_page_warleek', 'plugins' ), true ) ) { return; }
	printf(
		'<div class="notice notice-info"><p><strong>Warleek Core</strong> wurde auf %s aktualisiert, die Inhalte stammen noch von %s. <a href="%s">Installation erneut ausführen</a>, wenn du die mitgelieferten Inhalte übernehmen willst.</p></div>',
		esc_html( WARLEEK_CORE_VERSION ), esc_html( $installed ), esc_url( admin_url( 'admin.php?page=warleek' ) )
	);
}
add_action( 'admin_notices', 'warleek_content_outdated_notice' );

/** „Jetzt nach Updates suchen" auf der Warleek-Seite. */
function warleek_handle_update_check() {
	if ( ! isset( $_GET['warleek_update_check'] ) || ! current_user_can( 'manage_options' ) ) { return; }
	check_admin_referer( 'warleek_update_check' );
	delete_site_transient( WARLEEK_UPDATE_CACHE );
	delete_site_transient( 'update_plugins' );
	delete_site_transient( 'update_themes' );
	$m = warleek_update_manifest( true );
	set_transient( 'warleek_update_notice', $m ? sprintf( 'Neueste Veröffentlichung: Plugin %s, Theme %s.', $m['plugin']['version'] ?? '?', $m['theme']['version'] ?? '?' ) : 'Update-Quelle nicht erreichbar.', 60 );
	wp_safe_redirect( admin_url( 'admin.php?page=warleek' ) );
	exit;
}
add_action( 'admin_init', 'warleek_handle_update_check' );

function warleek_update_admin_notice() {
	$msg = get_transient( 'warleek_update_notice' );
	if ( ! $msg ) { return; }
	delete_transient( 'warleek_update_notice' );
	printf( '<div class="notice notice-info is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
}
add_action( 'admin_notices', 'warleek_update_admin_notice' );

/**
 * Versionszeile mit der Schaltfläche „Jetzt nach Updates suchen".
 *
 * Ohne diese Schaltfläche bleibt einem nur Warten: WordPress fragt von sich aus
 * etwa zweimal am Tag nach, und unser Manifest liegt sechs Stunden im
 * Zwischenspeicher. Der Knopf leert beides und holt die Antwort sofort.
 */
function warleek_render_update_box() {
	$m       = get_site_transient( WARLEEK_UPDATE_CACHE );
	$neuste  = is_array( $m ) ? ( $m['plugin']['version'] ?? '' ) : '';
	$aktuell = ! $neuste || version_compare( $neuste, WARLEEK_CORE_VERSION, '<=' );
	$link    = wp_nonce_url( admin_url( 'admin.php?page=warleek&warleek_update_check=1' ), 'warleek_update_check' );
	?>
	<div class="notice notice-<?php echo $aktuell ? 'info' : 'warning'; ?>" style="margin:0 0 18px">
		<p style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
			<span>
				Installiert: <strong>Plugin <?php echo esc_html( WARLEEK_CORE_VERSION ); ?></strong>,
				<strong>Theme <?php echo esc_html( wp_get_theme( 'warleek' )->get( 'Version' ) ?: '–' ); ?></strong>
				<?php if ( $neuste ) : ?>
					· zuletzt veröffentlicht: <strong><?php echo esc_html( $neuste ); ?></strong>
					<?php echo $aktuell ? '' : ' – <a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">jetzt aktualisieren</a>'; ?>
				<?php endif; ?>
			</span>
			<a class="button" href="<?php echo esc_url( $link ); ?>">Jetzt nach Updates suchen</a>
		</p>
	</div>
	<?php
}
