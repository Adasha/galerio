/**
 * A Query Loop of cards whose Cover uses the featured image: with one dark
 * image and one light, the cards' shared template part flipped its Cover's
 * `isDark` back and forth, redrawing the editor without end.
 */
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const zlib = require( 'zlib' );
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * A PNG of one grey, written to a temporary file.
 *
 * @param {string} name  File name.
 * @param {number} value Grey, 0 to 255.
 * @return {string} The file's path.
 */
function png( name, value ) {
	const size = 32;
	const chunk = ( type, data ) => {
		const length = Buffer.alloc( 4 );
		length.writeUInt32BE( data.length );
		const body = Buffer.concat( [ Buffer.from( type ), data ] );
		const sum = Buffer.alloc( 4 );
		sum.writeUInt32BE( zlib.crc32( body ) );
		return Buffer.concat( [ length, body, sum ] );
	};
	const header = Buffer.alloc( 13 );
	header.writeUInt32BE( size, 0 );
	header.writeUInt32BE( size, 4 );
	header[ 8 ] = 8; // Bit depth.
	header[ 9 ] = 0; // Greyscale.
	const rows = Buffer.alloc( ( size + 1 ) * size, value );
	for ( let y = 0; y < size; y++ ) {
		rows[ y * ( size + 1 ) ] = 0; // No filter.
	}
	const file = path.join( os.tmpdir(), name );
	fs.writeFileSync(
		file,
		Buffer.concat( [
			Buffer.from( [ 137, 80, 78, 71, 13, 10, 26, 10 ] ),
			chunk( 'IHDR', header ),
			chunk( 'IDAT', zlib.deflateSync( rows ) ),
			chunk( 'IEND', Buffer.alloc( 0 ) ),
		] )
	);
	return file;
}

test.describe( 'Covers in Query Loops', () => {
	const media = [];
	const collections = [];

	test.beforeAll( async ( { requestUtils } ) => {
		for ( const [ name, grey ] of [
			[ 'galerio-dark.png', 10 ],
			[ 'galerio-light.png', 245 ],
		] ) {
			const image = await requestUtils.uploadMedia( png( name, grey ) );
			media.push( image.id );
			const collection = await requestUtils.rest( {
				method: 'POST',
				path: '/wp/v2/galerio_collection',
				data: {
					title: `Cover test ${ name }`,
					status: 'publish',
					featured_media: image.id,
				},
			} );
			collections.push( collection.id );
		}
	} );

	test.afterAll( async ( { requestUtils } ) => {
		for ( const id of collections ) {
			await requestUtils.rest( {
				method: 'DELETE',
				path: `/wp/v2/galerio_collection/${ id }`,
				params: { force: true },
			} );
		}
		for ( const id of media ) {
			await requestUtils.deleteMedia( id );
		}
	} );

	test( 'cards over dark and light images settle', async ( {
		admin,
		editor,
		page,
	} ) => {
		await admin.createNewPost( { postType: 'page' } );
		await editor.insertBlock( {
			name: 'core/query',
			attributes: {
				namespace: 'galerio/collections',
				query: {
					perPage: 9,
					pages: 0,
					offset: 0,
					postType: 'galerio_collection',
					order: 'desc',
					orderBy: 'date',
					inherit: false,
				},
			},
			innerBlocks: [
				{
					name: 'core/post-template',
					attributes: {
						layout: { type: 'grid', columnCount: 3 },
					},
					innerBlocks: [
						{
							name: 'core/template-part',
							attributes: {
								slug: 'card-collection',
								tagName: 'div',
							},
						},
					],
				},
			],
		} );

		await expect(
			editor.canvas.getByText( 'Cover test galerio-dark.png' ).first()
		).toBeVisible();
		await page.waitForTimeout( 2000 );

		// No redrawing once the images are in.
		const changes = await editor.canvas.locator( 'body' ).evaluate(
			( body ) =>
				new Promise( ( resolve ) => {
					let count = 0;
					const observer = new window.MutationObserver(
						( records ) => ( count += records.length )
					);
					observer.observe( body, {
						subtree: true,
						childList: true,
						attributes: true,
					} );
					setTimeout( () => {
						observer.disconnect();
						resolve( count );
					}, 2000 );
				} )
		);
		expect( changes ).toBeLessThan( 20 );
	} );
} );
