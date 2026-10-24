/**
 * The folder tree in the Media Library: add a folder, drag a file onto it,
 * and open it.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Media folders', () => {
	const name = 'Test folder';

	test.afterAll( async ( { requestUtils } ) => {
		const folders = await requestUtils.rest( {
			path: '/wp/v2/media-folders',
			params: { search: name, per_page: 100 },
		} );
		for ( const folder of folders ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/media-folders/${ folder.id }`,
				params: { force: true },
			} );
		}
	} );

	test( 'files a file by dragging it onto a new folder', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/upload.php?mode=grid' );

		const tree = page.getByRole( 'navigation', { name: 'Folders' } );
		await expect( tree.getByText( 'All files' ) ).toBeVisible();

		await tree.getByRole( 'button', { name: 'New' } ).first().click();
		await tree.getByLabel( 'New folder name' ).fill( name );
		await tree.getByRole( 'button', { name: 'Save' } ).click();
		const folder = tree.getByRole( 'button', { name } );
		await expect( folder ).toBeVisible();

		const file = page.locator( 'li.attachment' ).first();
		const title = await file.getAttribute( 'aria-label' );
		await file.dragTo( folder );
		await expect( tree.getByText( '1 moved.' ) ).toBeVisible();

		await folder.click();
		await expect( page.locator( 'li.attachment' ) ).toHaveCount( 1 );
		await expect( page.locator( 'li.attachment' ) ).toHaveAttribute(
			'aria-label',
			title
		);
	} );
} );
