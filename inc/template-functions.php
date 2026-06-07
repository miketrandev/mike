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
 * 1. mike_excerpt_more     - replace the trailing [...] with an ellipsis
 * 2. mike_excerpt_length   - cap auto excerpts at 24 words
 * 3. mike_archive_title    - drop the "Category:" / "Tag:" prefix
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 1. mike_excerpt_more - replace the trailing [...] with an ellipsis
------------------------------------------------ */

if ( ! function_exists( 'mike_excerpt_more' ) ) :
	function mike_excerpt_more( $more ) {
		return '&hellip;';
	}
	add_filter( 'excerpt_more', 'mike_excerpt_more' );
endif;

/* 2. mike_excerpt_length - cap auto excerpts at 24 words
------------------------------------------------ */

if ( ! function_exists( 'mike_excerpt_length' ) ) :
	function mike_excerpt_length( $length ) {
		return 32;
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
