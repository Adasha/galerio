/**
 * Items without a featured image: their own image, or a plain colour band.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Image fallback', () => {
	test( 'a collection with no image shows a colour band', async ( {
		page,
		requestUtils,
	} ) => {
		const collection = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/galerio_collection',
			data: { title: 'Imageless collection', status: 'publish' },
		} );

		try {
			await page.goto( '/collections/' );
			const item = page.locator( `.post-${ collection.id }` );
			const band = item.locator(
				'.wp-block-cover.galerio-cover-no-image'
			);

			await expect( band ).toBeVisible();
			expect(
				await band.evaluate(
					( cover ) =>
						cover.ownerDocument.defaultView.getComputedStyle(
							cover
						).backgroundColor
				)
			).not.toBe( 'rgba(0, 0, 0, 0)' );
			await expect( item ).toContainText( 'Imageless collection' );

			// A collection with an image isn't marked.
			await expect(
				page.locator( '.galerio-cover-no-image' )
			).toHaveCount( 1 );
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/galerio_collection/${ collection.id }`,
				params: { force: true },
			} );
		}
	} );
} );
