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

	test( 'the list view has the tree: a subfolder, a dragged row and uploads into the open folder', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/upload.php?mode=list' );

		const tree = page.getByRole( 'navigation', { name: 'Folders' } );
		await expect( tree.getByText( 'Not in a folder' ) ).toBeVisible();

		// A folder, then one inside it from the chosen folder's actions.
		await tree.getByRole( 'button', { name: 'New' } ).first().click();
		await tree.getByLabel( 'New folder name' ).fill( `${ name } A` );
		await tree.getByRole( 'button', { name: 'Save' } ).click();
		await tree
			.getByRole( 'button', { name: new RegExp( `^${ name } A` ) } )
			.click();
		await page.waitForURL( /galerio_folder=\d+/ );

		await tree.getByRole( 'button', { name: 'New folder inside' } ).click();
		await expect(
			tree.getByText( `New folder inside “${ name } A”` )
		).toBeVisible();
		await tree.getByLabel( 'New folder name' ).fill( `${ name } B` );
		await tree.getByRole( 'button', { name: 'Save' } ).click();
		const inner = tree.getByRole( 'button', {
			name: new RegExp( `^${ name } B` ),
		} );
		await expect( inner ).toBeVisible();

		// A row dragged from all files onto the subfolder.
		await tree.getByRole( 'button', { name: 'All files' } ).click();
		await page.waitForURL(
			( url ) => ! url.search.includes( 'galerio_folder' )
		);
		const row = page.locator( '#the-list tr[id^="post-"]' ).first();
		const id = await row.getAttribute( 'id' );
		// The row stays in view, so the list reloads to show its new folder.
		const reloaded = page.waitForEvent( 'load' );
		await row.dragTo( inner );
		await reloaded;

		await inner.click();
		await page.waitForURL( /galerio_folder=\d+/ );
		await expect( page.locator( '#the-list tr[id^="post-"]' ) ).toHaveCount(
			1
		);
		await expect( page.locator( `#${ id }` ) ).toBeVisible();

		// Add New uploads into the open folder.
		await expect(
			page.locator( '.wrap .page-title-action' ).first()
		).toHaveAttribute( 'href', /galerio_folder=\d+/ );
	} );
} );
