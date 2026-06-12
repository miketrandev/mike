<?php
/**
 * Mike functions and definitions.
 *
 * TABLE OF CONTENTS
 * ------------------------------------------------
 * 1. Theme Setup
 * 2. Enqueue Styles
 * 3. Enqueue scripts (comment-reply.js + optional menu a11y.js)
 * 4. Icons               → inc/icons.php
 * 5. Template Tags       → inc/template-tags.php
 * 6. Template Functions  → inc/template-functions.php
 * 7. Admin               → inc/admin.php
 * 8. Customizer          → inc/customizer.php
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* THEME SETUP
------------------------------------------------ */

if ( ! function_exists( 'mike_setup' ) ) :
	function mike_setup() {
		
		// Automatic feed
		add_theme_support( 'automatic-feed-links' );
		
		// Set content-width
		global $content_width;
		if ( ! isset( $content_width ) ) $content_width = 600;
		
		// Post thumbnails
		add_theme_support( 'post-thumbnails' );
		set_post_thumbnail_size( 600, 9999 );

		// Custom logo
		add_theme_support( 'custom-logo', array(
			'flex-width'  => true,
			'flex-height' => true,
		) );
		
		// Title tag
		add_theme_support( 'title-tag' );
		
		// Register nav menus.
		register_nav_menus( array(
			'primary-menu' => __( 'Primary Menu', 'mike' ),
			'footer-menu'  => __( 'Footer Menu', 'mike' ),
		) );
		
		// Make the theme translation ready
		load_theme_textdomain( 'mike', get_template_directory() . '/languages' );

		// Add support for editor styles.
		add_theme_support( 'editor-styles' );
		add_editor_style( 'editor.css' );

		// Modern Gutenberg flags expected of every contemporary classic theme.
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'responsive-embeds' );
			add_theme_support( 'align-wide' );
		add_theme_support( 'html5', array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
			'navigation-widgets',
		) );
	}
	add_action( 'after_setup_theme', 'mike_setup' );
endif;

/* ENQUEUE STYLES
------------------------------------------------ */

if ( ! function_exists( 'mike_load_style' ) ) :
	function mike_load_style() {
		wp_enqueue_style( 'mike_style', get_stylesheet_uri(), [], wp_get_theme( 'mike' )->get( 'Version' ) );
	}
	add_action( 'wp_enqueue_scripts', 'mike_load_style' );
endif;


/* ENQUEUE SCRIPTS
------------------------------------------------ */

if ( ! function_exists( 'mike_load_scripts' ) ) :
	function mike_load_scripts() {

		// Core's comment-reply, only where threaded comments are in use.
		if ( ( ! is_admin() ) && is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}

		// Menu keyboard support (Esc-to-close + focus-trap). On by default;
		// turn off in Customize > Misc for a zero-JS-file build.
		if ( get_theme_mod( 'menu_a11y_js', true ) ) {
			wp_enqueue_script(
				'mike-a11y',
				get_template_directory_uri() . '/js/a11y.js',
				array(),
				wp_get_theme( 'mike' )->get( 'Version' ),
				true
			);
		}

	}
	add_action( 'wp_enqueue_scripts', 'mike_load_scripts' );
endif;

/* ICONS
------------------------------------------------ */

require get_template_directory() . '/inc/icons.php';

/* TEMPLATE TAGS
------------------------------------------------ */

require get_template_directory() . '/inc/template-tags.php';

/* TEMPLATE FUNCTIONS
------------------------------------------------ */

require get_template_directory() . '/inc/template-functions.php';

/* ADMIN
------------------------------------------------ */

require get_template_directory() . '/inc/admin.php';

/* CUSTOMIZER
------------------------------------------------ */

require get_template_directory() . '/inc/customizer.php';