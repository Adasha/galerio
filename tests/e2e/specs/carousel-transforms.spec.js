/**
 * Turning a Query Loop into a Galerio Carousel and back.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Carousel transforms', () => {
	test.beforeEach( async ( { admin } ) => {
		await admin.createNewPost( { postType: 'page' } );
	} );

	test( 'a Query Loop becomes a carousel of its items, and back', async ( {
		editor,
		page,
	} ) => {
		const query = {
			perPage: 6,
			pages: 0,
			offset: 0,
			postType: 'galerio_work',
			order: 'asc',
			orderBy: 'title',
			inherit: false,
		};
		await editor.insertBlock( {
			name: 'core/query',
			attributes: { query },
			innerBlocks: [
				{
					name: 'core/post-template',
					attributes: { layout: { type: 'grid', columnCount: 4 } },
					innerBlocks: [ { name: 'core/post-title' } ],
				},
			],
		} );

		await editor.transformBlockTo( 'galerio/carousel' );
		let [ carousel ] = await editor.getBlocks();
		expect( carousel.name ).toBe( 'galerio/carousel' );
		expect( carousel.attributes.source ).toBe( 'loop' );
		expect( carousel.attributes.slidesPerView ).toBe( 4 );
		expect( carousel.innerBlocks[ 0 ].name ).toBe( 'core/query' );
		expect( carousel.innerBlocks[ 0 ].attributes.query ).toMatchObject(
			query
		);

		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="galerio/carousel"]' )
		);
		await editor.transformBlockTo( 'core/query' );
		[ carousel ] = await editor.getBlocks();
		expect( carousel.name ).toBe( 'core/query' );
		expect( carousel.attributes.query ).toMatchObject( query );
		expect( carousel.innerBlocks[ 0 ].innerBlocks[ 0 ].name ).toBe(
			'core/post-title'
		);
		await expect( page.getByText( 'Error loading block' ) ).toHaveCount(
			0
		);
	} );

	test( 'a carousel’s own query becomes a Query Loop of cards', async ( {
		editor,
		page,
	} ) => {
		await editor.insertBlock( {
			name: 'galerio/carousel',
			attributes: {
				source: 'query',
				postType: 'galerio_collection',
				perPage: 4,
				orderBy: 'title',
				order: 'asc',
				taxonomy: 'galerio_collection_category',
				terms: [ 7 ],
				slidesPerView: 2,
			},
		} );
		await editor.transformBlockTo( 'core/query' );

		const [ loop ] = await editor.getBlocks();
		expect( loop.name ).toBe( 'core/query' );
		expect( loop.attributes.query ).toMatchObject( {
			perPage: 4,
			postType: 'galerio_collection',
			orderBy: 'title',
			order: 'asc',
			taxQuery: { include: { galerio_collection_category: [ 7 ] } },
		} );
		const template = loop.innerBlocks[ 0 ];
		expect( template.attributes.layout.columnCount ).toBe( 2 );
		// The theme's collection card.
		expect( template.innerBlocks[ 0 ] ).toMatchObject( {
			name: 'core/template-part',
			attributes: { slug: 'card-collection' },
		} );

		// Items picked by hand have no Query Loop equivalent.
		const offered = await page.evaluate( () => {
			const block = window.wp.blocks.createBlock( 'galerio/carousel', {
				source: 'query',
				postIds: [ 1, 2 ],
			} );
			return window.wp.blocks
				.getPossibleBlockTransformations( [ block ] )
				.map( ( type ) => type.name );
		} );
		expect( offered ).not.toContain( 'core/query' );
	} );

	test( 'the carousel made from a Query Loop shows its items as slides', async ( {
		editor,
		page,
		requestUtils,
	} ) => {
		await editor.insertBlock( {
			name: 'core/query',
			attributes: {
				query: {
					perPage: 3,
					pages: 0,
					offset: 0,
					postType: 'galerio_work',
					order: 'asc',
					orderBy: 'title',
					inherit: false,
				},
			},
			innerBlocks: [
				{
					name: 'core/post-template',
					innerBlocks: [ { name: 'core/post-title' } ],
				},
			],
		} );
		await editor.transformBlockTo( 'galerio/carousel' );
		const id = await editor.publishPost();

		const [ published ] = await requestUtils.rest( {
			path: '/wp/v2/pages',
			params: { include: [ id ] },
		} );
		await page.goto( published.link );
		const carousel = page.locator(
			'.wp-block-galerio-carousel.is-source-loop'
		);
		await expect( carousel ).toBeVisible();
		await expect(
			carousel.locator( '.wp-block-post-template > li' )
		).toHaveCount( 3 );
		await expect(
			carousel.getByRole( 'button', { name: /next/i } )
		).toBeVisible();

		await requestUtils.rest( {
			method: 'DELETE',
			path: `/wp/v2/pages/${ id }`,
			params: { force: true },
		} );
	} );
} );
