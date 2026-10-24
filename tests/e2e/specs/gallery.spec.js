/**
 * The Galerio Gallery block: its editor preview and the lightbox.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Gallery block', () => {
	let page;

	test.beforeAll( async ( { requestUtils } ) => {
		const images = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: { media_type: 'image', per_page: 3, orderby: 'id' },
		} );

		page = await requestUtils.createPage( {
			title: 'Gallery',
			status: 'publish',
			content: `<!-- wp:galerio/gallery ${ JSON.stringify( {
				source: 'media',
				ids: images.map( ( image ) => image.id ),
				order: 'saved',
			} ) } /-->`,
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/pages/${ page.id }`,
			params: { force: true },
		} );
	} );

	test( 'previews a collection’s own images in the editor', async ( {
		admin,
		editor,
		requestUtils,
	} ) => {
		const [ collection ] = await requestUtils.rest( {
			path: '/wp/v2/galerio_collection',
			params: { slug: 'shorelines', context: 'edit' },
		} );

		await admin.editPost( collection.id );
		await editor.insertBlock( { name: 'galerio/gallery' } );

		const items = editor.canvas.locator(
			'[data-type="galerio/gallery"] .wp-block-galerio-gallery__item'
		);
		// Pods' fields can push the canvas out of view, so count rather than look.
		await expect.poll( () => items.count() ).toBeGreaterThan( 1 );
	} );

	test( 'opens images in a lightbox and moves between them', async ( {
		page: browser,
	} ) => {
		await browser.goto( page.link );

		const links = browser.locator( '.wp-block-galerio-gallery__link' );
		await expect( links ).toHaveCount( 3 );
		await links.nth( 1 ).click();

		const lightbox = browser.getByRole( 'dialog', {
			name: 'Image viewer',
		} );
		await expect( lightbox ).toBeVisible();
		await expect( lightbox.getByText( '2 of 3' ) ).toBeVisible();

		await lightbox.getByRole( 'button', { name: 'Next image' } ).click();
		await expect( lightbox.getByText( '3 of 3' ) ).toBeVisible();

		await browser.keyboard.press( 'ArrowRight' );
		await expect( lightbox.getByText( '1 of 3' ) ).toBeVisible();

		await browser.keyboard.press( 'Escape' );
		await expect( lightbox ).toBeHidden();
		await expect( links.first() ).toBeFocused();
	} );
} );
