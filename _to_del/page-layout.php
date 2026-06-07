<?php
/**
 * Page layout — per-page width + sidebar choice.
 * -----------------------------------------------------------------------------
 * Five layouts, picked per-page from a "Page Layout" panel in the block editor
 * (js/editor.js → MikePageLayoutPanel). Mirrors the per-POST layout system
 * (inc/single.php → mike_single_types), but pages don't get hero variants —
 * pages are static documents, not articles. Five layouts cover the editorial
 * cases: narrow reading column, two with-sidebar variants, wide, full-bleed.
 *
 *   default     → "Default (Narrow)" — narrow reading column (~--reading
 *                 width). The current page default; an About / Privacy page
 *                 reads at editorial measure. Stored when the editor leaves
 *                 the panel alone, so the page.php contract stays
 *                 backwards-compatible.
 *
 *   with-sidebar      → "Sidebar right" — main column + 240px rail right.
 *   with-sidebar-left → "Sidebar left"  — same shape, rail on the left.
 *                       Both reuse .mike-withside (same grid + shared
 *                       "mike-sidebar" widget area used by posts, archives,
 *                       and the magazine page).
 *
 *   wide        → "Full width" — page content fills .container width
 *                 (~1170px). For landing pages, photo essays, or any page
 *                 where blocks should stretch wider than reading measure
 *                 without going edge-to-edge.
 *
 *   full-bleed  → "Full width (edge-to-edge)" — no .container at all.
 *                 Content blocks own their own width; align="wide" /
 *                 align="full" work as the editor expects. Padding collapses
 *                 to zero (especially when the page title is hidden) so the
 *                 editor has a true full canvas.
 *
 * TOC:
 *   1. Page meta-key constants       — _mike_page_layout, _mike_flush_top, _mike_flush_bottom
 *   2. mike_page_layouts()          — the editor-facing choice list (label → value)
 *   3. mike_page_layout()           — read+validate the meta for a given page
 *   4. mike_page_layout_body_class()— add page-layout + flush classes to <body>
 *                                      so SCSS can override .site-main padding etc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 1. Page meta-key constants. */

// Layout choice (default | with-sidebar | with-sidebar-left | wide | full-bleed).
const MIKE_PAGE_LAYOUT_META = '_mike_page_layout';

// "No padding top" — drop .site-main's top padding so the first block touches
// the header. Common request: a Cover block as the first block, no gap above it.
const MIKE_PAGE_FLUSH_TOP_META = '_mike_flush_top';

// "No padding bottom" — drop .site-main's bottom padding so the last block touches
// the footer. For pages ending in a CTA band or full-bleed image.
const MIKE_PAGE_FLUSH_BOTTOM_META = '_mike_flush_bottom';

/* 2. mike_page_layouts() — the layout vocabulary, single source of truth.
   Used by the meta registration (sanitize gate), the block-editor panel
   (radio choices), and page.php (branch labels). Keyed by the value stored
   in meta; the label is what the editor sees. */

if ( ! function_exists( 'mike_page_layouts' ) ) :
	function mike_page_layouts() {
		// Labels are written to teach the editor what each option does WITHOUT
		// reading docs. "Default (Narrow)" makes the relative scale obvious in
		// the radio list; the two full-width options name where the bleed
		// stops (.container cap vs. viewport edge). Sidebar names mirror the
		// rail's position (right/left), no "with-sidebar" preamble — single
		// posts have a global "has sidebar" customizer toggle that needs that
		// preamble; pages don't, so we drop it.
		return array(
			'default'           => esc_html__( 'Default (Narrow)', 'mike' ),
			'with-sidebar'      => esc_html__( 'Sidebar right', 'mike' ),
			'with-sidebar-left' => esc_html__( 'Sidebar left', 'mike' ),
			'wide'              => esc_html__( 'Full width', 'mike' ),
			'full-bleed'        => esc_html__( 'Full width (edge-to-edge)', 'mike' ),
		);
	}
endif;

/* 3. mike_page_layout() — resolve the meta to a guaranteed-valid layout key.
   Defaults to 'default' on empty, missing, or invalid values. Single trust
   boundary: page.php trusts whatever this returns. */

if ( ! function_exists( 'mike_page_layout' ) ) :
	/**
	 * @param int|null $page_id Page ID (defaults to the current page in the loop).
	 * @return string One of the keys from mike_page_layouts() — never anything else.
	 */
	function mike_page_layout( $page_id = null ) {
		$page_id = $page_id ? (int) $page_id : (int) get_the_ID();
		$value   = (string) get_post_meta( $page_id, MIKE_PAGE_LAYOUT_META, true );
		$choices = mike_page_layouts();
		return array_key_exists( $value, $choices ) ? $value : 'default';
	}
endif;

/* 4. mike_page_layout_body_class() — surface page-level layout decisions on
   <body> so CSS can override surfaces ABOVE the article (e.g. .site-main
   padding). Same pattern as the magazine-page body class. Only fires on
   singular pages.

   Classes added:
     - `mike-page-layout-{key}`  — always, one of the mike_page_layouts() keys.
     - `mike-flush-top`           — when _mike_flush_top is true (drops top
                                      padding of .site-main).
     - `mike-flush-bottom`        — when _mike_flush_bottom is true (drops
                                      bottom padding of .site-main).

   "Flush" is independent of layout: a Default-Narrow page can be flush-top
   (Cover-at-the-top use case), and a Full-bleed page can choose to keep
   default vertical breathing room. The flush toggles live in the editor's
   "Page Layout" panel alongside the radio. */

if ( ! function_exists( 'mike_page_layout_body_class' ) ) :
	function mike_page_layout_body_class( $classes ) {
		if ( ! is_singular( 'page' ) ) {
			return $classes;
		}
		$id = (int) get_queried_object_id();
		if ( $id < 1 ) {
			return $classes;
		}

		$classes[] = 'mike-page-layout-' . mike_page_layout( $id );

		if ( (bool) get_post_meta( $id, MIKE_PAGE_FLUSH_TOP_META, true ) ) {
			$classes[] = 'mike-flush-top';
		}
		if ( (bool) get_post_meta( $id, MIKE_PAGE_FLUSH_BOTTOM_META, true ) ) {
			$classes[] = 'mike-flush-bottom';
		}

		return $classes;
	}
endif;
add_filter( 'body_class', 'mike_page_layout_body_class' );
