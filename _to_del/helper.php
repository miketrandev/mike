<?php
/**
 * Shared helpers — tiny, pure utilities used across the theme.
 *
 * Pure functions only: no side effects, no WP queries, no hooks. Anything with
 * side effects belongs next to its caller (see inc/header.php's inline-CSS
 * emitter, which reads theme_mods and enqueues styles).
 *
 * Table of contents:
 *   mike_contrast_text( $hex )   → '#1a1a1a' | '#ffffff' | '' for bad input
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'mike_contrast_text' ) ) :
	/**
	 * Given a hex colour, return '#1a1a1a' (light bg) or '#ffffff' (dark bg).
	 * Uses WCAG relative luminance (sRGB, gamma-corrected). Threshold 0.5 — the
	 * standard cutoff; matches what every contrast picker on the web does.
	 *
	 * Returns '' for empty / invalid input so callers can use it as a guard:
	 *
	 *     $bg = '#222';
	 *     $tx = mike_contrast_text( $bg ); // '#ffffff'
	 *
	 * @param string $hex Hex string with or without leading #. 3- or 6-digit.
	 * @return string '#1a1a1a' | '#ffffff' | '' if input is unusable.
	 */
	function mike_contrast_text( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return '';
		}

		// sRGB → linear (WCAG 2.x relative-luminance formula).
		$srgb_to_linear = function ( $channel ) {
			$c = $channel / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		$r = $srgb_to_linear( hexdec( substr( $hex, 0, 2 ) ) );
		$g = $srgb_to_linear( hexdec( substr( $hex, 2, 2 ) ) );
		$b = $srgb_to_linear( hexdec( substr( $hex, 4, 2 ) ) );

		$luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

		// Dark text on light bg, light text on dark bg. '#1a1a1a' is --text.
		return $luminance > 0.5 ? '#1a1a1a' : '#ffffff';
	}
endif;
