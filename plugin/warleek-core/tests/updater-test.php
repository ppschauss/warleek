<?php
/**
 * Aufruf: wp eval-file wp-content/plugins/warleek-core/tests/updater-test.php
 * Kein echter Netzaufruf – die HTTP-Schicht wird abgefangen.
 */
$fail = 0;
function u_chk( $label, $cond, $extra = '' ) {
	global $fail;
	if ( ! $cond ) { $fail++; echo "FAIL $label $extra\n"; }
}

$GLOBALS['u_manifest'] = array(
	'plugin'    => array( 'version' => '9.9.9', 'package' => 'https://github.com/x/y/releases/download/v9.9.9/warleek-core-9.9.9.zip', 'requires_php' => '8.1', 'tested' => '7.1' ),
	'theme'     => array( 'version' => '9.9.9', 'package' => 'https://github.com/x/y/releases/download/v9.9.9/warleek-theme-9.9.9.zip' ),
	'changelog' => 'https://github.com/x/y/releases/tag/v9.9.9',
	'notes'     => "## 9.9.9\n\n- Etwas Neues\n",
);
$GLOBALS['u_fail_http'] = false;

add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'warleek-update.json' ) ) { return $pre; }
	if ( $GLOBALS['u_fail_http'] ) { return new WP_Error( 'http', 'kein Netz' ); }
	return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $GLOBALS['u_manifest'] ) );
}, 10, 3 );

delete_site_transient( WARLEEK_UPDATE_CACHE );

/* 1) Manifest wird gelesen */
$m = warleek_update_manifest( true );
u_chk( 'manifest', is_array( $m ) && '9.9.9' === $m['plugin']['version'] );

/* 2) Plugin-Update nur für unsere Datei */
$own = warleek_plugin_basename();
$upd = warleek_update_plugin( false, array(), $own, array() );
u_chk( 'plugin-update', is_array( $upd ) && '9.9.9' === $upd['version'] && $upd['plugin'] === $own );
u_chk( 'plugin-paket', is_array( $upd ) && false !== strpos( $upd['package'], 'warleek-core-9.9.9.zip' ) );

/* 3) Fremde Plugins bleiben unberührt – der Filter läuft für alle github-Plugins */
$fremd = warleek_update_plugin( false, array(), 'anderes-plugin/anderes.php', array() );
u_chk( 'fremdes plugin unberührt', false === $fremd, var_export( $fremd, true ) );

/* 4) Theme */
$t = warleek_update_theme( false, array(), 'warleek', array() );
u_chk( 'theme-update', is_array( $t ) && '9.9.9' === $t['version'] );
u_chk( 'fremdes theme unberührt', false === warleek_update_theme( false, array(), 'twentytwentyfive', array() ) );

/* 5) Details-Dialog */
$info = warleek_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'warleek-core' ) );
u_chk( 'details', is_object( $info ) && '9.9.9' === $info->version );
u_chk( 'changelog gerendert', is_object( $info ) && false !== strpos( $info->sections['changelog'], 'Etwas Neues' ) );
u_chk( 'fremder slug unberührt', false === warleek_plugins_api( false, 'plugin_information', (object) array( 'slug' => 'akismet' ) ) );

/* 6) Ordnername im Paket wird korrigiert */
$tmp = get_temp_dir() . 'warleek-src-test/';
$src = $tmp . 'warleek-1.2.3/';
wp_mkdir_p( $src );
$fixed = warleek_update_source_selection( $src, $tmp, null, array( 'theme' => 'warleek' ) );
u_chk( 'ordnername korrigiert', 'warleek' === basename( untrailingslashit( $fixed ) ), $fixed );
@rmdir( $tmp . 'warleek' ); @rmdir( $src ); @rmdir( $tmp );

/* 7) Nicht erreichbare Quelle: kein Update, kein Fehler */
delete_site_transient( WARLEEK_UPDATE_CACHE );
$GLOBALS['u_fail_http'] = true;
u_chk( 'fehler wird geschluckt', false === warleek_update_manifest( true ) );
u_chk( 'kein update bei fehler', false === warleek_update_plugin( false, array(), $own, array() ) );
$GLOBALS['u_fail_http'] = false;
delete_site_transient( WARLEEK_UPDATE_CACHE );

echo $fail ? "$fail FAILED\n" : "OK\n";
