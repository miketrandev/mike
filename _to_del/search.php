<?php
/**
 * Search behaviour.
 * -----------------------------------------------------------------------------
 * Three small, independent pieces, all native-WP:
 *
 *   1. SCOPE — site search returns POSTS ONLY by default (pages like About /
 *      Contact are noise in results and render badly in a post card). A Misc
 *      toggle (mike_search_include_pages) lets page-heavy sites opt back in.
 *      Done the canonical way via pre_get_posts (theme-check-safe).
 *
 *   2. TITLE INDEX — a cached list of {t:title, u:url} for every published post
 *      (same scope as search), stored in one option, rebuilt on save/delete.
 *      Served once via a tiny AJAX action so it never bloats normal page loads.
 *
 *   3. AUTOCOMPLETE — ~zero-weight vanilla JS that fetches the index on FIRST
 *      focus of the search field, then matches titles in memory. No per-keystroke
 *      DB query — that's the slow path we deliberately avoid at 10k+ posts.
 *      Titles only: people search for "the post about X", and the form still
 *      submits normally (full WP search) with JS off or no match.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
   1. SCOPE — posts-only search (canonical pre_get_posts)
------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_search_post_types' ) ) :
	/**
	 * The post types Mike search should cover. Posts only, unless the owner
	 * opted pages back in. Single source of truth for BOTH the query filter and
	 * the title index, so search results and autocomplete always agree.
	 *
	 * @return string[] Post-type slugs.
	 */
	function mike_search_post_types() {
		$types = array( 'post' );
		if ( get_theme_mod( 'mike_search_include_pages', false ) ) {
			$types[] = 'page';
		}
		return $types;
	}
endif;

if ( ! function_exists( 'mike_search_posts_only' ) ) :
	/**
	 * Limit the main search query to mike_search_post_types(). Front-end main
	 * query only — never the admin, never secondary queries.
	 *
	 * @param WP_Query $query The query being prepared.
	 */
	function mike_search_posts_only( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}
		$query->set( 'post_type', mike_search_post_types() );
	}
	add_action( 'pre_get_posts', 'mike_search_posts_only' );
endif;

/* -------------------------------------------------------------------------
   2. TITLE INDEX — cached, rebuilt on content change
------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_search_index_build' ) ) :
	/**
	 * Build the title index array: [ { t: title, u: permalink }, ... ] for every
	 * published post in scope. Lazy — only ever runs on a cache miss (the first
	 * search after activation or after a content change), so a site nobody searches
	 * never pays for it. When it does run it stays cheap even at 10k rows:
	 *
	 * We fetch FULL post objects (not 'fields' => 'ids') with meta + term caches
	 * OFF. Counter-intuitive, but right: get_the_title()/get_permalink() read from
	 * the post object, so loading the objects up front in ONE query means the 10k
	 * loop hits the in-memory cache, not the DB, per post. With 'ids' the loop would
	 * lazy-load each post separately — 10k DB round-trips, the slow path.
	 *
	 * Residual cost: get_permalink() on a HIERARCHICAL type (pages, only when the
	 * owner opted them into search) still resolves ancestors, which WP_Query doesn't
	 * prime. Flat posts — the default — have no such cost. Not worth a separate
	 * ancestor query for the rare pages-in-search case.
	 *
	 * @return array<int,array{t:string,u:string}>
	 */
	function mike_search_index_build() {
		$query = new WP_Query(
			array(
				'post_type'              => mike_search_post_types(),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$index = array();
		foreach ( $query->posts as $post ) {
			$title = get_the_title( $post );
			if ( '' === trim( $title ) ) {
				continue; // untitled posts are unhelpful suggestions.
			}
			// get_the_title() runs the_title filter → wptexturize + convert_chars,
			// which encodes smart quotes ('Mining's') as HTML entities ('&#8217;').
			// The JS renders rows via textContent (correct, no XSS surface) — which
			// shows entities LITERALLY. Decode here so the JSON carries clean
			// Unicode and the dropdown reads as intended. ENT_QUOTES handles both
			// &#39; and &#8217;; ENT_HTML5 covers the modern named entities.
			$index[] = array(
				't' => html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'u' => get_permalink( $post ),
			);
		}
		return $index;
	}
endif;

if ( ! function_exists( 'mike_search_index_get' ) ) :
	/**
	 * Return the cached title index, building (and caching) it on a miss. Stored
	 * in an option rather than a transient so it's a single reliable DB read and
	 * survives object-cache flushes; invalidated explicitly on content change.
	 *
	 * @return array<int,array{t:string,u:string}>
	 */
	function mike_search_index_get() {
		$index = get_option( 'mike_search_index', null );
		if ( ! is_array( $index ) ) {
			$index = mike_search_index_build();
			update_option( 'mike_search_index', $index, false ); // not autoloaded.
		}
		return $index;
	}
endif;

if ( ! function_exists( 'mike_search_index_flush' ) ) :
	/**
	 * Drop the cached index so it rebuilds on next request. Fired whenever a post
	 * is created, updated, trashed or deleted — a title or URL may have changed.
	 */
	function mike_search_index_flush() {
		delete_option( 'mike_search_index' );
	}
	add_action( 'save_post', 'mike_search_index_flush' );
	add_action( 'deleted_post', 'mike_search_index_flush' );
	add_action( 'trashed_post', 'mike_search_index_flush' );
	add_action( 'untrashed_post', 'mike_search_index_flush' );
endif;

if ( ! function_exists( 'mike_search_index_ajax' ) ) :
	/**
	 * Serve the title index as JSON. Public (logged-in + out) read-only endpoint;
	 * the fetched data is the same titles+URLs already public on the site, so no
	 * nonce/cap gate is needed. Long browser cache — content changes flush the
	 * option, and the response is keyed by the form's data so a stale list just
	 * means a missing brand-new title until the cache expires.
	 */
	function mike_search_index_ajax() {
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		echo wp_json_encode( mike_search_index_get() );
		wp_die();
	}
	add_action( 'wp_ajax_mike_search_index', 'mike_search_index_ajax' );
	add_action( 'wp_ajax_nopriv_mike_search_index', 'mike_search_index_ajax' );
endif;

/* -------------------------------------------------------------------------
   2b. RESULTS DISPLAY — a lean list, deliberately NOT the archive grid
------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_render_search_loop' ) ) :
	/**
	 * Render the search results as a lean list: thumbnail + title + excerpt +
	 * date. No category chips, no author — a search results page is for *finding*,
	 * not browsing, so it stays scannable regardless of the Archive grid/list
	 * choice. Reuses the shared card primitive + list container (one card source
	 * of truth); thumbnail ratio/side follow the Archive settings so search still
	 * looks like the rest of the site. Call inside The Loop's container.
	 */
	function mike_render_search_loop() {
		// SERP-style row: square 1:1 thumbnail, thumb-left, fixed (no archive
		// inheritance). Search results don't need to match the archive's editorial
		// ratio — they need to scan fast. Hard-coded so result rows stay tight
		// even when the Archive customizer is set to something tall/wide.
		$card = array(
			'style'         => 'list-row',
			'ratio'         => '1:1',
			'title_tag'     => 'h2',
			'show'          => array( 'thumb', 'title', 'excerpt', 'date' ),
			'excerpt_words' => 24,
		);

		// .mike-search-results wraps the list so CSS can size the thumb small
		// (~88px square, Google-style) at reading width, separate from the
		// archive's larger list-row thumb (38% / 240px).
		echo '<div class="mike-list mike-list--thumb-left mike-search-results">';
		while ( have_posts() ) {
			the_post();
			mike_post_card( get_the_ID(), $card );
		}
		echo '</div>'; // .mike-list
	}
endif;

/* -------------------------------------------------------------------------
   3. AUTOCOMPLETE — tiny JS, fetch-once-on-focus
------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_search_enqueue' ) ) :
	/**
	 * Enqueue the autocomplete script (front-end only) and hand it the AJAX URL.
	 * The script lazy-fetches the index on first focus, so this adds no measurable
	 * weight to a page until the visitor actually clicks the search field.
	 */
	function mike_search_enqueue() {
		if ( is_admin() ) {
			return;
		}
		$version = mike_asset_version();
		wp_enqueue_script(
			'mike-search',
			get_template_directory_uri() . '/js/search.js',
			array(),
			$version,
			true
		);
		wp_localize_script(
			'mike-search',
			'mikeSearch',
			array(
				'endpoint' => add_query_arg( 'action', 'mike_search_index', admin_url( 'admin-ajax.php' ) ),
			)
		);
	}
	add_action( 'wp_enqueue_scripts', 'mike_search_enqueue' );
endif;
