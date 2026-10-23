/**
 * Settings → Galerio content types: renaming types through a preset, and
 * redirecting their old addresses.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const SCREEN = 'options-general.php?page=galerio-content-types';

/**
 * Empty every box and save, putting the registered names back.
 *
 * @param {import('@playwright/test').Page} page Page.
 */
async function clearAliases( page ) {
	await page.goto( `/wp-admin/${ SCREEN }` );

	for ( const input of await page
		.locator( '.galerio-aliases input[data-property]' )
		.all() ) {
		await input.fill( '' );
	}

	await page.getByRole( 'button', { name: 'Save Changes' } ).click();
}

test.describe( 'Content type aliases', () => {
	test.afterEach( async ( { page } ) => {
		await clearAliases( page );
	} );

	test( 'a preset renames collections and moves their address', async ( {
		page,
		request,
	} ) => {
		await page.goto( `/wp-admin/${ SCREEN }` );

		await page
			.getByLabel( 'Start from a preset:' )
			.selectOption( 'gallery' );
		await page.getByRole( 'button', { name: 'Fill in' } ).click();

		const plural = page.getByRole( 'textbox', {
			name: 'Collections: Name (plural)',
		} );
		await expect( plural ).toHaveValue( 'Exhibitions' );

		// A preset is only a starting point: edit before saving.
		await page
			.getByRole( 'textbox', { name: 'Profiles: Name (plural)' } )
			.fill( 'Makers' );

		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect( page.getByText( 'Settings saved.' ) ).toBeVisible();
		await expect( plural ).toHaveValue( 'Exhibitions' );

		// The admin menu uses the new names.
		await expect(
			page.locator( '#adminmenu' ).getByRole( 'link', {
				name: 'Exhibitions',
				exact: true,
			} )
		).toBeVisible();
		await expect(
			page.locator( '#adminmenu' ).getByRole( 'link', {
				name: 'Makers',
				exact: true,
			} )
		).toBeVisible();

		// The archive lives at the new address, under the new name.
		await page.goto( '/exhibitions/' );
		await expect(
			page.getByRole( 'heading', { level: 1, name: 'Exhibitions' } )
		).toBeVisible();

		// Template headings use the new names.
		await page.goto( '/artist/ana-morgan/' );
		await expect(
			page.getByRole( 'heading', { name: 'Featured artworks:' } )
		).toBeVisible();

		// So does the theme's own wording that names a taxonomy or type.
		await page.goto( '/artwork/low-tide-rhossili/' );
		await expect( page.getByText( 'Mediums:' ) ).toBeVisible();

		// The old address redirects permanently.
		const old = await request.get( '/collections/', { maxRedirects: 0 } );
		expect( old.status() ).toBe( 301 );
		expect( old.headers().location ).toMatch( /\/exhibitions\/$/ );
	} );

	test( 'refuses a slug WordPress uses', async ( { page } ) => {
		await page.goto( `/wp-admin/${ SCREEN }` );

		await page
			.getByRole( 'textbox', { name: 'Profiles: Address', exact: true } )
			.fill( 'author' );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();

		await expect(
			page.getByText( 'The slug "author" is used by WordPress itself.' )
		).toBeVisible();
		await expect(
			page.getByRole( 'textbox', {
				name: 'Profiles: Address',
				exact: true,
			} )
		).toHaveValue( '' );
	} );
} );
