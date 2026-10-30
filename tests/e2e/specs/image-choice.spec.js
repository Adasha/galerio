/**
 * Choosing which image stands for an item: its featured image or its own.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Image choice', () => {
	let work;
	let original;

	test.beforeAll( async ( { requestUtils } ) => {
		[ work ] = await requestUtils.rest( {
			path: '/wp/v2/galerio_work',
			params: { per_page: 1, search: 'Low Tide' },
		} );
		original = work.featured_media;
		// A featured image as well as its definitive image: another work's.
		const [ other ] = await requestUtils.rest( {
			path: '/wp/v2/galerio_work',
			params: { per_page: 1, search: 'Copperworks I' },
		} );
		await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/galerio_work/${ work.id }`,
			data: {
				featured_media: other.galerio_fields.definitive_image,
				meta: { galerio_use_own_image: false },
			},
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/galerio_work/${ work.id }`,
			data: {
				featured_media: original,
				meta: { galerio_use_own_image: false },
			},
		} );
	} );

	test( 'a work with both images can use its definitive image', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		const cardImage = async () => {
			await page.goto( '/works/' );
			return page
				.locator( `.post-${ work.id } img` )
				.first()
				.getAttribute( 'src' );
		};
		const definitive = await requestUtils.rest( {
			path: `/wp/v2/media/${ work.galerio_fields.definitive_image }`,
		} );
		const stem = definitive.media_details.file
			.split( '/' )
			.pop()
			.replace( /\.\w+$/, '' );

		expect( await cardImage() ).not.toContain( stem );

		await admin.editPost( work.id );
		await editor.openDocumentSettingsSidebar();
		const toggle = page.getByRole( 'checkbox', {
			name: 'Use the “Definitive image” instead',
		} );
		await expect( toggle ).not.toBeChecked();
		await toggle.check();
		await page
			.getByRole( 'region', { name: 'Editor top bar' } )
			.getByRole( 'button', { name: 'Save', exact: true } )
			.click();
		await expect
			.poll(
				async () =>
					(
						await requestUtils.rest( {
							path: `/wp/v2/galerio_work/${ work.id }`,
							params: { context: 'edit' },
						} )
					).meta.galerio_use_own_image
			)
			.toBe( true );

		expect( await cardImage() ).toContain( stem );
	} );

	test( 'a profile’s portrait is offered, off', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		const [ profile ] = await requestUtils.rest( {
			path: '/wp/v2/galerio_profile',
			params: { per_page: 1, search: 'Ana Morgan' },
		} );

		await admin.editPost( profile.id );
		await editor.openDocumentSettingsSidebar();
		await expect(
			page.getByRole( 'checkbox', {
				name: /Use the “Portrait” (instead of the featured image|as its image)/,
			} )
		).not.toBeChecked();
	} );
} );
