<?php
/**
 * Warleek — dynamische Blöcke (Server-Side-Render) + Shortcode-Fallbacks.
 *  - warleek/chat-buttons     [warleek_chat layout="row|grid|header"]
 *  - warleek/patchnotes-latest [warleek_patchnotes count=3]
 *  - warleek/guides-grid      [warleek_guides count=6 thema=""]
 *  - [warleek_source]         Quellenhinweis auf Patch-Note-Einzelseiten
 *
 * @package warleek
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ------------------------------------------------------------- Icons */
function warleek_icon( $name ) {
	$icons = array(
		'discord'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.3 4.4A19.8 19.8 0 0 0 15.4 3l-.2.4a18 18 0 0 1 4.5 2.3 15.7 15.7 0 0 0-15.4 0A18 18 0 0 1 8.8 3.4L8.6 3a19.7 19.7 0 0 0-4.9 1.4C.6 9 0 13.4.3 17.8a19.9 19.9 0 0 0 6 3l1.3-2a12.8 12.8 0 0 1-2-1l.5-.4a14.2 14.2 0 0 0 11.8 0l.5.4a12.7 12.7 0 0 1-2 1l1.3 2a19.8 19.8 0 0 0 6-3c.5-5-.9-9.4-3.4-13.4ZM8.5 15.1c-1.2 0-2.1-1.1-2.1-2.4s.9-2.4 2.1-2.4 2.2 1.1 2.1 2.4c0 1.3-.9 2.4-2.1 2.4Zm7 0c-1.2 0-2.1-1.1-2.1-2.4s.9-2.4 2.1-2.4 2.2 1.1 2.1 2.4c0 1.3-.9 2.4-2.1 2.4Z"/></svg>',
		'whatsapp' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 12 12 0 0 0 4.6 4c1.7.7 2.1.6 2.9.5a2.5 2.5 0 0 0 1.6-1.2c.2-.6.2-1 .1-1.2l-.5-.3Z"/></svg>',
		'telegram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.9 4.3 18.7 19.6c-.2 1-.9 1.3-1.8.8l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.4-5 9.1-8.2c.4-.4-.1-.5-.6-.2L6.2 13.3l-4.8-1.5c-1-.3-1.1-1 .2-1.5L20.5 3c.9-.3 1.6.2 1.4 1.3Z"/></svg>',
		'steam'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 0 0-10 9.3l5.4 2.2a2.8 2.8 0 0 1 1.6-.5l2.4-3.5v-.1a3.8 3.8 0 1 1 3.8 3.8h-.1l-3.4 2.4a2.8 2.8 0 0 1-5.6.4L2.2 14.7A10 10 0 1 0 12 2Zm-5.7 14.1 1.2.5a2.1 2.1 0 1 0 1.2-4l1.3.5a1.6 1.6 0 1 1-1.2 2.8l-2.5-1Zm9.3-8.4a2.5 2.5 0 1 1-2.5 2.5 2.5 2.5 0 0 1 2.5-2.5Zm0 .6a1.9 1.9 0 1 0 1.9 1.9 1.9 1.9 0 0 0-1.9-1.9Z"/></svg>',
	);
	return $icons[ $name ] ?? '';
}

/* ------------------------------------------------------ Chat-Buttons */
function warleek_render_chat_buttons( $attrs = array() ) {
	$layout   = isset( $attrs['layout'] ) ? $attrs['layout'] : 'row';
	$channels = array(
		'discord'  => array( 'Discord',  warleek_opt( 'discord_url' ) ),
		'whatsapp' => array( 'WhatsApp', warleek_opt( 'whatsapp_url' ) ),
		'telegram' => array( 'Telegram', warleek_opt( 'telegram_url' ) ),
	);
	if ( 'header' === $layout ) { $channels = array( 'discord' => $channels['discord'] ); }
	$out = '<div class="wl-chat wl-chat--' . esc_attr( $layout ) . '">';
	foreach ( $channels as $key => $c ) {
		list( $label, $url ) = $c;
		$label_full = 'header' === $layout ? 'Discord' : $label . ' beitreten';
		if ( $url ) {
			$out .= '<a class="wl-chat__btn wl-chat__btn--' . $key . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . warleek_icon( $key ) . '<span>' . esc_html( $label_full ) . '</span></a>';
		} else {
			$out .= '<span class="wl-chat__btn wl-chat__btn--' . $key . ' is-pending" title="Link folgt in Kürze" aria-disabled="true">' . warleek_icon( $key ) . '<span>' . esc_html( $label ) . '</span><span class="screen-reader-text"> – Link folgt in Kürze</span></span>';
		}
	}
	return $out . '</div>';
}

/* ------------------------------------------------ Patch Notes (neueste) */
function warleek_render_patchnotes_latest( $attrs = array() ) {
	$count = isset( $attrs['count'] ) ? max( 1, (int) $attrs['count'] ) : 3;
	$q     = new WP_Query( array( 'post_type' => 'patchnote', 'posts_per_page' => $count, 'no_found_rows' => true, 'orderby' => 'date', 'order' => 'DESC' ) );
	$out   = '<ul class="wl-patchlist">';
	if ( ! $q->posts ) {
		$out .= '<li class="wl-patchlist__empty">Noch keine Patch Notes importiert – der Sync läuft stündlich.</li>';
	}
	foreach ( $q->posts as $p ) {
		$out .= '<li><time datetime="' . esc_attr( get_the_date( 'c', $p ) ) . '">' . esc_html( get_the_date( 'd.m.Y', $p ) ) . '</time>'
			. '<a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a>'
			. '<p>' . esc_html( wp_trim_words( $p->post_excerpt ?: wp_strip_all_tags( $p->post_content ), 28, '…' ) ) . '</p></li>';
	}
	$out .= '<li class="wl-patchlist__all"><a href="' . esc_url( get_post_type_archive_link( 'patchnote' ) ) . '">Alle Patch Notes →</a></li>';
	return $out . '</ul>';
}

/* ------------------------------------------------------- Guides-Grid */
function warleek_render_guides_grid( $attrs = array() ) {
	$count = isset( $attrs['count'] ) ? max( 1, (int) $attrs['count'] ) : 6;
	$thema = isset( $attrs['thema'] ) ? sanitize_title( $attrs['thema'] ) : '';
	$args  = array( 'post_type' => 'guide', 'posts_per_page' => $count, 'no_found_rows' => true, 'orderby' => 'menu_order date', 'order' => 'ASC' );
	if ( $thema ) { $args['tax_query'] = array( array( 'taxonomy' => 'guide-thema', 'field' => 'slug', 'terms' => $thema ) ); }
	$q   = new WP_Query( $args );
	$out = '<div class="wl-grid wl-guides">';
	if ( ! $q->posts ) { $out .= '<p class="wl-muted">Guides folgen in Kürze.</p>'; }
	foreach ( $q->posts as $p ) {
		$terms = get_the_terms( $p, 'guide-thema' );
		$chip  = $terms && ! is_wp_error( $terms ) ? '<span class="wl-tag">' . esc_html( $terms[0]->name ) . '</span>' : '';
		$img   = has_post_thumbnail( $p ) ? '<div class="wl-card__img">' . get_the_post_thumbnail( $p, 'medium_large', array( 'loading' => 'lazy' ) ) . '</div>' : '';
		$out  .= '<article class="wl-card">' . $img . $chip
			. '<h3><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3>'
			. '<p>' . esc_html( wp_trim_words( get_the_excerpt( $p ), 24, '…' ) ) . '</p>'
			. '<span class="wl-card__more">Guide lesen →</span></article>';
	}
	return $out . '</div>';
}

/* -------------------------------------------------- Registrierung */
function warleek_register_blocks() {
	$blocks = array(
		'chat-buttons'      => array( 'cb' => 'warleek_render_chat_buttons',      'attrs' => array( 'layout' => array( 'type' => 'string', 'default' => 'row' ) ) ),
		'patchnotes-latest' => array( 'cb' => 'warleek_render_patchnotes_latest', 'attrs' => array( 'count' => array( 'type' => 'number', 'default' => 3 ) ) ),
		'guides-grid'       => array( 'cb' => 'warleek_render_guides_grid',       'attrs' => array( 'count' => array( 'type' => 'number', 'default' => 6 ), 'thema' => array( 'type' => 'string', 'default' => '' ) ) ),
	);
	foreach ( $blocks as $name => $b ) {
		register_block_type( 'warleek/' . $name, array(
			'api_version'     => 3,
			'attributes'      => $b['attrs'],
			'render_callback' => $b['cb'],
			'editor_script'   => 'warleek-blocks',
		) );
	}
	add_shortcode( 'warleek_chat',       'warleek_render_chat_buttons' );
	add_shortcode( 'warleek_patchnotes', 'warleek_render_patchnotes_latest' );
	add_shortcode( 'warleek_guides',     'warleek_render_guides_grid' );
	add_shortcode( 'warleek_source',     function () { return is_singular( 'patchnote' ) ? warleek_patchnote_source_link( get_the_ID() ) : ''; } );
	add_shortcode( 'warleek_clan_tag',   function () { return esc_html( warleek_opt( 'clan_tag' ) ); } );
}
add_action( 'init', 'warleek_register_blocks' );

/** Editor-Registrierung ohne Build-Schritt (ServerSideRender-Vorschau). */
function warleek_register_block_editor_script() {
	wp_register_script( 'warleek-blocks', false, array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components' ), WARLEEK_CORE_VERSION, true );
	$js = <<<'JS'
(function(wp){
	var el=wp.element.createElement, SSR=wp.serverSideRender, IC=wp.blockEditor.InspectorControls, PB=wp.components.PanelBody, TC=wp.components.TextControl, RC=wp.components.RangeControl, SC=wp.components.SelectControl;
	function reg(name,title,icon,controls){
		wp.blocks.registerBlockType('warleek/'+name,{title:title,icon:icon,category:'widgets',
			edit:function(props){var a=props.attributes,s=props.setAttributes;
				return el(wp.element.Fragment,null,
					el(IC,null,el(PB,{title:'Einstellungen'},controls(a,s))),
					el(SSR,{block:'warleek/'+name,attributes:a}));},
			save:function(){return null;}});
	}
	reg('chat-buttons','Warleek Chat-Buttons','format-chat',function(a,s){return el(SC,{label:'Layout',value:a.layout,options:[{label:'Reihe',value:'row'},{label:'Raster',value:'grid'},{label:'Nur Discord (Header)',value:'header'}],onChange:function(v){s({layout:v});}});});
	reg('patchnotes-latest','Warleek Patch Notes (neueste)','update',function(a,s){return el(RC,{label:'Anzahl',value:a.count,min:1,max:10,onChange:function(v){s({count:v});}});});
	reg('guides-grid','Warleek Guides-Raster','book',function(a,s){return [el(RC,{key:'c',label:'Anzahl',value:a.count,min:1,max:12,onChange:function(v){s({count:v});}}),el(TC,{key:'t',label:'Thema (Slug, leer = alle)',value:a.thema,onChange:function(v){s({thema:v});}})];});
})(window.wp);
JS;
	wp_add_inline_script( 'warleek-blocks', $js );
}
add_action( 'init', 'warleek_register_block_editor_script', 5 );
