/**
 * An image's focal point: set in the Media Library, kept in view on the site.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Focal point', () => {
	let work;
	let image;

	test.beforeAll( async ( { requestUtils } ) => {
		[ work ] = await requestUtils.rest( {
			path: '/wp/v2/galerio_work',
			params: { per_page: 1, search: 'Low Tide' },
		} );
		image = work.galerio_fields.definitive_image;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/media/${ image }`,
			data: { meta: { galerio_focal_point: null } },
		} );
	} );

	test( 'clicking the image in its details sets the point, which places the image on the site', async ( {
		page,
		requestUtils,
	} ) => {
		const saved = async () =>
			(
				await requestUtils.rest( {
					path: `/wp/v2/media/${ image }`,
					params: { context: 'edit' },
				} )
			).meta.galerio_focal_point;

		await page.goto( `/wp-admin/upload.php?item=${ image }` );
		const field = page.locator( '.media-modal .galerio-focal-point' );
		const picture = field.locator( '.galerio-focal-point__image' );
		await expect( picture ).toBeVisible();

		const box = await picture.boundingBox();
		await picture.click( {
			position: { x: box.width * 0.25, y: box.height * 0.75 },
		} );

		await expect( field.locator( '.galerio-focal-point__x' ) ).toHaveValue(
			'25'
		);
		await expect
			.poll( saved )
			.toEqual( expect.objectContaining( { x: 0.25, y: 0.75 } ) );

		await page.goto( '/works/' );
		await expect(
			page.locator( `.post-${ work.id } img` ).first()
		).toHaveAttribute( 'style', /object-position:25% 75%/ );

		// Clearing it goes back to the middle.
		await page.goto( `/wp-admin/upload.php?item=${ image }` );
		await page
			.locator( '.media-modal .galerio-focal-point__reset' )
			.click();
		await expect.poll( async () => ( await saved() )?.x ).toBeUndefined();
	} );

	test( 'the editor’s Featured image pop-up offers the image’s Focal point', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.editPost( work.id );
		await editor.openDocumentSettingsSidebar();

		await page
			.locator( '.editor-post-featured-image' )
			.getByRole( 'button' )
			.first()
			.click();

		const modal = page.locator( '.media-modal' ).last();
		await modal.getByRole( 'tab', { name: 'Media Library' } ).click();
		await modal.locator( `li.attachment[data-id="${ image }"]` ).click();

		await expect(
			modal.locator( '.media-sidebar .galerio-focal-point' )
		).toBeVisible();
	} );
} );
