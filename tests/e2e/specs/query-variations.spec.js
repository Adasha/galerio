/**
 * Galerio's Query Loop variations, from the inserter to the page.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Query Loop variations', () => {
	test( 'lists current and upcoming exhibitions only', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		await admin.createNewPost( { postType: 'page', title: 'On now' } );

		await page
			.getByRole( 'region', { name: 'Editor top bar' } )
			.getByRole( 'button', { name: 'Block Inserter' } )
			.click();
		await page
			.getByRole( 'searchbox', { name: 'Search' } )
			.fill( 'current and upcoming' );
		await page
			.getByRole( 'option', {
				name: /Galerio current and upcoming collections/,
			} )
			.click();

		await editor.openDocumentSettingsSidebar();
		await expect(
			page.getByRole( 'combobox', { name: 'Show' } )
		).toHaveValue( 'current-upcoming' );

		const id = await editor.publishPost();
		const [ published ] = await requestUtils.rest( {
			path: '/wp/v2/pages',
			params: { include: [ id ] },
		} );

		await page.goto( published.link );
		const list = page.locator( '.wp-block-post-template' );
		await expect( list ).toContainText( 'Shorelines' );
		await expect( list ).not.toContainText( 'Studio Ceramics' );
		await expect( list ).toContainText( 'On now' );

		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/pages/${ id }`,
			params: { force: true },
		} );
	} );
} );
