/**
 * The theme's templates for content types and taxonomies have names, and
 * aren't offered in each item's Template menu.
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test( 'templates have names, and only custom ones are offered per item', async ( {
	requestUtils,
} ) => {
	const templates = await requestUtils.rest( {
		path: '/wp/v2/templates',
		params: { context: 'edit' },
	} );
	const bySlug = Object.fromEntries(
		templates.map( ( template ) => [ template.slug, template ] )
	);

	expect( bySlug[ 'single-galerio_work' ].title.rendered ).toBe(
		'Work page'
	);
	expect( bySlug[ 'archive-galerio_collection' ].title.rendered ).toBe(
		'Collections archive'
	);
	expect( bySlug[ 'taxonomy-galerio_group' ].title.rendered ).toBe(
		'Group archive'
	);
	expect( bySlug[ 'taxonomy-galerio_role' ].title.rendered ).toBe(
		'Role archive'
	);
	expect( bySlug[ 'single-galerio_work' ].is_custom ).toBe( false );
	expect( bySlug[ 'title-page' ].is_custom ).toBe( true );

	const single = await requestUtils.rest( {
		path: '/wp/v2/templates/galerio//archive-galerio_work',
		params: { context: 'edit' },
	} );
	expect( single.title.rendered ).toBe( 'Works archive' );
} );
