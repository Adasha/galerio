/**
 * The native field editor, on a site with Pods off.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const PODS = 'pods-custom-content-types-and-fields';

test.describe( 'Field editor', () => {
	let profile;
	let draft;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deactivatePlugin( PODS );

		profile = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/galerio_profile',
			data: { title: 'Published Painter', status: 'publish' },
		} );
		draft = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/galerio_collection',
			data: { title: 'Unfinished Show', status: 'draft' },
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/galerio_profile/${ profile.id }`,
			params: { force: true },
		} );
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/galerio_collection/${ draft.id }`,
			params: { force: true },
		} );
		await requestUtils.activatePlugin( PODS );
	} );

	test( 'starts open, leaving part of the editor showing', async ( {
		admin,
		page,
	} ) => {
		await admin.createNewPost( { postType: 'galerio_work' } );

		const pane = page.locator( '.edit-post-meta-boxes-main' );
		await expect(
			page.locator(
				'.edit-post-meta-boxes-main__presenter > button[aria-expanded]'
			)
		).toHaveAttribute( 'aria-expanded', 'true' );

		const paneHeight = ( await pane.boundingBox() ).height;
		const viewport = page.viewportSize().height;
		expect( paneHeight ).toBeLessThan( viewport * 0.75 );
	} );

	test( 'saves each kind of field', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.createNewPost( {
			postType: 'galerio_work',
			title: 'A field editor work',
		} );

		const fields = page.locator( '#galerio-field-editor' );

		await fields
			.getByRole( 'textbox', { name: 'Definitive title' } )
			.fill( 'A "quoted" title' );
		await fields
			.getByRole( 'spinbutton', { name: 'Year', exact: true } )
			.fill( '1984' );

		// A relationship, by search.
		const creator = fields.getByRole( 'combobox', { name: 'Creator' } );
		await creator.fill( 'Published' );
		await page.getByRole( 'option', { name: 'Published Painter' } ).click();
		await expect(
			fields.getByText( 'Published Painter', { exact: true } )
		).toBeVisible();

		// A link.
		await fields
			.getByRole( 'textbox', { name: 'URL' } )
			.fill( 'https://example.org/work' );

		// Formatted text, in the classic editor.
		const frame = page.frameLocator( '#galerio-field-description_ifr' );
		await frame.locator( 'body' ).click();
		await page.keyboard.type( 'First paragraph.' );
		await page.keyboard.press( 'Enter' );
		await page.keyboard.type( 'Second paragraph.' );

		// A draft can be chosen, and says so.
		await fields.getByRole( 'button', { name: 'Relationships' } ).click();
		await fields
			.getByRole( 'combobox', { name: 'Collections' } )
			.fill( 'Unfinished' );
		await page
			.getByRole( 'option', { name: 'Unfinished Show (draft)' } )
			.click();

		await page.getByRole( 'button', { name: 'Save draft' } ).click();
		await expect(
			page.getByRole( 'button', { name: 'Dismiss this notice' } ).first()
		).toBeVisible();

		const id = new URL( page.url() ).searchParams.get( 'post' );
		const work = await requestUtils.rest( {
			path: `/wp/v2/galerio_work/${ id }`,
			params: { context: 'edit' },
		} );

		expect( work.galerio_fields.definitive_title ).toBe(
			'A "quoted" title'
		);
		expect( work.galerio_fields.year ).toBe( 1984 );
		expect( work.galerio_fields.creator ).toBe( profile.id );
		expect( work.galerio_fields.website.url ).toBe(
			'https://example.org/work'
		);
		expect( work.galerio_fields.description ).toContain(
			'First paragraph.'
		);
		expect( work.galerio_fields.description ).toContain(
			'Second paragraph.'
		);
		expect( work.galerio_fields.collections ).toEqual( [ draft.id ] );

		// Both relationships reached the other side.
		const other = await requestUtils.rest( {
			path: `/wp/v2/galerio_profile/${ profile.id }`,
			params: { context: 'edit' },
		} );
		expect( other.galerio_fields.works ).toContain( Number( id ) );

		const show = await requestUtils.rest( {
			path: `/wp/v2/galerio_collection/${ draft.id }`,
			params: { context: 'edit' },
		} );
		expect( show.galerio_fields.works ).toContain( Number( id ) );

		// Everything is still there after a reload.
		await page.reload();
		await expect(
			fields.getByRole( 'textbox', { name: 'Definitive title' } )
		).toHaveValue( 'A "quoted" title' );
		await expect(
			fields.getByText( 'Published Painter', { exact: true } )
		).toBeVisible();
		await expect(
			page
				.frameLocator( '#galerio-field-description_ifr' )
				.locator( 'body' )
		).toContainText( 'Second paragraph.' );

		// Opening an item changes nothing, so it isn't marked as unsaved.
		await page.waitForTimeout( 500 );
		expect(
			await page.evaluate( () =>
				window.wp.data.select( 'core/editor' ).isEditedPostDirty()
			)
		).toBe( false );

		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/galerio_work/${ id }`,
			params: { force: true },
		} );
	} );

	test( 'chooses images from the Media Library', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		const media = await requestUtils.rest( { path: '/wp/v2/media' } );
		test.skip( ! media.length, 'The site has no images.' );

		await admin.createNewPost( { postType: 'galerio_work' } );

		const fields = page.locator( '#galerio-field-editor' );

		await fields
			.locator( '.galerio-field-image' )
			.first()
			.getByRole( 'button', { name: 'Choose image' } )
			.click();

		const frame = page.locator( '.media-modal:visible' );
		await frame.getByRole( 'tab', { name: 'Media Library' } ).click();
		await frame.locator( '.attachments .attachment' ).first().click();
		await frame.locator( '.media-button-select' ).click();

		await expect(
			fields.locator( '.galerio-field-image__preview' ).first()
		).toBeVisible();
		await expect(
			fields.getByRole( 'button', { name: 'Replace image' } )
		).toBeVisible();
	} );
} );
