/**
 * Images on works, collections, articles and profiles open in the lightbox,
 * including those linked to their own file.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Lightbox', () => {
	test( 'an image linked to its file opens in the lightbox on a work', async ( {
		page,
		requestUtils,
	} ) => {
		const [ media ] = await requestUtils.rest( {
			path: '/wp/v2/media',
			params: { per_page: 1, media_type: 'image' },
		} );
		const url = media.source_url;
		const image = ( link ) =>
			`<!-- wp:image {"id":${ media.id },"linkDestination":"${ link }"} --><figure class="wp-block-image"><a href="${ link === 'media' ? url : '/' }"><img src="${ url }" alt="" class="wp-image-${ media.id }"/></a></figure><!-- /wp:image -->`;

		const work = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/galerio_work',
			data: {
				title: 'Linked image study',
				status: 'publish',
				content: image( 'media' ) + image( 'custom' ),
			},
		} );

		try {
			await page.goto( work.link );
			const figures = page.locator(
				'.wp-block-post-content figure.wp-block-image'
			);

			// Linked to its file: the link gives way to the lightbox.
			await expect( figures.nth( 0 ) ).toHaveClass(
				/wp-lightbox-container/
			);
			await expect( figures.nth( 0 ).locator( 'a' ) ).toHaveCount( 0 );
			await figures.nth( 0 ).locator( 'img' ).click();
			await expect( page.locator( '.wp-lightbox-overlay' ) ).toHaveClass(
				/active/
			);
			await page.keyboard.press( 'Escape' );

			// Linked to a page: the link stays.
			await expect( figures.nth( 1 ).locator( 'a' ) ).toHaveCount( 1 );
			await expect( figures.nth( 1 ) ).not.toHaveClass(
				/wp-lightbox-container/
			);
		} finally {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/galerio_work/${ work.id }`,
				params: { force: true },
			} );
		}
	} );
} );
