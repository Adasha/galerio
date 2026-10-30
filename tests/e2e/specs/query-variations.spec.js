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

		// The cards' dates are previewed from the records the editor has
		// already loaded, not a server render each, which on a live host
		// added up to enough requests to trip its firewall.
		let renders = 0;
		page.on( 'request', ( request ) => {
			if ( request.url().includes( 'block-renderer' ) ) {
				renders++;
			}
		} );

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

		await expect(
			editor.canvas
				.locator( '.wp-block-galerio-exhibition-dates' )
				.filter( { hasText: 'On now' } )
				.first()
		).toBeVisible();
		await expect(
			editor.canvas
				.locator( '.wp-block-galerio-exhibition-dates time' )
				.first()
		).toBeVisible();

		await editor.openDocumentSettingsSidebar();
		await expect(
			page.getByRole( 'combobox', { name: 'Show' } )
		).toHaveValue( 'current-upcoming' );

		expect( renders ).toBe( 0 );

		// The preview reads as the page does.
		const previews = await editor.canvas
			.locator( '.wp-block-galerio-exhibition-dates' )
			.allInnerTexts();

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
		for ( const preview of previews.filter( ( text ) =>
			/\d/.test( text )
		) ) {
			await expect( list ).toContainText( preview, { ignoreCase: true } );
		}

		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/pages/${ id }`,
			params: { force: true },
		} );
	} );
} );
