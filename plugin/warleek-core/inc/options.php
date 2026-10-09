<?php
/**
 * Warleek — Optionsseite (Einstellungen → Warleek).
 * Chat-Links, Steam-AppID, Clan-Tag, Kontakt. Zugriff über warleek_opt().
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Felddefinitionen: key => [label, type, default, description]. */
/**
 * Vorgabe für den Analyse-Einbindungscode: Google Analytics 4 für warleek.de.
 *
 * Steht als Vorgabe und nicht fest verdrahtet – wer im Backend etwas anderes
 * einträgt, gewinnt (`warleek_opt()` nimmt den gespeicherten Wert, sobald er
 * nicht leer ist). Zwei Zeilen sind bewusst dabei: `allow_google_signals` und
 * `allow_ad_personalization_signals` auf false schalten die geräteübergreifende
 * Zuordnung und die Werbepersonalisierung ab. Das ist weniger, als Google
 * standardmäßig erhebt – und es ist das, was die Datenschutzerklärung zusagt.
 *
 * Der Block wird erst nach der Einwilligung ausgeführt (`inc/consent.php`).
 *
 * @return string
 */
function warleek_analytics_vorgabe() {
	return <<<'GA'
<script async src="https://www.googletagmanager.com/gtag/js?id=G-QDSF1KLBMM"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('set', 'allow_google_signals', false);
gtag('set', 'allow_ad_personalization_signals', false);
gtag('config', 'G-QDSF1KLBMM');
</script>
GA;
}

function warleek_option_fields() {
	return array(
		'discord_url'   => array( 'Discord-Invite',        'url',  '',        'Vollständiger Invite-Link, z. B. https://discord.gg/xyz. Leer = Button zeigt „bald".' ),
		'whatsapp_url'  => array( 'WhatsApp-Gruppe',       'url',  '',        'Einladungslink der WhatsApp-Community/-Gruppe.' ),
		'telegram_url'  => array( 'Telegram-Gruppe',       'url',  '',        'Link zur Telegram-Gruppe, z. B. https://t.me/xyz.' ),
		'steam_url'     => array( 'Steam-Store-Link',      'url',  'https://store.steampowered.com/app/1867240/WARDOGS/', 'Wird im Organization-Schema (sameAs) und in Patch Notes verwendet.' ),
		'steam_appid'   => array( 'Steam-AppID',           'int',  '1867240', 'AppID von WARDOGS für den Patch-Notes-Sync.' ),
		'clan_tag'      => array( 'Clan-Tag',              'text', '[WLK]',   'Wird an einzelnen Stellen (z. B. Clan-Seite, Dogtag-Zeile) angezeigt.' ),
		'kontakt_email' => array( 'Kontakt-E-Mail',        'email','',        'Für Kontakt-Hinweise auf der About-us-Seite.' ),
		'autor_name'    => array( 'Wer prüft (Name)',      'text', 'Patrick Schauß',        'Steht als „geprüft von" in den strukturierten Daten und im Herkunftsnachweis. Leer = die Angabe entfällt, statt anonym zu bleiben.' ),
		'autor_url'     => array( 'Autorenseite',          'url',  '',        'Seite, die diese Person als Spieler ausweist – Erfahrung ist das stärkste Vertrauenssignal, das eine Guide-Seite hat.' ),
		'autor_rolle'   => array( 'Kurzbeschreibung',      'text', '',        'Ein Satz, z. B. „Spielt WARDOGS seit dem Early-Access-Start, Schwerpunkt Logistik und FOB-Bau."' ),
		'autor_profile' => array( 'Profile (eine je Zeile)','textarea','',    'Steam, YouTube, Twitch … – werden als sameAs ausgegeben und machen die Person überprüfbar.' ),
		'twitter_handle'=> array( 'X/Twitter-Handle',      'text', '',        'Ohne @, z. B. warleek. Für twitter:site/creator-Metas.' ),
		'youtube_url'   => array( 'YouTube-Kanal',         'url',  '',        'Wird im Organization-Schema (sameAs) verlinkt.' ),
		'twitch_url'    => array( 'Twitch-Kanal',          'url',  '',        'Wird im Organization-Schema (sameAs) verlinkt.' ),
		'instagram_url' => array( 'Instagram',             'url',  '',        'Wird im Organization-Schema (sameAs) verlinkt.' ),
		'tiktok_url'    => array( 'TikTok',                'url',  '',        'Wird im Organization-Schema (sameAs) verlinkt.' ),
		'steam_group_url'=> array( 'Steam-Gruppe',         'url',  '',        'Link zur Steam-Community-Gruppe von Warleek.' ),
		'fb_app_id'     => array( 'Facebook App-ID',       'text', '',        'Optional, für fb:app_id.' ),
		'anthropic_api_key'  => array( 'Anthropic API-Schlüssel', 'password', '', 'Für die deutsche Übersetzung der Patch Notes. Sicherer: als Konstante WARLEEK_ANTHROPIC_KEY in der wp-config.php – die hat Vorrang.' ),
		'translate_enabled'  => array( 'Patch Notes übersetzen',  'bool',     '1', 'Aus: Patch Notes bleiben englisch.' ),
		'translate_model'    => array( 'Modell',                  'text',     'claude-opus-5', 'claude-opus-5 (Standard), claude-sonnet-5 oder claude-haiku-4-5 als günstigere Alternativen.' ),
		'translate_max_chars'=> array( 'Längengrenze (Zeichen)',  'int',      '20000', 'Längere Patch Notes bleiben englisch, statt eine teure Anfrage zu riskieren.' ),
		'translate_budget'   => array( 'Übersetzungen je Lauf',   'int',      '5',   'Deckel pro Sync-Lauf. Der stündliche Cron holt den Rest nach.' ),
		'consent_enabled'    => array( 'Einwilligungs-Banner',    'bool',     '1',   'Zeigt den Hinweis unten am Bildschirm. Ohne eingebettete externe Medien ist er rechtlich nicht nötig – dann kannst du ihn ausschalten.' ),
		'consent_title'      => array( 'Banner: Überschrift',     'text',     'Deine Entscheidung', 'Kurze Zeile über dem Text.' ),
		'consent_text'       => array( 'Banner: Text',            'textarea', 'Warleek zeigt keine Werbung und verkauft keine Daten. Zwei Dinge brauchen trotzdem deine Einwilligung, weil dabei fremde Server ins Spiel kommen: eingebettete Videos (YouTube, Vimeo) und die Reichweitenmessung mit Google Analytics. Ohne Zustimmung wird nichts davon geladen. Du kannst jede Kategorie einzeln wählen und die Entscheidung jederzeit ändern.', 'Was im Banner steht. Bleib bei dem, was die Seite wirklich tut.' ),
		'consent_accept'     => array( 'Banner: Zustimmen',       'text',     'Auswahl erlauben', 'Beschriftung der Zustimmen-Schaltfläche.' ),
		'consent_decline'    => array( 'Banner: Ablehnen',        'text',     'Nur notwendige',          'Beschriftung der Ablehnen-Schaltfläche. Muss gleichwertig aussehen – das ist Vorschrift.' ),
		'consent_media'      => array( 'Kategorie: externe Medien', 'bool',   '1',   'Einwilligung für eingebettete Videos von YouTube und Vimeo abfragen.' ),
		'consent_stats'      => array( 'Kategorie: Statistik',      'bool',   '1',   'Einwilligung für Reichweitenmessung abfragen. Erst einschalten, wenn unten auch ein Skript hinterlegt ist.' ),
		'consent_stats_label'=> array( 'Statistik: Beschriftung',   'text',   'Reichweitenmessung (Google Analytics)', 'Steht als Auswahl im Banner. Den Anbieter benennen, nicht „anonyme Statistik" schreiben – Google Analytics ist nicht anonym.' ),
		'analytics_code'     => array( 'Statistik: Einbindungscode', 'code',  warleek_analytics_vorgabe(),    'Vollständiger &lt;script&gt;-Block deines Analyse-Werkzeugs. Wird erst nach der Einwilligung ausgeführt – vorher steht er als toter Text im Quelltext. Leer lassen, solange nichts gemessen wird. <strong>Wichtig:</strong> Sobald hier etwas steht, gehört das Werkzeug samt Anbieter auch in die Datenschutzerklärung.' ),
		'external_nofollow'  => array( 'Externe Links entwerten',   'bool',   '1',   'Setzt bei allen externen Links in Guides und Patch Notes rel="nofollow noopener noreferrer" – gedacht für Quellenangaben.' ),
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
			case 'bool':  $out[ $key ] = $val ? '1' : '0'; break;
			case 'textarea': $out[ $key ] = sanitize_textarea_field( $val ); break;
			case 'code':
				// Einbindungscode darf Skript-Markup enthalten – aber nur von Leuten,
				// die ohnehin unfiltered_html dürfen. Sonst bleibt reiner Text übrig.
				$out[ $key ] = current_user_can( 'unfiltered_html' ) ? trim( (string) $val ) : wp_strip_all_tags( $val );
				break;
			case 'password':
				// Leeres Feld bedeutet „unverändert" – sonst würde jedes Speichern den Schlüssel löschen.
				$old = (array) get_option( 'warleek_options', array() );
				$out[ $key ] = '' === $val ? ( $old[ $key ] ?? '' ) : sanitize_text_field( $val );
				break;
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
	if ( 'bool' === $def[1] ) {
		printf(
			'<label><input type="checkbox" id="warleek_%1$s" name="warleek_options[%1$s]" value="1" %2$s> aktiv</label><p class="description">%3$s</p>',
			esc_attr( $key ), checked( '1', $val ?: $def[2], false ), esc_html( $def[3] )
		);
		return;
	}
	if ( 'password' === $def[1] ) {
		printf(
			'<input type="password" id="warleek_%1$s" name="warleek_options[%1$s]" value="" class="regular-text" autocomplete="new-password" placeholder="%2$s"><p class="description">%3$s</p>',
			esc_attr( $key ),
			esc_attr( $val ? '•••••••• (gespeichert – leer lassen, um ihn zu behalten)' : 'sk-ant-…' ),
			esc_html( $def[3] )
		);
		return;
	}
	if ( 'code' === $def[1] ) {
		printf(
			'<textarea id="warleek_%1$s" name="warleek_options[%1$s]" rows="6" class="large-text code" spellcheck="false" placeholder="&lt;script&gt;…&lt;/script&gt;">%2$s</textarea><p class="description">%3$s</p>',
			esc_attr( $key ), esc_textarea( $val ), wp_kses_post( $def[3] )
		);
		return;
	}
	if ( 'textarea' === $def[1] ) {
		printf(
			'<textarea id="warleek_%1$s" name="warleek_options[%1$s]" rows="4" class="large-text" placeholder="%2$s">%3$s</textarea><p class="description">%4$s</p>',
			esc_attr( $key ), esc_attr( $def[2] ), esc_textarea( $val ), esc_html( $def[3] )
		);
		return;
	}
	$type = 'int' === $def[1] ? 'number' : ( 'url' === $def[1] ? 'url' : ( 'email' === $def[1] ? 'email' : 'text' ) );
	printf(
		'<input type="%1$s" id="warleek_%2$s" name="warleek_options[%2$s]" value="%3$s" class="regular-text" placeholder="%4$s"><p class="description">%5$s</p>',
		esc_attr( $type ), esc_attr( $key ), esc_attr( $val ), esc_attr( $def[2] ), esc_html( $def[3] )
	);
}

/** Manueller Sync-Trigger über die Optionsseite. */
function warleek_handle_manual_sync() {
	if ( ! isset( $_GET['warleek_sync'] ) || ! current_user_can( 'manage_options' ) ) { return; }
	check_admin_referer( 'warleek_sync' );
	if ( function_exists( 'warleek_sync_patchnotes' ) ) {
		$r = warleek_sync_patchnotes( true );
		set_transient( 'warleek_sync_notice', $r, 60 );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=warleek' ) );
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
		if ( $screen && in_array( $screen->id, array( 'dashboard', 'toplevel_page_warleek', 'edit-patchnote' ), true ) ) {
			printf( '<div class="notice notice-warning"><p>Warleek: Der letzte Steam-Sync ist fehlgeschlagen (%s). Bestehende Patch Notes bleiben unverändert.</p></div>', esc_html( $err ) );
		}
	}
}
add_action( 'admin_notices', 'warleek_sync_admin_notice' );
