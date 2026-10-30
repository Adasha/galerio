/**
 * Query Loops limited to so many rows.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * A works Query Loop in a three-column grid.
 *
 * @param {Object} attributes Extra Query Loop attributes.
 * @return {string} Block markup.
 */
function loop( attributes ) {
	const query = JSON.stringify(
		Object.assign(
			{
				queryId: 1,
				query: {
					perPage: 9,
					pages: 0,
					offset: 0,
					postType: 'galerio_work',
					order: 'desc',
					orderBy: 'date',
					inherit: false,
				},
			},
			attributes
		)
	);

	return `<!-- wp:query ${ query } -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-title /-->
<!-- /wp:post-template --></div>
<!-- /wp:query -->`;
}

test.describe( 'Query Loop rows', () => {
	let pages = [];

	test.afterAll( async ( { requestUtils } ) => {
		for ( const id of pages ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/pages/${ id }`,
				params: { force: true },
			} );
		}
		pages = [];
	} );

	/**
	 * Publish a page holding a Query Loop.
	 *
	 * @param {Object} requestUtils Request utilities.
	 * @param {Object} attributes   Extra Query Loop attributes.
	 * @return {Promise<string>} The page's address.
	 */
	async function publish( requestUtils, attributes ) {
		const created = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/pages',
			data: {
				title: 'Rows',
				status: 'publish',
				content: loop( attributes ),
			},
		} );
		pages.push( created.id );
		return created.link;
	}

	test( 'hides the items after the last row, as laid out', async ( {
		page,
		requestUtils,
	} ) => {
		const link = await publish( requestUtils, { galerioRows: 1 } );
		const items = page.locator( '.wp-block-post-template > li' );

		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( link );
		expect( await items.count() ).toBeGreaterThan( 3 );
		await expect( items.locator( 'visible=true' ) ).toHaveCount( 3 );

		// One column on a phone, so one item.
		await page.setViewportSize( { width: 390, height: 900 } );
		await expect( items.locator( 'visible=true' ) ).toHaveCount( 1 );
	} );

	test( 'the editor shows the same rows', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		await publish( requestUtils, { galerioRows: 1 } );
		await page.setViewportSize( { width: 1280, height: 900 } );
		await admin.visitAdminPage(
			'post.php',
			`post=${ pages[ pages.length - 1 ] }&action=edit`
		);

		// The editor keeps a hidden copy of the item being edited, so only
		// the items laid out are counted.
		const items = editor.canvas.locator(
			'.wp-block-post-template > li:not(.block-editor-block-list__layout)'
		);
		await expect.poll( () => items.count() ).toBeGreaterThan( 3 );
		const total = await items.count();
		const shown = editor.canvas.locator(
			'.wp-block-post-template > li:not([hidden])'
		);

		// Only the first row is left showing.
		await expect
			.poll( () =>
				shown.evaluateAll( ( list, count ) => {
					const tops = list
						.filter( ( item ) => item.getClientRects().length )
						.map( ( item ) =>
							Math.round( item.getBoundingClientRect().top )
						);
					return tops.length < count ? new Set( tops ).size : 0;
				}, total )
			)
			.toBe( 1 );

		// Without the limit, every item shows again.
		await editor.canvas.locator( '[data-type="core/query"]' ).waitFor();
		await page.evaluate( () => {
			const { select, dispatch } = window.wp.data;
			const query = select( 'core/block-editor' )
				.getBlocks()
				.find( ( block ) => block.name === 'core/query' );
			dispatch( 'core/block-editor' ).updateBlockAttributes(
				query.clientId,
				{ galerioRows: 0 }
			);
		} );
		await expect(
			editor.canvas.locator( '.wp-block-post-template > li[hidden]' )
		).toHaveCount( 0 );
	} );

	test( 'the editor keeps the setting', async ( {
		admin,
		editor,
		page,
		requestUtils,
	} ) => {
		await publish( requestUtils, { galerioRows: 2 } );
		await admin.visitAdminPage(
			'post.php',
			`post=${ pages[ pages.length - 1 ] }&action=edit`
		);
		await editor.selectBlocks(
			editor.canvas.locator( '[data-type="core/query"]' )
		);
		await editor.openDocumentSettingsSidebar();
		await page.getByRole( 'tab', { name: 'Block' } ).click();
		await expect(
			page.getByRole( 'spinbutton', { name: 'Rows to show' } )
		).toHaveValue( '2' );
	} );
} );
