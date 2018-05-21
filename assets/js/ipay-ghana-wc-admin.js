(function( $ ) {

	'use strict';

	$( document ).ready(function () {
		$( '.is-hidden' ).parents( 'tr' ).hide();
		$( '.is-read-only' ).attr( 'readonly', 'readonly' );
	} );

})( jQuery );
