/**
 * The Galerio link field's editing form, saved through the post editor.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Link field', () => {
	let profileId;

	test.beforeEach( async ( { requestUtils } ) => {
		const profile = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/galerio_profile',
			data: { title: 'Link field test', status: 'publish' },
		} );
		profileId = profile.id;
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/galerio_profile/${ profileId }`,
			params: { force: true },
		} );
	} );

	test( 'saves links added in the editor', async ( {
		admin,
		page,
		requestUtils,
	} ) => {
		await admin.editPost( profileId );

		// Pods fields live in the meta box pane under the editor, collapsed at
		// first. Galerio names it after the type: "Profile details".
		const type = await requestUtils.rest( {
			path: '/wp/v2/types/galerio_profile',
			params: { context: 'edit' },
		} );
		const pane = page.getByRole( 'button', {
			name: `${ type.labels.singular_name } details`,
		} );
		if ( ( await pane.getAttribute( 'aria-expanded' ) ) === 'false' ) {
			// The pane's resize handle covers the middle of the toggle.
			await pane.focus();
			await page.keyboard.press( 'Enter' );
		}

		const field = page.locator(
			'.galerio-links[data-name="pods_meta_links"]'
		);
		const rows = field.locator( '.galerio-links__row' );

		await rows
			.nth( 0 )
			.getByRole( 'textbox', { name: 'URL' } )
			.fill( 'https://instagram.com/example' );
		await field.getByRole( 'button', { name: 'Add link' } ).click();
		await rows
			.nth( 1 )
			.getByRole( 'textbox', { name: 'URL' } )
			.fill( 'https://bsky.app/profile/example' );
		await rows
			.nth( 1 )
			.getByRole( 'textbox', { name: 'Display text (optional)' } )
			.fill( 'Bluesky posts' );

		const metaBoxesSaved = page.waitForResponse(
			( response ) =>
				response.url().includes( 'meta-box-loader=1' ) &&
				response.request().method() === 'POST'
		);
		await page
			.getByRole( 'region', { name: 'Editor top bar' } )
			.getByRole( 'button', { name: 'Save', exact: true } )
			.click();
		await metaBoxesSaved;

		const saved = await requestUtils.rest( {
			path: `/wp/v2/galerio_profile/${ profileId }`,
		} );
		expect( saved.galerio_fields.links ).toEqual( [
			{ url: 'https://instagram.com/example', label: '', offline: false },
			{
				url: 'https://bsky.app/profile/example',
				label: 'Bluesky posts',
				offline: false,
			},
		] );
	} );
} );
