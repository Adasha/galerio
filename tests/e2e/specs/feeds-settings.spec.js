/**
 * Galerio's feed and sitemap settings on Settings → Reading.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Feed and sitemap settings', () => {
	test( 'save, and clear again', async ( { admin, page, requestUtils } ) => {
		const type = await requestUtils.rest( {
			path: '/wp/v2/types/galerio_article',
			params: { context: 'edit' },
		} );
		const roles = await requestUtils.rest( {
			path: '/wp/v2/taxonomies/galerio_role',
			params: { context: 'edit' },
		} );
		const save = () =>
			page.getByRole( 'button', { name: 'Save Changes' } ).click();

		await admin.visitAdminPage( 'options-reading.php' );
		await expect(
			page.getByRole( 'heading', { name: 'Galerio feeds and sitemap' } )
		).toBeVisible();

		const articles = page.getByRole( 'checkbox', {
			name: `Include ${ type.labels.name } in the main feed`,
		} );
		const role = page
			.getByRole( 'group', { name: 'Leave out of the sitemap' } )
			.getByRole( 'checkbox', { name: roles.labels.name, exact: true } );
		const users = page.getByRole( 'checkbox', {
			name: 'Users (author pages)',
		} );

		await articles.check();
		await role.check();
		await users.check();
		await save();
		await expect( page.getByText( 'Settings saved.' ) ).toBeVisible();
		await expect( articles ).toBeChecked();
		await expect( role ).toBeChecked();
		await expect( users ).toBeChecked();

		// The sitemap index leaves them out.
		const index = await page.request.get( '/wp-sitemap.xml' );
		const xml = await index.text();
		expect( xml ).not.toContain( 'taxonomies-galerio_role' );
		expect( xml ).not.toContain( 'wp-sitemap-users' );
		expect( xml ).toContain( 'posts-galerio_work' );

		await articles.uncheck();
		await role.uncheck();
		await users.uncheck();
		await save();
		await expect( articles ).not.toBeChecked();
		await expect( role ).not.toBeChecked();
		await expect( users ).not.toBeChecked();
	} );
} );
