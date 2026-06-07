<?php
/**
 * Single post — config resolver + part renderers.
 * -----------------------------------------------------------------------------
 * The single-post surface mirrors the homepage builder's spine:
 *   owner theme_mods (house style)  +  per-post metabox (which TYPE)
 *      → mike_single_config()  (resolve: metabox type ?? owner default ?? hardcoded)
 *      → a flat normalized config — a TYPE + a few derived atoms (NOT slots, NO width
 *        dial). single.php then dispatches the part fns below in fixed editorial order.
 *
 * Two vocabularies, on purpose (see philosophy.md / marketing/define-house-style-once):
 *   - OWNER sets the house style ONCE in Customizer → Single Post (atoms: which
 *     sidebar, which side, media order, what a "Default" post is, which parts show).
 *   - WRITER picks ONE name per post in the Post Options metabox: Default / With
 *     sidebar / No sidebar / Hero. The name expands to atoms; the writer never sees
 *     sidebar-side or media-order while writing.
 *
 * DERIVED, not dialed: content width is always reading-width (a CSS token, see
 * scss). No sidebar → centered column; sidebar → the rail fills the side. There is
 * no width control anywhere. Sidebar SCOPE is fixed: title + featured image span the
 * full content width, the rail begins at the body (the editorial default).
 *
 * CPT tolerance: these parts suppress themselves when empty (no category → no
 * category line; no author → no byline). A foreign CPT (e.g. an ACF "Movie") that
 * falls through to single.php gets the site skin + title + image + content, cleanly,
 * with no post-furniture it doesn't have. The metabox is registered for `post` only,
 * so a CPT resolves to the owner default. Developers tailor via single-{cpt}.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Meta key the per-post layout TYPE is stored under (metabox in inc/featured.php). */
const MIKE_SINGLE_TYPE_META = '_mike_single_type';

/* -----------------------------------------------------------------------------
   The layout TYPES. Each is a complete recipe (replace-not-merge), so a chosen
   type can never half-apply over the owner's atoms (no "hero with a leftover
   sidebar"). 'default' is special: it defers to the owner's house style.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_single_types' ) ) :
	/**
	 * The per-post layout choices (writer-facing names). 'default' first so it's the
	 * natural top option. Keyed by the value stored in post meta. Hero size is a
	 * per-post choice (full vs half) — NOT a global option — so each feature picks its
	 * own intensity right here.
	 */
	function mike_single_types() {
		return array(
			'default'      => esc_html__( 'Default (site style)', 'mike' ),
			'no-sidebar'   => esc_html__( 'No sidebar', 'mike' ),
			'with-sidebar' => esc_html__( 'With sidebar', 'mike' ),
			'hero-full'    => esc_html__( 'Hero — full', 'mike' ),
			'hero-split'   => esc_html__( 'Hero — split', 'mike' ),
		);
	}
endif;

/* -----------------------------------------------------------------------------
   Resolve the post's config. THE trust boundary for the single-post surface:
   returns a guaranteed shape regardless of caller, tolerant in production.

   Resolution ladder (mirrors the builder's local ?? global ?? default):
     type     = metabox type ?? 'default' (which defers to the owner house style)
     atoms    = derived from the resolved type. The standard layout (none/right/left)
                lives in ONE owner setting; the rail is always the main "Sidebar"
                widget area (hard-wired — no picker). The article header always runs
                full-width above the body/sidebar; image always follows the header.
----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_single_config' ) ) :
	/**
	 * @param int|null $post_id Defaults to the current post in the loop.
	 * @return array {
	 *   @type string $type        Resolved layout type ('with-sidebar' | 'no-sidebar'
	 *                             | 'hero-full' | 'hero-split' — never 'default').
	 *   @type string $sidebar     '' when no rail; else the registered sidebar id.
	 *   @type string $sidebar_side 'left' | 'right' (only meaningful with a rail).
	 *   @type string $hero        'none' | 'full' | 'split'.
	 *   @type bool   $author_box  Show the author bio block.
	 *   @type bool   $prev_next   Show adjacent-post navigation.
	 *   // related posts + comments are owned by inc/archive.php / WP core natively.
	 * }
	 */
	function mike_single_config( $post_id = null ) {
		$post_id = $post_id ? (int) $post_id : (int) get_the_ID();

		// The owner's standard layout, set once: does a normal post have a sidebar,
		// and on which side? This is what a "Default" post inherits.
		$owner_has_sidebar = (bool) get_theme_mod( 'mike_single_has_sidebar', false );
		$side              = ( 'left' === get_theme_mod( 'mike_single_sidebar_side', 'right' ) ) ? 'left' : 'right';

		// Per-post TYPE (metabox). Only `post` carries the metabox; a CPT or an unset
		// value falls through to 'default', which then defers to the owner layout.
		$type = get_post_meta( $post_id, MIKE_SINGLE_TYPE_META, true );
		if ( ! array_key_exists( $type, mike_single_types() ) || '' === $type ) {
			$type = 'default';
		}
		// Resolve 'default' → the owner's standard layout as a concrete type.
		if ( 'default' === $type ) {
			$type = $owner_has_sidebar ? 'with-sidebar' : 'no-sidebar';
		}

		$config = array(
			'type'         => $type,
			'sidebar'      => '',
			'sidebar_side' => $side,
			'hero'         => 'none',
		);

		if ( 'with-sidebar' === $type ) {
			// A rail only counts if the main sidebar actually exists + is registered.
			$config['sidebar'] = is_registered_sidebar( 'mike-sidebar' ) ? 'mike-sidebar' : '';
		} elseif ( 'hero-full' === $type || 'hero-split' === $type ) {
			// Hero owns its atoms: a full-screen header, no rail. Two distinct looks
			// the writer picks per feature:
			//   full  → featured image as a full-bleed background, dark overlay, text
			//           centred over it (the immersive NYT cover).
			//   split → image fills the right half, text + a "Start reading" button
			//           centred in the left half.
			$config['hero'] = ( 'hero-split' === $type ) ? 'split' : 'full';
		}
		// 'no-sidebar' keeps the defaults above (no rail, no hero).

		// Global on/off PARTS (owner-only — not per-post, by design: a writer should
		// not toggle "author box on THIS post").
		$config['author_box'] = (bool) get_theme_mod( 'mike_single_author_box', true );
		$config['prev_next']  = (bool) get_theme_mod( 'mike_single_prev_next', true );

		/**
		 * Final escape valve (child themes / single-{cpt}.php helpers).
		 * @param array $config  Resolved config.
		 * @param int   $post_id The post.
		 */
		return apply_filters( 'mike_single_config', $config, $post_id );
	}
endif;

/* -----------------------------------------------------------------------------
   Part renderers — fixed editorial order is enforced by single.php, not here.
   Each part is self-contained and SUPPRESSES ITSELF when its data is absent
   (emptiness-is-off), which is exactly what makes a foreign CPT render cleanly.
----------------------------------------------------------------------------- */

// NOTE: a breadcrumb (Home › Category › Post + BreadcrumbList JSON-LD) is deferred
// to v1.1 — category + breadcrumb both above the title was redundant for v1. The
// category kicker below stays.

if ( ! function_exists( 'mike_single_category' ) ) :
	/** The kicker category above the title. Suppresses off an uncategorized type. */
	function mike_single_category( $post_id ) {
		// Customizer → Single → Post header → "Show category". Off hides the kicker
		// site-wide; on falls through to the existing emptiness-is-off behavior.
		if ( ! (bool) get_theme_mod( 'mike_single_show_category', true ) ) {
			return;
		}
		if ( ! is_object_in_taxonomy( get_post_type( $post_id ), 'category' ) ) {
			return;
		}
		$cats = get_the_category( $post_id );
		if ( empty( $cats ) ) {
			return;
		}
		$cat = $cats[0];
		printf(
			'<a class="mike-single__cat" href="%s">%s</a>',
			esc_url( get_category_link( $cat ) ),
			esc_html( $cat->name )
		);
	}
endif;

if ( ! function_exists( 'mike_single_meta' ) ) :
	/**
	 * Byline + date. Reuses mike_card_byline() (CAP-aware, avatar-capable) so the
	 * single byline matches cards exactly. Byline suppresses off a type with no
	 * author support (returns ''); the date suppresses off non-`post` types where a
	 * publish date is rarely meaningful furniture.
	 */
	function mike_single_meta( $post_id ) {
		// Customizer → Single → Post header. Author and avatar are separate toggles
		// (author off implies the avatar is irrelevant; avatar off keeps the name).
		$show_author = (bool) get_theme_mod( 'mike_single_show_author', true );
		$show_avatar = (bool) get_theme_mod( 'mike_single_show_avatar', true );
		$show_date   = (bool) get_theme_mod( 'mike_single_show_date',   true );

		$bits = array();

		if ( $show_author && post_type_supports( get_post_type( $post_id ), 'author' ) ) {
			$byline = mike_card_byline( $post_id, $show_avatar );
			if ( '' !== $byline ) {
				$bits[] = $byline;
			}
		}

		if ( $show_date && 'post' === get_post_type( $post_id ) ) {
			$bits[] = sprintf(
				'<time class="mike-single__date" datetime="%s">%s</time>',
				esc_attr( get_the_date( 'c', $post_id ) ),
				esc_html( get_the_date( '', $post_id ) )
			);
		}

		if ( empty( $bits ) ) {
			return;
		}
		echo '<div class="mike-single__meta">'
			. implode( ' <span class="mike-single__sep" aria-hidden="true">&bull;</span> ', $bits ) // phpcs:ignore WordPress.Security.EscapeOutput -- bits escaped in mike_card_byline / above.
			. '</div>';
	}
endif;

if ( ! function_exists( 'mike_single_thumbnail' ) ) :
	/**
	 * The featured image. Called by single.php in two contexts:
	 *   - Regular post (in flow, below the header) — uses size 'large'.
	 *   - Hero post (wrapped by mike_single_hero(), restyled into a backdrop /
	 *     right-half photo on tablet+) — uses size 'mike-hero' (1600×900) so the
	 *     image is sharp at the hero's pixel dimensions.
	 *
	 * The size is picked here, not by the caller, because hero markup is identical
	 * to a regular post (one DOM, breakpoint-driven shape) — the choice of which
	 * registered size to render is the only PHP-level branch.
	 *
	 * Suppresses when there's no thumbnail, the post is password-protected, or
	 * the editor turned off "Show featured image" in the Customizer.
	 */
	function mike_single_thumbnail( $post_id ) {
		if ( ! (bool) get_theme_mod( 'mike_single_show_thumbnail', true ) ) {
			return;
		}
		if ( ! has_post_thumbnail( $post_id ) || post_password_required( $post_id ) ) {
			return;
		}

		// Pick the right registered size for this post. The metabox-stored type
		// is the source of truth (mike-hero = 1600×900 crop for hero posts,
		// 'large' for the in-flow inline image).
		$type = get_post_meta( $post_id, MIKE_SINGLE_TYPE_META, true );
		$size = in_array( $type, array( 'hero-full', 'hero-split' ), true ) ? 'mike-hero' : 'large';

		echo '<figure class="mike-single__thumb">';
		echo get_the_post_thumbnail( $post_id, $size, array( 'loading' => 'eager' ) );
		$caption = wp_get_attachment_caption( get_post_thumbnail_id( $post_id ) );
		if ( $caption ) {
			// wp_kses_post: captions commonly carry a source link (<a>) or <em> — allow
			// the same safe HTML the editor permits, not plain text.
			echo '<figcaption class="mike-single__caption">' . wp_kses_post( $caption ) . '</figcaption>';
		}
		echo '</figure>';
	}
endif;

if ( ! function_exists( 'mike_single_hero' ) ) :
	/**
	 * The hero band for type=hero. Tablet+ only; on phones single.php hides the
	 * hero shell (display:none) and shows the regular header + thumbnail instead.
	 *
	 * MARKUP STRATEGY — ONE source of truth.
	 * The hero is just a wrapper class. The inner DOM is the EXACT same markup a
	 * regular post emits — `mike_single_header()` (category + h1 + subtitle + meta)
	 * followed by `mike_single_thumbnail()` (figure + img). The CSS then
	 * RE-LAYS-OUT those same elements per variant:
	 *
	 *   - --full  : the <figure> is absolutely positioned to fill the band as a
	 *               backdrop; the header is grid-stacked on top with white text
	 *               and a dark overlay between the two (the immersive NYT cover).
	 *               Fixed 600px tall on tablet+.
	 *   - --split : the band is a 2-col grid (text left / figure right). The
	 *               header sits in the left half on the page bg; the figure
	 *               renders at its NATURAL height (no fixed-height crop), and
	 *               the grid row matches whichever side is taller.
	 *
	 * Benefits over the previous "two separate hero markups" approach:
	 *   • ONE <h1> per page (was previously two: the hero's and the regular header's).
	 *   • Mobile fallback is the SAME elements, just unwrapped. No duplicate codebase.
	 *   • The figure keeps its real <img> with alt/srcset (no CSS background-image
	 *     trick on full), so SEO + accessibility stay intact.
	 *
	 * @param array $config The resolved config ($config['hero'] = 'full' | 'split').
	 */
	function mike_single_hero( $post_id, $config ) {
		$variant   = ( 'split' === $config['hero'] ) ? 'split' : 'full';
		$has_image = has_post_thumbnail( $post_id ) && ! post_password_required( $post_id );

		// Hero-split: resolve the title-panel background (per-post override →
		// Customizer default → empty). When non-empty, emit two custom
		// properties on the wrapper — --hero-split-bg (the picked colour) and
		// --hero-split-text (auto-derived from WCAG luminance via
		// mike_contrast_text()). CSS reads them; if either is unset, the
		// panel falls back to the transparent / inherit-text default.
		$inline_style = '';
		if ( 'split' === $variant ) {
			$bg = '';
			if ( defined( 'MIKE_HERO_SPLIT_BG_META' ) ) {
				$bg = trim( (string) get_post_meta( $post_id, MIKE_HERO_SPLIT_BG_META, true ) );
			}
			if ( '' === $bg ) {
				$bg = trim( (string) get_theme_mod( 'mike_single_hero_split_bg', '' ) );
			}
			// Re-validate at the boundary — meta is sanitized on save, but a
			// rogue Customizer value or an old import could carry junk. A bad
			// value renders as no colour (transparent) — tolerant in production.
			if ( '' !== $bg && preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $bg ) ) {
				$text_color = function_exists( 'mike_contrast_text' ) ? mike_contrast_text( $bg ) : '';
				if ( '' !== $text_color ) {
					$inline_style = sprintf(
						' style="--hero-split-bg:%s;--hero-split-text:%s"',
						esc_attr( $bg ),
						esc_attr( $text_color )
					);
				}
			}
		}

		printf(
			'<div class="mike-single-hero mike-single-hero--%s%s"%s>',
			esc_attr( $variant ),
			$has_image ? ' mike-single-hero--has-image' : ' mike-single-hero--no-image',
			$inline_style // phpcs:ignore WordPress.Security.EscapeOutput -- esc_attr applied to each interpolation above.
		);

		// SAME functions a regular post calls. CSS does the layout work.
		mike_single_header( $post_id );
		mike_single_thumbnail( $post_id );

		echo '</div><!-- .mike-single-hero -->';
	}
endif;

if ( ! function_exists( 'mike_single_subtitle' ) ) :
	/**
	 * The subtitle / dek, beneath the title and above the byline (Ghost/Substack
	 * convention). Stored in the `_mike_subtitle` post meta (a textarea — see
	 * inc/editor-meta.php). Suppresses when empty; line breaks become <br>.
	 */
	function mike_single_subtitle( $post_id ) {
		// Customizer → Single → Post header → "Show subtitle". Off hides the dek
		// even when meta is filled; on falls through to the emptiness-is-off check.
		if ( ! (bool) get_theme_mod( 'mike_single_show_subtitle', true ) ) {
			return;
		}
		$subtitle = get_post_meta( $post_id, MIKE_SUBTITLE_META, true );
		$subtitle = trim( (string) $subtitle );
		if ( '' === $subtitle ) {
			return;
		}
		echo '<p class="mike-single__subtitle">' . nl2br( esc_html( $subtitle ) ) . '</p>';
	}
endif;

if ( ! function_exists( 'mike_single_header' ) ) :
	/**
	 * The in-flow article header (category → title → subtitle → meta) for non-hero
	 * types. Always full-width above the body/sidebar row; the featured image always
	 * follows it (modern editorial default — no per-post ordering). The caller renders
	 * the thumb right after; this renders just the text header.
	 */
	function mike_single_header( $post_id ) {
		echo '<header class="mike-single__header">';
		mike_single_category( $post_id );
		the_title( '<h1 class="mike-single__title">', '</h1>' );
		mike_single_subtitle( $post_id );
		mike_single_meta( $post_id );
		echo '</header>';
	}
endif;

if ( ! function_exists( 'mike_single_tags' ) ) :
	/** The tag list below the content. Suppresses off a type without tags / when none. */
	function mike_single_tags( $post_id ) {
		if ( ! is_object_in_taxonomy( get_post_type( $post_id ), 'post_tag' ) ) {
			return;
		}
		$tags = get_the_tags( $post_id );
		if ( empty( $tags ) || is_wp_error( $tags ) ) {
			return;
		}
		echo '<nav class="mike-single__tags" aria-label="' . esc_attr__( 'Tags', 'mike' ) . '">';
		foreach ( $tags as $tag ) {
			$link = get_tag_link( $tag );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			printf(
				'<a class="mike-single__tag" href="%s">%s</a>',
				esc_url( $link ),
				esc_html( $tag->name )
			);
		}
		echo '</nav>';
	}
endif;

if ( ! function_exists( 'mike_single_share' ) ) :
	/**
	 * Social share band — five fixed icon buttons at the end of the article body
	 * (X · Facebook · LinkedIn · Email · Copy link), in that order.
	 *
	 * Design choices (per CLAUDE.md "curated > configurable"):
	 *   - Networks + order are fixed. Editors picking which networks ship is the
	 *     plugin-territory rabbit hole; five covers the modern editorial set.
	 *   - All icons render via mike_icon() — the SAME library the header / footer
	 *     social row use, so the share band stays visually consistent with the
	 *     site's existing social affordances. Brand glyphs ('x', 'facebook',
	 *     'linkedin', 'email') are filled; 'link' (copy) is stroked. No brand
	 *     colors — icons inherit currentColor; hover/focus tints to --accent.
	 *   - Copy link is a <button> with a data-mike-copy attribute; js/single-
	 *     share.js calls navigator.clipboard.writeText. All other buttons are
	 *     plain anchor links with rel="noopener nofollow" target="_blank". No JS
	 *     popup window — modern browsers handle target=_blank fine.
	 *   - URL params encoded via rawurlencode (single encoding strategy for all
	 *     four hrefs; the Fox theme's mixed urlencode/rawurlencode is a smell we
	 *     don't copy).
	 *
	 * Self-suppresses when the customizer toggle is off.
	 *
	 * @param int $post_id The current post.
	 */
	function mike_single_share( $post_id ) {
		if ( ! (bool) get_theme_mod( 'mike_single_share_enable', true ) ) {
			return;
		}

		$permalink = get_permalink( $post_id );
		$title     = get_the_title( $post_id );
		if ( '' === $permalink || '' === $title ) {
			return; // bad state — bail silently.
		}

		// Pre-encode once. rawurlencode for everything (single source of truth for
		// URL encoding across all four share hrefs).
		$enc_url   = rawurlencode( $permalink );
		$enc_title = rawurlencode( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) );

		// Each network: which icon (mike_icon name) + the share URL + the
		// accessible label. Email uses the post title as subject + URL as body
		// so the reader gets context, not just a bare link.
		$links = array(
			'x' => array(
				'icon'  => 'x',
				'label' => esc_html__( 'Share on X', 'mike' ),
				'href'  => 'https://twitter.com/intent/tweet?url=' . $enc_url . '&text=' . $enc_title,
			),
			'facebook' => array(
				'icon'  => 'facebook',
				'label' => esc_html__( 'Share on Facebook', 'mike' ),
				'href'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $enc_url,
			),
			'linkedin' => array(
				'icon'  => 'linkedin',
				'label' => esc_html__( 'Share on LinkedIn', 'mike' ),
				'href'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $enc_url,
			),
			'email' => array(
				'icon'  => 'email',
				'label' => esc_html__( 'Share by email', 'mike' ),
				'href'  => 'mailto:?subject=' . $enc_title . '&body=' . $enc_url,
			),
		);

		// Visible "Share:" label inline with the icons. Replaces the previous
		// aria-label on the nav (a visible label is better a11y than a hidden one,
		// and it signals the affordance to sighted readers too).
		echo '<nav class="mike-share">';
		printf( '<span class="mike-share__label">%s</span>', esc_html__( 'Share:', 'mike' ) );

		foreach ( $links as $key => $data ) {
			printf(
				'<a class="mike-share__link mike-share__link--%1$s" href="%2$s" aria-label="%3$s" rel="noopener nofollow"%4$s>',
				esc_attr( $key ),
				esc_url( $data['href'] ),
				esc_attr( $data['label'] ),
				'email' === $key ? '' : ' target="_blank"'  // mailto: opens the user's mail client; no target=_blank.
			);
			mike_icon( $data['icon'], array( 'size' => 18 ) );
			echo '</a>';
		}

		// Copy link — a <button> (not an <a>) so it's a real action, not navigation.
		// The URL travels via data-mike-copy; js/single-share.js handles the click.
		// The "Copied" feedback is a CSS-driven inline text swap toggled by .is-copied.
		printf(
			'<button type="button" class="mike-share__link mike-share__link--copy" data-mike-copy="%1$s" aria-label="%2$s">',
			esc_attr( $permalink ),
			esc_attr__( 'Copy link', 'mike' )
		);
		mike_icon( 'link', array( 'size' => 18 ) );
		printf( '<span class="mike-share__copied" aria-hidden="true">%s</span>', esc_html__( 'Copied', 'mike' ) );
		echo '</button>';

		echo '</nav>';
	}
endif;

if ( ! function_exists( 'mike_single_newsletter' ) ) :
	/**
	 * Newsletter block — a curated heading + description + form (shortcode / HTML).
	 * Single curated feature; the position (after share, before tags) is the
	 * modern editorial conversion-moment slot.
	 *
	 * Self-suppresses when:
	 *   - the master toggle is off, OR
	 *   - all three fields (heading, description, form) are empty.
	 *
	 * The form field is run through do_shortcode (so [mc4wp_form id="N"] etc.
	 * resolve) and rendered raw — the editor's capability-gated sanitization
	 * happens at save time (see customizer.php), same model as the ad slot.
	 */
	function mike_single_newsletter() {
		if ( ! (bool) get_theme_mod( 'mike_newsletter_enable', false ) ) {
			return;
		}
		$heading     = trim( (string) get_theme_mod( 'mike_newsletter_heading', '' ) );
		$description = trim( (string) get_theme_mod( 'mike_newsletter_description', '' ) );
		$form        = trim( (string) get_theme_mod( 'mike_newsletter_form', '' ) );
		if ( '' === $heading && '' === $description && '' === $form ) {
			return;
		}
		echo '<aside class="mike-newsletter">';
		if ( '' !== $heading ) {
			echo '<h2 class="mike-newsletter__heading">' . esc_html( $heading ) . '</h2>';
		}
		if ( '' !== $description ) {
			// wp_kses_post: the description is plain prose with optional emphasis.
			// nl2br so a two-line description (set in a textarea) keeps its breaks.
			echo '<p class="mike-newsletter__description">' . nl2br( wp_kses_post( $description ) ) . '</p>';
		}
		if ( '' !== $form ) {
			echo '<div class="mike-newsletter__form">';
			// phpcs:ignore WordPress.Security.EscapeOutput -- intentional raw editor-pasted form embed.
			echo do_shortcode( $form );
			echo '</div>';
		}
		echo '</aside>';
	}
endif;

if ( ! function_exists( 'mike_single_author_box' ) ) :
	/**
	 * Author bio block(s) below the content. Co-Authors Plus aware via the same
	 * resolution mike_card_byline() uses — a multi-author post shows a box per
	 * author. Suppresses off a type without author support, when disabled, or when no
	 * author resolves (a box with no name is furniture). One box per author who has a
	 * bio OR avatar (an empty box is worse than none).
	 */
	function mike_single_author_box( $post_id ) {
		if ( ! post_type_supports( get_post_type( $post_id ), 'author' ) ) {
			return;
		}

		if ( function_exists( 'get_coauthors' ) ) {
			$authors = get_coauthors( $post_id );
		} else {
			$author  = get_userdata( (int) get_post_field( 'post_author', $post_id ) );
			$authors = $author ? array( $author ) : array();
		}
		if ( empty( $authors ) ) {
			return;
		}

		$blocks = array();
		foreach ( $authors as $author ) {
			if ( empty( $author->ID ) || empty( $author->display_name ) ) {
				continue;
			}
			$bio    = isset( $author->description ) ? trim( (string) $author->description ) : '';
			$avatar = function_exists( 'coauthors_get_avatar' )
				? coauthors_get_avatar( $author, 64, '', '', 'mike-authorbox__avatar' )
				: get_avatar( $author->ID, 64, '', '', array( 'class' => 'mike-authorbox__avatar' ) );

			// Skip an author with neither a bio nor an avatar — nothing to show.
			if ( '' === $bio && '' === $avatar ) {
				continue;
			}

			$nicename = isset( $author->user_nicename ) ? $author->user_nicename : '';
			$url      = get_author_posts_url( $author->ID, $nicename );

			$block  = '<div class="mike-authorbox">';
			$block .= $avatar; // safe <img> markup from core / CAP.
			$block .= '<div class="mike-authorbox__body">';
			$block .= sprintf(
				'<a class="mike-authorbox__name" href="%s" rel="author">%s</a>',
				esc_url( $url ),
				esc_html( $author->display_name )
			);
			if ( '' !== $bio ) {
				$block .= '<p class="mike-authorbox__bio">' . esc_html( $bio ) . '</p>';
			}
			$block .= '</div></div>';
			$blocks[] = $block;
		}

		if ( empty( $blocks ) ) {
			return;
		}
		echo '<div class="mike-authorboxes">' . implode( '', $blocks ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- each piece escaped above.
	}
endif;

if ( ! function_exists( 'mike_single_prev_next' ) ) :
	/**
	 * Adjacent-post navigation. Uses core get_{previous,next}_post_link() which are
	 * post-type aware (they navigate within the current type), so this works for a
	 * CPT too. Suppresses when neither link resolves (first/only/last post).
	 */
	function mike_single_prev_next() {
		$prev = get_previous_post_link( '%link', '&larr; %title' );
		$next = get_next_post_link( '%link', '%title &rarr;' );
		if ( '' === $prev && '' === $next ) {
			return;
		}
		echo '<nav class="mike-single__adjacent" aria-label="' . esc_attr__( 'Post navigation', 'mike' ) . '">';
		echo '<div class="mike-single__adjacent-prev">' . $prev . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- core link markup.
		echo '<div class="mike-single__adjacent-next">' . $next . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- core link markup.
		echo '</nav>';
	}
endif;

if ( ! function_exists( 'mike_single_related_ratio' ) ) :
	/**
	 * The thumbnail ratio related cards use. INHERITED from the Archive setting (so
	 * related cards match the site's grid), honoring the Archive "Custom…" value.
	 * Returns a W:H string or 'original'. mike_card_thumb() is the trust boundary.
	 */
	function mike_single_related_ratio() {
		$ratio = (string) get_theme_mod( 'mike_archive_ratio', '16:9' );
		if ( 'custom' === $ratio ) {
			$custom = trim( (string) get_theme_mod( 'mike_archive_ratio_custom', '' ) );
			$ratio  = '' !== $custom ? $custom : '16:9';
		}
		return $ratio;
	}
endif;

if ( ! function_exists( 'mike_render_related_posts' ) ) :
	/**
	 * Related posts: a curated row of recent posts related to the current one.
	 * Relation criterion is editor-picked (Customizer → Single Post → "Relate by"):
	 *   - category  : posts sharing any category with the current post (default).
	 *   - tag       : posts sharing any tag.
	 * Layout AUTO-ADAPTS to the query result count — no separate layout option:
	 *   - 3 posts  : 3-up grid (the default look).
	 *   - 2 posts  : 2-up grid (modifier .mike-related__grid--2). A two-card row
	 *                centered in the reading width reads better than two cards
	 *                stretched to the 3-up slots with an empty hole on the right.
	 *   - 1 post   : compact list (.mike-postlist) — the same horizontal "thumb +
	 *                title + date" row used by the Post List widget. A single
	 *                grid cell looks like a layout bug; the compact row is the
	 *                honest shape for one item.
	 * When a card has no featured image, its excerpt stands in (grid only — the
	 * compact list always has its tiny thumb). Ratio inherits the Archive setting.
	 *
	 * Self-suppresses: only on a single `post`, only when enabled, and only when
	 * the chosen criterion actually returns posts (never pads — D7).
	 */
	function mike_render_related_posts() {
		if ( ! is_singular( 'post' ) || ! get_theme_mod( 'mike_related_enable', true ) ) {
			return;
		}

		$post_id   = get_the_ID();
		$relate_by = get_theme_mod( 'mike_related_relate_by', 'category' );
		if ( ! in_array( $relate_by, array( 'category', 'tag' ), true ) ) {
			$relate_by = 'category';
		}

		// Build the query args. Each criterion: gather the current post's terms;
		// if none, there's nothing to relate by → bail (the row hides cleanly).
		$query_args = array(
			'post__not_in'        => array( $post_id ),
			'posts_per_page'      => 3, // max — the layout adapts to what the query actually returns.
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		if ( 'tag' === $relate_by ) {
			$tag_ids = wp_get_post_tags( $post_id, array( 'fields' => 'ids' ) );
			if ( empty( $tag_ids ) || is_wp_error( $tag_ids ) ) {
				return;
			}
			$query_args['tag__in'] = $tag_ids;
		} else {
			$cats = wp_get_post_categories( $post_id );
			if ( empty( $cats ) ) {
				return;
			}
			$query_args['category__in'] = $cats;
		}

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return;
		}

		$count = (int) $query->post_count;
		$ratio = mike_single_related_ratio();

		echo '<aside class="mike-related">';
		echo '<h2 class="mike-related__title">' . esc_html__( 'Related posts', 'mike' ) . '</h2>';

		if ( 1 === $count ) {
			// Compact list shape — reuses the Post List widget's markup contract
			// (.mike-postlist > .mike-postlist__item.mike-card.mike-card--compact),
			// already styled in _archive.scss. One row, tiny 1:1 thumb, title, date.
			$query->the_post();
			$rid = get_the_ID();
			?>
			<ul class="mike-postlist">
				<li class="mike-postlist__item mike-card mike-card--compact">
					<?php
					if ( has_post_thumbnail( $rid ) ) {
						mike_card_thumb( $rid, '1:1', 'mike-thumb', false );
					}
					?>
					<div class="mike-card__body">
						<?php mike_card_title( $rid, 'h3' ); ?>
						<div class="mike-card__meta">
							<time class="mike-card__date" datetime="<?php echo esc_attr( get_the_date( 'c', $rid ) ); ?>">
								<?php echo esc_html( get_the_date( '', $rid ) ); ?>
							</time>
						</div>
					</div>
				</li>
			</ul>
			<?php
		} else {
			// 2 or 3 results → grid. The --2 modifier caps to two columns; default
			// stays at three (the existing look) so a 3-result row is unchanged.
			$grid_class = 'mike-related__grid' . ( 2 === $count ? ' mike-related__grid--2' : '' );
			echo '<div class="' . esc_attr( $grid_class ) . '">';
			while ( $query->have_posts() ) {
				$query->the_post();
				$rid       = get_the_ID();
				$has_thumb = has_post_thumbnail( $rid );
				?>
				<article class="mike-related__card">
					<?php
					if ( $has_thumb ) {
						mike_card_thumb( $rid, $ratio, 'mike-card', false );
					}
					mike_card_title( $rid, 'h3' );
					if ( ! $has_thumb ) {
						mike_card_excerpt( $rid, 20 );
					}
					?>
					<time class="mike-related__date" datetime="<?php echo esc_attr( get_the_date( 'c', $rid ) ); ?>">
						<?php echo esc_html( get_the_date( '', $rid ) ); ?>
					</time>
				</article>
				<?php
			}
			echo '</div><!-- .mike-related__grid -->';
		}

		echo '</aside><!-- .mike-related -->';

		wp_reset_postdata();
	}
endif;

if ( ! function_exists( 'mike_comment' ) ) :
	/**
	 * Render one comment (the wp_list_comments callback in comments.php). Clean,
	 * typographic markup matching the theme — avatar, author, dated permalink, body,
	 * reply link. WP closes the <li> itself (no </li> here — that's the callback
	 * contract). Pingbacks/trackbacks get a one-line form.
	 *
	 * @param WP_Comment $comment The comment.
	 * @param array      $args    wp_list_comments args (avatar_size, etc.).
	 * @param int        $depth   Threading depth.
	 */
	function mike_comment( $comment, $args, $depth ) {
		$is_pingback = in_array( $comment->comment_type, array( 'pingback', 'trackback' ), true );
		?>
		<li id="comment-<?php comment_ID(); ?>" <?php comment_class( $is_pingback ? 'mike-comment mike-comment--ping' : 'mike-comment' ); ?>>
			<article class="mike-comment__body">

				<?php if ( $is_pingback ) : ?>

					<p class="mike-comment__pingback">
						<?php esc_html_e( 'Pingback:', 'mike' ); ?>
						<?php comment_author_link( $comment ); ?>
					</p>

				<?php else : ?>

					<header class="mike-comment__head">
						<?php
						if ( 0 !== (int) $args['avatar_size'] ) {
							echo get_avatar( $comment, $args['avatar_size'], '', '', array( 'class' => 'mike-comment__avatar' ) );
						}
						?>
						<div class="mike-comment__meta">
							<span class="mike-comment__author"><?php comment_author_link( $comment ); ?></span>
							<a class="mike-comment__date" href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>">
								<time datetime="<?php echo esc_attr( get_comment_date( 'c', $comment ) ); ?>">
									<?php
									printf(
										/* translators: %s: human-readable time difference. */
										esc_html__( '%s ago', 'mike' ),
										esc_html( human_time_diff( get_comment_time( 'U' ), current_time( 'timestamp' ) ) )
									);
									?>
								</time>
							</a>
						</div>
					</header>

					<div class="mike-comment__content">
						<?php
						if ( '0' === $comment->comment_approved ) {
							echo '<p class="mike-comment__moderation">' . esc_html__( 'Your comment is awaiting moderation.', 'mike' ) . '</p>';
						}
						comment_text();
						?>
					</div>

					<footer class="mike-comment__foot">
						<?php
						comment_reply_link( array_merge( $args, array(
							'add_below' => 'comment',
							'depth'     => $depth,
							'max_depth' => $args['max_depth'],
							'reply_text' => esc_html__( 'Reply', 'mike' ),
						) ) );
						edit_comment_link( esc_html__( 'Edit', 'mike' ), ' <span class="mike-comment__edit">', '</span>' );
						?>
					</footer>

				<?php endif; ?>

			</article>
		<?php
		// NOTE: no closing </li> — wp_list_comments closes it (callback contract).
	}
endif;
