<?php
/**
 * Header — data normalizer + element template tags + per-band inline CSS.
 * Each template tag RETURNS a string (no echo).
 *
 * TOC:
 *    1. mike_header_data()             → array. Normalized customizer values; trust boundary, memoized, filterable.
 *    2. mike_header_logo()             → image: '<h1|p class="site-branding site-branding--image …"><a …><img …></a></h1|p>'
 *                                         text:  '<h1|p class="site-branding site-branding--text site-title"><a …>name</a></h1|p>'
 *    3. mike_header_primary_menu()     → '<nav class="primary-nav"><ul class="primary-menu">…</ul></nav>' | '' (memoized)
 *    4. mike_header_secondary_menu()   → '<nav class="secondary-nav">…</nav>' | '' (memoized)
 *    5. mike_social_icons()            → '<nav class="social-nav">…icons…</nav>' | '' (memoized). Shared with footer.php.
 *    6. mike_header_search()           → '<div class="header-search …"><button class="header-search__toggle">…</button><div class="header-search__panel">…<form>…</form></div></div>'
 *    7. mike_header_hamburger()        → '<button class="header-hamburger" aria-controls="header-offcanvas">…</button>'
 *    8. mike_header_offcanvas()        → '<div class="header-offcanvas">…nav, social, buttons, utility…</div>'
 *    9. mike_header_buttons()          → '<div class="header-buttons"><a class="mike-button mike-button--…">…</a>…</div>' | ''
 *   10. mike_header_darkmode()         → '<button class="header-darkmode" aria-pressed="false">…sun + moon…</button>'
 *   11. mike_header_date()             → '<span class="header-date">{localized date}</span>'
 *   12. mike_header_sticky_body_class()→ body_class filter adding .mike-header--sticky-{desktop,mobile}
 *   13. mike_header_inline_css()       → per-band bg + auto-text tokens (uses mike_contrast_text() from inc/helper.php)
 *   14. mike_header_enqueue_inline()   → hooks #13 onto wp_enqueue_scripts
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 1. mike_header_data() — editorial language → data; trust boundary. */

if ( ! function_exists( 'mike_header_data' ) ) :
	/**
	 * The header's trust boundary. Reads every header theme_mod once, normalizes,
	 * memoizes. Nothing downstream calls get_theme_mod() for the header again.
	 * Filterable via `mike_header_data` so imports / demo / AI can override.
	 *
	 * `layout` decodes the single image_radio choice into two independent axes
	 * (nav_position inline|stacked, logo_position left|center) so CSS keys off
	 * clean booleans, not a compound string.
	 *
	 * For the full output shape see the example in /header.php (the comment block
	 * above the mike_header_data() call site).
	 */
	function mike_header_data() {
		static $data = null;
		if ( null !== $data ) {
			return $data;
		}

		// The image_radio stores one of four keys; split it into the two axes.
		$layout = get_theme_mod( 'header_layout', 'inline-left' );
		$valid  = array( 'inline-left', 'inline-center', 'stacked-left', 'stacked-center' );
		if ( ! in_array( $layout, $valid, true ) ) {
			$layout = 'inline-left';
		}
		$nav  = ( 0 === strpos( $layout, 'stacked' ) ) ? 'stacked' : 'inline';
		$logo = ( false !== strpos( $layout, 'center' ) ) ? 'center' : 'left';

		// Buttons: two, each text+url+style+new_tab. Kept only if text AND url set
		// (emptiness is off) — normalized here so stage 2 never re-checks.
		$buttons = array();
		foreach ( array( 1, 2 ) as $n ) {
			$text = trim( (string) get_theme_mod( 'header_button_' . $n . '_text', '' ) );
			$url  = trim( (string) get_theme_mod( 'header_button_' . $n . '_url', '' ) );
			if ( '' === $text || '' === $url ) {
				continue;
			}
			$style   = get_theme_mod( 'header_button_' . $n . '_style', 1 === $n ? 'primary' : 'outline' );
			$allowed = array( 'primary', 'outline', 'black', 'text' );
			$buttons[] = array(
				'text'    => $text,
				'url'     => $url,
				'style'   => in_array( $style, $allowed, true ) ? $style : 'primary',
				'new_tab' => (bool) get_theme_mod( 'header_button_' . $n . '_new_tab', false ),
			);
		}

		$has_secondary = has_nav_menu( 'secondary-menu' );
		// Social shown in header = menu assigned AND header toggle on. Toggle
		// DEFAULTS ON, so the discovery story still holds: assign a Social Links menu
		// and icons appear immediately (no second action). The toggle exists only so
		// the common "footer-only social" layout is reachable — untick here, tick
		// 'footer_show_social' there. Both regions gate the shared menu independently.
		$has_social    = has_nav_menu( 'social-menu' ) && (bool) get_theme_mod( 'header_social', true );
		$show_date     = (bool) get_theme_mod( 'header_show_date', false );
		$dark_mode     = (bool) get_theme_mod( 'header_dark_mode', false );

		// Topbar: fixed two-slot layout — date on the left, secondary menu on
		// the right. Social and dark-mode do NOT live in the topbar (social is
		// header-actions territory if desired later; dark-mode always lives in
		// the masthead actions). Topbar appears IFF it has content.
		$has_topbar = $has_secondary || $show_date;

		$data = array(
			'layout'         => $layout, // the raw choice, kept for the body/data attr.
			'nav_position'   => $nav,    // inline | stacked
			'logo_position'  => $logo,   // left | center

			// Logos: light (always) + optional dark variant. Both live in Header
			// (Customizer → Header), NOT native Site Identity, so we can ship a
			// dark-mode logo (Site Identity has only one slot). Text title is the
			// fallback when no light image is set.
			'logo_light'     => esc_url( get_theme_mod( 'header_logo_light', '' ) ),
			'logo_dark'      => esc_url( get_theme_mod( 'header_logo_dark', '' ) ),
			// Image-logo max width in px (0 = unset → CSS default). Image only;
			// the text site-title keeps its token font size.
			'logo_width'     => absint( get_theme_mod( 'header_logo_width', 0 ) ),

			// Which elements exist at all (presence drives whether a zone shows).
			'has_primary'    => has_nav_menu( 'primary-menu' ),
			'has_secondary'  => $has_secondary,
			'has_social'     => $has_social,
			'has_search'     => (bool) get_theme_mod( 'header_search', true ),

			'buttons'        => $buttons,
			'dark_mode'      => $dark_mode,
			'desktop_burger' => (bool) get_theme_mod( 'header_desktop_burger', false ),
			'show_date'      => $show_date,

			// Sticky toggles — when on, masthead (+ bottombar on stacked) pins to
			// the top; topbar scrolls away. CSS hides/shrinks under .mike-header-is-stuck
			// (toggled by js/header-sticky.js via an IntersectionObserver sentinel).
			'sticky_desktop' => (bool) get_theme_mod( 'header_sticky_desktop', true ),
			'sticky_mobile'  => (bool) get_theme_mod( 'header_sticky_mobile',  true ),

			// Resolved placement flag the template reads (no logic in the view).
			'has_topbar' => $has_topbar,
		);

		/**
		 * Filter the header data array (stage-1 output). The single seam for an
		 * import/AI/child-theme to inject or override header config before render.
		 *
		 * @param array $data Normalized header data.
		 */
		$data = apply_filters( 'mike_header_data', $data );
		return $data;
	}
endif;

/* ─────────────────────────────────────────────────────────────────────────────
   Element builders below. Each RETURNS a string. Menu/logo builders memoize so
   a call from desktop + mobile + off-canvas runs wp_nav_menu() exactly once.
   ───────────────────────────────────────────────────────────────────────── */

/* 2. mike_header_logo() — <h1|p> branding (image with optional dark variant, or text). */

if ( ! function_exists( 'mike_header_logo' ) ) :
	/**
	 * Logos live in Customizer → Header (NOT native Site Identity) so we can ship
	 * a dark-mode variant — Site Identity offers only one slot.
	 *
	 * Branching:
	 *   light only        → <img>
	 *   light + dark      → both <img>s; CSS swaps under .is-dark
	 *   no image          → site title as text
	 *
	 * Wrapper is <h1> on the front page (one h1/page), <p> elsewhere. Always one
	 * link wrapping the inner content, pointing home.
	 *
	 * @param array $data Reads logo_light, logo_dark, logo_width.
	 */
	function mike_header_logo( $data = array() ) {
		$light = isset( $data['logo_light'] ) ? $data['logo_light'] : '';
		$dark  = isset( $data['logo_dark'] ) ? $data['logo_dark'] : '';
		$width = isset( $data['logo_width'] ) ? (int) $data['logo_width'] : 0;
		$tag   = is_front_page() ? 'h1' : 'p';
		$name  = get_bloginfo( 'name' );
		$home  = esc_url( home_url( '/' ) );

		if ( '' !== $light ) {
			$class = 'site-branding site-branding--image';
			// Width as a CSS var on the branding element (image only). CSS caps
			// max-width to it, and shrinks on mobile. 0 → unset (CSS default).
			$style = ( $width > 0 ) ? ' style="--logo-width:' . (int) $width . 'px"' : '';
			$img   = sprintf(
				'<img class="site-logo site-logo--light" src="%s" alt="%s" />',
				esc_url( $light ),
				esc_attr( $name )
			);
			if ( '' !== $dark ) {
				$img  .= sprintf(
					'<img class="site-logo site-logo--dark" src="%s" alt="%s" />',
					esc_url( $dark ),
					esc_attr( $name )
				);
				$class .= ' site-branding--has-dark';
			}
			$inner = sprintf( '<a class="site-logo-link" href="%s" rel="home">%s</a>', $home, $img );
		} else {
			// Text fallback: .site-title goes ON THE WRAPPER (h1 / p), not on
			// the inner <a>. The wrapper sets font-size, line-height, margin
			// explicitly so the styling is identical whether the wrapper is
			// h1 (homepage) or p (everywhere else) — no inheritance gotchas.
			// Pattern follows Twenty Fifteen / Sixteen.
			$class = 'site-branding site-branding--text site-title';
			$style = ''; // width control is image-only.
			$inner = sprintf( '<a href="%s" rel="home">%s</a>', $home, esc_html( $name ) );
		}

		return sprintf(
			'<%1$s class="%2$s"%3$s>%4$s</%1$s>',
			tag_escape( $tag ),
			esc_attr( $class ),
			$style, // phpcs:ignore WordPress.Security.EscapeOutput -- integer-only CSS var, built above
			$inner
		);
	}
endif;

/* 3. mike_header_primary_menu() — primary nav (depth 4); memoized. */

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

/* 4. mike_header_secondary_menu() — secondary nav (depth 1); memoized. */

if ( ! function_exists( 'mike_header_secondary_menu' ) ) :
	/**
	 * Secondary nav, depth 1. Memoized — topbar + off-canvas use one render.
	 */
	function mike_header_secondary_menu() {
		static $html = null;
		if ( null !== $html ) {
			return $html;
		}
		if ( ! has_nav_menu( 'secondary-menu' ) ) {
			$html = '';
			return $html;
		}
		$html = wp_nav_menu( array(
			'theme_location'       => 'secondary-menu',
			'menu_class'           => 'menu secondary-menu',
			'container'            => 'nav',
			'container_class'      => 'secondary-nav',
			'container_aria_label' => esc_attr__( 'Secondary menu', 'mike' ),
			'depth'                => 1,
			'fallback_cb'          => false,
			'echo'                 => false,
		) );
		return $html;
	}
endif;

/* 5. mike_social_icons() — Social Links menu with URL → brand icon swap; memoized. Shared with footer.php. */

if ( ! function_exists( 'mike_social_icons' ) ) :
	/**
	 * Social icons row from the 'social-menu' location. Each item's link text is
	 * swapped for a brand icon (URL → icon name via inc/icons.php). Memoized.
	 * The walker_nav_menu_start_el filter is added + removed within this fn so
	 * the swap never leaks to other wp_nav_menu calls.
	 *
	 * NOT header-private despite living in this file: footer.php also calls it
	 * for the footer social row. Kept here (instead of spun off into inc/social.php)
	 * because one shared fn doesn't earn its own file — and the icon-swap walker
	 * naturally sits next to the other menu builders.
	 */
	function mike_social_icons() {
		static $html = null;
		if ( null !== $html ) {
			return $html;
		}
		if ( ! has_nav_menu( 'social-menu' ) ) {
			$html = '';
			return $html;
		}

		$swap = function ( $item_output, $item ) {
			$url   = isset( $item->url ) ? $item->url : '';
			$name  = function_exists( 'mike_social_icon_name' ) ? mike_social_icon_name( $url ) : 'website';
			$label = isset( $item->title ) ? $item->title : '';

			ob_start();
			if ( function_exists( 'mike_icon' ) ) {
				mike_icon( $name, array( 'class' => 'social-icon', 'size' => 20, 'label' => $label ) );
			}
			$icon = ob_get_clean();

			return sprintf(
				'<a class="social-link" href="%s"%s>%s</a>',
				esc_url( $url ),
				( isset( $item->target ) && '_blank' === $item->target ) ? ' target="_blank" rel="noopener noreferrer"' : '',
				$icon // phpcs:ignore WordPress.Security.EscapeOutput -- mike_icon outputs internal, escaped SVG
			);
		};

		add_filter( 'walker_nav_menu_start_el', $swap, 10, 2 );
		$html = wp_nav_menu( array(
			'theme_location'       => 'social-menu',
			'menu_class'           => 'menu social-menu',
			'container'            => 'nav',
			'container_class'      => 'social-nav',
			'container_aria_label' => esc_attr__( 'Social links', 'mike' ),
			'depth'                => 1,
			'fallback_cb'          => false,
			'echo'                 => false,
		) );
		remove_filter( 'walker_nav_menu_start_el', $swap, 10 );
		return $html;
	}
endif;

/* 6. mike_header_search() — Newspack-style wrapper: toggle + dropdown panel as siblings. */

if ( ! function_exists( 'mike_header_search' ) ) :
	/**
	 * Newspack-style header search: ONE wrapper holds both the toggle and the
	 * panel as siblings, so the panel drops absolutely-positioned right under
	 * its own icon — no separate panel rendered elsewhere, no aria-controls
	 * id-juggling. JS finds the panel as the toggle's sibling within the wrapper.
	 *
	 * Rendered once per bar: 'desktop' (anchored in the masthead's right cluster)
	 * and 'mobile' (anchored in the mobile bar's right cell). Variant is a CSS
	 * hook only — searchform.php generates a unique input id per call, so two
	 * forms on one page never collide.
	 *
	 * @param string $variant        'desktop' | 'mobile'.
	 * @param string $dropdown_side  'right' (default) | 'left'. Which edge of the
	 *                               panel is anchored to the toggle. Pass 'left'
	 *                               when the search icon sits near the LEFT edge
	 *                               of the masthead (e.g. case 4 left cluster) —
	 *                               otherwise the panel drops off-screen.
	 *                               Emitted as data-search-dropdown-side on the
	 *                               wrapper; CSS reads it.
	 */
	function mike_header_search( $variant = 'desktop', $dropdown_side = 'right' ) {
		$variant       = 'mobile' === $variant ? 'mobile' : 'desktop';
		$dropdown_side = 'left' === $dropdown_side ? 'left' : 'right';
		ob_start();
		?>
		<div class="header-search header-search--<?php echo esc_attr( $variant ); ?>" data-search-dropdown-side="<?php echo esc_attr( $dropdown_side ); ?>">
			<button type="button" class="header-search__toggle" aria-expanded="false">
				<?php mike_icon( 'search', array( 'class' => 'header-search__icon' ) ); ?>
				<?php mike_icon( 'close',  array( 'class' => 'header-search__close-icon' ) ); ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Search', 'mike' ); ?></span>
			</button>
			<div class="header-search__panel" hidden>
				<?php get_search_form(); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
endif;

/* 7. mike_header_hamburger() — opens #header-offcanvas. Cheap + id-free, safe in multiple bars. */

if ( ! function_exists( 'mike_header_hamburger' ) ) :
	/**
	 * Opens #header-offcanvas (the single off-canvas overlay). Cheap + id-free,
	 * safe to render in multiple bars (in practice only the mobile bar uses it).
	 */
	function mike_header_hamburger() {
		ob_start();
		?>
		<button type="button" class="header-hamburger" aria-expanded="false" aria-controls="header-offcanvas">
			<?php mike_icon( 'menu-2line', array( 'class' => 'header-hamburger__icon' ) ); ?>
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'mike' ); ?></span>
		</button>
		<?php
		return ob_get_clean();
	}
endif;

/* 8. mike_header_offcanvas() — the one off-canvas panel (nav + secondary + social + buttons + utility). */

if ( ! function_exists( 'mike_header_offcanvas' ) ) :
	/**
	 * The one off-canvas panel. Mobile bars only show hamburger·logo·search, so
	 * everything else the editor enabled is reachable inside this panel.
	 * Order: primary nav → secondary → social → buttons → utility (date + dark).
	 * No-primary-menu fallback: wp_page_menu so brand-new sites still navigate.
	 *
	 * @param array $data Reads buttons, dark_mode.
	 */
	function mike_header_offcanvas( $data = array() ) {
		$buttons   = isset( $data['buttons'] ) ? $data['buttons'] : array();
		$dark_mode = ! empty( $data['dark_mode'] );
		ob_start();
		?>
		<div class="header-offcanvas" id="header-offcanvas" hidden>
			<div class="header-offcanvas__backdrop" data-offcanvas-close></div>
			<div class="header-offcanvas__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menu', 'mike' ); ?>">
				<?php
				// Dark-mode FIRST — an eyebrow chip pinned to the top-right of the
				// panel (CSS pushes it right via margin-left:auto). Rendered before
				// the menu so it sits above it in the visual flow. $with_label=true
				// emits visible "Dark mode" / "Light mode" text next to the icon.
				if ( $dark_mode ) {
					echo mike_header_darkmode( true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in builder
				}
				if ( has_nav_menu( 'primary-menu' ) ) {
					wp_nav_menu( array(
						'theme_location'       => 'primary-menu',
						'menu_class'           => 'menu offcanvas-menu',
						'container'            => 'nav',
						'container_class'      => 'offcanvas-nav',
						'container_aria_label' => esc_attr__( 'Mobile menu', 'mike' ),
						'depth'                => 4,
						'fallback_cb'          => false,
					) );
				} else {
					wp_page_menu( array(
						'menu_class' => 'offcanvas-nav',
						'before'     => '<nav aria-label="' . esc_attr__( 'Mobile menu', 'mike' ) . '">',
						'after'      => '</nav>',
					) );
				}
				if ( ! empty( $buttons ) ) {
					echo mike_header_buttons( $buttons ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in builder
				}
				// __footer — pinned to the bottom-left of the panel (absolute).
				// Groups secondary menu + social into one slot so a Concept can
				// restyle the whole strip in one place. Rendered LAST in source
				// order even though it's visually pinned, so screen-reader order
				// matches visual reading order (primary nav → CTAs → footer meta).
				$secondary = mike_header_secondary_menu();
				$social    = mike_social_icons();
				if ( '' !== $secondary || '' !== $social ) {
					echo '<div class="header-offcanvas__footer">' . $secondary . $social . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- both prebuilt + escaped above
				}
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
endif;

/* 9. mike_header_buttons() — up to two CTA buttons; trusts its $buttons arg (normalized in #1). */

if ( ! function_exists( 'mike_header_buttons' ) ) :
	/**
	 * Up to two CTA buttons. Reads only its $buttons arg — never get_theme_mod;
	 * the normalized buttons array from mike_header_data() IS the contract.
	 *
	 * @param array $buttons Each: [
	 *     'text'    => str,
	 *     'url'     => str,
	 *     'style'   => 'primary'|'outline'|'black'|'text',
	 *     'new_tab' => bool,
	 * ].
	 */
	function mike_header_buttons( $buttons ) {
		$buttons = is_array( $buttons ) ? $buttons : array();
		if ( empty( $buttons ) ) {
			return '';
		}
		$out = '';
		foreach ( $buttons as $button ) {
			$out .= sprintf(
				'<a class="mike-button mike-button--%1$s" href="%2$s"%3$s>%4$s</a>',
				esc_attr( $button['style'] ),
				esc_url( $button['url'] ),
				! empty( $button['new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '',
				esc_html( $button['text'] )
			);
		}
		return '<div class="header-buttons">' . $out . '</div>';
	}
endif;

/* 10. mike_header_darkmode() — sun + moon stacked; CSS picks one per .is-dark. */

if ( ! function_exists( 'mike_header_darkmode' ) ) :
	/**
	 * Light/dark toggle. Both sun + moon icons render; CSS shows the right one
	 * based on .is-dark, so no extra DOM work per toggle.
	 *
	 * @param bool $with_label  When true, render visible "Dark / Light" text
	 *                          labels next to the icon (offcanvas/footer usage,
	 *                          where icon-only is ambiguous in a stacked
	 *                          layout). Desktop topbar/masthead callers omit
	 *                          this — icon-only there. Adds .header-darkmode--labeled
	 *                          to the button so CSS can lay out the label row.
	 */
	function mike_header_darkmode( $with_label = false ) {
		$class = 'header-darkmode' . ( $with_label ? ' header-darkmode--labeled' : '' );
		ob_start();
		?>
		<button type="button" class="<?php echo esc_attr( $class ); ?>" aria-pressed="false">
			<?php
			mike_icon( 'sun', array( 'class' => 'header-darkmode__sun' ) );
			mike_icon( 'moon', array( 'class' => 'header-darkmode__moon' ) );
			if ( $with_label ) {
				// Two labels, CSS shows the one for the mode you can switch TO
				// (mirrors the sun/moon icon-swap rule in _header.scss § 15).
				echo '<span class="header-darkmode__label header-darkmode__label--dark">' . esc_html__( 'Dark mode', 'mike' ) . '</span>';
				echo '<span class="header-darkmode__label header-darkmode__label--light">' . esc_html__( 'Light mode', 'mike' ) . '</span>';
			}
			?>
			<span class="screen-reader-text"><?php esc_html_e( 'Toggle dark mode', 'mike' ); ?></span>
		</button>
		<?php
		return ob_get_clean();
	}
endif;

/* 11. mike_header_date() — today's date, localised via wp_date(). */

if ( ! function_exists( 'mike_header_date' ) ) :
	/**
	 * Today's date, newspaper-style. Localised via wp_date(), site timezone.
	 */
	function mike_header_date() {
		return '<span class="header-date">' . esc_html( wp_date( 'l, j F Y' ) ) . '</span>';
	}
endif;

/* 12. mike_header_sticky_body_class()
   Body classes for the two sticky toggles. CSS keys off them
   (.mike-header--sticky-{desktop,mobile}) so the rules only apply when
   the editor opted in. The runtime .mike-header-is-stuck class (added
   by js/header.js when the sentinel scrolls out) is independent. */

if ( ! function_exists( 'mike_header_sticky_body_class' ) ) :
	function mike_header_sticky_body_class( $classes ) {
		$data = mike_header_data();
		if ( ! empty( $data['sticky_desktop'] ) ) {
			$classes[] = 'mike-header--sticky-desktop';
		}
		if ( ! empty( $data['sticky_mobile'] ) ) {
			$classes[] = 'mike-header--sticky-mobile';
		}
		return $classes;
	}
endif;
add_filter( 'body_class', 'mike_header_sticky_body_class' );


/* 13. mike_header_inline_css()
   Per-band colours — bg picked by the editor, text auto-derived for contrast.
   Topbar / masthead / navbar each get one bg option (Customizer → Header →
   Colors). When set, the matching text colour is computed from WCAG relative
   luminance (mike_contrast_text() in inc/helper.php) and the band's bottom
   hairline is dropped — a filled band separates itself; the line on top
   would be double work.

   Tokens emitted (only the ones the editor set):
     --topbar-bg / --topbar-text
     --masthead-bg / --masthead-text
     --navbar-bg / --navbar-text

   The "border-off" rule is appended inline (single source of truth) rather
   than added as a class hook — keeps SCSS clean and the toggle logic in PHP. */

if ( ! function_exists( 'mike_header_inline_css' ) ) :
	function mike_header_inline_css() {
		// Map of theme_mod id → CSS selector for the band that uses it. The
		// selector is also the target of the border-off rule when a bg is set.
		/* Masthead bg also paints .header-mobile (mobile bar reuses the masthead
		   token), so both selectors share the border-off rule when bg is set. */
		$bands = array(
			'topbar'   => array( 'mod' => 'header_topbar_bg',   'selector' => '.header-topbar' ),
			'masthead' => array( 'mod' => 'header_masthead_bg', 'selector' => '.header-masthead,.header-mobile' ),
			'navbar'   => array( 'mod' => 'header_navbar_bg',   'selector' => '.header-bottombar' ),
		);

		$root_decls = '';      // pieces inside :root { … }
		$border_off = '';      // selectors whose border-bottom we kill.

		foreach ( $bands as $key => $conf ) {
			$bg = trim( (string) get_theme_mod( $conf['mod'], '' ) );
			if ( '' === $bg ) {
				continue;
			}
			$text = mike_contrast_text( $bg );
			if ( '' === $text ) {
				continue; // Bad hex, skip — don't half-emit one without the other.
			}
			$root_decls .= '--' . $key . '-bg:' . $bg . ';';
			$root_decls .= '--' . $key . '-text:' . $text . ';';
			$border_off .= $conf['selector'] . '{border-bottom:0}';
		}

		if ( '' === $root_decls ) {
			return '';
		}
		return ':root{' . $root_decls . '}' . $border_off;
	}
endif;

/* 14. mike_header_enqueue_inline() — hook #13 onto wp_enqueue_scripts. */

if ( ! function_exists( 'mike_header_enqueue_inline' ) ) :
	function mike_header_enqueue_inline() {
		$css = mike_header_inline_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'mike_style', $css );
		}
	}
	add_action( 'wp_enqueue_scripts', 'mike_header_enqueue_inline', 20 );
endif;

