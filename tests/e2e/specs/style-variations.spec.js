/**
 * The theme's style variations are offered in the Site Editor.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test( 'offers the Gallery White, Dark Room and Editorial variations', async ( {
	requestUtils,
} ) => {
	const variations = await requestUtils.rest( {
		path: '/wp/v2/global-styles/themes/galerio/variations',
	} );

	expect( variations.map( ( variation ) => variation.title ).sort() ).toEqual(
		[ 'Dark Room', 'Editorial', 'Gallery White' ]
	);
} );
