<?php
/**
 * Featured posts — the editorial loop.
 * -----------------------------------------------------------------------------
 * Editors mark a post as "featured" from the post editor (a metabox, via the
 * metabox mini-framework) OR with a one-click star in the posts list. The
 * homepage builder's Query source "Featured" then reads these.
 *
 * Stored as post meta `_mike_featured` = '1'. This is a curated shortcut over a
 * raw meta query — it exists because "star a post" matches the editor's mental
 * model better than "set up a tag and filter by it" (see philosophy.md).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MIKE_FEATURED_META = '_mike_featured';

/* -----------------------------------------------------------------------------
   1. The "Feature this post" control + the Layout radio live INLINE in the block
   editor's Status panel — see inc/editor-meta.php (registers the meta + the
   no-build editor.js). MIKE_FEATURED_META is registered there with show_in_rest.
   This file owns the posts-LIST star column + the one-click admin-post toggle.
----------------------------------------------------------------------------- */

/* -----------------------------------------------------------------------------
   2. Posts-list star column + one-click toggle.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_featured_column' ) ) :
	function mike_featured_column( $columns ) {
		// Insert a compact star column after the title.
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['mike_featured'] = esc_html__( 'Featured', 'mike' );
			}
		}
		return $out;
	}
endif;
add_filter( 'manage_post_posts_columns', 'mike_featured_column' );

if ( ! function_exists( 'mike_featured_column_content' ) ) :
	function mike_featured_column_content( $column, $post_id ) {
		if ( 'mike_featured' !== $column ) {
			return;
		}
		$on  = '1' === get_post_meta( $post_id, MIKE_FEATURED_META, true );
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=mike_toggle_featured&post=' . $post_id ),
			'mike_toggle_featured_' . $post_id
		);
		printf(
			'<a href="%1$s" class="mike-star %2$s" aria-label="%3$s" title="%3$s">%4$s</a>',
			esc_url( $url ),
			$on ? 'is-on' : '',
			esc_attr( $on ? __( 'Unfeature', 'mike' ) : __( 'Feature', 'mike' ) ),
			$on ? '&#9733;' : '&#9734;' // ★ / ☆
		);
	}
endif;
add_action( 'manage_post_posts_custom_column', 'mike_featured_column_content', 10, 2 );

if ( ! function_exists( 'mike_toggle_featured' ) ) :
	function mike_toggle_featured() {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

		if ( ! $post_id
			|| ! current_user_can( 'edit_post', $post_id )
			|| ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'mike_toggle_featured_' . $post_id ) ) {
			wp_die( esc_html__( 'Unable to update featured status.', 'mike' ) );
		}

		if ( '1' === get_post_meta( $post_id, MIKE_FEATURED_META, true ) ) {
			delete_post_meta( $post_id, MIKE_FEATURED_META );
		} else {
			update_post_meta( $post_id, MIKE_FEATURED_META, '1' );
		}

		// Back to where the editor clicked from.
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'edit.php' ) );
		exit;
	}
endif;
add_action( 'admin_post_mike_toggle_featured', 'mike_toggle_featured' );

if ( ! function_exists( 'mike_featured_column_style' ) ) :
	function mike_featured_column_style() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-post' !== $screen->id ) {
			return;
		}
		echo '<style>
			.column-mike_featured { width: 70px; text-align: center; }
			.mike-star { text-decoration: none; font-size: 18px; color: #c3c4c7; }
			.mike-star.is-on { color: #f0b849; }
		</style>';
	}
endif;
add_action( 'admin_head', 'mike_featured_column_style' );
