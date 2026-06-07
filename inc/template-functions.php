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
 * 1. mike_excerpt_more       - replace the trailing [...] with a "Read more" link
 * 2. mike_excerpt_length     - 32 words with a thumbnail, 48 without
 * 3. mike_archive_title      - drop the "Category:" / "Tag:" prefix
 * 4. mike_search_posts_only  - exclude pages from search results
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 1. mike_excerpt_more - replace the trailing [...] with a "Read more" link
------------------------------------------------ */

if ( ! function_exists( 'mike_excerpt_more' ) ) :
	function mike_excerpt_more( $more ) {
		return sprintf(
			'&hellip; <a class="more-link" href="%s">%s</a>',
			esc_url( get_permalink() ),
			esc_html__( 'Read more', 'mike' )
		);
	}
	add_filter( 'excerpt_more', 'mike_excerpt_more' );
endif;

/* 2. mike_excerpt_length - 32 words with a thumbnail, 48 without
------------------------------------------------ */

if ( ! function_exists( 'mike_excerpt_length' ) ) :
	function mike_excerpt_length( $length ) {
		// Posts without a thumbnail get a longer excerpt to fill the row.
		return 28;
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

/* 4. mike_search_posts_only - exclude pages from search results
------------------------------------------------ */

if ( ! function_exists( 'mike_search_posts_only' ) ) :
	function mike_search_posts_only( $query ) {
		// Only the front-end main search query — leave admin and sub-queries alone.
		if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
			$query->set( 'post_type', 'post' );
		}
	}
	add_action( 'pre_get_posts', 'mike_search_posts_only' );
endif;
