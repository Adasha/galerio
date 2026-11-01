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

	test( 'collection, article, profile and documentation pages use the top layout and stack their splits', async ( {
		page,
		requestUtils,
	} ) => {
		const docs = await requestUtils.rest( {
			path: '/wp/v2/pages',
			method: 'POST',
			data: {
				title: 'Starter docs test',
				status: 'publish',
				template: 'wp-custom-template-documentation',
			},
		} );

		try {
			const pages = [
				'/collection/shorelines/',
				'/article/in-conversation-shorelines/',
				'/profile/ana-morgan/',
				docs.link,
			];

			for ( const [ width, stacked ] of [
				[ 1280, false ],
				[ 420, true ],
			] ) {
				await page.setViewportSize( { width, height: 900 } );

				for ( const url of pages ) {
					await page.goto( url );
					await expect(
						page.locator( '.galerio-header-bar' )
					).toBeVisible();
					await expect(
						page.locator( '.galerio-site-header' )
					).toHaveCount( 0 );

					const columns = page.locator(
						'.galerio-split > .wp-block-columns > .wp-block-column'
					);
					const first = await columns.first().boundingBox();
					const last = await columns.last().boundingBox();

					// Side by side on a wide page; one above the other on a phone.
					if ( stacked ) {
						expect( last.y ).toBeGreaterThan( first.y );
					} else {
						expect( last.x ).toBeGreaterThan( first.x );
					}
				}
			}
		} finally {
			await requestUtils.rest( {
				path: `/wp/v2/pages/${ docs.id }`,
				method: 'DELETE',
				params: { force: true },
			} );
		}
	} );
} );
