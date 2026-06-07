<?php
/**
 * Footer — element template tags + inline-CSS emitter.
 *
 * Mirrors inc/header.php's role for the footer band. Each template tag RETURNS
 * a string (no echo) so footer.php composes the bands top-down without
 * ob/echo dance.
 *
 * TOC:
 *   1. mike_footer_branding()        → '<span class="footer-brand …"><a class="footer-brand__link">…</a></span>'
 *   2. mike_footer_menu()            → '<nav class="footer-menu-nav"><ul class="menu footer-menu">…</ul></nav>' | ''
 *   3. mike_footer_inline_css()      → ':root{--footer-bg:…;--footer-text:…;}' or '' (uses mike_contrast_text() from inc/helper.php)
 *   4. mike_footer_enqueue_inline()  → hooks #3 into wp_enqueue_scripts so the inline style ships with style.css
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/* 1. mike_footer_branding()
   ---------------------------------------------------------------------------
   Footer logo if set, else site title — always a plain linked <span> (NEVER
   <h1>, unlike the header, so we don't emit a second front-page h1). Uses a
   DEDICATED footer logo (often a mono/white mark for a dark footer), with an
   optional dark-mode variant that swaps under .is-dark via CSS. */

if ( ! function_exists( 'mike_footer_branding' ) ) :
	function mike_footer_branding() {
		$light = esc_url( get_theme_mod( 'footer_logo', '' ) );
		$dark  = esc_url( get_theme_mod( 'footer_logo_dark', '' ) );
		$home  = esc_url( home_url( '/' ) );
		$name  = get_bloginfo( 'name' );

		if ( '' !== $light ) {
			$class = 'footer-brand footer-brand--image';
			$img   = sprintf( '<img class="footer-logo footer-logo--light" src="%s" alt="%s" />', $light, esc_attr( $name ) );
			if ( '' !== $dark ) {
				$img   .= sprintf( '<img class="footer-logo footer-logo--dark" src="%s" alt="%s" />', $dark, esc_attr( $name ) );
				$class .= ' footer-brand--has-dark';
			}
			$inner = $img;
		} else {
			$class = 'footer-brand footer-brand--text';
			$inner = esc_html( $name );
		}

		return sprintf(
			'<span class="%s"><a class="footer-brand__link" href="%s" rel="home">%s</a></span>',
			esc_attr( $class ),
			$home,
			$inner // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url/esc_html/esc_attr applied above
		);
	}
endif;


/* 2. mike_footer_menu()
   ---------------------------------------------------------------------------
   Legal/utility links (Terms, About, Contact, Ads…) for the footer bottom.
   Returns '' when no menu is assigned to the 'footer-menu' location, so the
   footer bottom shows nothing rather than a page dump. depth 1 — flat row. */

if ( ! function_exists( 'mike_footer_menu' ) ) :
	function mike_footer_menu() {
		if ( ! has_nav_menu( 'footer-menu' ) ) {
			return '';
		}
		return (string) wp_nav_menu( array(
			'theme_location'       => 'footer-menu',
			'menu_class'           => 'menu footer-menu',
			'container'            => 'nav',
			'container_class'      => 'footer-menu-nav',
			'container_aria_label' => esc_attr__( 'Footer links', 'mike' ),
			'depth'                => 1,
			'fallback_cb'          => false,
			'echo'                 => false,
		) );
	}
endif;


/* 3. mike_footer_inline_css()
   ---------------------------------------------------------------------------
   Single bg picker, auto-derived text colour for contrast. Mirrors the
   per-band scheme in inc/header.php → mike_header_inline_css(). When the
   editor sets a bg, the matching text colour is computed via the shared
   mike_contrast_text() in inc/helper.php.

   Tokens emitted (only when the editor set a bg):
     --footer-bg / --footer-text */

if ( ! function_exists( 'mike_footer_inline_css' ) ) :
	function mike_footer_inline_css() {
		$bg = trim( (string) get_theme_mod( 'footer_bg', '' ) );
		if ( '' === $bg ) {
			return '';
		}
		$text = mike_contrast_text( $bg );
		if ( '' === $text ) {
			return ''; // Bad hex — don't half-emit one without the other.
		}
		return ':root{--footer-bg:' . $bg . ';--footer-text:' . $text . ';}';
	}
endif;


/* 4. mike_footer_enqueue_inline() — hook #3 onto wp_enqueue_scripts. */

if ( ! function_exists( 'mike_footer_enqueue_inline' ) ) :
	function mike_footer_enqueue_inline() {
		$css = mike_footer_inline_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'mike_style', $css );
		}
	}
	add_action( 'wp_enqueue_scripts', 'mike_footer_enqueue_inline', 20 );
endif;
