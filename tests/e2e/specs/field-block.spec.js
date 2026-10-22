/**
 * The Galerio Field block in the block editor.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Field block', () => {
	test( 'previews a field of the post being edited', async ( {
		admin,
		editor,
		requestUtils,
	} ) => {
		const [ profile ] = await requestUtils.rest( {
			path: '/wp/v2/galerio_profile',
			params: { slug: 'ana-morgan', context: 'edit' },
		} );

		await admin.editPost( profile.id );
		await editor.insertBlock( {
			name: 'galerio/field-display',
			attributes: { key: 'profile.bio', label: 'About' },
		} );

		const block = editor.canvas
			.locator( '[data-type="galerio/field-display"]' )
			.last();
		await expect( block ).toContainText( 'About' );
		await expect( block ).toContainText( 'Gower coast' );
	} );
} );
