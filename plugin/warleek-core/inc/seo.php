<?php
/**
 * Warleek — SEO: Organization-Schema (immer), Title/Description/OG/Canonical (nur ohne RankMath).
 * Meta-Quellen: _warleek_seo_title, _warleek_seo_desc, _warleek_noindex (vom Seed gesetzt, im Backend über RankMath pflegbar).
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Übernimmt ein SEO-Plugin die Meta-Ausgabe?
 * Rank Math gibt im Frontend erst nach dem Setup-Assistenten aus – bis dahin liefern wir selbst,
 * sonst hätte eine frisch installierte Seite gar keine Titles/Descriptions.
 */
function warleek_seo_plugin_active() {
	if ( defined( 'WPSEO_VERSION' ) ) { return true; }
	if ( class_exists( 'RankMath' ) ) { return (bool) get_option( 'rank_math_is_configured' ); }
	return false;
}

/**
 * Datum der neuesten Patch Note, deutsch formatiert.
 *
 * Steht im Titel der Übersichtsseite, damit in der Suche sichtbar ist, wie
 * aktuell die Sammlung ist. Ergebnis wird kurz zwischengespeichert – die Seite
 * wird oft aufgerufen, die Antwort ändert sich höchstens stündlich.
 *
 * @return string z. B. „30.09.2026" oder '' wenn es noch keine gibt.
 */
function warleek_seo_latest_patch_date() {
	$cached = get_transient( 'warleek_latest_patch_date' );
	if ( false !== $cached ) { return (string) $cached; }
	$posts = get_posts( array( 'post_type' => 'patchnote', 'posts_per_page' => 1, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids' ) );
	$date  = $posts ? get_the_date( 'd.m.Y', (int) $posts[0] ) : '';
	set_transient( 'warleek_latest_patch_date', $date, HOUR_IN_SECONDS );
	return $date;
}
/** Zwischenspeicher leeren, sobald eine Patch Note dazukommt oder sich ändert. */
add_action( 'save_post_patchnote', function () { delete_transient( 'warleek_latest_patch_date' ); } );

/**
 * Versionsnummer aus einem Patch-Note-Titel ziehen, z. B. „0.1.2".
 *
 * @param string $titel
 * @return string
 */
function warleek_seo_patch_version( $titel ) {
	return preg_match( '/\b(\d+\.\d+(?:\.\d+)?)\b/', (string) $titel, $m ) ? $m[1] : '';
}

/**
 * Titel einer einzelnen Patch Note: Thema + Sprache + Datum.
 *
 * Beispiel: „Wardogs Update 0.1.2 – Patch Notes Deutsch (30.09.2026)"
 */
function warleek_seo_patch_title( $post ) {
	$datum   = get_the_date( 'd.m.Y', $post );
	$version = warleek_seo_patch_version( get_the_title( $post ) );
	return $version
		? sprintf( 'Wardogs Update %s – Patch Notes Deutsch (%s)', $version, $datum )
		: sprintf( 'Wardogs Patch Notes Deutsch – %s', $datum );
}

/**
 * Beschreibung einer Patch Note. Trägt das Datum, weil danach gesucht wird,
 * und formuliert bewusst anders als der Titel.
 */
function warleek_seo_patch_desc( $post ) {
	$datum = get_the_date( 'd.m.Y', $post );
	$ende  = ' Für Spieler in Deutschland, Österreich und der Schweiz.';
	$satz  = sprintf( 'WARDOGS Patch Notes vom %s auf Deutsch', $datum );

	// Nur die **deutsche** Kurzfassung der Übersetzung darf zitiert werden.
	// Solange nicht übersetzt wurde, ist der Originaltext englisch – englische
	// Bruchstücke in einer Beschreibung, die „auf Deutsch" verspricht, schaden mehr,
	// als sie nützen. Dann steht hier ein sauberer deutscher Satz.
	$kurz   = get_post_meta( $post->ID, '_warleek_summary', true );
	$quelle = is_array( $kurz ) ? (string) reset( $kurz ) : (string) $kurz;
	$quelle = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $quelle ) ) );

	if ( '' === $quelle ) {
		$version = warleek_seo_patch_version( get_the_title( $post ) );
		$satz   .= $version
			? sprintf( ': Update %s mit allen Änderungen im Überblick.', $version )
			: ': alle Änderungen, Hotfixes und Anpassungen im Überblick.';
		return $satz . $ende;
	}

	$platz = 158 - mb_strlen( $satz . ': .' . $ende );
	if ( $platz > 30 && mb_strlen( $quelle ) > $platz ) {
		$quelle  = mb_substr( $quelle, 0, $platz );
		$schnitt = mb_strrpos( $quelle, ' ' );
		if ( $schnitt && $schnitt > 20 ) { $quelle = mb_substr( $quelle, 0, $schnitt ); }
	}
	return $satz . ': ' . rtrim( $quelle, " .,;:–-" ) . '.' . $ende;
}

/** Titel: eigenes Meta > Muster je Inhaltstyp > Standard. */
function warleek_seo_title( $title ) {
	if ( warleek_seo_plugin_active() ) { return $title; }
	if ( is_singular() ) {
		$t = get_post_meta( get_queried_object_id(), '_warleek_seo_title', true );
		if ( $t ) { return $t; }
		if ( is_singular( 'patchnote' ) ) { return warleek_seo_patch_title( get_queried_object() ) . ' | Warleek'; }
	}
	if ( is_post_type_archive( 'patchnote' ) ) {
		$datum = warleek_seo_latest_patch_date();
		return $datum ? sprintf( 'Wardogs Patch Notes Deutsch (%s) | Warleek', $datum ) : 'Wardogs Patch Notes Deutsch | Warleek';
	}
	if ( is_post_type_archive( 'guide' ) ) { return 'Wardogs Guides auf Deutsch – Tipps für DACH | Warleek'; }
	if ( is_post_type_archive( 'item' ) ) { return 'Wardogs Datenbank Deutsch – Waffen & Fahrzeuge | Warleek'; }
	if ( is_tax( 'item-typ' ) ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) { return sprintf( 'Wardogs %s – Werte auf Deutsch | Warleek', $term->name ); }
	}
	if ( is_tax( 'guide-thema' ) ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) { return sprintf( 'Wardogs %s – deutsche Guides & Tipps | Warleek', $term->name ); }
	}
	return $title;
}
add_filter( 'pre_get_document_title', 'warleek_seo_title', 20 );

/**
 * Description je Kontext.
 *
 * Formuliert bewusst anders als der Titel: Der Titel nennt Thema und Sprache
 * knapp, die Beschreibung nennt die Region aus und bringt die Nebenbegriffe unter.
 */
function warleek_seo_description() {
	if ( is_singular() ) {
		$d = get_post_meta( get_queried_object_id(), '_warleek_seo_desc', true );
		if ( $d ) { return $d; }
		$p = get_queried_object();
		if ( is_singular( 'patchnote' ) ) { return warleek_seo_patch_desc( $p ); }
		return wp_trim_words( wp_strip_all_tags( $p->post_excerpt ?: $p->post_content ), 28, '…' );
	}
	if ( is_post_type_archive( 'patchnote' ) ) {
		return 'WARDOGS Patch Notes auf Deutsch: Updates, Hotfixes und Changelogs übersetzt und nach Datum sortiert – für Spieler in Deutschland, Österreich und der Schweiz.';
	}
	if ( is_tax( 'guide-thema' ) ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) {
			$basis = $term->description ? rtrim( wp_strip_all_tags( $term->description ), '. ' ) . '. ' : '';
			return warleek_seo_kuerzen( $basis . sprintf( 'Wardogs-Guides zu %s auf Deutsch – für DACH.', $term->name ) );
		}
	}
	if ( is_post_type_archive( 'item' ) ) {
		return 'Alle Waffen, Fahrzeuge, Emplacements und Bauwerke aus Wardogs mit Preis, Freischaltung und Werten – auf Deutsch, für Deutschland, Österreich und die Schweiz.';
	}
	if ( is_tax( 'item-typ' ) ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) {
			return warleek_seo_kuerzen( sprintf( 'Alle %s aus Wardogs mit Preis, Freischaltung und Werten auf Deutsch – für Spieler in Deutschland, Österreich und der Schweiz.', $term->name ) );
		}
	}
	if ( is_post_type_archive( 'guide' ) ) {
		return 'Wardogs Guides und Tipps auf Deutsch: Einsteiger, FOB, Logistik, Gameplay, Equipment, Technik – für Deutschland, Österreich und die Schweiz.';
	}
	return get_bloginfo( 'description' );
}

/**
 * Beschreibung auf Suchergebnis-Länge bringen.
 *
 * Google schneidet um die 155–160 Zeichen ab. Abgeschnitten wird an der letzten
 * Wortgrenze, nicht mitten im Wort.
 *
 * @param string $text
 * @param int    $max
 * @return string
 */
function warleek_seo_kuerzen( $text, $max = 158 ) {
	$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
	if ( mb_strlen( $text ) <= $max ) { return $text; }
	$kurz    = mb_substr( $text, 0, $max - 1 );
	$schnitt = mb_strrpos( $kurz, ' ' );
	if ( $schnitt && $schnitt > 40 ) { $kurz = mb_substr( $kurz, 0, $schnitt ); }
	return rtrim( $kurz, " .,;:–-" ) . '…';
}

function warleek_seo_head() {
	$home = home_url( '/' );
	$logo = get_theme_mod( 'custom_logo' ) ?: (int) get_option( 'site_logo', 0 );
	$logo_url = $logo ? wp_get_attachment_image_url( $logo, 'full' ) : '';
	$same = array_filter( array( warleek_opt( 'discord_url' ), warleek_opt( 'whatsapp_url' ), warleek_opt( 'telegram_url' ), warleek_opt( 'steam_url' ), warleek_opt( 'steam_group_url' ), warleek_opt( 'youtube_url' ), warleek_opt( 'twitch_url' ), warleek_opt( 'instagram_url' ), warleek_opt( 'tiktok_url' ), warleek_opt( 'twitter_handle' ) ? 'https://x.com/' . ltrim( warleek_opt( 'twitter_handle' ), '@' ) : '' ) );
	$org = array(
		'@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Warleek', 'url' => $home,
		'description' => get_bloginfo( 'description' ), 'sameAs' => array_values( $same ),
	);
	if ( $logo_url ) { $org['logo'] = $logo_url; }
	echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";

	// WebSite + Suchfunktion: sagt Suchmaschinen, dass hier nachgeschlagen wird.
	$site_ld = array(
		'@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Warleek', 'url' => $home,
		'inLanguage' => 'de-DE', 'description' => get_bloginfo( 'description' ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array( '@type' => 'EntryPoint', 'urlTemplate' => $home . '?s={search_term_string}&post_type=guide' ),
			'query-input' => 'required name=search_term_string',
		),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $site_ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";

	// Guides sind Anleitungen – als Article mit Autorenangabe auszeichnen.
	if ( is_singular( 'guide' ) ) {
		$g = get_queried_object();
		$art = array(
			'@context' => 'https://schema.org', '@type' => 'TechArticle',
			'headline' => get_the_title( $g ), 'inLanguage' => 'de-DE',
			'datePublished' => get_the_date( 'c', $g ), 'dateModified' => get_the_modified_date( 'c', $g ),
			'author' => array( '@type' => 'Organization', 'name' => 'Warleek', 'url' => $home ),
			'publisher' => array( '@type' => 'Organization', 'name' => 'Warleek', 'url' => $home ),
			'mainEntityOfPage' => get_permalink( $g ),
		);
		if ( has_post_thumbnail( $g ) ) { $art['image'] = get_the_post_thumbnail_url( $g, 'large' ); }
		echo '<script type="application/ld+json">' . wp_json_encode( $art, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	// Patch Notes: Artikel über ein Spiel, mit Datum und Quelle bei Steam.
	if ( is_singular( 'patchnote' ) ) {
		$p    = get_queried_object();
		$ld   = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => warleek_seo_patch_title( $p ),
			'alternativeHeadline' => get_the_title( $p ),
			'inLanguage'       => 'de-DE',
			'datePublished'    => get_the_date( 'c', $p ),
			'dateModified'     => get_the_modified_date( 'c', $p ),
			'description'      => warleek_seo_patch_desc( $p ),
			'author'           => array( '@type' => 'Organization', 'name' => 'BULKHEAD' ),
			'publisher'        => array( '@type' => 'Organization', 'name' => 'Warleek', 'url' => $home ),
			'mainEntityOfPage' => get_permalink( $p ),
			'about'            => warleek_seo_game_ld(),
		);
		$version = warleek_seo_patch_version( get_the_title( $p ) );
		if ( $version ) { $ld['about']['softwareVersion'] = $version; }
		$quelle = get_post_meta( $p->ID, 'steam_url', true );
		if ( $quelle ) { $ld['isBasedOn'] = $quelle; }
		if ( has_post_thumbnail( $p ) ) { $ld['image'] = get_the_post_thumbnail_url( $p, 'large' ); }
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	// Patch-Notes-Übersicht: Sammlung mit Liste der jüngsten Einträge.
	if ( is_post_type_archive( 'patchnote' ) ) {
		$liste = get_posts( array( 'post_type' => 'patchnote', 'posts_per_page' => 10, 'orderby' => 'date', 'order' => 'DESC' ) );
		$items = array();
		foreach ( $liste as $i => $pn ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'url'      => get_permalink( $pn ),
				'name'     => warleek_seo_patch_title( $pn ),
			);
		}
		$ld = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'CollectionPage',
			'name'        => wp_get_document_title(),
			'inLanguage'  => 'de-DE',
			'description' => warleek_seo_description(),
			'about'       => warleek_seo_game_ld(),
			'mainEntity'  => array( '@type' => 'ItemList', 'numberOfItems' => count( $items ), 'itemListElement' => $items ),
		);
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	$krumen = warleek_seo_breadcrumbs();
	if ( $krumen ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $krumen, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}

	$desc  = warleek_seo_kuerzen( warleek_seo_description() );
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : ( is_post_type_archive() ? get_post_type_archive_link( get_query_var( 'post_type' ) ) : ( is_front_page() ? $home : '' ) );
	$img   = ''; $img_w = 0; $img_h = 0; $img_alt = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$tid = get_post_thumbnail_id(); $src = wp_get_attachment_image_src( $tid, 'large' );
		if ( $src ) { $img = $src[0]; $img_w = $src[1]; $img_h = $src[2]; $img_alt = get_post_meta( $tid, '_wp_attachment_image_alt', true ); }
	}
	if ( ! $img ) {
		$og = (int) get_option( 'warleek_og_default_id', 0 ); $src = $og ? wp_get_attachment_image_src( $og, 'full' ) : false;
		if ( $src ) { $img = $src[0]; $img_w = $src[1]; $img_h = $src[2]; $img_alt = get_post_meta( $og, '_wp_attachment_image_alt', true ); }
	}
	$is_article = is_singular( array( 'guide', 'patchnote', 'post' ) );
	$post       = is_singular() ? get_queried_object() : null;

	/* Dublin Core (immer, unabhängig vom SEO-Plugin) */
	echo '<link rel="schema.DC" href="http://purl.org/dc/elements/1.1/">' . "\n";
	echo '<link rel="schema.DCTERMS" href="http://purl.org/dc/terms/">' . "\n";
	$dc = array(
		'DC.title'       => $title,
		'DC.description' => $desc,
		'DC.language'    => 'de-DE',
		'DC.publisher'   => 'Warleek',
		'DC.creator'     => ( $post && $is_article && get_the_author_meta( 'display_name', $post->post_author ) ) ? get_the_author_meta( 'display_name', $post->post_author ) : 'Warleek',
		'DC.type'        => 'Text',
		'DC.format'      => 'text/html',
		'DC.identifier'  => $url ?: $home,
		'DC.rights'      => '© ' . gmdate( 'Y' ) . ' Warleek – Fan-Projekt, unabhängig vom Entwickler von WARDOGS',
		'DC.subject'     => warleek_seo_keywords(),
		'DC.coverage'    => 'Deutschland, Österreich, Schweiz',
	);
	if ( $post ) {
		$dc['DC.date']          = get_the_date( 'Y-m-d', $post );
		$dc['DCTERMS.created']  = get_the_date( 'c', $post );
		$dc['DCTERMS.modified'] = get_the_modified_date( 'c', $post );
	}
	if ( is_singular( 'patchnote' ) ) { $dc['DC.source'] = get_post_meta( get_queried_object_id(), 'steam_url', true ); $dc['DC.creator'] = 'BULKHEAD (via Steam)'; }
	foreach ( $dc as $k => $v ) { if ( '' !== $v && null !== $v ) { echo '<meta name="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '">' . "\n"; } }
	echo '<meta name="keywords" content="' . esc_attr( warleek_seo_keywords() ) . '">' . "\n";
	echo '<meta name="theme-color" content="#0d110f">' . "\n";

	/* DACH: dieselbe Seite für alle drei Länder anmelden. Das ist das Signal,
	   das Suchmaschinen tatsächlich auswerten – anders als das Keywords-Feld. */
	foreach ( array( 'DE', 'AT', 'CH' ) as $land ) {
		echo '<meta name="geo.region" content="' . esc_attr( $land ) . '">' . "\n";
	}
	if ( $url ) {
		foreach ( array( 'de-DE', 'de-AT', 'de-CH', 'de', 'x-default' ) as $lang ) {
			echo '<link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( $url ) . '">' . "\n";
		}
	}

	if ( warleek_seo_plugin_active() ) { return; }

	$noindex = is_singular() && get_post_meta( get_queried_object_id(), '_warleek_noindex', true );
	if ( $noindex || is_search() || is_404() ) { echo '<meta name="robots" content="noindex,follow">' . "\n"; }
	if ( $desc ) { echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n"; }

	if ( $url && ! is_singular() ) { echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n"; }

	/* Open Graph */
	echo '<meta property="og:type" content="' . ( $is_article ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:site_name" content="Warleek">' . "\n";
	echo '<meta property="og:locale" content="de_DE">' . "\n";
	echo '<meta property="og:locale:alternate" content="de_AT">' . "\n";
	echo '<meta property="og:locale:alternate" content="de_CH">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc ) { echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n"; }
	if ( $url ) { echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n"; }
	if ( $img ) {
		echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
		echo '<meta property="og:image:secure_url" content="' . esc_url( set_url_scheme( $img, 'https' ) ) . '">' . "\n";
		if ( $img_w ) { echo '<meta property="og:image:width" content="' . (int) $img_w . '">' . "\n"; echo '<meta property="og:image:height" content="' . (int) $img_h . '">' . "\n"; }
		echo '<meta property="og:image:type" content="' . esc_attr( wp_check_filetype( $img )['type'] ?: 'image/jpeg' ) . '">' . "\n";
		if ( $img_alt ) { echo '<meta property="og:image:alt" content="' . esc_attr( $img_alt ) . '">' . "\n"; }
	}
	if ( $is_article && $post ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c', $post ) ) . '">' . "\n";
		echo '<meta property="og:updated_time" content="' . esc_attr( get_the_modified_date( 'c', $post ) ) . '">' . "\n";
		echo '<meta property="article:author" content="Warleek">' . "\n";
		echo '<meta property="article:section" content="' . esc_attr( is_singular( 'patchnote' ) ? 'Patch Notes' : 'Guides' ) . '">' . "\n";
		$terms = is_singular( 'guide' ) ? get_the_terms( $post, 'guide-thema' ) : array();
		if ( $terms && ! is_wp_error( $terms ) ) { foreach ( $terms as $t ) { echo '<meta property="article:tag" content="' . esc_attr( $t->name ) . '">' . "\n"; } }
	}
	$fb = warleek_opt( 'fb_app_id' );
	if ( $fb ) { echo '<meta property="fb:app_id" content="' . esc_attr( $fb ) . '">' . "\n"; }

	/* Twitter / X */
	$tw = ltrim( (string) warleek_opt( 'twitter_handle' ), '@' );
	echo '<meta name="twitter:card" content="' . ( $img ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
	if ( $desc ) { echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n"; }
	if ( $img ) { echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n"; if ( $img_alt ) { echo '<meta name="twitter:image:alt" content="' . esc_attr( $img_alt ) . '">' . "\n"; } }
	if ( $tw ) { echo '<meta name="twitter:site" content="@' . esc_attr( $tw ) . '">' . "\n"; echo '<meta name="twitter:creator" content="@' . esc_attr( $tw ) . '">' . "\n"; }

	/* Weitere Plattformen (Pinterest/Discord/Telegram/WhatsApp lesen OG; Discord zusätzlich theme-color) */
	echo '<meta name="author" content="Warleek">' . "\n";
	echo '<meta name="application-name" content="Warleek">' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="Warleek">' . "\n";
}

/**
 * Das Spiel als Schema-Objekt – Bezugspunkt für Patch Notes und Guides.
 *
 * @return array
 */
function warleek_seo_game_ld() {
	return array(
		'@type'       => 'VideoGame',
		'name'        => 'WARDOGS',
		'gamePlatform' => 'PC',
		'author'      => array( '@type' => 'Organization', 'name' => 'BULKHEAD' ),
		'publisher'   => array( '@type' => 'Organization', 'name' => 'Team17' ),
		'sameAs'      => 'https://store.steampowered.com/app/' . (int) ( warleek_opt( 'steam_appid' ) ?: 1867240 ) . '/',
	);
}

/**
 * Brotkrumen als Schema – zeigt Suchmaschinen den Weg zur Seite.
 *
 * @return array|false
 */
function warleek_seo_breadcrumbs() {
	$home  = home_url( '/' );
	$weg   = array( array( 'name' => 'Startseite', 'url' => $home ) );

	if ( is_singular( 'guide' ) ) {
		$weg[] = array( 'name' => 'Guides', 'url' => get_post_type_archive_link( 'guide' ) );
		$terms = get_the_terms( get_queried_object(), 'guide-thema' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$t     = reset( $terms );
			$weg[] = array( 'name' => $t->name, 'url' => get_term_link( $t ) );
		}
		$weg[] = array( 'name' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_singular( 'patchnote' ) ) {
		$weg[] = array( 'name' => 'Patch Notes', 'url' => get_post_type_archive_link( 'patchnote' ) );
		$weg[] = array( 'name' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_page() ) {
		foreach ( array_reverse( get_post_ancestors( get_queried_object_id() ) ) as $an ) {
			$weg[] = array( 'name' => get_the_title( $an ), 'url' => get_permalink( $an ) );
		}
		$weg[] = array( 'name' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_post_type_archive( 'guide' ) ) {
		$weg[] = array( 'name' => 'Guides', 'url' => get_post_type_archive_link( 'guide' ) );
	} elseif ( is_post_type_archive( 'patchnote' ) ) {
		$weg[] = array( 'name' => 'Patch Notes', 'url' => get_post_type_archive_link( 'patchnote' ) );
	} elseif ( is_tax( 'guide-thema' ) ) {
		$term  = get_queried_object();
		$weg[] = array( 'name' => 'Guides', 'url' => get_post_type_archive_link( 'guide' ) );
		if ( $term && ! is_wp_error( $term ) ) { $weg[] = array( 'name' => $term->name, 'url' => get_term_link( $term ) ); }
	} else {
		return false;
	}

	$items = array();
	foreach ( $weg as $i => $s ) {
		$items[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $s['name'], 'item' => $s['url'] );
	}
	return array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items );
}

/** Keyword-Liste (Site-weit + Seiten-Thema), immer mit Sprach- und Länderbezug. */
function warleek_seo_keywords() {
	$base = array( 'Wardogs', 'Wardogs Deutsch', 'Wardogs Guide Deutsch', 'Wardogs Tipps', 'Wardogs Anleitung', 'Wardogs Deutschland', 'Wardogs Österreich', 'Wardogs Schweiz', 'DACH', 'deutschsprachig' );
	if ( is_singular( 'guide' ) ) {
		$terms = get_the_terms( get_queried_object(), 'guide-thema' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$base[] = 'Wardogs ' . $t->name;
				$base[] = 'Wardogs ' . $t->name . ' Deutsch';
			}
		}
	} elseif ( is_singular( 'patchnote' ) ) {
		$version = warleek_seo_patch_version( get_the_title() );
		array_push( $base, 'Wardogs Patch Notes Deutsch', 'Wardogs Update', 'Wardogs Hotfix', 'Wardogs Changelog', 'Wardogs Patch ' . get_the_date( 'd.m.Y' ) );
		if ( $version ) { $base[] = 'Wardogs Update ' . $version; }
	} elseif ( is_post_type_archive( 'patchnote' ) ) {
		array_push( $base, 'Wardogs Patch Notes Deutsch', 'Wardogs Update', 'Wardogs Hotfix', 'Wardogs Changelog', 'Wardogs Patch Notes übersetzt' );
	} elseif ( is_singular() ) {
		$base[] = get_the_title();
	}
	return implode( ', ', array_unique( $base ) );
}
add_action( 'wp_head', 'warleek_seo_head', 5 );

/** Sprache/Region explizit. */
add_filter( 'language_attributes', function ( $attr ) { return str_contains( $attr, 'lang=' ) ? $attr : $attr . ' lang="de-DE"'; } );
