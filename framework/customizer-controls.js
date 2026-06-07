/**
 * Mike — multi-value customizer controls
 *
 * WordPress binds one value per input via $this->link(). For multicheckbox /
 * multiselect we collect the picked values and write them, comma-joined, into
 * the hidden .mike-multi-value input that IS linked to the setting, then fire
 * a change so the customizer saves it.
 */
( function ( $ ) {
	'use strict';

	function sync( $control ) {
		var $hidden = $control.find( '.mike-multi-value' );
		var values = [];

		// Checkbox list.
		$control.find( '.mike-multicheck__input:checked' ).each( function () {
			values.push( this.value );
		} );

		// <select multiple>.
		$control.find( '.mike-multiselect__input option:checked' ).each( function () {
			values.push( this.value );
		} );

		$hidden.val( values.join( ',' ) ).trigger( 'change' );
	}

	$( document ).on(
		'change',
		'.mike-multicheck__input, .mike-multiselect__input',
		function () {
			sync( $( this ).closest( '.mike-multicheck, .mike-multiselect' ) );
		}
	);

	// Columns control: three number inputs (desktop/tablet/mobile) → one hidden
	// "d,t,m" value. Read in device order and write the joined string.
	function syncColumns( $control ) {
		var order = [ 'desktop', 'tablet', 'mobile' ];
		var parts = order.map( function ( device ) {
			var $input = $control.find( '.mike-columns__input[data-device="' + device + '"]' );
			var n = parseInt( $input.val(), 10 );
			return ( n >= 1 && n <= 6 ) ? n : ( device === 'desktop' ? 3 : device === 'tablet' ? 2 : 1 );
		} );
		$control.find( '.mike-columns-value' ).val( parts.join( ',' ) ).trigger( 'change' );
	}

	$( document ).on( 'change input', '.mike-columns__input', function () {
		syncColumns( $( this ).closest( '.mike-columns' ) );
	} );

	// Cross-section pointer: a button with data-mike-focus-section focuses that
	// section in the live preview (e.g. the Site Identity → Header logo pointer).
	// Uses the customizer API so it works inside the iframe-driven panel.
	$( document ).on( 'click', '[data-mike-focus-section]', function ( event ) {
		event.preventDefault();
		var id = this.getAttribute( 'data-mike-focus-section' );
		if ( id && window.wp && wp.customize && wp.customize.section( id ) ) {
			wp.customize.section( id ).focus();
		}
	} );

	// Cross-panel pointer: same idea for the top-level customizer panels (e.g.
	// "Menus" via panel id 'nav_menus'). Useful when a control needs to point
	// the editor at a whole panel rather than a section inside our own panel.
	$( document ).on( 'click', '[data-mike-focus-panel]', function ( event ) {
		event.preventDefault();
		var id = this.getAttribute( 'data-mike-focus-panel' );
		if ( id && window.wp && wp.customize && wp.customize.panel( id ) ) {
			wp.customize.panel( id ).focus();
		}
	} );
} )( jQuery );
