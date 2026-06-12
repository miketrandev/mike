/**
 * Off-canvas menu — keyboard accessibility.
 *
 * The open/close toggle itself is inline onclick in header.php (no JS file
 * needed for that). This file adds only the two things inline handlers can't
 * do: Escape-to-close, and trapping Tab focus inside the open dialog.
 *
 * Loaded only when "Enhanced menu keyboard support" is on (Customize > Misc).
 */
document.addEventListener( 'keydown', function ( e ) {
	var panel = document.getElementById( 'mike-offcanvas' );
	// The panel is "open" when it carries the is-open class (CSS-driven).
	if ( ! panel || ! panel.classList.contains( 'is-open' ) ) {
		return;
	}

	// Escape closes the panel — mirror the inline close: drop the class,
	// unlock scroll, and return focus to the hamburger.
	if ( 'Escape' === e.key ) {
		panel.classList.remove( 'is-open' );
		document.body.classList.remove( 'has-offcanvas-open' );
		var burger = document.querySelector( '.header-hamburger' );
		if ( burger ) {
			burger.setAttribute( 'aria-expanded', 'false' );
			burger.focus();
		}
		return;
	}

	// Trap Tab inside the open panel.
	if ( 'Tab' === e.key ) {
		var f = panel.querySelectorAll( 'a[href], button, input, textarea, select, [tabindex]:not([tabindex="-1"])' );
		if ( ! f.length ) {
			return;
		}
		var first = f[0];
		var last  = f[ f.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}
} );
