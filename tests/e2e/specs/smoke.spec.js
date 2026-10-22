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
