/**
 * The edit screens of Galerio's content types.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Edit screen', () => {
	test( 'names the meta box pane and keeps accolades short', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const type = await requestUtils.rest( {
			path: '/wp/v2/types/galerio_profile',
			params: { context: 'edit' },
		} );
		const name = type.labels.singular_name;

		await admin.createNewPost( { postType: 'galerio_profile' } );

		const pane = page.getByRole( 'button', { name: `${ name } settings` } );
		await expect( pane ).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Meta Boxes' } )
		).toHaveCount( 0 );

		// The pane starts collapsed. Its resize handle covers the middle of
		// the toggle, so it's opened from the keyboard.
		if ( ( await pane.getAttribute( 'aria-expanded' ) ) === 'false' ) {
			await pane.focus();
			await page.keyboard.press( 'Enter' );
		}

		const accolade = page
			.locator( 'textarea[name^="pods_meta_accolades"]' )
			.first();
		await accolade.scrollIntoViewIfNeeded();
		const box = await accolade.boundingBox();
		expect( box.height ).toBeLessThan( 60 );
	} );
} );
