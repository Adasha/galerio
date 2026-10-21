/**
 * The `galerio/field` block bindings source in the block editor.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'Galerio field bindings', () => {
	let workId;

	test.beforeAll( async ( { requestUtils } ) => {
		const works = await requestUtils.rest( {
			path: '/wp/v2/galerio_work',
			params: { slug: 'copperworks-ii', context: 'edit' },
		} );
		workId = works[ 0 ].id;
	} );

	test( 'previews a bound field', async ( { admin, editor } ) => {
		await admin.editPost( workId );
		await editor.insertBlock( {
			name: 'core/paragraph',
			attributes: {
				metadata: {
					bindings: {
						content: {
							source: 'galerio/field',
							args: { key: 'work.year' },
						},
					},
				},
			},
		} );

		await expect(
			editor.canvas.locator( '[data-type="core/paragraph"]' ).last()
		).toHaveText( '2018' );
	} );

	test( 'lists the fields of the post being edited', async ( {
		admin,
		page,
	} ) => {
		await admin.editPost( workId );

		const labels = await page.evaluate(
			( postId ) =>
				window.wp.blocks
					.getBlockBindingsSource( 'galerio/field' )
					.getFieldsList( {
						select: window.wp.data.select,
						context: { postId, postType: 'galerio_work' },
					} )
					.map( ( field ) => field.label ),
			workId
		);

		expect( labels ).toContain( 'Year' );
		expect( labels ).toContain( 'Creator' );
		expect( labels ).not.toContain( 'Biography' );
	} );
} );
