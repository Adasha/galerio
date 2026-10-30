/**
 * The archive filters: a search, dropdowns that apply as they change, and
 * a panel that opens while anything is in use.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Archive filters', () => {
	test( 'sort applies on change and a search is sent with Enter', async ( {
		page,
	} ) => {
		await page.goto( '/works/' );

		const panel = page.locator(
			'.wp-block-galerio-archive-filters__toggle'
		);
		await expect( panel ).not.toHaveAttribute( 'open', '' );
		await page.getByText( 'Filters', { exact: true } ).click();

		// The Apply button is only for when scripts are off.
		await expect(
			page.locator( '.wp-block-galerio-archive-filters__apply' )
		).toBeHidden();

		await Promise.all( [
			page.waitForURL( /\?sort=title$/ ),
			page.locator( '#galerio-archive-sort' ).selectOption( 'title' ),
		] );
		await expect( panel ).toHaveAttribute( 'open', '' );
		await expect(
			page.locator( '.wp-block-galerio-archive-filters__count' )
		).toHaveText( '(1 in use)' );

		const first = await page
			.locator( '.wp-block-post-template > li' )
			.first()
			.locator( 'h2' )
			.textContent();

		await page.locator( '#galerio-archive-q' ).fill( first.trim() );
		await Promise.all( [
			page.waitForURL( /[?&]q=/ ),
			page.locator( '#galerio-archive-q' ).press( 'Enter' ),
		] );
		await expect(
			page.locator( '.wp-block-post-template > li h2' ).first()
		).toHaveText( first.trim() );
		await expect( page.locator( 'h1.wp-block-query-title' ) ).toHaveText(
			'Works'
		);

		await page.getByRole( 'link', { name: 'Clear' } ).click();
		await expect( page ).toHaveURL( /\/works\/$/ );
	} );
} );
