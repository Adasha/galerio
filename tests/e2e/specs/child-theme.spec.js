/**
 * The child theme starter: its colours, fonts, footer pattern and top header
 * layout take effect over Galerio's, and everything else is inherited.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Child theme starter', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activateTheme( 'galerio-child-starter' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.activateTheme( 'galerio' );
	} );

	test( 'overrides the palette, fonts, footer credit and layout', async ( {
		page,
	} ) => {
		await page.goto( '/work/low-tide-rhossili/' );

		const preset = ( name ) =>
			page.evaluate(
				( property ) =>
					window
						.getComputedStyle( document.body )
						.getPropertyValue( property )
						.trim(),
				name
			);

		expect( await preset( '--wp--preset--color--primary' ) ).toBe(
			'#b04a2f'
		);
		await expect( page.locator( 'h1' ).last() ).toHaveCSS(
			'font-family',
			/Georgia/
		);

		// The child's pattern replaces Galerio's footer credit.
		const footer = page.locator( 'footer' ).last();
		await expect( footer ).toContainText( '© ' );
		await expect( footer ).not.toContainText( 'Powered by' );

		// The child's templates put the site title and menu in a bar across the
		// top, instead of Galerio's side menu column.
		await expect( page.locator( '.galerio-header-bar' ) ).toBeVisible();
		await expect( page.locator( '.galerio-site-header' ) ).toHaveCount( 0 );

		// Galerio's templates and fields are inherited.
		await expect(
			page.getByRole( 'heading', {
				level: 1,
				name: 'Low Tide, Rhossili',
			} )
		).toBeVisible();
	} );
} );
