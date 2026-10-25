/**
 * The Galerio Carousel block: its editor preview and the carousel on the
 * front end, over the demo site's two collections.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const CAROUSEL = ( attributes ) =>
	`<!-- wp:galerio/carousel ${ JSON.stringify( attributes ) } /-->`;

test.describe( 'Carousel block', () => {
	const pages = [];

	/**
	 * Publish a page holding a carousel and return its address.
	 *
	 * @param {Object} requestUtils Request utilities.
	 * @param {Object} attributes   Block attributes.
	 * @return {Promise<string>} The page's URL.
	 */
	async function publish( requestUtils, attributes ) {
		const created = await requestUtils.createPage( {
			title: 'Carousel',
			content: CAROUSEL( attributes ),
			status: 'publish',
		} );
		pages.push( created.id );
		return created.link;
	}

	test.afterAll( async ( { requestUtils } ) => {
		for ( const id of pages ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ id }`,
				params: { force: true },
			} );
		}
	} );

	test( 'previews the latest collections in the editor', async ( {
		admin,
		editor,
	} ) => {
		await admin.createNewPost();
		await editor.insertBlock( {
			name: 'galerio/carousel',
			attributes: { buttonText: 'View Collection' },
		} );

		const block = editor.canvas.locator( '[data-type="galerio/carousel"]' );
		await expect( block ).toContainText( 'Shorelines' );
		await expect( block ).toContainText( 'View Collection' );
	} );

	test( 'moves between slides with the arrows and dots', async ( {
		page,
		requestUtils,
	} ) => {
		await page.goto(
			await publish( requestUtils, {
				buttonText: 'View Collection',
				label: 'Latest collections',
			} )
		);

		const carousel = page.getByRole( 'region', {
			name: 'Latest collections',
		} );
		const slides = carousel.locator( '.wp-block-galerio-carousel__slide' );
		const dots = carousel.getByRole( 'button', { name: /^Go to slide/ } );

		await expect( slides ).toHaveCount( 2 );
		await expect( dots ).toHaveCount( 2 );
		await expect( dots.first() ).toHaveAttribute( 'aria-current', 'true' );
		await expect( slides.nth( 0 ) ).toHaveAttribute(
			'aria-label',
			'1 of 2'
		);
		await expect( slides.nth( 1 ) ).toHaveJSProperty( 'inert', true );

		await carousel.getByRole( 'button', { name: 'Next slide' } ).click();
		await expect( dots.nth( 1 ) ).toHaveAttribute( 'aria-current', 'true' );
		await expect( slides.nth( 0 ) ).toHaveJSProperty( 'inert', true );
		await expect( slides.nth( 1 ) ).toHaveJSProperty( 'inert', false );

		await dots.first().click();
		await expect( dots.first() ).toHaveAttribute( 'aria-current', 'true' );
	} );

	test( 'draws slides with the Collection card part, at its height', async ( {
		page,
		requestUtils,
	} ) => {
		await page.goto(
			await publish( requestUtils, {
				slidePart: 'collection-card',
				height: '400px',
				label: 'Collection cards',
			} )
		);

		const carousel = page.getByRole( 'region', {
			name: 'Collection cards',
		} );
		const card = carousel
			.locator( '.galerio-collection-card' )
			.filter( { hasText: 'Shorelines' } );
		await expect( card ).toBeVisible();
		await expect(
			card.getByRole( 'link', { name: /View collection/ } )
		).toHaveAttribute( 'href', /\/collection\/shorelines\/$/ );
		expect( ( await card.boundingBox() ).height ).toBeCloseTo( 400, 0 );
	} );

	test( 'autoplay can be paused, and starts paused for reduced motion', async ( {
		page,
		requestUtils,
	} ) => {
		const url = await publish( requestUtils, { autoplay: true } );

		await page.goto( url );
		const button = page.getByRole( 'button', {
			name: 'Pause the carousel',
		} );
		await button.click();
		await expect(
			page.getByRole( 'button', { name: 'Play the carousel' } )
		).toBeVisible();

		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await page.goto( url );
		await expect(
			page.getByRole( 'button', { name: 'Play the carousel' } )
		).toBeVisible();
	} );
} );
