/**
 * Smoke tests: the seeded demo site renders its main archives and singles
 * without PHP errors or unrendered Pods shortcodes.
 */
const { test, expect } = require( '@playwright/test' );

const PHP_ERROR = /(Fatal error|Warning|Notice|Deprecated):/;

async function expectCleanPage( page ) {
	const body = page.locator( 'body' );
	await expect( body ).not.toContainText( PHP_ERROR );
	await expect( body ).not.toContainText( '[pods' );
}

test.describe( 'Demo site', () => {
	test( 'front page loads', async ( { page } ) => {
		const response = await page.goto( '/' );
		expect( response.status() ).toBe( 200 );
		await expect(
			page.locator( 'main' ).getByRole( 'heading', { level: 1 } )
		).toContainText( 'Galerio Demo' );
		await expectCleanPage( page );
	} );

	for ( const [ archive, item ] of [
		[ '/works/', 'Low Tide, Rhossili' ],
		[ '/profiles/', 'Ana Morgan' ],
		[ '/collections/', 'Shorelines' ],
	] ) {
		test( `${ archive } lists demo content`, async ( { page } ) => {
			const response = await page.goto( archive );
			expect( response.status() ).toBe( 200 );
			await expect(
				page.getByRole( 'link', { name: item } ).first()
			).toBeVisible();
			await expectCleanPage( page );
		} );
	}

	test( 'the not-found page links to this site', async ( { page } ) => {
		const response = await page.goto( '/no-such-page/' );
		expect( response.status() ).toBe( 404 );
		await expect(
			page.getByRole( 'heading', { name: /^404 . Page not found$/ } )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: 'home page' } )
		).toHaveAttribute( 'href', new URL( '/', page.url() ).href );
		await expect(
			page.getByRole( 'link', { name: 'Galerio', exact: true } )
		).toBeVisible();
		await expectCleanPage( page );
	} );

	test( 'a work page shows its title', async ( { page } ) => {
		await page.goto( '/works/' );
		await page
			.getByRole( 'link', { name: 'Low Tide, Rhossili' } )
			.first()
			.click();
		await expect( page.locator( 'main' ) ).toContainText(
			'Low Tide, Rhossili'
		);
		await expectCleanPage( page );
	} );
} );

test.describe( 'Images', () => {
	for ( const [ path, alt ] of [
		[ '/work/low-tide-rhossili/', 'Low Tide, Rhossili' ],
		[ '/profile/ana-morgan/', 'Low Tide, Rhossili' ],
		[ '/collection/shorelines/', 'Copperworks I' ],
	] ) {
		test( `${ path } shows its images`, async ( { page } ) => {
			await page.goto( path );
			const image = page
				.locator( 'main' )
				.getByRole( 'img', { name: alt } )
				.first();
			await expect( image ).toBeVisible();
			expect(
				await image.evaluate(
					( img ) => img.complete && img.naturalWidth > 0
				)
			).toBe( true );
		} );
	}
} );

/**
 * The text CSS adds after an element, such as the comma `.galerio-comma-after` adds.
 *
 * @param {import('@playwright/test').Locator} locator Element.
 * @return {Promise<string>} The ::after content, e.g. '","', or 'none'.
 */
function afterContent( locator ) {
	return locator.evaluate(
		( element ) => window.getComputedStyle( element, '::after' ).content
	);
}

test.describe( 'Field details', () => {
	test( 'a work page shows its details', async ( { page } ) => {
		await page.goto( '/work/low-tide-rhossili/' );
		const main = page.locator( 'main' );
		await expect( main ).toContainText( '2019 Ana Morgan' );
		expect(
			await afterContent( main.locator( '.galerio-comma-after' ) )
		).toBe( '","' );
		await expect( main ).toContainText( 'Oil on canvas' );
		await expect( main ).toContainText( '120 × 90 cm' );
		await expect(
			main.getByRole( 'link', { name: 'Shorelines', exact: true } )
		).toBeVisible();
		// Related items from Galerio Blocks, as cards.
		await expect(
			main.getByRole( 'heading', { name: 'Articles' } )
		).toBeVisible();
		await expect(
			main.getByRole( 'heading', { name: 'In conversation: Shorelines' } )
		).toBeVisible();
	} );

	test( 'a profile page shows its details and asides', async ( { page } ) => {
		await page.goto( '/profile/ana-morgan/' );
		const main = page.locator( 'main' );
		await expect( main ).toContainText( 'Welsh b. 1978' );
		expect(
			await afterContent( main.locator( '.galerio-comma-after' ) )
		).toBe( '","' );
		await expect( main ).toContainText( 'Gower coast' );
		await expect( main ).toContainText( 'Member of:' );
		await expect(
			main.getByRole( 'link', { name: 'Instagram' } )
		).toBeVisible();
	} );

	test( 'the articles archive lists articles with their bylines', async ( {
		page,
	} ) => {
		await page.goto( '/articles/' );
		const main = page.locator( 'main' );
		await expect(
			main
				.getByRole( 'heading', { level: 2 } )
				.getByRole( 'link', { name: 'In conversation: Shorelines' } )
		).toBeVisible();
		await expect( main ).toContainText( 'by Tomos Price' );
	} );

	test( 'an article page shows its standfirst and asides', async ( {
		page,
	} ) => {
		await page.goto( '/article/in-conversation-shorelines/' );
		const main = page.locator( 'main' );
		await expect( main ).toContainText(
			'Ana Morgan and Tomos Price on painting and photographing the same place.'
		);
		await expect( main ).toContainText( 'By Tomos Price' );
		await expect( main ).toContainText( 'Featuring:' );
	} );

	test( 'work cards link their titles', async ( { page } ) => {
		await page.goto( '/works/' );
		await expect(
			page
				.locator( 'main' )
				.getByRole( 'heading', { level: 2 } )
				.getByRole( 'link', { name: 'Copperworks II' } )
		).toBeVisible();
	} );
} );

test.describe( 'Admin', () => {
	test( 'Galerio content types are registered', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		const menu = page.locator( '#adminmenu' );
		for ( const label of [
			'Works',
			'Profiles',
			'Collections',
			'Articles',
		] ) {
			await expect(
				menu.getByRole( 'link', { name: label, exact: true } ).first()
			).toBeVisible();
		}
	} );
} );

test.describe( 'Main menu', () => {
	test( 'highlights the current page and the item under the pointer', async ( {
		page,
	} ) => {
		await page.goto( '/sample-page/' );

		const highlight = ( link ) =>
			link.evaluate(
				( a ) =>
					a.ownerDocument.defaultView.getComputedStyle( a )
						.backgroundColor
			);
		const current = page
			.locator( '.galerio-site-header [aria-current="page"]' )
			.first();
		const other = page
			.locator(
				'.galerio-site-header .wp-block-navigation-item__content:not([aria-current])'
			)
			.first();

		expect( await highlight( current ) ).not.toBe( 'rgba(0, 0, 0, 0)' );
		expect( await highlight( other ) ).toBe( 'rgba(0, 0, 0, 0)' );

		await other.hover();
		await expect
			.poll( () => highlight( other ) )
			.toBe( await highlight( current ) );
	} );
} );

test.describe( 'Phone layout', () => {
	test.use( { viewport: { width: 390, height: 844 } } );

	test( 'a work page fits a phone screen', async ( { page } ) => {
		await page.goto( '/work/low-tide-rhossili/' );

		// The header is a bar: the menu button sits beside the site title.
		const title = await page
			.locator( '.galerio-site-header .wp-block-site-title' )
			.boundingBox();
		const menu = await page
			.locator( '.galerio-site-header' )
			.getByRole( 'button', { name: 'Open menu' } )
			.boundingBox();
		expect( menu.y ).toBeLessThan( title.y + title.height );
		expect( menu.x ).toBeGreaterThanOrEqual( title.x + title.width );

		// The tagline is hidden on phones.
		await expect(
			page.locator( '.galerio-site-header .wp-block-site-tagline' )
		).toBeHidden();

		// The image box fits its image rather than most of the screen.
		const imageBox = await page
			.locator( '.is-style-image-box' )
			.boundingBox();
		expect( imageBox.height ).toBeLessThan( 844 * 0.5 );

		// Nothing is wider than the screen.
		const overflow = await page.evaluate(
			() => document.documentElement.scrollWidth - window.innerWidth
		);
		expect( overflow ).toBeLessThanOrEqual( 0 );
	} );
} );
