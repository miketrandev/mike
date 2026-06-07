<?php
/**
 * Homepage section sidebars — the builder's only "soft grid".
 * -----------------------------------------------------------------------------
 * The homepage builder is a FLAT flow of sections (no row/column page-builder).
 * The one concession to side-by-side layout is the section sidebar: a section
 * can opt into a ~240px rail filled with classic widgets. It's "soft" because it
 * uses WordPress's own sidebar/widget vocabulary — editors already know it —
 * not a bespoke grid.
 *
 * We register a small POOL of widget areas (not a sidebar generator). A section
 * picks which one (None / 1 / 2 / 3) in its Section tab; the same area can be
 * reused on several sections (e.g. one sidebar all the way down the page) or
 * each section can use a different one (editorial: "editors" here, "ads" there).
 *
 * Editors fill these in Appearance → Widgets (or Customizer → Widgets) — the
 * native place. The render shows nothing if the chosen area is empty.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mike_section_sidebars' ) ) :
	/**
	 * The pool of homepage section sidebars: id => label. Single source of truth —
	 * the registration below, the Section-tab dropdown choices, and the render all
	 * read this. To add/remove a sidebar, change ONLY this list.
	 */
	function mike_section_sidebars() {
		return array(
			'mike-custom-sidebar-1' => esc_html__( 'Homepage Sidebar 1', 'mike' ),
			'mike-custom-sidebar-2' => esc_html__( 'Homepage Sidebar 2', 'mike' ),
			'mike-custom-sidebar-3' => esc_html__( 'Homepage Sidebar 3', 'mike' ),
		);
	}
endif;

if ( ! function_exists( 'mike_section_sidebar_bar' ) ) :
	/**
	 * Render the inner contents of a section sidebar rail. Three states, never
	 * silently conflated (a stale/bad value must LOOK wrong, not look like "None"):
	 *  - Registered + active (has widgets) → the normal dynamic_sidebar() output.
	 *  - Registered + empty → editor-only "add widgets" notice (placeholder image
	 *    + new-tab link to the Widgets screen). Visitors see an empty rail.
	 *  - NOT registered (stale id — e.g. saved before a sidebar was renamed/removed,
	 *    or a theme update changed the pool) → editor-only WARNING that the chosen
	 *    sidebar no longer exists, so the data problem surfaces instead of masquerading
	 *    as empty/None. Visitors see nothing (we never show a broken rail to them).
	 * The wrapping <aside class="mike-withside__bar"> is emitted by the caller.
	 *
	 * @param string $sidebar_id A mike_section_sidebars() id (possibly stale).
	 */
	function mike_section_sidebar_bar( $sidebar_id ) {
		$registered = array_key_exists( $sidebar_id, mike_section_sidebars() );

		// Real, filled sidebar → render it. (is_active_sidebar covers registration
		// AND has-widgets; the array check above is what tells empty from stale.)
		if ( $registered && is_active_sidebar( $sidebar_id ) ) {
			dynamic_sidebar( $sidebar_id );
			return;
		}

		// Everything below is an editor-only helper. Visitors get an empty rail
		// (the layout keeps it) and never see a notice or a broken-data warning.
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		// Stale id: the section points at a sidebar that isn't in the pool. This is
		// a DATA condition (import, a DB/AI edit, or a sidebar renamed in a later
		// theme version) — NOT a code bug — so log it as a fact via error_log(),
		// not _doing_it_wrong() (which asserts a developer called the API wrong and
		// would point at the wrong culprit). The editor-facing fix is the warning
		// rendered below; this line is just a developer breadcrumb under WP_DEBUG.
		if ( ! $registered ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'Mike: section sidebar "%s" is set to a widget area that is not registered (stale value — check the section in the Customizer).', $sidebar_id ) );
			}
			?>
			<div class="mike-sidebar-empty mike-sidebar-empty--stale" role="note">
				<p class="mike-sidebar-empty__text">
					<?php
					printf(
						/* translators: %s: the stale sidebar id stored in the section. */
						esc_html__( 'This section points at a sidebar that no longer exists (“%s”). Open this section’s Section tab and pick a current sidebar (or “None”). Only logged-in editors see this.', 'mike' ),
						esc_html( $sidebar_id )
					);
					?>
				</p>
				<?php
				// In the preview, jump to the Homepage builder control (where the
				// editor re-picks the sidebar); on the live front end, link to the
				// Customizer. The fix for a stale value is in the builder, not Widgets.
				if ( is_customize_preview() ) :
					?>
					<a class="mike-sidebar-empty__link" href="#" data-mike-focus-builder>
						<?php esc_html_e( 'Open the magazine page', 'mike' ); ?>
					</a>
				<?php else : ?>
					<a class="mike-sidebar-empty__link" href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Open the Customizer', 'mike' ); ?>
					</a>
				<?php endif; ?>
			</div>
			<?php
			return;
		}

		// Registered but empty → the shared "add widgets" helper.
		$sidebars = mike_section_sidebars();
		mike_sidebar_empty_notice( $sidebar_id, $sidebars[ $sidebar_id ] );
	}
endif;

if ( ! function_exists( 'mike_sidebar_empty_notice' ) ) :
	/**
	 * The editor-only "this sidebar is empty — add widgets" notice. Shared by the
	 * homepage section sidebars AND the archive sidebar. Caller decides WHEN to show
	 * it (i.e. sidebar chosen but not active); this just renders the notice, and
	 * only for users who can manage widgets — visitors get nothing (empty rail).
	 * In the Customizer preview the link focuses the Widgets UI in-flow; on the live
	 * front end it links out to Appearance → Widgets.
	 *
	 * @param string $sidebar_id The widget-area id (for the in-flow focus link).
	 * @param string $name       The sidebar's display name.
	 */
	function mike_sidebar_empty_notice( $sidebar_id, $name ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}
		// Placeholder instruction art. Swap this filename for your screenshot (e.g.
		// sidebar-empty.jpg) — it's the only line to change.
		$placeholder = get_template_directory_uri() . '/builder/img/sidebar-empty.svg';
		?>
		<div class="mike-sidebar-empty" role="note">
			<img class="mike-sidebar-empty__img" src="<?php echo esc_url( $placeholder ); ?>" alt="" />
			<p class="mike-sidebar-empty__text">
				<?php
				printf(
					/* translators: %s: the sidebar's name, e.g. "Sidebar". */
					esc_html__( '“%s” is empty. Only you (logged-in editors) see this — visitors see nothing here.', 'mike' ),
					esc_html( $name )
				);
				?>
			</p>
			<?php
			// In the Customizer preview, keep the editor IN FLOW: the link focuses
			// THIS sidebar's panel in the Customizer's own Widgets UI (handled by
			// customizer-builder.js via a preview→controls message) — no trip to wp-admin. On
			// the normal logged-in front end, link out to Appearance → Widgets.
			if ( is_customize_preview() ) :
				?>
				<a class="mike-sidebar-empty__link" href="#" data-mike-focus-widgets="<?php echo esc_attr( $sidebar_id ); ?>">
					<?php esc_html_e( 'Add widgets to this sidebar', 'mike' ); ?>
				</a>
			<?php else : ?>
				<a class="mike-sidebar-empty__link" href="<?php echo esc_url( admin_url( 'widgets.php' ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Add widgets in Appearance → Widgets', 'mike' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}
endif;

if ( ! function_exists( 'mike_register_sidebars' ) ) :
	function mike_register_sidebars() {
		// The main content sidebar — the rail a single post (or any sidebar-using
		// template) shows. Distinct from the homepage section pool below: this is the
		// conventional "Sidebar" an editor expects in Appearance → Widgets, and the
		// Single Post house-style option points at it by default.
		register_sidebar( array(
			'id'            => 'mike-sidebar',
			'name'          => esc_html__( 'Sidebar', 'mike' ),
			'description'   => esc_html__( 'The main content sidebar, shown beside single posts set to “With sidebar”. Add widgets like Recent Posts, Categories, Newsletter, an ad, etc.', 'mike' ),
			'before_widget' => '<section id="%1$s" class="widget mike-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title mike-widget__title">',
			'after_title'   => '</h2>',
		) );

		foreach ( mike_section_sidebars() as $id => $name ) {
			register_sidebar( array(
				'id'            => $id,
				'name'          => $name,
				'description'   => esc_html__( 'Shown in any homepage section set to use this sidebar (Section tab → Sidebar). Add widgets like Recent Posts, Categories, Newsletter, an ad, etc.', 'mike' ),
				'before_widget' => '<section id="%1$s" class="widget mike-widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="widget-title mike-widget__title">',
				'after_title'   => '</h2>',
			) );
		}

		// ── Footer widget area ───────────────────────────────────────────────
		// Classic widget area, not a footer builder (NO-list: presets + classic
		// widgets). One widget = one equal column (CSS auto-flow), so there's no
		// column-count knob. A site-wide newsletter / legal-links nav / about blurb
		// all just live here as widgets. The footer MENU (legal links inline at the
		// very bottom) is separate — a nav-menu location rendered in the footer bottom.
		register_sidebar( array(
			'id'            => 'mike-footer',
			'name'          => esc_html__( 'Footer', 'mike' ),
			'description'   => esc_html__( 'The footer. Each widget here becomes its own column. Add About, Recent Posts, Categories, a newsletter form, a navigation menu, etc.', 'mike' ),
			'before_widget' => '<section id="%1$s" class="widget mike-widget footer-widgets__col %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title mike-widget__title">',
			'after_title'   => '</h2>',
		) );
	}
endif;
add_action( 'widgets_init', 'mike_register_sidebars' );


/* -----------------------------------------------------------------------------
   Widget output filters — surgical, opt-in HTML tweaks for WP core widgets.
   ---------------------------------------------------------------------------
   WP core widgets emit fixed HTML that doesn't always read well in a modern
   sidebar (e.g. Categories widget appends " (8)" as a bare suffix to each
   link). Each filter below targets ONE widget's output and is documented
   with the WHY so a future change knows what's safe to remove.
   ----------------------------------------------------------------------------- */

if ( ! function_exists( 'mike_widget_categories_count_markup' ) ) :
	/**
	 * Wrap the trailing " (N)" count in Categories widget links with a real span
	 * so we can style it (small, gray, possibly hide).
	 *
	 * WP core's Walker_Category renders `<a>Name</a> (N)` — count is a bare
	 * text suffix AFTER the closing </a>, not inside it (see WP core
	 * class-walker-category.php lines 186-194). So we do a two-step
	 * str_replace on the finished list HTML: turn `</a> (` into
	 * `</a><span class="cat-count">`, and the matching `)` into `</span>`.
	 *
	 * Same idiom as Fox's fox56_cat_count_span(). The taxonomy guard skips
	 * WooCommerce product categories (which also use wp_list_categories and
	 * would otherwise get rewritten too).
	 *
	 * @param string $output The HTML output of wp_list_categories().
	 * @param array  $args   The args passed to wp_list_categories().
	 * @return string Filtered output with .cat-count spans.
	 */
	function mike_widget_categories_count_markup( $output, $args = array() ) {
		if ( empty( $args['show_count'] ) ) {
			return $output;
		}
		// Skip non-category taxonomies (e.g. WooCommerce product_cat) — leaves
		// their counts as the raw "(N)" suffix WP emits, so other themes/plugins
		// that own that taxonomy aren't surprised by our markup change.
		if ( isset( $args['taxonomy'] ) && 'category' !== $args['taxonomy'] ) {
			return $output;
		}
		$output = str_replace( '</a> (', '</a><span class="cat-count">', $output );
		$output = str_replace( ')', '</span>', $output );
		return $output;
	}
endif;
add_filter( 'wp_list_categories', 'mike_widget_categories_count_markup', 10, 2 );
