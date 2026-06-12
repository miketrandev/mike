<?php
/**
 * Template tags — presentational helpers used across theme templates.
 *
 * Loaded from functions.php. Each is wrapped in function_exists() so a child
 * theme can override it.
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mike_header_primary_menu' ) ) :
	/**
	 * Primary nav, depth 4. Returns '' when no menu is assigned — the bar shows
	 * nothing rather than a page-dump fallback (that's the off-canvas's job).
	 * Memoized via static $html.
	 */
	function mike_header_primary_menu() {
		static $html = null;
		if ( null !== $html ) {
			return $html;
		}
		if ( ! has_nav_menu( 'primary-menu' ) ) {
			$html = '';
			return $html;
		}
		$html = wp_nav_menu( array(
			'theme_location'       => 'primary-menu',
			'menu_id'              => 'menu-menu', // matches the header skip-link target.
			'menu_class'           => 'menu primary-menu',
			'container'            => 'nav',
			'container_class'      => 'primary-nav',
			'container_aria_label' => esc_attr__( 'Primary menu', 'mike' ),
			'depth'                => 4,
			'fallback_cb'          => false,
			'echo'                 => false,
		) );
		return $html;
	}
endif;

if ( ! function_exists( 'mike_offcanvas_menu' ) ) :
	/**
	 * The same primary menu, rendered stacked for the off-canvas panel. Returns ''
	 * when no menu is assigned. Memoized via static $html.
	 */
	function mike_offcanvas_menu() {
		static $html = null;
		if ( null !== $html ) {
			return $html;
		}
		if ( ! has_nav_menu( 'primary-menu' ) ) {
			$html = '';
			return $html;
		}
		$html = wp_nav_menu( array(
			'theme_location'       => 'primary-menu',
			'menu_class'           => 'menu offcanvas-menu',
			'container'            => 'nav',
			'container_class'      => 'offcanvas-nav',
			'container_aria_label' => esc_attr__( 'Mobile menu', 'mike' ),
			'depth'                => 4,
			'fallback_cb'          => false,
			'echo'                 => false,
		) );
		return $html;
	}
endif;

if ( ! function_exists( 'mike_single_tags' ) ) :
	/** The tag list below the content. Suppresses off a type without tags / when none. */
	function mike_single_tags( $post_id = null ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		if ( ! is_object_in_taxonomy( get_post_type( $post_id ), 'post_tag' ) ) {
			return;
		}
		$tags = get_the_tags( $post_id );
		if ( empty( $tags ) || is_wp_error( $tags ) ) {
			return;
		}
		echo '<nav class="entry-tags" aria-label="' . esc_attr__( 'Tags', 'mike' ) . '">';
		echo '<span class="entry-tags__label" aria-hidden="true">' . esc_html__( 'Tags:', 'mike' ) . '</span> ';
		foreach ( $tags as $tag ) {
			$link = get_tag_link( $tag );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			printf(
				'<a class="entry-tag" href="%s">%s</a>',
				esc_url( $link ),
				esc_html( $tag->name )
			);
		}
		echo '</nav>';
	}
endif;

if ( ! function_exists( 'mike_entry_categories' ) ) :
	/** The category list above the title. Suppresses off a type without categories / when none. */
	function mike_entry_categories( $post_id = null ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		if ( ! is_object_in_taxonomy( get_post_type( $post_id ), 'category' ) ) {
			return;
		}
		$sep  = '<span class="entry-categories-sep">&middot;</span>';
		$list = get_the_category_list( $sep, '', $post_id );
		if ( ! $list ) {
			return;
		}
		echo '<nav class="entry-categories" aria-label="' . esc_attr__( 'Categories', 'mike' ) . '">';
		echo $list; // phpcs:ignore WordPress.Security.EscapeOutput -- core builds escaped links.
		echo '</nav>';
	}
endif;

if ( ! function_exists( 'mike_entry_date' ) ) :
	/** The published date, in a <time> with a machine-readable datetime. */
	function mike_entry_date( $post_id = null ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		printf(
			'<time class="entry-date" datetime="%s">%s</time>',
			esc_attr( get_the_date( DATE_W3C, $post_id ) ),
			esc_html( get_the_date( '', $post_id ) )
		);
	}
endif;

if ( ! function_exists( 'mike_entry_comments' ) ) :
	/**
	 * Comment icon + count, linking to the comments. Hidden when there are none.
	 * Archive uses the Blog / Archive "Show comment count" toggle; single uses
	 * the Single Post one — both default on.
	 */
	function mike_entry_comments( $post_id = null ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		$mod = is_singular() ? 'single_show_comment_count' : 'show_comment_count';
		if ( ! get_theme_mod( $mod, true ) ) {
			return;
		}
		$count = (int) get_comments_number( $post_id );
		if ( $count < 1 ) {
			return;
		}
		printf(
			'<a class="entry-comments" href="%s">',
			esc_url( get_comments_link( $post_id ) )
		);
		mike_icon( 'comment', array( 'size' => 15 ) );
		echo '<span class="entry-comments__count">' . esc_html( number_format_i18n( $count ) ) . '</span>';
		echo '</a>';
	}
endif;

if ( ! function_exists( 'mike_entry_meta' ) ) :
	/**
	 * Categories + date + comment count wrapper above the title. Posts only.
	 * Archive and single each have their own categories/date/comment toggles
	 * (Blog / Archive vs Single Post customizer sections); all default on.
	 */
	function mike_entry_meta( $post_id = null ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		if ( 'post' !== get_post_type( $post_id ) ) {
			return;
		}
		$is_single = is_singular();
		$show_cats = get_theme_mod( $is_single ? 'single_show_categories' : 'show_categories', true );
		$show_date = get_theme_mod( $is_single ? 'single_show_date' : 'show_date', true );
		echo '<div class="entry-meta">';
		if ( $show_cats ) {
			mike_entry_categories( $post_id );
		}
		if ( $show_date ) {
			mike_entry_date( $post_id );
		}
		mike_entry_comments( $post_id );
		echo '</div>';
	}
endif;

if ( ! function_exists( 'mike_get_thumbnail' ) ) :
	/**
	 * Resolve a post's thumbnail. Prefers the featured image; when absent, falls
	 * back to the first <img> in the post content that is at least $min_width
	 * wide (so tiny inline icons/emoji don't get promoted to a thumbnail).
	 *
	 * Returns an <img> HTML string, or '' when nothing suitable is found.
	 *
	 * @param int|null $post_id   Defaults to the current post.
	 * @param string   $size      Featured-image size. Default 'medium'.
	 * @param int      $min_width Min width for a content-image fallback. Default 300.
	 * @return string
	 */
	function mike_get_thumbnail( $post_id = null, $size = 'medium', $min_width = 300 ) {
		$post_id = $post_id ? $post_id : get_the_ID();

		// Memoize per post — the regex below should run once per post.
		static $cache = array();
		if ( isset( $cache[ $post_id ] ) ) {
			return $cache[ $post_id ];
		}

		$cache[ $post_id ] = '';

		// 1. Featured image wins.
		if ( has_post_thumbnail( $post_id ) ) {
			$cache[ $post_id ] = get_the_post_thumbnail( $post_id, $size, array( 'loading' => 'lazy', 'alt' => '' ) );
			return $cache[ $post_id ];
		}

		// 2. Fall back to the first wide-enough <img> in the content.
		$content = get_post_field( 'post_content', $post_id );
		if ( ! $content || false === strpos( $content, '<img' ) ) {
			return $cache[ $post_id ];
		}

		if ( ! preg_match_all( '/<img\b[^>]*>/i', $content, $imgs ) ) {
			return $cache[ $post_id ];
		}

		foreach ( $imgs[0] as $img ) {
			// Prefer the explicit width attribute; bail out of too-small images.
			if ( preg_match( '/\bwidth\s*=\s*["\']?(\d+)/i', $img, $w ) && (int) $w[1] < $min_width ) {
				continue;
			}
			if ( ! preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $img, $src ) ) {
				continue;
			}
			$cache[ $post_id ] = sprintf(
				'<img src="%s" loading="lazy" alt="" />',
				esc_url( $src[1] )
			);
			break;
		}

		return $cache[ $post_id ];
	}
endif;

if ( ! function_exists( 'mike_has_thumbnail' ) ) :
	/** Whether mike_get_thumbnail() finds an image. Cached via that function. */
	function mike_has_thumbnail( $post_id = null ) {
		return '' !== mike_get_thumbnail( $post_id );
	}
endif;
