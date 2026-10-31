/**
 * The Focal point field in an image's details: clicking the image sets how
 * far across and down the point is, and the marker follows the two numbers.
 * The media pop-up saves its fields when one changes, so a click is saved
 * straight away there; on the image's own edit screen it's saved with the
 * rest of the form.
 *
 * The field is drawn again each time the pop-up shows an image, so events
 * are listened for on the document.
 *
 * @param {Object} $ jQuery.
 */
( function ( $ ) {
	function place( field ) {
		const x = field.find( '.galerio-focal-point__x' ).val();
		const y = field.find( '.galerio-focal-point__y' ).val();

		field.find( '.galerio-focal-point__marker' ).css( {
			left: ( '' === x ? 50 : x ) + '%',
			top: ( '' === y ? 50 : y ) + '%',
		} );
		field
			.find( '.galerio-focal-point__reset' )
			.prop( 'hidden', '' === x && '' === y );
	}

	$( document ).on( 'click', '.galerio-focal-point__image', function ( e ) {
		const field = $( this ).closest( '.galerio-focal-point' );
		const box = this.getBoundingClientRect();
		const percent = ( value ) =>
			Math.round( Math.min( 1, Math.max( 0, value ) ) * 100 );

		field
			.find( '.galerio-focal-point__x' )
			.val( percent( ( e.clientX - box.left ) / box.width ) );
		field
			.find( '.galerio-focal-point__y' )
			.val( percent( ( e.clientY - box.top ) / box.height ) );
		place( field );
		field.find( '.galerio-focal-point__x' ).trigger( 'change' );
	} );

	$( document ).on(
		'input change',
		'.galerio-focal-point__x, .galerio-focal-point__y',
		function () {
			place( $( this ).closest( '.galerio-focal-point' ) );
		}
	);

	$( document ).on( 'click', '.galerio-focal-point__reset', function () {
		const field = $( this ).closest( '.galerio-focal-point' );

		field
			.find( '.galerio-focal-point__x, .galerio-focal-point__y' )
			.val( '' );
		place( field );
		field.find( '.galerio-focal-point__x' ).trigger( 'change' );
	} );
} )( window.jQuery );
