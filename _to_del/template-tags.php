<?php
/**
 * Template tags — reusable render functions the templates call.
 * -----------------------------------------------------------------------------
 * Theme-level UI primitives (not config, not get_template_part markup). The
 * post-card system lives here: ONE renderer for "a post card", used by the
 * archive/search loop (parts/content.php) AND by the homepage builder's post
 * widgets. Single source of truth — the builder *consumes* this; it does not
 * own it.
 *
 * Fixed curated element order (toggle on/off, no reordering):
 *   thumbnail → category → title → excerpt → meta(date • author)
 * Variety comes from `style`: standard | overlay | compact | list-row.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------------
   Element helpers — one place each element is produced.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_card_aspect_ratio' ) ) :
	/**
	 * Normalize a ratio string to a safe CSS aspect-ratio value ("16 / 9").
	 * Accepts "W:H" or "W/H" with positive numbers; anything else → '' (caller
	 * then omits the rule). This is the trust boundary for the ratio: only digits
	 * and one separator survive, so it's safe inside a style attribute.
	 *
	 * @param string $ratio e.g. '16:9', '3/2', '21:9'.
	 * @return string        e.g. '16 / 9', or '' if not a valid ratio.
	 */
	function mike_card_aspect_ratio( $ratio ) {
		if ( preg_match( '#^\s*(\d+(?:\.\d+)?)\s*[:/]\s*(\d+(?:\.\d+)?)\s*$#', (string) $ratio, $m ) ) {
			if ( (float) $m[1] > 0 && (float) $m[2] > 0 ) {
				return $m[1] . ' / ' . $m[2];
			}
		}
		return '';
	}
endif;

if ( ! function_exists( 'mike_card_thumb' ) ) :
	/**
	 * @param string $ratio   '16:9' style ratio, or 'original' for uncropped.
	 * @param bool   $caption Render the attachment caption below the image.
	 */
	function mike_card_thumb( $post_id, $ratio = '16:9', $size = 'mike-card', $caption = false ) {
		if ( ! has_post_thumbnail( $post_id ) ) {
			return;
		}
		// 'original' means "don't force an aspect ratio" — let the image be itself.
		// Otherwise emit an inline aspect-ratio from the ratio value so ANY ratio
		// works (curated 16:9 etc. AND arbitrary custom like 21:9), no per-ratio
		// CSS class needed. mike_card_aspect_ratio() validates the W:H shape.
		//
		// The aspect-ratio + overflow:hidden go on the inner <a> (the IMAGE box),
		// NOT the <figure>. If they were on the figure, the figure's height locks to
		// the ratio and overflow:hidden clips the <figcaption> right out of view.
		// The figure stays a plain block: [ratio-locked clipped image] then caption.
		$style = '';
		if ( 'original' !== $ratio ) {
			$aspect = mike_card_aspect_ratio( $ratio );
			if ( '' !== $aspect ) {
				$style = ' style="aspect-ratio:' . esc_attr( $aspect ) . '"';
			}
		}
		// <figure> wraps image + caption: the correct (and only valid) parent for
		// <figcaption>. One extra node per card — negligible — and valid HTML
		// whether or not a caption shows.
		?>
		<figure class="mike-card__thumb">
			<a class="mike-card__thumb-link" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"<?php echo $style; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_attr applied above ?> tabindex="-1" aria-hidden="true"><?php echo get_the_post_thumbnail( $post_id, $size, array( 'loading' => 'lazy', 'alt' => '' ) ); ?></a>
			<?php
			if ( $caption ) {
				$text = wp_get_attachment_caption( get_post_thumbnail_id( $post_id ) );
				if ( $text ) {
					// Captions legitimately carry inline markup (a link, <em>, <cite>).
					// wp_kses_post keeps that safe HTML and strips anything dangerous —
					// the WordPress-correct middle (esc_html would kill the markup; raw
					// output is XSS). Same allowance core uses for caption content.
					echo '<figcaption class="mike-card__caption">' . wp_kses_post( $text ) . '</figcaption>';
				}
			}
			?>
		</figure>
		<?php
	}
endif;

if ( ! function_exists( 'mike_card_category' ) ) :
	function mike_card_category( $post_id ) {
		$cats = get_the_category( $post_id );
		if ( empty( $cats ) ) {
			return;
		}
		echo '<div class="mike-card__cats">';
		foreach ( $cats as $cat ) {
			printf(
				'<a class="mike-card__cat" href="%s">%s</a>',
				esc_url( get_category_link( $cat ) ),
				esc_html( $cat->name )
			);
		}
		echo '</div>';
	}
endif;

if ( ! function_exists( 'mike_card_title' ) ) :
	function mike_card_title( $post_id, $tag = 'h3' ) {
		$tag = in_array( $tag, array( 'h2', 'h3', 'h4' ), true ) ? $tag : 'h3';
		// Editors can put inline formatting (<em>, <strong>, <i>, <b>, <code>)
		// into post titles. esc_html() would strip them to literal text; wp_kses_post()
		// runs the same allow-list WP core uses for the_title() in listings —
		// safe HTML kept, anything malicious stripped.
		printf(
			'<%1$s class="mike-card__title"><a href="%2$s">%3$s</a></%1$s>',
			tag_escape( $tag ),
			esc_url( get_permalink( $post_id ) ),
			wp_kses_post( get_the_title( $post_id ) )
		);
	}
endif;

if ( ! function_exists( 'mike_card_excerpt' ) ) :
	function mike_card_excerpt( $post_id, $words = 22 ) {
		// Manual excerpt wins; else trim content (DECIDED).
		if ( has_excerpt( $post_id ) ) {
			$text = get_the_excerpt( $post_id );
		} else {
			$raw  = get_post_field( 'post_content', $post_id );
			$text = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $raw ) ), (int) $words, '&hellip;' );
		}
		if ( '' === trim( $text ) ) {
			return;
		}
		echo '<p class="mike-card__excerpt">' . esc_html( $text ) . '</p>';
	}
endif;

if ( ! function_exists( 'mike_card_byline' ) ) :
	/**
	 * The byline: one or more authors, each linked to their archive, optionally
	 * avatar-prefixed. Co-Authors Plus aware — if CAP is active we render every
	 * coauthor (the same author objects CAP uses, so guest authors + their links
	 * and avatars Just Work); otherwise the single WP post author. Multiple authors:
	 * comma-separated as plain text, or comma-less (spaced chips) when avatars are
	 * on — a comma between two photo chips looks like a stray glyph. No "and" either way.
	 *
	 * @param int  $post_id
	 * @param bool $avatar  Prefix each author with their avatar.
	 * @return string HTML (already escaped); '' if no author resolves.
	 */
	function mike_card_byline( $post_id, $avatar = false ) {
		// Resolve authors as objects. CAP's get_coauthors() returns user AND
		// guest-author objects; without it, wrap the single post author to match.
		if ( function_exists( 'get_coauthors' ) ) {
			$authors = get_coauthors( $post_id );
		} else {
			$author = get_userdata( (int) get_post_field( 'post_author', $post_id ) );
			$authors = $author ? array( $author ) : array();
		}
		if ( empty( $authors ) ) {
			return '';
		}

		$links = array();
		foreach ( $authors as $author ) {
			if ( empty( $author->ID ) || empty( $author->display_name ) ) {
				continue; // tolerate a malformed/deleted author — skip it.
			}
			$nicename = isset( $author->user_nicename ) ? $author->user_nicename : '';
			// get_author_posts_url() is filtered by CAP (author_link) to return the
			// correct archive for guest authors too, so this one call covers both.
			$url = get_author_posts_url( $author->ID, $nicename );

			// Avatar: CAP's helper handles guest-author thumbnails; else core.
			$img = '';
			if ( $avatar ) {
				$img = function_exists( 'coauthors_get_avatar' )
					? coauthors_get_avatar( $author, 24, '', '', 'mike-card__avatar' )
					: get_avatar( $author->ID, 24, '', '', array( 'class' => 'mike-card__avatar' ) );
			}

			$links[] = sprintf(
				'<a class="mike-card__author" href="%s" rel="author">%s<span class="mike-card__author-name">%s</span></a>',
				esc_url( $url ),
				$img, // get_avatar/coauthors_get_avatar return safe <img> markup.
				esc_html( $author->display_name )
			);
		}

		if ( empty( $links ) ) {
			return '';
		}
		// Separator depends on the avatar mode. Plain text needs a comma between
		// names ("Jane Doe, John Smith"). But with avatars each author is a photo+
		// name chip — a comma floating between two chips looks like a stray glyph,
		// so we drop it and let CSS gap space the chips instead. No "and" either way.
		$sep   = $avatar ? '' : '<span class="mike-card__author-sep">, </span>';
		$class = $avatar ? 'mike-card__authors mike-card__authors--avatars' : 'mike-card__authors';
		return '<span class="' . $class . '">' . implode( $sep, $links ) . '</span>';
	}
endif;

if ( ! function_exists( 'mike_card_meta' ) ) :
	/**
	 * @param array $show   Which bits to show: 'date', 'author'. The display
	 *                      ORDER is fixed here (author • date) regardless of the
	 *                      array order — this function is the authority on order.
	 * @param bool  $avatar Prefix the author(s) with their avatar (only if 'author').
	 */
	function mike_card_meta( $post_id, $show = array( 'date' ), $avatar = false ) {
		$bits = array();

		// Author first (editorial convention: byline leads, date follows).
		if ( in_array( 'author', $show, true ) ) {
			$byline = mike_card_byline( $post_id, $avatar );
			if ( '' !== $byline ) {
				$bits[] = $byline;
			}
		}
		if ( in_array( 'date', $show, true ) ) {
			$bits[] = sprintf(
				'<time class="mike-card__date" datetime="%s">%s</time>',
				esc_attr( get_the_date( 'c', $post_id ) ),
				esc_html( get_the_date( '', $post_id ) )
			);
		}

		if ( empty( $bits ) ) {
			return;
		}
		echo '<div class="mike-card__meta">' . implode( ' <span class="mike-card__sep">&bull;</span> ', $bits ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- bits escaped above
	}
endif;

/* -----------------------------------------------------------------------------
   The card: assemble elements in fixed order, per style.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_card_args_from_settings' ) ) :
	/**
	 * Map a builder widget's Display `settings` bag → mike_post_card() args.
	 * One place the Display toggles (settings.show_*, ratio, excerpt_words…) are
	 * translated into the card's `show[]` + options. Post widgets call this so the
	 * Display tab behaves identically everywhere. Tolerant: missing keys fall back
	 * to the same defaults mike_post_card() uses.
	 *
	 * @param array  $settings        The section's `settings` (normalized array).
	 * @param string $fallback_style  Style to use when no `settings.card` is
	 *                                stored — the widget's natural style (grid →
	 *                                'standard', list → 'list-row'). A widget that
	 *                                hides the style dropdown pins its style here.
	 * @param string $ratio_default   Ratio to use when no `settings.ratio` is
	 *                                stored. MUST match the widget's
	 *                                mike_display_fields( ratio_default ).
	 * @return array                  Args for mike_post_card().
	 */
	function mike_card_args_from_settings( $settings, $fallback_style = 'standard', $ratio_default = '16:9' ) {
		$settings = is_array( $settings ) ? $settings : array();

		$on = function ( $key, $default ) use ( $settings ) {
			return array_key_exists( $key, $settings ) ? ! empty( $settings[ $key ] ) : $default;
		};

		// Build the show[] list from per-component toggles (curated order is fixed
		// in mike_post_card; this only decides membership).
		$show = array();
		if ( $on( 'show_thumb', true ) ) {
			$show[] = 'thumb';
		}
		if ( $on( 'show_category', true ) ) {
			$show[] = 'category';
		}
		if ( $on( 'show_title', true ) ) {
			$show[] = 'title';
		}
		if ( $on( 'show_excerpt', false ) ) {
			$show[] = 'excerpt';
		}
		if ( $on( 'show_date', true ) ) {
			$show[] = 'date';
		}
		if ( $on( 'show_author', false ) ) {
			$show[] = 'author';
		}

		// Ratio: a 'custom' selection uses the free-text ratio_custom (fallback to
		// the widget's ratio_default).
		$ratio = isset( $settings['ratio'] ) ? (string) $settings['ratio'] : (string) $ratio_default;
		if ( 'custom' === $ratio ) {
			$custom = isset( $settings['ratio_custom'] ) ? trim( (string) $settings['ratio_custom'] ) : '';
			$ratio  = '' !== $custom ? $custom : (string) $ratio_default;
		}

		return array(
			'style'         => isset( $settings['card'] ) ? (string) $settings['card'] : (string) $fallback_style,
			'ratio'         => $ratio,
			'show'          => $show,
			'excerpt_words' => isset( $settings['excerpt_words'] ) ? (int) $settings['excerpt_words'] : 22,
			'caption'       => $on( 'show_caption', false ),
			'avatar'        => $on( 'show_avatar', false ),
		);
	}
endif;

if ( ! function_exists( 'mike_post_card' ) ) :
	/**
	 * @param int|WP_Post $post
	 * @param array       $args style, ratio, title_tag, show[], excerpt_words,
	 *                          caption (bool), avatar (bool)
	 */
	function mike_post_card( $post, $args = array() ) {
		$post_id = is_object( $post ) ? $post->ID : (int) $post;

		$defaults = array(
			'style'         => 'standard',
			'ratio'         => '16:9',
			'title_tag'     => 'h3',
			'show'          => array( 'thumb', 'category', 'title', 'excerpt', 'date' ),
			'excerpt_words' => 22,
			'size'          => 'mike-card',
			'caption'       => false,
			'avatar'        => false,
		);
		$args = array_merge( $defaults, $args );
		$show = (array) $args['show'];

		$style   = in_array( $args['style'], array( 'standard', 'overlay', 'compact', 'list-row' ), true ) ? $args['style'] : 'standard';
		$classes = 'mike-card mike-card--' . $style;

		// Track for de-dup (D2) — only when the builder's tracker is loaded.
		if ( function_exists( 'mike_shown_ids' ) ) {
			mike_shown_ids( array( $post_id ) );
		}

		$want = function ( $el ) use ( $show ) {
			return in_array( $el, $show, true );
		};

		// Will a real image render here? (element shown AND post has a featured
		// image.) Overlay needs a backdrop for its white text either way, so when
		// there's no image it still emits an empty .mike-card__thumb the CSS
		// paints solid (see .mike-card--overlay .mike-card__thumb--empty).
		$has_image      = $want( 'thumb' ) && has_post_thumbnail( $post_id );
		$overlay_filler = ( 'overlay' === $style ) && ! $has_image;
		?>
		<article <?php post_class( $classes, $post_id ); ?>>
			<?php
			// thumbnail
			if ( $has_image ) {
				mike_card_thumb( $post_id, $args['ratio'], $args['size'], ! empty( $args['caption'] ) );
			} elseif ( $overlay_filler ) {
				$aspect = ( 'original' === $args['ratio'] ) ? '' : mike_card_aspect_ratio( $args['ratio'] );
				$style_attr = '' !== $aspect ? ' style="aspect-ratio:' . esc_attr( $aspect ) . '"' : '';
				echo '<span class="mike-card__thumb mike-card__thumb--empty" aria-hidden="true"' . $style_attr . '></span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_attr applied above
			}
			?>
			<div class="mike-card__body">
				<?php
				if ( $want( 'category' ) ) {
					mike_card_category( $post_id );
				}
				if ( $want( 'title' ) ) {
					mike_card_title( $post_id, $args['title_tag'] );
				}
				if ( $want( 'excerpt' ) && 'compact' !== $style ) {
					mike_card_excerpt( $post_id, $args['excerpt_words'] );
				}
				$meta_show = array_values( array_intersect( $show, array( 'date', 'author' ) ) );
				if ( $meta_show ) {
					mike_card_meta( $post_id, $meta_show, ! empty( $args['avatar'] ) );
				}
				?>
			</div><!-- .mike-card__body -->
		</article>
		<?php
	}
endif;


/* -----------------------------------------------------------------------------
   mike_link_pages() — themed wrapper around wp_link_pages()
   ---------------------------------------------------------------------------
   Multi-page post navigation (<!--nextpage-->). Wrapped here so the markup
   (<nav class="mike-pagelinks" aria-label="Pages">) is identical across
   single.php, page.php, parts/content-singular.php. Echoes; no return value.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_link_pages' ) ) :
	function mike_link_pages() {
		
	}
endif;
