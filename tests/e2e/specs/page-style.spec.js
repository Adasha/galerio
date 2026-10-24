/**
 * A collection's own style, set from the editor's Page style panel.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Page style', () => {
	let collection;

	test.beforeAll( async ( { requestUtils } ) => {
		collection = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/galerio_collection',
			data: { title: 'Styled collection', status: 'publish' },
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/galerio_collection/${ collection.id }`,
			params: { force: true },
		} );
	} );

	test( 'sets a font for one collection, previewed and published', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.editPost( collection.id );
		await editor.openDocumentSettingsSidebar();

		// A closed panel by default; the editor remembers it if opened before.
		const panel = page.getByRole( 'button', {
			name: 'Page style',
			exact: true,
		} );
		if ( ( await panel.getAttribute( 'aria-expanded' ) ) === 'false' ) {
			await panel.click();
		}
		await expect(
			page.getByText( 'Uses the site’s style.' )
		).toBeVisible();

		await page.getByRole( 'button', { name: 'Edit page style…' } ).click();
		const dialog = page.getByRole( 'dialog', { name: 'Page style' } );
		await dialog.getByLabel( 'Heading' ).selectOption( { label: 'Arvo' } );
		await dialog.getByRole( 'button', { name: 'Done' } ).click();

		await expect( page.getByText( 'Own style: 1 font.' ) ).toBeVisible();
		await expect
			.poll( () =>
				editor.canvas
					.locator( 'body' )
					.evaluate( ( body ) =>
						body.ownerDocument.defaultView
							.getComputedStyle( body )
							.getPropertyValue(
								'--wp--preset--font-family--heading'
							)
					)
			)
			.toContain( 'Arvo' );

		await page
			.getByRole( 'region', { name: 'Editor top bar' } )
			.getByRole( 'button', { name: 'Save', exact: true } )
			.click();
		await expect(
			page.getByRole( 'button', { name: 'Dismiss this notice' } ).first()
		).toContainText( 'updated' );
		await page.goto( collection.link );
		expect(
			await page
				.locator( '#galerio-page-style-inline-css' )
				.evaluate( ( style ) => style.textContent )
		).toContain( '--wp--preset--font-family--heading:Arvo' );
	} );
} );
