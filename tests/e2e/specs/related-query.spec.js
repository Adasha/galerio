/**
 * The Query Loop's Related items setting, in the editor.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const LOOP = {
	name: 'core/query',
	attributes: {
		query: {
			perPage: 10,
			pages: 0,
			offset: 0,
			postType: 'galerio_profile',
			order: 'desc',
			orderBy: 'date',
			inherit: false,
		},
	},
	innerBlocks: [
		{
			name: 'core/post-template',
			innerBlocks: [ { name: 'core/post-title' } ],
		},
	],
};

test.describe( 'Related items in Query Loops', () => {
	test( 'a profile can list its works, as arranged', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		const [ profile ] = await requestUtils.rest( {
			path: '/wp/v2/galerio_profile',
			params: { slug: 'ana-morgan', context: 'edit' },
		} );
		await admin.editPost( profile.id );
		await editor.insertBlock( LOOP );
		await editor.openDocumentSettingsSidebar();

		// Closed by default.
		const panel = page.getByRole( 'button', { name: 'Related items' } );
		await expect( panel ).toHaveAttribute( 'aria-expanded', 'false' );
		await panel.click();

		await page
			.getByRole( 'combobox', { name: 'Show' } )
			.selectOption( { label: 'Works' } );
		await page
			.getByRole( 'combobox', { name: 'Order', exact: true } )
			.selectOption( 'random' );

		const [ loop ] = ( await editor.getBlocks() ).filter(
			( block ) => block.name === 'core/query'
		);
		expect( loop.attributes.query ).toMatchObject( {
			galerioRelated: 'profile.works',
			galerioRelatedOrder: 'random',
			postType: 'galerio_work',
		} );
	} );

	test( 'a post has no Related items setting', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost();
		await editor.insertBlock( LOOP );
		await editor.openDocumentSettingsSidebar();

		await expect(
			page.getByRole( 'button', { name: 'Related items' } )
		).toHaveCount( 0 );
	} );
} );
