<?php
/**
 * SVG icon library — one source of truth for inline icons in the theme.
 *
 * Why inline SVG: crisp, recolours with `currentColor`, no extra request, no
 * FOUT. Paths are stored WITHOUT the outer <svg>; mike_icon() builds the
 * wrapper so size/class/accessibility are applied in one place.
 *
 * Icons are 24×24, stroke style, inherit colour via currentColor.
 *
 * TABLE OF CONTENTS
 * ------------------------------------------------
 * 1. mike_icon_map  - name → SVG inner paths
 * 2. mike_icon      - echo one icon by name, wrapped in <svg>
 *
 * @package Mike
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* 1. mike_icon_map - name → SVG inner paths
------------------------------------------------ */

if ( ! function_exists( 'mike_icon_map' ) ) :
	function mike_icon_map() {
		return array(
			'search' => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
			'menu'    => '<line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/>',
			'close'   => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
			'comment' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
		);
	}
endif;

/* 2. mike_icon - echo one icon by name, wrapped in <svg>
------------------------------------------------ */

if ( ! function_exists( 'mike_icon' ) ) :
	/**
	 * Echo one inline SVG icon. Decorative by default (aria-hidden) since it sits
	 * next to a label or screen-reader text. Pass 'label' to make it a standalone
	 * labelled graphic (role="img" + aria-label).
	 *
	 * @param string $name  Icon key (see mike_icon_map()).
	 * @param array  $attrs { @type string $class, @type int $size, @type string $label }
	 */
	function mike_icon( $name, $attrs = array() ) {
		$map = mike_icon_map();
		if ( ! isset( $map[ $name ] ) ) {
			return;
		}

		$class = 'mike-icon mike-icon--' . $name;
		if ( ! empty( $attrs['class'] ) ) {
			$class .= ' ' . $attrs['class'];
		}
		$size = isset( $attrs['size'] ) ? (int) $attrs['size'] : 24;

		$label = isset( $attrs['label'] ) ? trim( (string) $attrs['label'] ) : '';
		if ( '' !== $label ) {
			$a11y  = ' role="img" aria-label="' . esc_attr( $label ) . '"';
			$title = '<title>' . esc_html( $label ) . '</title>';
		} else {
			$a11y  = ' aria-hidden="true" focusable="false"';
			$title = '';
		}

		printf(
			'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"%3$s>%4$s%5$s</svg>',
			esc_attr( $class ),
			$size,
			$a11y,  // phpcs:ignore WordPress.Security.EscapeOutput -- esc_attr applied above
			$title, // phpcs:ignore WordPress.Security.EscapeOutput -- esc_html applied above
			$map[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput -- static icon paths from the internal map
		);
	}
endif;
