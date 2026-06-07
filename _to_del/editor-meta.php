<?php
/**
 * Block-editor per-post controls (no build step).
 * -----------------------------------------------------------------------------
 * The writer's per-post choices live INLINE in the editor's native panels — like
 * Newspack — not in a boxed "Post Options" metabox. We do it WITHOUT a JS build
 * toolchain (no JSX / webpack / package.json): the meta is registered with
 * show_in_rest so the editor data store can read/write it, and a small plain-JS
 * file (js/editor.js) renders the controls via the global `wp.*` runtime.
 *
 * Controls:
 *   - Post  → Status & Visibility panel: "Feature this post" toggle + a Layout
 *             radio (Default / No sidebar / With sidebar / Hero full / Hero half).
 *   - Page  → Status & Visibility panel: "Hide page title" toggle.
 *
 * Meta keys are underscore-prefixed (protected), so each registration supplies an
 * auth_callback (edit_post capability) — required for REST to allow writes. The
 * front end reads these via get_post_meta() exactly as before (single.php / page.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Page meta: hide the theme-rendered <h1> title (content owns its own heading). */
const MIKE_HIDE_PAGE_TITLE_META = '_mike_hide_page_title';

/** Post meta: a subtitle / dek shown beneath the title (Ghost/Substack style). */
const MIKE_SUBTITLE_META = '_mike_subtitle';

/** Post meta: per-post hero-split background colour. Empty falls back to the
 *  Customizer default (mike_single_hero_split_bg). Only meaningful on a post
 *  whose layout type is 'hero-split' — silently ignored elsewhere. */
const MIKE_HERO_SPLIT_BG_META = '_mike_hero_split_bg';

/* -----------------------------------------------------------------------------
   1. Register the meta (show_in_rest → editable in the block editor).
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_register_editor_meta' ) ) :
	function mike_register_editor_meta() {
		// Protected meta needs an auth_callback to be writable over REST.
		$can_edit = function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		// Post: layout type. Valid values come from mike_single_types(); a bad value
		// is harmless (mike_single_config() falls back to 'default').
		register_post_meta(
			'post',
			MIKE_SINGLE_TYPE_META,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => 'default',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $can_edit,
			)
		);

		// Post: featured flag. Stored as '1' (string) to match the existing star
		// column + one-click toggle in inc/featured.php (a single source of truth).
		register_post_meta(
			'post',
			MIKE_FEATURED_META,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => $can_edit,
			)
		);

		// Page: hide the page title.
		register_post_meta(
			'page',
			MIKE_HIDE_PAGE_TITLE_META,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'default'           => false,
				'auth_callback'     => $can_edit,
			)
		);

		// Page: layout (default | with-sidebar | with-sidebar-left | wide | full-bleed).
		// Sanitize gate: only accept keys declared in mike_page_layouts(). Anything
		// else collapses to 'default' — same defensive shape as the single-post type.
		register_post_meta(
			'page',
			MIKE_PAGE_LAYOUT_META,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => 'default',
				'sanitize_callback' => function ( $value ) {
					$choices = function_exists( 'mike_page_layouts' ) ? mike_page_layouts() : array();
					return array_key_exists( (string) $value, $choices ) ? (string) $value : 'default';
				},
				'auth_callback'     => $can_edit,
			)
		);

		// Page: flush-to-header (drop .site-main top padding so the first block
		// touches the header). Independent from layout — works with any layout.
		register_post_meta(
			'page',
			MIKE_PAGE_FLUSH_TOP_META,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'default'           => false,
				'auth_callback'     => $can_edit,
			)
		);

		// Page: flush-to-footer (drop .site-main bottom padding).
		register_post_meta(
			'page',
			MIKE_PAGE_FLUSH_BOTTOM_META,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'boolean',
				'default'           => false,
				'auth_callback'     => $can_edit,
			)
		);

		// Post: subtitle / dek (a textarea — keep line breaks).
		register_post_meta(
			'post',
			MIKE_SUBTITLE_META,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'auth_callback'     => $can_edit,
			)
		);

		// Post: per-post hero-split background colour. Stored as a hex string
		// ('#aabbcc' or '#abc'); empty = inherit the Customizer default. Inline
		// hex sanitizer (WP's sanitize_hex_color is only loaded in admin
		// context; this runs on REST writes too).
		register_post_meta(
			'post',
			MIKE_HERO_SPLIT_BG_META,
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => function ( $value ) {
					$value = trim( (string) $value );
					if ( '' === $value ) {
						return '';
					}
					return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value ) ? $value : '';
				},
				'auth_callback'     => $can_edit,
			)
		);
	}
endif;
add_action( 'init', 'mike_register_editor_meta' );

/* -----------------------------------------------------------------------------
   2. Enqueue the no-build editor script + hand it the data PHP owns (the layout
      choices and the meta-key names — single source of truth stays in PHP).
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_editor_meta_assets' ) ) :
	function mike_editor_meta_assets() {
		$theme_version = mike_asset_version();

		wp_enqueue_script(
			'mike-editor',
			get_template_directory_uri() . '/js/editor.js',
			// The wp.* packages the script reads from the global runtime. Listing them
			// as deps guarantees they're loaded first (no bundling needed). wp-editor
			// provides PluginDocumentSettingPanel (with a wp.editPost fallback).
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			$theme_version,
			true
		);

		wp_set_script_translations( 'mike-editor', 'mike' );

		// Pass the layout choices (label + value) so the radio's options are defined
		// ONCE in PHP (mike_single_types / mike_page_layouts) and never duplicated
		// in JS.
		$single_choices = array();
		if ( function_exists( 'mike_single_types' ) ) {
			foreach ( mike_single_types() as $value => $label ) {
				$single_choices[] = array( 'value' => $value, 'label' => $label );
			}
		}
		$page_choices = array();
		if ( function_exists( 'mike_page_layouts' ) ) {
			foreach ( mike_page_layouts() as $value => $label ) {
				$page_choices[] = array( 'value' => $value, 'label' => $label );
			}
		}

		wp_localize_script(
			'mike-editor',
			'mikeEditor',
			array(
				'metaType'          => MIKE_SINGLE_TYPE_META,
				'metaFeatured'      => MIKE_FEATURED_META,
				'metaHideTitle'     => MIKE_HIDE_PAGE_TITLE_META,
				'metaSubtitle'      => MIKE_SUBTITLE_META,
				'metaHeroSplitBg'   => MIKE_HERO_SPLIT_BG_META,
				'metaPageLayout'    => MIKE_PAGE_LAYOUT_META,
				'metaFlushTop'      => MIKE_PAGE_FLUSH_TOP_META,
				'metaFlushBottom'   => MIKE_PAGE_FLUSH_BOTTOM_META,
				'layoutChoices'     => $single_choices,
				'pageLayoutChoices' => $page_choices,
			)
		);
	}
endif;
add_action( 'enqueue_block_editor_assets', 'mike_editor_meta_assets' );
