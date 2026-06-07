<?php
/**
 * Theme options
 * -----------------------------------------------------------------------------
 * Declared with the mini customizer framework (see framework/customizer-framework.php).
 *
 * All register_*() calls run inside the `customize_register` hook — never at
 * bare file-load time (per CLAUDE.md hard rules). Add a section, then register
 * options against it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mike_archive_display_options' ) ) :
	/**
	 * Register the per-card display toggles for the Archive post flow. Option IDs
	 * are namespaced by the section id (e.g. 'mike_archive_show_thumb'); the read
	 * side (mike_archive_card_args) reads the same keys. Kept as a small helper so
	 * the toggle set is declared in one tidy loop (and is ready to serve a second
	 * post-flow surface later without re-typing the list).
	 *
	 * @param string $section          Section id, also the option-id prefix.
	 * @param bool   $with_excerpt_len Include the excerpt-length number.
	 */
	function mike_archive_display_options( $section, $with_excerpt_len = true ) {
		// Thumbnail group: show + ratio (preset dropdown + conditional Custom field)
		// + caption + side. mike_card_aspect_ratio() is the trust boundary for the
		// final ratio value (preset or custom).
		mike_register_option( array(
			'id'      => $section . '_show_thumb',
			'type'    => 'checkbox',
			'section' => $section,
			'label'   => esc_html__( 'Show thumbnail', 'mike' ),
			'default' => true,
		) );
		// Ratio: a dropdown of presets + "Custom…". The custom text field below only
		// appears when 'custom' is selected (active_callback — WP-native, no JS).
		mike_register_option( array(
			'id'      => $section . '_ratio',
			'type'    => 'select',
			'section' => $section,
			'label'   => esc_html__( 'Thumbnail ratio', 'mike' ),
			'default' => '16:9',
			'options' => array(
				'original' => esc_html__( 'Original (uncropped)', 'mike' ),
				'16:9'     => esc_html__( '16:9 — widescreen', 'mike' ),
				'4:3'      => esc_html__( '4:3 — classic', 'mike' ),
				'3:2'      => esc_html__( '3:2 — photo', 'mike' ),
				'1:1'      => esc_html__( '1:1 — square', 'mike' ),
				'2:3'      => esc_html__( '2:3 — portrait', 'mike' ),
				'custom'   => esc_html__( 'Custom…', 'mike' ),
			),
		) );
		$mike_ratio_id = $section . '_ratio';
		mike_register_option( array(
			'id'              => $section . '_ratio_custom',
			'type'            => 'text',
			'section'         => $section,
			'label'           => esc_html__( 'Custom ratio', 'mike' ),
			'description'     => esc_html__( 'e.g. 21:9. Use W:H.', 'mike' ),
			'default'         => '',
			'active_callback' => function ( $control ) use ( $mike_ratio_id ) {
				$setting = $control->manager->get_setting( $mike_ratio_id );
				return $setting && 'custom' === $setting->value();
			},
		) );
		mike_register_option( array(
			'id'      => $section . '_show_caption',
			'type'    => 'checkbox',
			'section' => $section,
			'label'   => esc_html__( 'Show thumbnail caption', 'mike' ),
			'default' => false,
		) );
		// Thumbnail side — only meaningful for the List layout (side-by-side rows);
		// the grid is stacked. Harmless if set while on grid (the render ignores it).
		mike_register_option( array(
			'id'      => $section . '_thumb_side',
			'type'    => 'radio',
			'section' => $section,
			'label'   => esc_html__( 'Thumbnail side (list layout)', 'mike' ),
			'default' => 'left',
			'options' => array(
				'left'  => esc_html__( 'Left', 'mike' ),
				'right' => esc_html__( 'Right', 'mike' ),
			),
		) );

		$toggles = array(
			'show_category' => array( esc_html__( 'Show category', 'mike' ), true ),
			'show_excerpt'  => array( esc_html__( 'Show excerpt', 'mike' ), true ),
			'show_date'     => array( esc_html__( 'Show date', 'mike' ), true ),
			'show_author'   => array( esc_html__( 'Show author', 'mike' ), false ),
			'show_avatar'   => array( esc_html__( 'Show author avatar', 'mike' ), false ),
		);
		foreach ( $toggles as $key => $conf ) {
			mike_register_option( array(
				'id'      => $section . '_' . $key,
				'type'    => 'checkbox',
				'section' => $section,
				'label'   => $conf[0],
				'default' => $conf[1],
			) );
		}

		if ( $with_excerpt_len ) {
			mike_register_option( array(
				'id'          => $section . '_excerpt_words',
				'type'        => 'number',
				'section'     => $section,
				'label'       => esc_html__( 'Excerpt length (words)', 'mike' ),
				'default'     => 24,
				'input_attrs' => array( 'min' => 1, 'max' => 100, 'step' => 1 ),
			) );
		}
	}
endif;

if ( ! function_exists( 'mike_register_options' ) ) :
	function mike_register_options() {

		/* --------------------------------------------
		Header
		--------------------------------------------
		Everything header lives here (logos included — see the logo note below).
		Menus (primary/secondary/social) stay in the native Menus panel; we just
		register the locations. Options are grouped under visual headings (the
		`html` display control rendering an <h3 class="mike-customize-heading">).
		Settings map to mike_header_data() (stage 1), which feeds the renderer.
		-------------------------------------------- */
		// Priorities ≥ 130 so all theme sections sit AFTER the WordPress default
		// panels (Menus ~100, Widgets ~110, Homepage Settings ~120) and before
		// Additional CSS (~200). Order: Header > Footer > Archive > Single > Misc.
		mike_register_section( 'mike_header', array(
			'title'    => esc_html__( 'Header', 'mike' ),
			'priority' => 130,
		) );

		/* Site Identity pointer — logos live in Header, not the native custom-logo
		   slot. An editor opening Site Identity (the habitual home for a logo) finds
		   a note linking them to the Header section so they're never left hunting.
		   Display-only control registered into the NATIVE 'title_tagline' section.
		   CSS for .mike-customize-notice lives in framework/customizer-controls.css. */
		mike_register_option( array(
			'id'       => 'mike_logo_pointer',
			'type'     => 'html',
			'section'  => 'title_tagline',
			'priority' => 1, // top of Site Identity.
			'content'  => '<div class="mike-customize-notice">' .
				'<p class="mike-customize-notice__title">' . esc_html__( 'Looking for the logo?', 'mike' ) . '</p>' .
				'<p class="mike-customize-notice__body">' .
					esc_html__( 'Mike sets the logo (and a dark-mode logo) in the Header section.', 'mike' ) .
					' <a href="#" class="mike-customize-notice__link" data-mike-focus-section="mike_header">' . esc_html__( 'Open Header →', 'mike' ) . '</a>' .
				'</p>' .
				'</div>',
		) );

		// A tiny helper for section headings inside the panel — one consistent
		// <h3> style instead of ad-hoc <hr><strong>. Keeps the panel scannable.
		$mike_heading = function ( $id, $text ) {
			mike_register_option( array(
				'id'      => $id,
				'type'    => 'html',
				'section' => 'mike_header',
				'content' => '<h3 class="mike-customize-heading">' . esc_html( $text ) . '</h3>',
			) );
		};

		/* ---- Layout (one visual pick of the four legitimate arrangements) ----
		   No "LAYOUT" sub-heading: this is the first control in the panel, and
		   the section title "Header" already names it. A sub-heading would be
		   redundant at the very top. Headings appear from "LOGO" onward to
		   divide the groups that follow. */
		$mike_header_img = get_template_directory_uri() . '/framework/img/';
		mike_register_option( array(
			'id'      => 'header_layout',
			'type'    => 'image_radio',
			'section' => 'mike_header',
			'label'   => esc_html__( 'Header layout', 'mike' ),
			'default' => 'inline-left',
			'options' => array(
				'inline-left'    => array( 'src' => $mike_header_img . 'header-inline-left.svg',    'title' => esc_html__( 'Logo left, menu beside', 'mike' ),     'width' => '48%' ),
				'inline-center'  => array( 'src' => $mike_header_img . 'header-inline-center.svg',  'title' => esc_html__( 'Logo centered, menu beside', 'mike' ), 'width' => '48%' ),
				'stacked-left'   => array( 'src' => $mike_header_img . 'header-stacked-left.svg',   'title' => esc_html__( 'Logo left, menu below', 'mike' ),      'width' => '48%' ),
				'stacked-center' => array( 'src' => $mike_header_img . 'header-stacked-center.svg', 'title' => esc_html__( 'Logo centered, menu below', 'mike' ),  'width' => '48%' ),
			),
		) );

		/* ---- Colors (per-band backgrounds; text auto-derived for contrast) ----
		   Three bands → three backgrounds. Text colour is NOT a separate option:
		   the theme computes black-or-white per band from WCAG luminance of the bg
		   the editor picked (see mike_contrast_text() in inc/helper.php). Two
		   reasons for auto-text: (1) one decision instead of two halves the surface
		   area; (2) no editor ever ships an unreadable header by accident.
		   Default empty = no fill, the theme's hairlines do the separating. Set
		   any band and that band's bg+text tokens are emitted and the band's
		   hairline is dropped (a bg already separates — a line on top is double
		   work). All three are independent: a dark navbar under a white masthead
		   is one click. */
		$mike_heading( 'mike_html_header_colors', esc_html__( 'Colors', 'mike' ) );
		mike_register_option( array(
			'id'          => 'header_topbar_bg',
			'type'        => 'color',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Topbar background', 'mike' ),
			'description' => esc_html__( 'Leave empty for transparent (no fill). Text colour is chosen automatically for contrast.', 'mike' ),
			'default'     => '',
		) );
		mike_register_option( array(
			'id'          => 'header_masthead_bg',
			'type'        => 'color',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Masthead background', 'mike' ),
			'description' => esc_html__( 'The main band that holds the logo. Empty = transparent.', 'mike' ),
			'default'     => '',
		) );
		mike_register_option( array(
			'id'          => 'header_navbar_bg',
			'type'        => 'color',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Navbar background', 'mike' ),
			'description' => esc_html__( 'The nav strip (used in stacked layouts). Empty = transparent.', 'mike' ),
			'default'     => '',
		) );

		/* ---- Logo (light + optional dark; lives here, not Site Identity) ----
		   Site Identity has ONE logo slot — not enough for a dark-mode variant.
		   So both logos live here. A pointer in Site Identity links back (see
		   the mike_logo_pointer html control above). No image set → text site
		   title automatically. */
		$mike_heading( 'mike_html_header_logo', esc_html__( 'Logo', 'mike' ) );

		/* Logo-mode notice: spells out the "image OR text" rule so editors don't
		   hunt for a separate text-logo field. The text-logo path is the site
		   title (native), reachable via Site Identity. */
		mike_register_option( array(
			'id'      => 'mike_html_header_logo_note',
			'type'    => 'html',
			'section' => 'mike_header',
			'content' => '<div class="mike-customize-notice">' .
				'<p class="mike-customize-notice__title">' . esc_html__( 'You\'re editing an image logo.', 'mike' ) . '</p>' .
				'<p class="mike-customize-notice__body">' .
					esc_html__( 'Want a text logo instead? Remove the image below — the site title takes over automatically.', 'mike' ) .
					' <a href="#" class="mike-customize-notice__link" data-mike-focus-section="title_tagline">' . esc_html__( 'Edit site title →', 'mike' ) . '</a>' .
				'</p>' .
				'</div>',
		) );

		mike_register_option( array(
			'id'          => 'header_logo_light',
			'type'        => 'image',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Logo', 'mike' ),
			'default'     => '',
		) );
		mike_register_option( array(
			'id'          => 'header_logo_dark',
			'type'        => 'image',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Dark mode logo', 'mike' ),
			'description' => esc_html__( 'Shown when dark mode is active. Optional — falls back to the logo above.', 'mike' ),
			'default'     => '',
		) );
		// Logo width (px) — image only. 0/empty leaves it at the theme default
		// (capped by height so it never blows up the bar). Shrinks on mobile.
		mike_register_option( array(
			'id'          => 'header_logo_width',
			'type'        => 'number',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Logo width (px)', 'mike' ),
			'description' => esc_html__( 'Max width of the image logo. Leave 0 for the default.', 'mike' ),
			'default'     => 0,
			'input_attrs' => array( 'min' => 0, 'max' => 600, 'step' => 1 ),
		) );

		/* ---- Header buttons (up to 2: text + URL + style + new tab) ----
		   Each button gets its own sub-heading (BUTTON 1, BUTTON 2) so the four
		   fields of each group sit unmistakably under one label — no outer
		   "Buttons" wrapper, the sub-headings ARE the divider. */
		$header_buttons = array(
			1 => array( 'label' => esc_html__( 'Button 1', 'mike' ), 'style' => 'primary' ),
			2 => array( 'label' => esc_html__( 'Button 2', 'mike' ), 'style' => 'outline' ),
		);
		foreach ( $header_buttons as $n => $conf ) {
			$mike_heading( 'mike_html_header_button_' . $n, $conf['label'] );
			mike_register_option( array(
				'id'      => 'header_button_' . $n . '_text',
				'type'    => 'text',
				'section' => 'mike_header',
				'label'   => esc_html__( 'Text', 'mike' ),
				'default' => '',
			) );
			mike_register_option( array(
				'id'      => 'header_button_' . $n . '_url',
				'type'    => 'url',
				'section' => 'mike_header',
				'label'   => esc_html__( 'Link', 'mike' ),
				'default' => '',
			) );
			// Dropdown so the list can grow without overflowing the Customizer
			// column. Values map 1:1 to .mike-button variants in _base.scss § 14.
			mike_register_option( array(
				'id'      => 'header_button_' . $n . '_style',
				'type'    => 'select',
				'section' => 'mike_header',
				'label'   => esc_html__( 'Style', 'mike' ),
				'default' => $conf['style'],
				'options' => array(
					'primary' => esc_html__( 'Filled (accent)', 'mike' ),
					'outline' => esc_html__( 'Outline', 'mike' ),
					'black'   => esc_html__( 'Filled (black)', 'mike' ),
					'text'    => esc_html__( 'Text link', 'mike' ),
				),
			) );
			mike_register_option( array(
				'id'      => 'header_button_' . $n . '_new_tab',
				'type'    => 'checkbox',
				'section' => 'mike_header',
				'label'   => esc_html__( 'Open in new tab', 'mike' ),
				'default' => false,
			) );
		}

		/* ---- Extras (toggles) ---- */
		$mike_heading( 'mike_html_header_extras', esc_html__( 'Extras', 'mike' ) );

		// Search — the icon→reveal search. On by default; off for sites that don't
		// want a search affordance at all (no toggle AND no panel rendered).
		mike_register_option( array(
			'id'          => 'header_search',
			'type'        => 'checkbox',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Show search', 'mike' ),
			'description' => esc_html__( 'A search icon that reveals the search field.', 'mike' ),
			'default'     => true,
		) );

		// Social icons — DEFAULT ON so the discovery story holds (assign a Social
		// Links menu → icons appear in the header, no second action). The toggle
		// exists for ONE job: the common "social in footer only" layout — untick
		// here, tick "Show social icons in the footer". Header and footer gate the
		// shared 'social' menu independently.
		mike_register_option( array(
			'id'          => 'header_social',
			'type'        => 'checkbox',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Show social icons', 'mike' ),
			/* description allows inline HTML (WP core renders it through wp_kses_post).
			   The "Edit menu" link opens the Menus panel without leaving the customizer. */
			'description' => esc_html__( 'Uses your Social Links menu.', 'mike' ) .
				' <a href="#" data-mike-focus-panel="nav_menus">' . esc_html__( 'Edit menus →', 'mike' ) . '</a><br>' .
				esc_html__( 'Untick to keep social in the footer only — this does not affect the footer.', 'mike' ),
			'default'     => true,
		) );

		// Desktop hamburger — ADDITIVE: keep the full bar AND show a hamburger
		// (opens the off-canvas). For sites that want the menu always reachable
		// from a burger on desktop too. Off by default.
		mike_register_option( array(
			'id'          => 'header_desktop_burger',
			'type'        => 'checkbox',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Show hamburger on desktop', 'mike' ),
			'description' => esc_html__( 'Adds a menu button to the desktop header (opens the slide-out menu).', 'mike' ),
			'default'     => false,
		) );

		mike_register_option( array(
			'id'          => 'header_dark_mode',
			'type'        => 'checkbox',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Show dark mode toggle', 'mike' ),
			'description' => esc_html__( 'A light/dark switch in the header.', 'mike' ),
			'default'     => false,
		) );

		mike_register_option( array(
			'id'          => 'header_show_date',
			'type'        => 'checkbox',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Show today\'s date', 'mike' ),
			'description' => esc_html__( 'Newspaper-style date in the header.', 'mike' ),
			'default'     => false,
		) );

		/* ---- Sticky header (compact: pins masthead + nav; topbar scrolls away) ---- */
		$mike_heading( 'mike_html_header_sticky', esc_html__( 'Sticky header', 'mike' ) );
		mike_register_option( array(
			'id'          => 'header_sticky_desktop',
			'type'        => 'checkbox',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Sticky on desktop', 'mike' ),
			'description' => esc_html__( 'Keep the masthead (and primary nav, in stacked layouts) pinned to the top as the reader scrolls. The topbar scrolls away.', 'mike' ),
			'default'     => true,
		) );
		mike_register_option( array(
			'id'          => 'header_sticky_mobile',
			'type'        => 'checkbox',
			'section'     => 'mike_header',
			'label'       => esc_html__( 'Sticky on mobile', 'mike' ),
			'description' => esc_html__( 'Keep the mobile bar (hamburger · logo · search) pinned at the top of the viewport.', 'mike' ),
			'default'     => true,
		) );

		/* --------------------------------------------
		Typography
		--------------------------------------------
		Two dropdowns: heading + body. Each lists System default + every font the
		user installed via WordPress's own Font Library (wp-admin → Appearance →
		Fonts, WP 7.0+ — which downloads Google fonts to the site / self-hosts them,
		GDPR-clean, or accepts uploads). Mike just reads those installs
		(mike_font_choices → wp_font_family posts) and, on selection, emits
		@font-face + repoints the --heading-font / --body-font tokens
		(mike_font_active in inc/fonts.php). Install there, pick here.
		-------------------------------------------- */
		mike_register_section( 'mike_typography', array(
			'title'    => esc_html__( 'Typography', 'mike' ),
			'priority' => 132,
		) );

		// '' (system) + every installed Font Library family. Built once.
		$mike_font_choices = function_exists( 'mike_font_choices' ) ? mike_font_choices() : array( '' => esc_html__( 'System default', 'mike' ) );

		mike_register_option( array(
			'id'          => 'mike_heading_font',
			'type'        => 'select',
			'section'     => 'mike_typography',
			'label'       => esc_html__( 'Heading font', 'mike' ),
			'description' => esc_html__( 'System, or a font installed via the Font Library.', 'mike' ),
			'default'     => '',
			'options'     => $mike_font_choices,
		) );

		mike_register_option( array(
			'id'      => 'mike_body_font',
			'type'    => 'select',
			'section' => 'mike_typography',
			'label'   => esc_html__( 'Body font', 'mike' ),
			'default' => '',
			'options' => $mike_font_choices,
		) );

		// Pointer to WordPress's Font Library (where fonts are installed). Only
		// link it when that page exists (WP 7.0+); otherwise tell them they need it.
		if ( function_exists( 'wp_is_font_dir_writable' ) || file_exists( ABSPATH . 'wp-admin/font-library.php' ) ) {
			$mike_font_pointer = sprintf(
				/* translators: %s: URL of the WordPress Font Library admin page. */
				wp_kses( __( 'To add fonts, open the <a href="%s">Font Library</a> — install from Google (downloaded and served from your own site) or upload your own. Installed fonts then appear in the lists above.', 'mike' ), array( 'a' => array( 'href' => array() ) ) ),
				esc_url( admin_url( 'font-library.php' ) )
			);
		} else {
			$mike_font_pointer = esc_html__( 'Installing fonts needs WordPress 6.5 or newer (the Font Library). Until then, only the system font is available.', 'mike' );
		}
		mike_register_option( array(
			'id'      => 'mike_html_typo_install_pointer',
			'type'    => 'html',
			'section' => 'mike_typography',
			'content' => '<p class="description">' . $mike_font_pointer . '</p>',
		) );

		/* --------------------------------------------
		Magazine page (the builder engine — internal name kept in code) — the
		section + control are registered inside the builder engine
		(builder/core/control.php + builder/core/page-setting.php), so removing
		the builder include from functions.php takes them with it. The section ID
		is 'mike_homepage', priority 115 (right before WordPress's Homepage
		Settings at ~120). User-facing label: "Magazine page".

		Homepage Settings (WordPress core section: static_front_page)
		--------------------------------------------
		Mike-owned controls (the homepage sidebar below) sit next to core's
		Front page / Posts page dropdowns. The builder-page assignment (mode +
		custom page) is also registered against this core section — but from
		inside builder/core/page-setting.php, so it disappears with the builder.
		-------------------------------------------- */

		// Homepage sidebar — a page-level rail beside the whole homepage, exactly
		// like the Archive / Single sidebar (same shared "Sidebar" widget area,
		// same .mike-withside layout). DISTINCT from the builder's per-section
		// sidebars: this is "homepage = content + one long rail". When on, builder
		// sections are constrained into the content column (full-bleed yields to the
		// rail, just as page/archive content does). Read by page.php.
		mike_register_option( array(
			'id'          => 'mike_home_sidebar',
			'type'        => 'radio',
			'section'     => 'static_front_page',
			'priority'    => 32,
			'label'       => esc_html__( 'Magazine sidebar', 'mike' ),
			'description' => esc_html__( 'A rail beside the magazine, filled from the Sidebar widget area. Separate from per-section sidebars.', 'mike' ),
			'default'     => 'none',
			'options'     => array(
				'none'  => esc_html__( 'None (full width)', 'mike' ),
				'right' => esc_html__( 'Right', 'mike' ),
				'left'  => esc_html__( 'Left', 'mike' ),
			),
		) );

		/* --------------------------------------------
		Archive (category/tag/date/author lists + search)
		-------------------------------------------- */
		mike_register_section( 'mike_archive', array(
			'title'    => esc_html__( 'Archive', 'mike' ),
			'priority' => 140,
		) );

		// Layout: grid of cards, or a stacked list. (Featured area on top is a
		// separate, deferred feature — this is just the main post flow.)
		mike_register_option( array(
			'id'      => 'mike_archive_layout',
			'type'    => 'select',
			'section' => 'mike_archive',
			'label'   => esc_html__( 'Post layout', 'mike' ),
			'default' => 'grid',
			'options' => array(
				'grid' => esc_html__( 'Grid (cards)', 'mike' ),
				'list' => esc_html__( 'List (rows)', 'mike' ),
			),
		) );

		// Columns (grid only) — one grouped control for desktop/tablet/mobile, same
		// model as the builder's Post Grid. Value: "d,t,m" (e.g. "3,2,1").
		mike_register_option( array(
			'id'          => 'mike_archive_columns',
			'type'        => 'columns',
			'section'     => 'mike_archive',
			'label'       => esc_html__( 'Columns (grid)', 'mike' ),
			'description' => esc_html__( 'Columns per screen size when using the grid layout.', 'mike' ),
			'default'     => '3,2,1',
		) );

		// Sidebar: a rail beside the post flow (same ~240px width as the homepage
		// section sidebar). Fills from the shared "Sidebar" widget area.
		mike_register_option( array(
			'id'      => 'mike_archive_sidebar',
			'type'    => 'radio',
			'section' => 'mike_archive',
			'label'   => esc_html__( 'Sidebar', 'mike' ),
			'default' => 'none',
			'options' => array(
				'none'  => esc_html__( 'None (full width)', 'mike' ),
				'right' => esc_html__( 'Right', 'mike' ),
				'left'  => esc_html__( 'Left', 'mike' ),
			),
		) );

		mike_archive_display_options( 'mike_archive', true ); // include excerpt-length.

		/* --------------------------------------------
		Single post
		--------------------------------------------
		Two parts, in order: (1) the OWNER's house style — what a "normal" post looks
		like, set once (the writer just picks a TYPE per post in the editor; see
		inc/single.php + marketing/define-house-style-once); (2) the Related-posts
		block (read by inc/archive.php). Content WIDTH is deliberately NOT here — it's
		derived (reading-width), not a dial (marketing/reading-width-is-not-a-setting).
		-------------------------------------------- */
		mike_register_section( 'mike_single', array(
			'title'    => esc_html__( 'Single Post', 'mike' ),
			'priority' => 145,
		) );

		// --- House style: what a "standard" post looks like (set once). ---
		// Two controls: does a standard post have a sidebar, and (if so) which side.
		// The rail is ALWAYS the main "Sidebar" widget area (mike-sidebar) —
		// hard-wired, no widget-area picker. Writers override the layout per post
		// (Default / No sidebar / Sidebar / Hero) from the editor; this is the
		// "Default" they inherit. Side stays a separate control because it's a
		// distinct decision an owner makes once and rarely revisits.
		mike_register_option( array(
			'id'          => 'mike_single_has_sidebar',
			'type'        => 'checkbox',
			'section'     => 'mike_single',
			'label'       => esc_html__( 'Standard posts have a sidebar', 'mike' ),
			'description' => esc_html__( 'What a normal post looks like. Writers can override per post from the editor.', 'mike' ),
			'default'     => false,
		) );

		mike_register_option( array(
			'id'      => 'mike_single_sidebar_side',
			'type'    => 'radio',
			'section' => 'mike_single',
			'label'   => esc_html__( 'Sidebar side (of post having sidebar)', 'mike' ),
			'default' => 'right',
			'options' => array(
				'right' => esc_html__( 'Right', 'mike' ),
				'left'  => esc_html__( 'Left', 'mike' ),
			),
		) );

		// Hero-split background — the site-wide DEFAULT colour painted behind the
		// title panel on hero-split posts. Empty = transparent (current look —
		// text on the page background). A writer can override per post from the
		// editor sidebar; useful for brand features, sponsored posts, and special
		// editorial where the panel colour matches a brand identity. Text colour
		// auto-derives from the picked colour's WCAG luminance (same pattern the
		// header band colours use via mike_contrast_text() in inc/helper.php) —
		// no separate text-color option, no chance of an unreadable post.
		mike_register_option( array(
			'id'          => 'mike_single_hero_split_bg',
			'type'        => 'color',
			'section'     => 'mike_single',
			'label'       => esc_html__( 'Hero-split background', 'mike' ),
			'description' => esc_html__( 'Colour painted behind the title panel on hero-split posts. Leave empty for transparent. Text colour is chosen automatically for contrast. A writer can override this per post.', 'mike' ),
			'default'     => '',
		) );

		// --- Post header parts (above the article body). ---
		// On/off toggles for each header element. Off = element is hidden even when
		// its data exists. (Each element still auto-suppresses when data is absent —
		// emptiness-is-off — so a post without a featured image never shows an empty
		// thumb box regardless of this toggle.) Hero post types (full / split) render
		// their own combined header — these toggles affect ONLY the in-flow header.
		mike_register_option( array(
			'id'      => 'mike_html_single_header',
			'type'    => 'html',
			'section' => 'mike_single',
			'content' => '<hr><strong>' . esc_html__( 'Post header', 'mike' ) . '</strong>',
		) );
		$mike_single_header_toggles = array(
			'mike_single_show_category'  => esc_html__( 'Show category', 'mike' ),
			'mike_single_show_thumbnail' => esc_html__( 'Show featured image', 'mike' ),
			'mike_single_show_subtitle'  => esc_html__( 'Show subtitle', 'mike' ),
			'mike_single_show_date'      => esc_html__( 'Show date', 'mike' ),
			'mike_single_show_author'    => esc_html__( 'Show author', 'mike' ),
			'mike_single_show_avatar'    => esc_html__( 'Show author avatar', 'mike' ),
		);
		foreach ( $mike_single_header_toggles as $mike_toggle_id => $mike_toggle_label ) {
			mike_register_option( array(
				'id'      => $mike_toggle_id,
				'type'    => 'checkbox',
				'section' => 'mike_single',
				'label'   => $mike_toggle_label,
				'default' => true,
			) );
		}

		// --- Global parts (on/off for ALL posts — not per-post, by design). ---
		mike_register_option( array(
			'id'      => 'mike_single_author_box',
			'type'    => 'checkbox',
			'section' => 'mike_single',
			'label'   => esc_html__( 'Show author box', 'mike' ),
			'default' => true,
		) );

		mike_register_option( array(
			'id'      => 'mike_single_prev_next',
			'type'    => 'checkbox',
			'section' => 'mike_single',
			'label'   => esc_html__( 'Show previous/next post links', 'mike' ),
			'default' => true,
		) );

		// Comments — global hide for ALL posts. Off completely skips the
		// comments_template() call in single.php (no comment list, no reply form,
		// no "comments closed" line). This is the SITE-WIDE switch; WP's per-post
		// Discussion meta box still works on top (a closed-per-post stays closed).
		// Pages have their own per-page Discussion control and are not affected.
		mike_register_option( array(
			'id'          => 'mike_single_show_comments',
			'type'        => 'checkbox',
			'section'     => 'mike_single',
			'label'       => esc_html__( 'Show comments', 'mike' ),
			'description' => esc_html__( 'Off hides comments on every post site-wide. Per-post settings in the Discussion meta box still apply when this is on.', 'mike' ),
			'default'     => true,
		) );

		// --- Related posts. Curated, not configurable: a fixed 3-column row of
		// thumbnail + title + date (excerpt fills in when a post has no thumbnail),
		// reusing the Archive thumbnail RATIO so they match the site. Only the on/off
		// is exposed (emptiness-is-off elsewhere; here a clean single toggle). ---
		mike_register_option( array(
			'id'      => 'mike_html_related',
			'type'    => 'html',
			'section' => 'mike_single',
			'content' => '<hr><strong>' . esc_html__( 'Related posts', 'mike' ) . '</strong>',
		) );

		mike_register_option( array(
			'id'          => 'mike_related_enable',
			'type'        => 'checkbox',
			'section'     => 'mike_single',
			'label'       => esc_html__( 'Show related posts', 'mike' ),
			'description' => esc_html__( 'A row of recent posts related to the current one.', 'mike' ),
			'default'     => true,
		) );

		// Relation criterion. Category by default (every post has one; tag is
		// optional). The render auto-adapts the layout to how many posts the query
		// returns — 3-up grid for 3, 2-up grid for 2, compact list for 1 — so no
		// "layout" option is exposed.
		mike_register_option( array(
			'id'          => 'mike_related_relate_by',
			'type'        => 'select',
			'section'     => 'mike_single',
			'label'       => esc_html__( 'Relate by', 'mike' ),
			'description' => esc_html__( 'What makes a post “related”. If the criterion finds nothing, the section is hidden (never padded with unrelated posts).', 'mike' ),
			'default'     => 'category',
			'options'     => array(
				'category' => esc_html__( 'Same category', 'mike' ),
				'tag'      => esc_html__( 'Same tag', 'mike' ),
			),
		) );

		// --- Social share. A small band of brand-neutral icon links (X · Facebook ·
		// LinkedIn · Email · Copy link) at the end of the article, before the tag
		// list. Curated, not configurable beyond on/off — picking networks per post
		// is editor-noise; the position is the modern editorial standard. ---
		mike_register_option( array(
			'id'      => 'mike_html_single_share',
			'type'    => 'html',
			'section' => 'mike_single',
			'content' => '<hr><strong>' . esc_html__( 'Social share', 'mike' ) . '</strong>',
		) );

		mike_register_option( array(
			'id'          => 'mike_single_share_enable',
			'type'        => 'checkbox',
			'section'     => 'mike_single',
			'label'       => esc_html__( 'Show share buttons', 'mike' ),
			'description' => esc_html__( 'A small band of X, Facebook, LinkedIn, Email, and Copy-link buttons at the end of every post.', 'mike' ),
			'default'     => true,
		) );

		/* --------------------------------------------
		Newsletter
		--------------------------------------------
		A single, curated block shown beneath every post (after the share band,
		before the tag list — the editorial "you-just-finished-reading-and-care"
		conversion moment). NOT a widget area: editors who want a newsletter get
		one obvious place to set it. Two-line shape: heading + a description, and
		a free-form HTML field for the actual form (typically a plugin shortcode:
		MC4WP, Mailchimp, Kit, ConvertKit, etc. — or raw HTML for a self-hosted
		form). do_shortcode() runs on the HTML field so shortcodes resolve.
		Self-suppresses when the toggle is off or all three fields are empty.
		-------------------------------------------- */
		mike_register_section( 'mike_newsletter', array(
			'title'    => esc_html__( 'Newsletter', 'mike' ),
			'priority' => 146,
		) );

		mike_register_option( array(
			'id'          => 'mike_newsletter_enable',
			'type'        => 'checkbox',
			'section'     => 'mike_newsletter',
			'label'       => esc_html__( 'Show newsletter block after posts', 'mike' ),
			'description' => esc_html__( 'A heading + description + form block shown beneath every single post. Hides on its own when all fields below are empty.', 'mike' ),
			'default'     => false,
		) );

		mike_register_option( array(
			'id'          => 'mike_newsletter_heading',
			'type'        => 'text',
			'section'     => 'mike_newsletter',
			'label'       => esc_html__( 'Heading', 'mike' ),
			'description' => esc_html__( 'e.g. “Get our weekly newsletter”.', 'mike' ),
			'default'     => '',
		) );

		mike_register_option( array(
			'id'          => 'mike_newsletter_description',
			'type'        => 'textarea',
			'section'     => 'mike_newsletter',
			'label'       => esc_html__( 'Description', 'mike' ),
			'description' => esc_html__( 'One or two sentences shown under the heading.', 'mike' ),
			'default'     => '',
		) );

		// Form field sanitizer — mirrors WP's Custom HTML widget rule: a user with
		// the unfiltered_html capability (admins on single-site, super-admins on
		// multisite) gets passthrough so newsletter/ad embed code (incl. <script>,
		// <iframe sandbox>, <ins class="adsbygoogle">) survives the save. Users
		// without the cap get the wp_kses_post filter (the same safety net WP
		// applies to post content). Reused below for the Ads field.
		$mike_sanitize_embed_html = function ( $value ) {
			return current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( (string) $value );
		};

		mike_register_option( array(
			'id'                => 'mike_newsletter_form',
			'type'              => 'textarea',
			'section'           => 'mike_newsletter',
			'label'             => esc_html__( 'Form (shortcode or HTML)', 'mike' ),
			'description'       => esc_html__( 'Paste your newsletter shortcode (e.g. [mc4wp_form id="123"]) or the raw HTML embed from your provider. Shortcodes are run; HTML is rendered as-is — don’t paste anything you don’t trust.', 'mike' ),
			'default'           => '',
			'sanitize_callback' => $mike_sanitize_embed_html,
		) );

		/* --------------------------------------------
		Ads — a Panel with one section per position
		--------------------------------------------
		Mental model: an editor wanting to place an ad asks "WHERE do I put it?"
		— so positions are the section names, not the field names. Four positions
		ship in v1, ordered by where the editor scrolls to find them:
		  • After header        (top of <main>, before any content)
		  • Before post content (inside <article>, just above the_content)
		  • After post content  (between the_content and the share band)
		  • Footer              (top of <footer>, before the widget area)
		The builder has its own per-section Ad/promo widget — global builder
		slot deferred (would create two places to configure builder ads).

		Each section ships the SAME two-input shape, so editors learn it once:
		  IMAGE (attachment) + link URL + new-tab    ← quick + easy path
		    OR
		  CODE (textarea, raw HTML / shortcode)       ← ad-network path
		If both are filled, the IMAGE wins at render time (simpler intent).
		The image's alt text is read from the native WordPress media library —
		editors fill it once on upload; the theme uses it at every render.
		Image's max-width is capped to 100% via CSS (.mike-ad img) so a
		1200px creative never overflows on a 360px phone.

		Code is the raw HTML field (same capability-gated sanitizer as the
		newsletter form: passthrough for admins with unfiltered_html,
		wp_kses_post for lesser roles). do_shortcode() runs at render time.
		-------------------------------------------- */
		mike_register_panel( 'mike_ads', array(
			'title'       => esc_html__( 'Ads', 'mike' ),
			'description' => esc_html__( 'Place advertisements in four positions across the site. Each position takes an image (with link) OR a code block (HTML / shortcode). If both are filled, the image renders.', 'mike' ),
			'priority'    => 147,
		) );

		// The four positions: id → label. Single source of truth — used here for
		// registration AND in inc/single.php's renderer to map a position id to
		// its setting keys (mike_ad_{position}_image / _link / _target / _code).
		$mike_ad_positions = array(
			'after_header'        => esc_html__( 'After header', 'mike' ),
			'before_post_content' => esc_html__( 'Before post content', 'mike' ),
			'after_post_content'  => esc_html__( 'After post content', 'mike' ),
			'above_footer'        => esc_html__( 'Above footer', 'mike' ),
		);

		foreach ( $mike_ad_positions as $mike_pos_id => $mike_pos_label ) {

			$mike_section_id = 'mike_ads_' . $mike_pos_id;

			mike_register_section( $mike_section_id, array(
				'title' => $mike_pos_label,
				'panel' => 'mike_ads',
			) );

			// IMAGE: attachment id. Native WP media frame; editor fills alt on
			// upload, theme reads it back via _wp_attachment_image_alt meta.
			mike_register_option( array(
				'id'      => 'mike_ad_' . $mike_pos_id . '_image',
				'type'    => 'media_image',
				'section' => $mike_section_id,
				'label'   => esc_html__( 'Image (desktop)', 'mike' ),
				'default' => 0,
			) );

			// MOBILE image (optional) — art-direction variant for phones (< 600px).
			// A square 300×250 on phones where a wide 728×90 leaderboard wouldn't
			// fit. Empty = use the desktop image at every viewport. Same shape as
			// the builder Ad/Promo widget's mobile field for consistency.
			mike_register_option( array(
				'id'          => 'mike_ad_' . $mike_pos_id . '_mobile_image',
				'type'        => 'media_image',
				'section'     => $mike_section_id,
				'label'       => esc_html__( 'Image (mobile, optional)', 'mike' ),
				'description' => esc_html__( 'Shown on narrow screens (under 600px) instead of the image above — use a differently-shaped banner (e.g. a square 300×250 on phones where a wide leaderboard won’t fit). Leave empty to use the same image everywhere.', 'mike' ),
				'default'     => 0,
			) );

			// IMAGE link URL — where clicking the image goes. Empty = no link.
			mike_register_option( array(
				'id'      => 'mike_ad_' . $mike_pos_id . '_link',
				'type'    => 'url',
				'section' => $mike_section_id,
				'label'   => esc_html__( 'Link URL', 'mike' ),
				'default' => '',
			) );

			// Open in new tab toggle for the image link.
			mike_register_option( array(
				'id'      => 'mike_ad_' . $mike_pos_id . '_new_tab',
				'type'    => 'checkbox',
				'section' => $mike_section_id,
				'label'   => esc_html__( 'Open link in new tab', 'mike' ),
				'default' => true,
			) );

			// Width — the banner's TARGET width on desktop. Any CSS length
			// (e.g. 728px, 50%, 60rem). On phones the value is automatically
			// capped to the parent so a 728px banner shrinks to fit a 360px
			// screen — same gutter math as .container. Empty = the image's
			// natural width.
			mike_register_option( array(
				'id'          => 'mike_ad_' . $mike_pos_id . '_max_width',
				'type'        => 'text',
				'section'     => $mike_section_id,
				'label'       => esc_html__( 'Width (e.g. 728px)', 'mike' ),
				'description' => esc_html__( 'The banner’s target width on desktop. Any CSS length — 728px, 100%, 60rem. On phones it shrinks automatically to fit the screen. Leave empty for the image’s natural size.', 'mike' ),
				'default'     => '',
			) );

			// CODE (textarea, raw HTML / shortcode) — fallback when an editor
			// pastes an ad-network embed instead of a banner image. Used only
			// when no image is set above.
			mike_register_option( array(
				'id'                => 'mike_ad_' . $mike_pos_id . '_code',
				'type'              => 'textarea',
				'section'           => $mike_section_id,
				'label'             => esc_html__( 'Code (HTML / shortcode)', 'mike' ),
				'description'       => esc_html__( 'Used only if no image is set above. Paste your ad-network embed (AdSense <ins>, network shortcode, raw HTML). Shortcodes run; HTML is rendered as-is — don\'t paste anything you don\'t trust.', 'mike' ),
				'default'           => '',
				'sanitize_callback' => $mike_sanitize_embed_html,
			) );

			// "Advertisement" disclosure label — default ON. Many regions and ad
			// networks REQUIRE paid placements to be labeled, so the safe default
			// is to show it. Editors can hide it for non-paid house promos.
			mike_register_option( array(
				'id'          => 'mike_ad_' . $mike_pos_id . '_show_label',
				'type'        => 'checkbox',
				'section'     => $mike_section_id,
				'label'       => esc_html__( 'Show “Advertisement” label', 'mike' ),
				'description' => esc_html__( 'A small disclosure label above the ad. Many regions and ad networks require paid placements to be clearly labeled.', 'mike' ),
				'default'     => true,
			) );
		}

		/* --------------------------------------------
		Misc
		--------------------------------------------
		Small site-wide behaviours that don't belong to a layout section. Kept
		deliberately tiny (CLAUDE.md: no option-for-everything).
		-------------------------------------------- */
		mike_register_section( 'mike_misc', array(
			'title'    => esc_html__( 'Misc', 'mike' ),
			'priority' => 150,
		) );

		// Search scope. Mike limits site search to posts by default — pages
		// ("About", "Contact", Privacy) are usually noise in results and look broken
		// in a post card (no date/category, one-word title). Page-heavy sites can
		// flip this on. Read by mike_search_posts_only() in inc/search.php.
		mike_register_option( array(
			'id'          => 'mike_search_include_pages',
			'type'        => 'checkbox',
			'section'     => 'mike_misc',
			'label'       => esc_html__( 'Include pages in search results', 'mike' ),
			'description' => esc_html__( 'Off by default — search returns posts only. Turn on if your pages are worth finding by search.', 'mike' ),
			'default'     => false,
		) );

		// Widgets screen: classic (Appearance → Widgets) by default. Mike is a
		// classic-theme audience — bloggers/editors who want the familiar widget
		// boxes, not the block widgets screen. Flip this on to opt back into the
		// block editor for widgets. Read by the use_widgets_block_editor filter in
		// functions.php.
		mike_register_option( array(
			'id'          => 'mike_use_block_widgets',
			'type'        => 'checkbox',
			'section'     => 'mike_misc',
			'label'       => esc_html__( 'Use the block editor for widgets', 'mike' ),
			'description' => esc_html__( 'Off by default — Mike uses the classic widgets screen (Appearance → Widgets). Turn on to use the block-editor widgets screen instead.', 'mike' ),
			'default'     => false,
		) );

		/* --------------------------------------------
		Footer
		-------------------------------------------- */
		mike_register_section( 'footer', array(
			'title'    => esc_html__( 'Footer', 'mike' ),
			'priority' => 135,
		) );

		// Footer = three stacked bands under the widget area:
		//   1. widgets   — Appearance → Widgets → "Mike Footer" sidebar
		//   2. branding  — logo (left) · social icons (right). Each side has its own
		//                  show/hide toggle below. If BOTH are off the whole row hides.
		//   3. bottom    — copyright (left) · Footer Menu (right). Standard footer
		//                  bottom; copyright text is set below; menu is a nav-menu
		//                  location (Appearance → Menus → "Footer Menu").

		// ── Background (mirrors the header band colors) ──────────────────────
		mike_register_option( array(
			'id'          => 'footer_bg',
			'type'        => 'color',
			'section'     => 'footer',
			'label'       => esc_html__( 'Footer background', 'mike' ),
			'description' => esc_html__( 'Leave empty for transparent (no fill). Text colour is chosen automatically for contrast.', 'mike' ),
			'default'     => '',
		) );

		// ── Branding row: logo (image + dark variant) ────────────────────────
		mike_register_option( array(
			'id'          => 'footer_show_logo',
			'type'        => 'checkbox',
			'section'     => 'footer',
			'label'       => esc_html__( 'Show footer logo', 'mike' ),
			'description' => esc_html__( 'Shown on the left of the branding row. Falls back to the site title if no logo image is set below.', 'mike' ),
			'default'     => true,
		) );
		mike_register_option( array(
			'id'          => 'footer_logo',
			'type'        => 'image',
			'section'     => 'footer',
			'label'       => esc_html__( 'Footer logo', 'mike' ),
			'description' => esc_html__( 'Often a mono/white mark for a dark footer. Leave empty to show the site title as text.', 'mike' ),
			'default'     => '',
		) );
		mike_register_option( array(
			'id'          => 'footer_logo_dark',
			'type'        => 'image',
			'section'     => 'footer',
			'label'       => esc_html__( 'Footer dark mode logo', 'mike' ),
			'description' => esc_html__( 'Shown in the footer when dark mode is active. Optional — falls back to the footer logo above.', 'mike' ),
			'default'     => '',
		) );

		// ── Branding row: social icons (reuses the Social Links menu) ────────
		mike_register_option( array(
			'id'          => 'footer_show_social',
			'type'        => 'checkbox',
			'section'     => 'footer',
			'label'       => esc_html__( 'Show social icons in the footer', 'mike' ),
			'description' => esc_html__( 'Uses your Social Links menu.', 'mike' ) .
				' <a href="#" data-mike-focus-panel="nav_menus">' . esc_html__( 'Edit menus →', 'mike' ) . '</a><br>' .
				esc_html__( 'Shown on the right of the branding row.', 'mike' ),
			'default'     => true,
		) );

		// ── Footer bottom: copyright (left) — menu is the 'footer-menu' location.
		mike_register_option( array(
			'id'          => 'footer_copyright',
			'type'        => 'textarea',
			'section'     => 'footer',
			'label'       => esc_html__( 'Copyright text', 'mike' ),
			'description' => esc_html__( 'Shown after “© {year} {site}”. Leave blank to show just the © line. Basic HTML allowed.', 'mike' ),
			'default'     => '',
		) );
	}
endif;
add_action( 'customize_register', 'mike_register_options' );


/* -----------------------------------------------------------------------------
   Selective-refresh partials — Customizer "edit pencil" shortcuts.
   ---------------------------------------------------------------------------
   For each partial registered below, the Customizer preview draws a small blue
   pencil icon next to the matching element. Clicking it opens the relevant
   control(s) in the editing pane — the editor's "where do I change this?"
   shortcut. We do NOT supply a render_callback, so changes still trigger a
   full preview refresh (simplest + safest); the pencil itself still appears.

   `selector` must match exactly one stable element in the front-end markup.
   `settings` lists every theme_mod the partial represents — clicking the
   pencil focuses the first one. Listing multiple settings means changes to
   any of them route through this partial.
   ----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_register_partials' ) ) :
	function mike_register_partials( $wp_customize ) {
		// Selective refresh isn't available in every WP context (e.g. older cores
		// or stripped envs). Guard so theme-check stays clean.
		if ( ! isset( $wp_customize->selective_refresh ) ) {
			return;
		}

		// Logo / site title — jumps to the Logo group in the Header section.
		$wp_customize->selective_refresh->add_partial( 'mike_header_logo', array(
			'selector'        => '.site-branding',
			'settings'        => array( 'header_logo_light', 'header_logo_dark', 'header_logo_width' ),
			'container_inclusive' => false,
		) );

		// Primary nav — jumps to the menu locations control. (WP core also
		// auto-registers menu partials when the location is selective-refresh-
		// aware; ours is a coarse one that covers the whole <nav>.)
		$wp_customize->selective_refresh->add_partial( 'mike_header_primary_nav', array(
			'selector'        => '.primary-nav',
			'settings'        => array( 'nav_menu_locations[primary-menu]' ),
			'container_inclusive' => false,
		) );

		// Header CTA buttons — jumps to the Button 1/2 fields.
		$wp_customize->selective_refresh->add_partial( 'mike_header_buttons', array(
			'selector'        => '.header-buttons',
			'settings'        => array(
				'header_button_1_text', 'header_button_1_url', 'header_button_1_style', 'header_button_1_new_tab',
				'header_button_2_text', 'header_button_2_url', 'header_button_2_style', 'header_button_2_new_tab',
			),
			'container_inclusive' => false,
		) );
	}
endif;
add_action( 'customize_register', 'mike_register_partials' );
