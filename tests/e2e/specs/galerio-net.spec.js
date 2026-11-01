/**
 * The galerio.net child theme: its Documentation page template.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'galerio.net theme', () => {
	test.use( { viewport: { width: 390, height: 844 } } );

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activateTheme( 'galerio-net' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.activateTheme( 'galerio' );
	} );

	test( 'the Documentation template puts its menu beside the content, and stacks them on a phone', async ( {
		page,
		requestUtils,
	} ) => {
		const docs = await requestUtils.rest( {
			path: '/wp/v2/pages',
			method: 'POST',
			data: {
				title: 'Docs layout test',
				status: 'publish',
				template: 'wp-custom-template-documentation',
			},
		} );

		try {
			const columns = page.locator(
				'.galerio-split > .wp-block-columns > .wp-block-column'
			);

			await page.setViewportSize( { width: 1280, height: 900 } );
			await page.goto( docs.link );
			let first = await columns.first().boundingBox();
			let last = await columns.last().boundingBox();
			expect( last.x ).toBeGreaterThan( first.x );

			await page.setViewportSize( { width: 420, height: 900 } );
			await page.goto( docs.link );
			first = await columns.first().boundingBox();
			last = await columns.last().boundingBox();
			expect( last.y ).toBeGreaterThan( first.y );
		} finally {
			await requestUtils.rest( {
				path: `/wp/v2/pages/${ docs.id }`,
				method: 'DELETE',
				params: { force: true },
			} );
		}
	} );

	test( 'the open menu covers other sticky menus', async ( {
		page,
		request,
	} ) => {
		// The Documentation template has its own sticky menu beside the site header's.
		const login = await request.post( '/wp-login.php', {
			form: { log: 'admin', pwd: 'password' },
		} );
		expect( login.ok() ).toBeTruthy();
		const nonce = await (
			await request.get( '/wp-admin/admin-ajax.php?action=rest-nonce' )
		).text();
		const created = await request.post( '/wp-json/wp/v2/pages', {
			headers: { 'X-WP-Nonce': nonce },
			data: {
				title: 'Menu stacking test',
				status: 'publish',
				template: 'wp-custom-template-documentation',
			},
		} );
		const docs = await created.json();

		try {
			await page.goto( docs.link );
			await page
				.locator( 'header' )
				.getByRole( 'button', { name: 'Open menu' } )
				.click();

			const open = page.locator(
				'.wp-block-navigation__responsive-container.is-menu-open'
			);
			await expect( open ).toBeVisible();

			// Where the page's other links sit, the open menu is on top.
			const showingThrough = await page.evaluate(
				() =>
					[
						...document.querySelectorAll(
							'main a, main + * a, .wp-block-navigation a'
						),
					]
						.filter( ( link ) => ! link.closest( '.is-menu-open' ) )
						.map( ( link ) => link.getBoundingClientRect() )
						.filter(
							( box ) =>
								box.width &&
								box.height &&
								box.top >= 0 &&
								box.bottom <= window.innerHeight
						)
						.filter(
							( box ) =>
								! document
									.elementFromPoint(
										box.left + box.width / 2,
										box.top + box.height / 2
									)
									?.closest( '.is-menu-open' )
						).length
			);
			expect( showingThrough ).toBe( 0 );
		} finally {
			await request.delete( `/wp-json/wp/v2/pages/${ docs.id }`, {
				headers: { 'X-WP-Nonce': nonce },
				params: { force: true },
			} );
		}
	} );
} );
