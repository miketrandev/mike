<?php
/**
 * Template functions — filters that tweak how WordPress behaves.
 *
 * Loaded from functions.php. These are not called directly in templates; they
 * hook into core filters. Each is wrapped in function_exists() so a child theme
 * can override it.
 *
 * TABLE OF CONTENTS
 * ------------------------------------------------
 * 1. mike_excerpt_more       - trailing ellipsis + optional "(more)" link
 * 2. mike_excerpt_length     - excerpt word count (Blog / Archive option)
 * 3. mike_archive_title      - drop the "Category:" / "Tag:" prefix
 * 4. mike_search_posts_only  - exclude pages from search results (Misc toggle)
 * 5. mike_classic_editor     - turn off the block editor (Misc toggle)
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 1. mike_excerpt_more - replace the trailing [...] with a "(more)" link
------------------------------------------------ */

if ( ! function_exists( 'mike_excerpt_more' ) ) :
	function mike_excerpt_more( $more ) {
		// The "(more)" link is optional (Blog / Archive customizer); the
		// ellipsis always closes the trimmed excerpt.
		if ( ! get_theme_mod( 'show_excerpt_more', true ) ) {
			return '&hellip;';
		}
		return sprintf(
			'&hellip; <a class="more-link" href="%s">%s</a>',
			esc_url( get_permalink() ),
			esc_html__( '(more)', 'mike' )
		);
	}
	add_filter( 'excerpt_more', 'mike_excerpt_more' );
endif;

/* 2. mike_excerpt_length - word count, set in the Blog / Archive customizer
------------------------------------------------ */

if ( ! function_exists( 'mike_excerpt_length' ) ) :
	function mike_excerpt_length( $length ) {
		return (int) get_theme_mod( 'excerpt_length', 20 );
	}
	add_filter( 'excerpt_length', 'mike_excerpt_length' );
endif;

/* 3. mike_archive_title - drop the "Category:" / "Tag:" prefix
------------------------------------------------ */

if ( ! function_exists( 'mike_archive_title' ) ) :
	function mike_archive_title( $title ) {
		// Strip the leading "Label: " that core prepends.
		return preg_replace( '/^[^:]+:\s*/', '', $title );
	}
	add_filter( 'get_the_archive_title', 'mike_archive_title' );
endif;

/* 4. mike_search_posts_only - exclude pages from search results (Misc toggle)
------------------------------------------------ */

if ( ! function_exists( 'mike_search_posts_only' ) ) :
	function mike_search_posts_only( $query ) {
		// Opt-out via the Misc customizer toggle (on by default).
		if ( ! get_theme_mod( 'disable_pages_in_search', true ) ) {
			return;
		}
		// Only the front-end main search query — leave admin and sub-queries alone.
		if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
			$query->set( 'post_type', 'post' );
		}
	}
	add_action( 'pre_get_posts', 'mike_search_posts_only' );
endif;

/* 5. mike_classic_editor - turn off the block editor (Misc toggle)
------------------------------------------------ */

if ( ! function_exists( 'mike_classic_editor' ) ) :
	function mike_classic_editor( $use_block_editor, $post_type ) {
		// Off by default; when the Misc toggle is on, force the classic editor.
		if ( get_theme_mod( 'use_classic_editor', false ) ) {
			return false;
		}
		return $use_block_editor;
	}
	add_filter( 'use_block_editor_for_post_type', 'mike_classic_editor', 10, 2 );
endif;
