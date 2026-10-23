/**
 * Settings → Galerio content types: fill the form in from a preset.
 *
 * Each preset option carries its values in `data-preset`. Filling in only
 * changes the form; nothing is saved until the form is submitted. Boxes a
 * preset leaves out are emptied, so they fall back to the registered names.
 */
( function () {
	const select = document.getElementById( 'galerio-alias-preset' );
	const button = document.getElementById( 'galerio-alias-preset-apply' );

	if ( ! select || ! button ) {
		return;
	}

	button.addEventListener( 'click', function () {
		const option = select.options[ select.selectedIndex ];

		if ( ! option || ! option.dataset.preset ) {
			return;
		}

		const preset = JSON.parse( option.dataset.preset );

		document
			.querySelectorAll( '.galerio-aliases input[data-property]' )
			.forEach( function ( input ) {
				const { kind, key, property } = input.dataset;
				const value = preset[ kind ]?.[ key ]?.[ property ];

				input.value = typeof value === 'string' ? value : '';
			} );
	} );
} )();
