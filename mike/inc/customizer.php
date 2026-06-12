<?php
/**
 * Customizer — sections, options, and the few helpers they need.
 *
 * Built straight on WP's add_section / add_setting / add_control — no registry
 * framework (we only have two sections). Helpers the options depend on sit at
 * the TOP; the section + option registrations sit at the BOTTOM.
 *
 * Fonts use core's Font Library (WP 7.0+): the user installs families under
 * Appearance > Fonts; we list them in two dropdowns and emit @font-face +
 * :root token overrides for the chosen ones.
 *
 * TABLE OF CONTENTS
 * ------------------------------------------------
 * FRAMEWORK (helpers)
 *   1. mike_font_choices       - dropdown list: System default + installed families
 *   2. mike_installed_fonts    - read core's wp_font_family / wp_font_face posts
 *   3. mike_font_inline_css    - @font-face + :root tokens for the chosen fonts
 *   4. mike_font_enqueue       - attach that CSS to the stylesheet
 *   5. mike_sanitize_checkbox  - true | false sanitizer for checkboxes
 *   6. mike_color_inline_css   - :root token overrides + ::selection (only if set)
 *   7. mike_color_enqueue      - attach the color overrides to the stylesheet
 *
 * CONTENT (sections + options)
 *   8. mike_customize_register - Typography, Style, Blog/Archive, Single, Footer
 *
 * Theme-mod keys carry NO mike_ prefix — get_theme_mod() is already per-theme,
 * so the names can't collide. Settings read elsewhere: heading_font, body_font
 * (font CSS); the Style colors (emitted by mike_color_inline_css above); the
 * archive parts (show_excerpt|categories|date|thumbnail|comment_count) in
 * content.php + mike_entry_meta, and excerpt_length / show_excerpt_more in
 * template-functions.php; the single parts (show_tags|comments in singular.php;
 * single_show_categories|date|comment_count in mike_entry_meta); the Misc
 * toggle (disable_pages_in_search)
 * in template-functions.php; menu_a11y_js in functions.php (enqueue) +
 * header.php (aria-modal); footer.php reads show_copyright_prefix, copyright,
 * keep_credit.
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =============================================================================
   FRAMEWORK (helpers)
============================================================================= */

/* 1. mike_font_choices - dropdown list: System default + installed families
------------------------------------------------ */

if ( ! function_exists( 'mike_font_choices' ) ) :
	function mike_font_choices() {
		$choices = array( '' => esc_html__( 'System default (fastest)', 'mike' ) );
		foreach ( mike_installed_fonts() as $slug => $font ) {
			$choices[ $slug ] = $font['name'];
		}
		return $choices;
	}
endif;

/* 2. mike_installed_fonts - read core's wp_font_family / wp_font_face posts
------------------------------------------------ */

if ( ! function_exists( 'mike_installed_fonts' ) ) :
	// Returns slug => array{ name, css, faces[] }. Empty before WP 7.0.
	function mike_installed_fonts() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache = array();

		if ( ! post_type_exists( 'wp_font_family' ) ) {
			return $cache; // Font Library is WP 7.0+.
		}

		$families = get_posts( array(
			'post_type'      => 'wp_font_family',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$dir  = wp_get_font_dir();
		$base = trailingslashit( $dir['url'] );

		foreach ( $families as $family ) {
			$settings = json_decode( $family->post_content, true );
			$settings = is_array( $settings ) ? $settings : array();

			// CSS font-family value: prefer what core stored (has a fallback).
			$name = $family->post_title;
			$css  = ! empty( $settings['fontFamily'] ) ? $settings['fontFamily'] : '"' . $name . '"';

			// Child wp_font_face posts hold the actual files.
			$faces      = array();
			$face_posts = get_children( array(
				'post_parent'    => $family->ID,
				'post_type'      => 'wp_font_face',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			) );
			foreach ( $face_posts as $face ) {
				$fs = json_decode( $face->post_content, true );
				if ( ! is_array( $fs ) || empty( $fs['src'] ) ) {
					continue;
				}
				$srcs = is_array( $fs['src'] ) ? $fs['src'] : array( $fs['src'] );
				$src  = (string) reset( $srcs );
				// Resolve a bare filename against the font dir.
				if ( 0 !== strpos( $src, 'http' ) && 0 !== strpos( $src, '//' ) ) {
					$src = $base . ltrim( $src, '/' );
				}
				$faces[] = array(
					'src'    => $src,
					'weight' => isset( $fs['fontWeight'] ) ? (string) $fs['fontWeight'] : '400',
					'style'  => isset( $fs['fontStyle'] ) ? (string) $fs['fontStyle'] : 'normal',
				);
			}

			$cache[ $family->post_name ] = array(
				'name'  => $name,
				'css'   => $css,
				'faces' => $faces,
			);
		}

		return $cache;
	}
endif;

/* 3. mike_font_inline_css - @font-face + :root tokens for the chosen fonts
------------------------------------------------ */

if ( ! function_exists( 'mike_font_inline_css' ) ) :
	function mike_font_inline_css() {
		$fonts = mike_installed_fonts();

		// Resolve each role's chosen slug to an installed family (or null = system).
		$roles = array(
			'heading' => get_theme_mod( 'heading_font', '' ),
			'body'    => get_theme_mod( 'body_font', '' ),
		);

		$face = '';
		$root = '';
		foreach ( $roles as $role => $slug ) {
			if ( '' === $slug || empty( $fonts[ $slug ]['faces'] ) ) {
				continue; // system fallback.
			}
			$font = $fonts[ $slug ];

			// @font-face name = the web family (first token of the CSS value).
			$family = trim( explode( ',', $font['css'] )[0] );
			$family = trim( $family, '"\'' );

			$seen = array();
			foreach ( $font['faces'] as $f ) {
				$key = $f['weight'] . '|' . $f['style'];
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$face        .= sprintf(
					'@font-face{font-family:"%s";font-style:%s;font-weight:%s;font-display:swap;src:url(%s) format("woff2");}',
					$family,
					preg_replace( '/[^a-z]/', '', strtolower( $f['style'] ) ) ?: 'normal',
					preg_replace( '/[^0-9 ]/', '', (string) $f['weight'] ) ?: '400',
					esc_url( $f['src'] )
				);
			}
			$root .= '--' . $role . '-font:' . $font['css'] . ';';
		}

		return $root ? $face . ':root{' . $root . '}' : '';
	}
endif;

/* 4. mike_font_enqueue - attach that CSS to the stylesheet
------------------------------------------------ */

if ( ! function_exists( 'mike_font_enqueue' ) ) :
	function mike_font_enqueue() {
		$css = mike_font_inline_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'mike_style', $css );
		}
	}
	add_action( 'wp_enqueue_scripts', 'mike_font_enqueue', 20 );
endif;

/* 5. mike_sanitize_checkbox - true | false sanitizer for checkboxes
------------------------------------------------ */

if ( ! function_exists( 'mike_sanitize_checkbox' ) ) :
	function mike_sanitize_checkbox( $value ) {
		return (bool) $value;
	}
endif;

/* 6. mike_color_inline_css - :root token overrides + ::selection for Style
------------------------------------------------ */

if ( ! function_exists( 'mike_color_inline_css' ) ) :
	// Empty mods = theme default (no CSS emitted for that color).
	function mike_color_inline_css() {
		// These override :root tokens.
		$map = array(
			'text_color'   => '--text',
			'accent_color' => '--accent',
			'bg_color'     => '--bg',
		);
		$root = '';
		foreach ( $map as $setting => $token ) {
			$value = get_theme_mod( $setting, '' );
			if ( '' !== $value ) {
				$root .= $token . ':' . $value . ';';
			}
		}
		$css = $root ? ':root{' . $root . '}' : '';

		// Selection colors get a real ::selection rule, and ONLY when set — so
		// the browser default highlight stays intact otherwise.
		$sel    = '';
		$sel_bg = get_theme_mod( 'selection_bg_color', '' );
		$sel_fg = get_theme_mod( 'selection_text_color', '' );
		if ( '' !== $sel_bg ) {
			$sel .= 'background:' . $sel_bg . ';';
		}
		if ( '' !== $sel_fg ) {
			$sel .= 'color:' . $sel_fg . ';';
		}
		if ( '' !== $sel ) {
			$css .= '::selection{' . $sel . '}';
		}

		return $css;
	}
endif;

/* 7. mike_color_enqueue - attach the color overrides to the stylesheet
------------------------------------------------ */

if ( ! function_exists( 'mike_color_enqueue' ) ) :
	function mike_color_enqueue() {
		$css = mike_color_inline_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'mike_style', $css );
		}
	}
	add_action( 'wp_enqueue_scripts', 'mike_color_enqueue', 20 );
endif;

/* =============================================================================
   CONTENT (sections + options)
============================================================================= */

/* 8. mike_customize_register - Typography, Style, Blog/Archive, Single, Footer
------------------------------------------------ */

if ( ! function_exists( 'mike_customize_register' ) ) :
	function mike_customize_register( $wp_customize ) {

		/* Site Identity (core section) — show/hide title + tagline
		------------------- */
		$wp_customize->add_setting( 'show_title', array(
			'default'           => true,
			'sanitize_callback' => 'mike_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'show_title', array(
			'type'    => 'checkbox',
			'section' => 'title_tagline',
			'label'   => esc_html__( 'Display site title', 'mike' ),
		) );

		$wp_customize->add_setting( 'show_tagline', array(
			'default'           => true,
			'sanitize_callback' => 'mike_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'show_tagline', array(
			'type'    => 'checkbox',
			'section' => 'title_tagline',
			'label'   => esc_html__( 'Display tagline', 'mike' ),
		) );

		/* Typography
		------------------- */
		// Priorities 130-155 land all our sections after the core Menus (100) and
		// Homepage Settings (120) sections, but before Additional CSS (200).
		$wp_customize->add_section( 'mike_typography', array(
			'title'       => esc_html__( 'Typography', 'mike' ),
			'priority'    => 130,
			'description' => esc_html__( 'Install fonts under Appearance > Fonts, then pick them here.', 'mike' ),
		) );

		$wp_customize->add_setting( 'heading_font', array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'heading_font', array(
			'type'    => 'select',
			'section' => 'mike_typography',
			'label'   => esc_html__( 'Heading font', 'mike' ),
			'choices' => mike_font_choices(),
		) );

		$wp_customize->add_setting( 'body_font', array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'body_font', array(
			'type'    => 'select',
			'section' => 'mike_typography',
			'label'   => esc_html__( 'Body font', 'mike' ),
			'choices' => mike_font_choices(),
		) );

		/* Style — colors (override the :root tokens; empty = theme default)
		------------------- */
		$wp_customize->add_section( 'mike_style', array(
			'title'    => esc_html__( 'Style', 'mike' ),
			'priority' => 135,
		) );

		$mike_colors = array(
			'text_color'           => esc_html__( 'Text color', 'mike' ),
			'accent_color'         => esc_html__( 'Accent color', 'mike' ),
			'bg_color'             => esc_html__( 'Background color', 'mike' ),
			'selection_bg_color'   => esc_html__( 'Selection color', 'mike' ),
			'selection_text_color' => esc_html__( 'Selected text color', 'mike' ),
		);
		foreach ( $mike_colors as $mike_id => $mike_label ) {
			$wp_customize->add_setting( $mike_id, array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_hex_color',
			) );
			$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $mike_id, array(
				'section' => 'mike_style',
				'label'   => $mike_label,
			) ) );
		}

		/* Blog / Archive — show/hide parts of each post in the listing
		------------------- */
		$wp_customize->add_section( 'mike_blog', array(
			'title'    => esc_html__( 'Blog / Archive', 'mike' ),
			'priority' => 140,
		) );

		$mike_blog_options = array(
			'show_excerpt'       => esc_html__( 'Show excerpt', 'mike' ),
			'show_excerpt_more'  => esc_html__( 'Show excerpt "(more)" link', 'mike' ),
			'show_categories'    => esc_html__( 'Show categories', 'mike' ),
			'show_date'          => esc_html__( 'Show date', 'mike' ),
			'show_thumbnail'     => esc_html__( 'Show thumbnail', 'mike' ),
			'show_comment_count' => esc_html__( 'Show comment count', 'mike' ),
		);
		foreach ( $mike_blog_options as $mike_id => $mike_label ) {
			$wp_customize->add_setting( $mike_id, array(
				'default'           => true,
				'sanitize_callback' => 'mike_sanitize_checkbox',
			) );
			$wp_customize->add_control( $mike_id, array(
				'type'    => 'checkbox',
				'section' => 'mike_blog',
				'label'   => $mike_label,
			) );
		}

		// Excerpt length, in words.
		$wp_customize->add_setting( 'excerpt_length', array(
			'default'           => 20,
			'sanitize_callback' => 'absint',
		) );
		$wp_customize->add_control( 'excerpt_length', array(
			'type'        => 'number',
			'section'     => 'mike_blog',
			'label'       => esc_html__( 'Excerpt length (words)', 'mike' ),
			'input_attrs' => array( 'min' => 5, 'max' => 200, 'step' => 1 ),
		) );

		/* Single — show/hide parts below a single post
		------------------- */
		$wp_customize->add_section( 'mike_single', array(
			'title'    => esc_html__( 'Single Post', 'mike' ),
			'priority' => 145,
		) );

		$mike_single_options = array(
			'single_show_categories'    => esc_html__( 'Show category (top)', 'mike' ),
			'single_show_date'          => esc_html__( 'Show date (top)', 'mike' ),
			'single_show_comment_count' => esc_html__( 'Show comment count (top)', 'mike' ),
			'show_tags'                 => esc_html__( 'Show tags', 'mike' ),
			'show_comments'             => esc_html__( 'Show comments', 'mike' ),
		);
		foreach ( $mike_single_options as $mike_id => $mike_label ) {
			$wp_customize->add_setting( $mike_id, array(
				'default'           => true,
				'sanitize_callback' => 'mike_sanitize_checkbox',
			) );
			$wp_customize->add_control( $mike_id, array(
				'type'    => 'checkbox',
				'section' => 'mike_single',
				'label'   => $mike_label,
			) );
		}

		/* Misc — behavior toggles (read in inc/template-functions.php)
		------------------- */
		$wp_customize->add_section( 'mike_misc', array(
			'title'    => esc_html__( 'Misc', 'mike' ),
			'priority' => 155,
		) );

		// Exclude pages from search results (off by default).
		$wp_customize->add_setting( 'disable_pages_in_search', array(
			'default'           => false,
			'sanitize_callback' => 'mike_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'disable_pages_in_search', array(
			'type'    => 'checkbox',
			'section' => 'mike_misc',
			'label'   => esc_html__( 'Disable pages in search results', 'mike' ),
		) );

		// Mobile-menu keyboard support: Esc-to-close + focus-trap (loads js/a11y.js).
		// On by default; off = no JS file and the menu drops its aria-modal claim.
		$wp_customize->add_setting( 'menu_a11y_js', array(
			'default'           => true,
			'sanitize_callback' => 'mike_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'menu_a11y_js', array(
			'type'        => 'checkbox',
			'section'     => 'mike_misc',
			'label'       => esc_html__( 'Enhanced menu keyboard support', 'mike' ),
			'description' => esc_html__( 'Adds Esc-to-close and focus-trapping to the mobile menu (loads a tiny script).', 'mike' ),
		) );

		/* Footer
		------------------- */
		$wp_customize->add_section( 'mike_footer', array(
			'title'    => esc_html__( 'Footer', 'mike' ),
			'priority' => 150,
		) );

		// Prefix: "© YEAR Site name".
		$wp_customize->add_setting( 'show_copyright_prefix', array(
			'default'           => true,
			'sanitize_callback' => 'mike_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'show_copyright_prefix', array(
			'type'    => 'checkbox',
			'section' => 'mike_footer',
			'label'   => esc_html__( 'Show © + year', 'mike' ),
		) );

		// Main copyright line.
		$wp_customize->add_setting( 'copyright', array(
			'default'           => '',
			'sanitize_callback' => 'wp_kses_post',
		) );
		$wp_customize->add_control( 'copyright', array(
			'type'    => 'textarea',
			'section' => 'mike_footer',
			'label'   => esc_html__( 'Copyright text', 'mike' ),
		) );

		// Theme credit
		$wp_customize->add_setting( 'keep_credit', array(
			'default'           => true,
			'sanitize_callback' => 'mike_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'keep_credit', array(
			'type'    => 'checkbox',
			'section' => 'mike_footer',
			'label'   => esc_html__( 'Keep theme credit', 'mike' ),
		) );
	}
	add_action( 'customize_register', 'mike_customize_register' );
endif;
