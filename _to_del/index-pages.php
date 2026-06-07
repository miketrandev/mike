<?php
/**
 * Index pages — the generated lists behind the index page templates
 * (page-templates/topics.php, authors.php, archive-by-date.php).
 * -----------------------------------------------------------------------------
 * These are the one real gap WordPress leaves for a news site's visitor-facing
 * navigation: section archives (category/tag/author/date) are free and infinite,
 * and About/Contact are ordinary prose pages — but three site-wide indexes have to
 * be GENERATED from site data so they never go stale:
 *
 *   - Topics  — every category, by name.
 *   - Authors — every contributor with published posts.
 *   - Archive — a dated table-of-contents of EVERY post, grouped Year › Month
 *               (the editorial "Archives: 2026, 2025…" page, paginated for scale).
 *
 * The page templates render the editor's own title + intro content first (it IS
 * a real Page), then call one of these to append the auto-generated list. Adding
 * a category, author, or post later updates the page with zero editor effort.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mike_render_topics_index' ) ) :
	/**
	 * Render every category that has at least one published post, as a list of
	 * name + post count linking to the category archive. Empty categories are
	 * hidden (a "Sections" page listing sections with no articles is noise).
	 * Description shows when set — editors use it as a one-line section blurb.
	 */
	function mike_render_topics_index() {
		// hide_empty default true: only sections with real coverage. Ordered by
		// name so the index is scannable/alphabetical, not by post count.
		$categories = get_categories(
			array(
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( empty( $categories ) ) {
			echo '<p class="mike-index-empty">' . esc_html__( 'No topics yet.', 'mike' ) . '</p>';
			return;
		}

		echo '<ul class="mike-topics">';
		foreach ( $categories as $category ) {
			printf(
				'<li class="mike-topics__item"><a class="mike-topics__link" href="%1$s">%2$s</a> <span class="mike-topics__count">%3$s</span>',
				esc_url( get_category_link( $category->term_id ) ),
				esc_html( $category->name ),
				/* translators: %s: number of posts in the category. */
				esc_html( sprintf( _n( '%s article', '%s articles', $category->count, 'mike' ), number_format_i18n( $category->count ) ) )
			);
			if ( '' !== trim( (string) $category->description ) ) {
				echo '<p class="mike-topics__desc">' . esc_html( $category->description ) . '</p>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
endif;

if ( ! function_exists( 'mike_render_authors_index' ) ) :
	/**
	 * Render contributors who have published posts: avatar + name (→ author
	 * archive) + bio. Reuses the .mike-authorbox markup from the single-post
	 * author box so styling is shared.
	 *
	 * Authorship the WP-6.9-correct way: we ask for users who CAN write
	 * ('capability' => 'edit_posts' — NOT the deprecated who => 'authors'), then
	 * keep only those with >=1 published post. That drops "ghost" contributors
	 * (an editor account that never published) from a public writers page.
	 * Co-Authors Plus aware: when CAP is active, guest authors are included too.
	 */
	function mike_render_authors_index() {
		$authors = mike_index_published_authors();

		if ( empty( $authors ) ) {
			echo '<p class="mike-index-empty">' . esc_html__( 'No authors yet.', 'mike' ) . '</p>';
			return;
		}

		echo '<div class="mike-authorboxes mike-authorboxes--index">';
		foreach ( $authors as $author ) {
			$bio    = isset( $author->description ) ? trim( (string) $author->description ) : '';
			$avatar = function_exists( 'coauthors_get_avatar' )
				? coauthors_get_avatar( $author, 64, '', '', 'mike-authorbox__avatar' )
				: get_avatar( $author->ID, 64, '', '', array( 'class' => 'mike-authorbox__avatar' ) );

			$nicename = isset( $author->user_nicename ) ? $author->user_nicename : '';
			$url      = get_author_posts_url( $author->ID, $nicename );

			echo '<div class="mike-authorbox">';
			echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput -- safe <img> from core/CAP.
			echo '<div class="mike-authorbox__body">';
			printf(
				'<a class="mike-authorbox__name" href="%s" rel="author">%s</a>',
				esc_url( $url ),
				esc_html( $author->display_name )
			);
			if ( '' !== $bio ) {
				echo '<p class="mike-authorbox__bio">' . esc_html( $bio ) . '</p>';
			}
			echo '</div></div>';
		}
		echo '</div>';
	}
endif;

if ( ! function_exists( 'mike_index_published_authors' ) ) :
	/**
	 * The list of contributors for the authors index: WP_User objects for everyone
	 * who can edit posts AND has published at least one. Single query for the
	 * candidates, then a published-count filter (count_user_posts is cached). The
	 * trust boundary for "who is an author" — both the render and any future caller
	 * read this one list.
	 *
	 * @return WP_User[] Ordered by display name.
	 */
	function mike_index_published_authors() {
		$users = get_users(
			array(
				'capability' => array( 'edit_posts' ),
				'orderby'    => 'display_name',
				'order'      => 'ASC',
			)
		);

		$authors = array();
		foreach ( $users as $user ) {
			// Only contributors with public output — skip never-published accounts.
			if ( count_user_posts( $user->ID, 'post', true ) > 0 ) {
				$authors[] = $user;
			}
		}
		return $authors;
	}
endif;

if ( ! function_exists( 'mike_render_archive_by_date' ) ) :
	/**
	 * Render the editorial "Archives" page: a dated table-of-contents of every
	 * published post, newest first, grouped Year › Month, each row a title + date
	 * link. Paginated (default 100/page) so it stays light even at 10k posts.
	 *
	 * Headings are printed PER PAGE when the year/month changes from the previous
	 * row — so a page that starts mid-2025 re-prints its "2025 › March" headings at
	 * the top and stays self-labelled wherever the page boundary falls. The query
	 * loads full post objects (one query, meta/term caches off) rather than ids +
	 * per-row lazy loads — same reason as the search index: get_the_title()/
	 * get_permalink() then read from the primed cache, not the DB, per row.
	 *
	 * @param int $per_page Posts per page (clamped 10..500).
	 */
	function mike_render_archive_by_date( $per_page = 100 ) {
		$per_page = max( 10, min( 500, (int) $per_page ) );

		// On a static front page the post-list paged var is 'page'; elsewhere it's
		// 'paged'. Read both so the template works wherever it's assigned.
		$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => $paged,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( ! $query->have_posts() ) {
			echo '<p class="mike-index-empty">' . esc_html__( 'No posts yet.', 'mike' ) . '</p>';
			return;
		}

		$current_year  = '';
		$current_month = '';

		echo '<div class="mike-datearchive">';
		while ( $query->have_posts() ) {
			$query->the_post();

			$year  = get_the_date( 'Y' );
			$month = get_the_date( 'm' );

			// New year heading (resets the month so the first month re-prints).
			if ( $year !== $current_year ) {
				if ( '' !== $current_year ) {
					echo '</ul>'; // close previous month's list.
				}
				echo '<h2 class="mike-datearchive__year">' . esc_html( $year ) . '</h2>';
				$current_year  = $year;
				$current_month = '';
			}

			// New month heading within the year.
			if ( $month !== $current_month ) {
				if ( '' !== $current_month ) {
					echo '</ul>';
				}
				echo '<h3 class="mike-datearchive__month">' . esc_html( get_the_date( 'F' ) ) . '</h3>';
				echo '<ul class="mike-datearchive__list">';
				$current_month = $month;
			}

			printf(
				'<li class="mike-datearchive__item"><a class="mike-datearchive__link" href="%1$s">%2$s</a> <time class="mike-datearchive__date" datetime="%3$s">%4$s</time></li>',
				esc_url( get_permalink() ),
				wp_kses_post( get_the_title() ),
				esc_attr( get_the_date( 'c' ) ),
				esc_html( get_the_date() )
			);
		}
		if ( '' !== $current_month ) {
			echo '</ul>'; // close the final month's list.
		}
		echo '</div>';

		// Numbered pagination driven by THIS query (the main query is just the
		// Page). Reuses the .pagination .page-numbers styling from _layout.scss.
		$links = paginate_links(
			array(
				'total'     => (int) $query->max_num_pages,
				'current'   => $paged,
				'mid_size'  => 2,
				'prev_text' => '&larr; ' . esc_html__( 'Previous', 'mike' ),
				'next_text' => esc_html__( 'Next', 'mike' ) . ' &rarr;',
			)
		);
		if ( $links ) {
			echo '<nav class="pagination" aria-label="' . esc_attr__( 'Archive pages', 'mike' ) . '"><div class="nav-links">' . $links . '</div></nav>'; // phpcs:ignore WordPress.Security.EscapeOutput -- paginate_links returns escaped <a>/<span> markup.
		}

		wp_reset_postdata();
	}
endif;
