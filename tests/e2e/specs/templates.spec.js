/**
 * The term archive, search and page templates, and page links on archives.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Theme templates', () => {
	test( 'a term archive lists every type with the term', async ( {
		page,
	} ) => {
		await page.goto( '/medium/ceramics/' );

		const main = page.getByRole( 'main' );
		await expect( main.getByRole( 'heading', { level: 1 } ) ).toHaveText(
			/Ceramics/i
		);
		await expect(
			main.getByRole( 'link', { name: 'Kiln Collective' } ).first()
		).toBeVisible();
		await expect(
			main.getByRole( 'link', { name: 'Ash Glaze Vessel' } ).first()
		).toBeVisible();
	} );

	test( "a list using the Card part shows each item with its type's card", async ( {
		page,
	} ) => {
		await page.goto( '/medium/ceramics/' );

		const main = page.getByRole( 'main' );
		await expect(
			main
				.locator( '.galerio-card-profile' )
				.filter( { hasText: 'Kiln Collective' } )
		).toBeVisible();
		await expect(
			main
				.locator( '.galerio-card-work' )
				.filter( { hasText: 'Ash Glaze Vessel' } )
		).toBeVisible();
	} );

	test( 'search shows its form, results, or a message', async ( {
		page,
	} ) => {
		await page.goto( '/?s=shore' );
		const main = page.getByRole( 'main' );
		await expect( main.getByRole( 'searchbox' ) ).toHaveValue( 'shore' );
		await expect(
			main.getByRole( 'link', { name: 'Shorelines' } ).first()
		).toBeVisible();

		await page.goto( '/?s=zzzz' );
		await expect(
			page.getByText( 'Nothing matched your search.' )
		).toBeVisible();
	} );

	test( 'archives link to their next page', async ( {
		page,
		requestUtils,
	} ) => {
		const { posts_per_page: perPage } =
			await requestUtils.getSiteSettings();
		await requestUtils.updateSiteSettings( { posts_per_page: 2 } );

		try {
			await page.goto( '/works/' );
			await page
				.getByRole( 'link', { name: 'Next', exact: true } )
				.click();
			await expect( page ).toHaveURL( /\/works\/page\/2\/$/ );

			// Page numbers, with the current one marked.
			await expect(
				page.locator(
					'.wp-block-query-pagination-numbers .page-numbers.current'
				)
			).toHaveText( '2' );
			await page
				.locator( '.wp-block-query-pagination-numbers' )
				.getByRole( 'link', { name: '1' } )
				.click();
			await expect( page ).toHaveURL( /\/works\/$/ );
		} finally {
			await requestUtils.updateSiteSettings( {
				posts_per_page: perPage,
			} );
		}
	} );
} );
