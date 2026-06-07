<?php
/**
 * Typography — built on WordPress core's Font Library (WP 7.0+)
 * -----------------------------------------------------------------------------
 * Core does the heavy, fragile work: the Font Library admin page
 * (wp-admin/font-library.php, since 7.0) lets the user install fonts — from
 * Google (downloaded + SELF-HOSTED to wp-content/fonts, GDPR-clean) or by
 * uploading their own. Each install becomes a `wp_font_family` post with child
 * `wp_font_face` posts (file in _wp_font_face_file meta). We don't reimplement
 * any of that.
 *
 * What a CLASSIC theme must still do itself: core only auto-emits @font-face on
 * the front end from a block theme's theme.json, which Mike doesn't have. So we:
 *   1. read the installed wp_font_family posts → offer them in two customizer
 *      dropdowns (heading + body),
 *   2. emit @font-face for the chosen families + repoint the --heading-font /
 *      --body-font CSS tokens.
 *
 * Settings (inc/customizer.php): mike_heading_font, mike_body_font — each a
 * wp_font_family slug (post_name), or '' = system stack (the scss/_base.scss
 * default; fastest). An unknown/missing family → system fallback, never an error.
 *
 * Requires WP 7.0+ for the Font Library. On older WP the dropdowns simply show
 * "System default" only (mike_installed_font_families returns nothing useful).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mike_font_stack' ) ) :
	/**
	 * System-stack fallback for a category, appended after the web family so a
	 * missing/loading font still renders. Mirrors scss/_base.scss.
	 */
	function mike_font_stack( $category ) {
		switch ( $category ) {
			case 'serif':
				return 'Georgia, Times, serif';
			case 'monospace':
				return 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace';
			default:
				return "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif";
		}
	}
endif;

if ( ! function_exists( 'mike_font_quote' ) ) :
	/** Quote a multi-word family name for valid CSS. */
	function mike_font_quote( $name ) {
		return ( false !== strpos( $name, ' ' ) ) ? '"' . $name . '"' : $name;
	}
endif;

/* -----------------------------------------------------------------------------
   Read core's installed fonts (the wp_font_family / wp_font_face post types).
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_installed_font_families' ) ) :
	/**
	 * Every font installed via core's Font Library.
	 *
	 * @return array<string,array> slug => {
	 *     name   string             family name (post_title),
	 *     css    string             the CSS font-family value (incl. fallback),
	 *     faces  array<{src,weight,style}>  @font-face inputs
	 * }
	 * Memoized per request. Empty if the post type doesn't exist (pre-7.0) or
	 * nothing is installed.
	 */
	function mike_installed_font_families() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache = array();

		if ( ! post_type_exists( 'wp_font_family' ) ) {
			return $cache; // pre-7.0 / Font Library unavailable.
		}

		$families = get_posts( array(
			'post_type'      => 'wp_font_family',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		foreach ( $families as $family ) {
			$settings = json_decode( $family->post_content, true );
			$settings = is_array( $settings ) ? $settings : array();

			$name = $family->post_title;
			// CSS font-family value: prefer what core stored (it includes a sane
			// fallback), else quote the name + our own stack guess.
			if ( ! empty( $settings['fontFamily'] ) ) {
				$css = $settings['fontFamily'];
			} else {
				$css = mike_font_quote( $name ) . ', ' . mike_font_stack( '' );
			}

			$cache[ $family->post_name ] = array(
				'name'  => $name,
				'css'   => $css,
				'faces' => mike_font_family_faces( $family->ID ),
			);
		}

		return $cache;
	}
endif;

if ( ! function_exists( 'mike_font_family_faces' ) ) :
	/**
	 * The @font-face inputs for one family, from its child wp_font_face posts.
	 *
	 * @return array<{src:string,weight:string,style:string}>
	 */
	function mike_font_family_faces( $family_id ) {
		$face_posts = get_children( array(
			'post_parent'    => $family_id,
			'post_type'      => 'wp_font_face',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		) );

		$dir   = wp_get_font_dir();
		$base  = trailingslashit( $dir['url'] );
		$faces = array();

		foreach ( $face_posts as $face ) {
			$settings = json_decode( $face->post_content, true );
			if ( ! is_array( $settings ) || empty( $settings['src'] ) ) {
				continue;
			}
			$srcs = is_array( $settings['src'] ) ? $settings['src'] : array( $settings['src'] );
			$src  = (string) reset( $srcs );

			// Core stores src as a URL already; if it's a bare filename, resolve it
			// against the font dir. Only self-hosted (local) files are emitted.
			if ( 0 !== strpos( $src, 'http' ) && 0 !== strpos( $src, '//' ) ) {
				$src = $base . ltrim( $src, '/' );
			}

			$faces[] = array(
				'src'    => $src,
				'weight' => isset( $settings['fontWeight'] ) ? (string) $settings['fontWeight'] : '400',
				'style'  => isset( $settings['fontStyle'] ) ? (string) $settings['fontStyle'] : 'normal',
			);
		}

		return $faces;
	}
endif;

if ( ! function_exists( 'mike_font_choices' ) ) :
	/**
	 * Customizer dropdown choices: '' (system) + every installed family (slug =>
	 * name). Install happens in the Font Library page; this just lists results.
	 */
	function mike_font_choices() {
		$choices = array( '' => esc_html__( 'System default (fastest)', 'mike' ) );
		foreach ( mike_installed_font_families() as $slug => $family ) {
			$choices[ $slug ] = $family['name'];
		}
		return $choices;
	}
endif;

/* -----------------------------------------------------------------------------
   Resolve a role into a render plan. Unknown/missing slug → null (system).
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_font_active' ) ) :
	/**
	 * @param string $role 'heading' | 'body'
	 * @return array{slug:string,css:string,faces:array}|null
	 */
	function mike_font_active( $role ) {
		$role = ( 'body' === $role ) ? 'body' : 'heading';
		$slug = (string) get_theme_mod( 'mike_' . $role . '_font', '' );
		if ( '' === $slug ) {
			return null;
		}
		$families = mike_installed_font_families();
		if ( empty( $families[ $slug ]['faces'] ) ) {
			return null; // not installed (anymore) → system fallback.
		}
		return array(
			'slug'  => $slug,
			'css'   => $families[ $slug ]['css'],
			'faces' => $families[ $slug ]['faces'],
		);
	}
endif;

/* -----------------------------------------------------------------------------
   Emit: @font-face for chosen families + a :root token override.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_font_face_css' ) ) :
	/** @font-face rules for one family's faces (de-duped by weight+style). */
	function mike_font_face_css( $css_family, $faces ) {
		// The @font-face family-name is the first token of the CSS value (the web
		// family), unquoted for the descriptor.
		$name = trim( explode( ',', $css_family )[0] );
		$name = trim( $name, '"\'' );

		$out  = '';
		$seen = array();
		foreach ( $faces as $face ) {
			$key = $face['weight'] . '|' . $face['style'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out         .= sprintf(
				"@font-face{font-family:\"%s\";font-style:%s;font-weight:%s;font-display:swap;src:url(%s) format(\"woff2\");}\n",
				$name,
				preg_replace( '/[^a-z]/', '', strtolower( $face['style'] ) ) ?: 'normal',
				preg_replace( '/[^0-9 ]/', '', (string) $face['weight'] ) ?: '400',
				esc_url( $face['src'] )
			);
		}
		return $out;
	}
endif;

if ( ! function_exists( 'mike_font_inline_css' ) ) :
	/** All @font-face + :root token overrides. '' when both roles are system. */
	function mike_font_inline_css() {
		$heading = mike_font_active( 'heading' );
		$body    = mike_font_active( 'body' );
		if ( ! $heading && ! $body ) {
			return '';
		}

		$face = '';
		$root = '';
		if ( $heading ) {
			$face .= mike_font_face_css( $heading['css'], $heading['faces'] );
			$root .= '--heading-font:' . $heading['css'] . ';';
		}
		if ( $body ) {
			$face .= mike_font_face_css( $body['css'], $body['faces'] );
			$root .= '--body-font:' . $body['css'] . ';';
		}
		return $face . ':root{' . $root . '}';
	}
endif;

if ( ! function_exists( 'mike_font_enqueue' ) ) :
	/** Attach typography CSS to the main stylesheet (same channel as builder CSS). */
	function mike_font_enqueue() {
		$css = mike_font_inline_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'mike_style', $css );
		}
	}
	add_action( 'wp_enqueue_scripts', 'mike_font_enqueue', 20 );
endif;
