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
 *
 * CONTENT (sections + options)
 *   6. mike_customize_register - Typography + Footer sections and their controls
 *
 * Settings read elsewhere: mike_heading_font, mike_body_font (footer.php reads
 * mike_show_copyright_prefix, mike_copyright, mike_keep_credit).
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
			'heading' => get_theme_mod( 'mike_heading_font', '' ),
			'body'    => get_theme_mod( 'mike_body_font', '' ),
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

/* =============================================================================
   CONTENT (sections + options)
============================================================================= */

/* 6. mike_customize_register - Typography + Footer sections and their controls
------------------------------------------------ */

if ( ! function_exists( 'mike_customize_register' ) ) :
	function mike_customize_register( $wp_customize ) {

		/* Site Identity (core section) — show/hide title + tagline
		------------------- */
		$wp_customize->add_setting( 'mike_show_title', array(
			'default'           => true,
			'sanitize_callback' => 'mike_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'mike_show_title', array(
			'type'    => 'checkbox',
			'section' => 'title_tagline',
			'label'   => esc_html__( 'Display site title', 'mike' ),
		) );

		$wp_customize->add_setting( 'mike_show_tagline', array(
			'default'           => true,
			'sanitize_callback' => 'mike_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'mike_show_tagline', array(
			'type'    => 'checkbox',
			'section' => 'title_tagline',
			'label'   => esc_html__( 'Display tagline', 'mike' ),
		) );

		/* Typography
		------------------- */
		$wp_customize->add_section( 'mike_typography', array(
			'title'       => esc_html__( 'Typography', 'mike' ),
			'priority'    => 40,
			'description' => esc_html__( 'Install fonts under Appearance > Fonts, then pick them here.', 'mike' ),
		) );

		$wp_customize->add_setting( 'mike_heading_font', array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'mike_heading_font', array(
			'type'    => 'select',
			'section' => 'mike_typography',
			'label'   => esc_html__( 'Heading font', 'mike' ),
			'choices' => mike_font_choices(),
		) );

		$wp_customize->add_setting( 'mike_body_font', array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'mike_body_font', array(
			'type'    => 'select',
			'section' => 'mike_typography',
			'label'   => esc_html__( 'Body font', 'mike' ),
			'choices' => mike_font_choices(),
		) );

		/* Footer
		------------------- */
		$wp_customize->add_section( 'mike_footer', array(
			'title'    => esc_html__( 'Footer', 'mike' ),
			'priority' => 90,
		) );

		// Prefix: "© YEAR Site name".
		$wp_customize->add_setting( 'mike_show_copyright_prefix', array(
			'default'           => true,
			'sanitize_callback' => 'mike_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'mike_show_copyright_prefix', array(
			'type'    => 'checkbox',
			'section' => 'mike_footer',
			'label'   => esc_html__( 'Show © year + site name', 'mike' ),
		) );

		// Main copyright line.
		$wp_customize->add_setting( 'mike_copyright', array(
			'default'           => esc_html__( 'All rights reserved.', 'mike' ),
			'sanitize_callback' => 'wp_kses_post',
		) );
		$wp_customize->add_control( 'mike_copyright', array(
			'type'    => 'textarea',
			'section' => 'mike_footer',
			'label'   => esc_html__( 'Copyright text', 'mike' ),
		) );

		// Theme credit ("made with ♥ by Mike").
		$wp_customize->add_setting( 'mike_keep_credit', array(
			'default'           => true,
			'sanitize_callback' => 'mike_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'mike_keep_credit', array(
			'type'    => 'checkbox',
			'section' => 'mike_footer',
			'label'   => esc_html__( 'Keep theme credit', 'mike' ),
		) );
	}
	add_action( 'customize_register', 'mike_customize_register' );
endif;
