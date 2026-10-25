/**
 * The collection card and work details patterns.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Theme patterns', () => {
	test( 'the collections archive shows each collection as a card', async ( {
		page,
	} ) => {
		await page.goto( '/collections/' );

		const card = page
			.locator( '.galerio-collection-card' )
			.filter( { hasText: 'Shorelines' } );
		await expect( card ).toBeVisible();
		await expect( card.getByText( 'On now' ) ).toBeVisible();
		await expect(
			card.getByRole( 'link', { name: /View collection/ } )
		).toHaveAttribute( 'href', /\/collection\/shorelines\/$/ );
	} );

	test( 'the work details table hides empty rows', async ( {
		page,
		requestUtils,
	} ) => {
		const work = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/galerio_work',
			data: { title: 'Untitled study', status: 'publish' },
		} );

		try {
			await page.goto( work.link );
			const details = page.locator( '.galerio-details' );
			await expect( details.getByText( 'Medium' ) ).toBeHidden();
			await expect( details.getByText( 'Dimensions' ) ).toBeHidden();
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/galerio_work/${ work.id }`,
				params: { force: true },
			} );
		}
	} );
} );
