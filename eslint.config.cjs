/**
 * ESLint: the WordPress defaults, plus the `@wordpress/*` packages that block
 * scripts import. Those are provided by WordPress at run time (the build maps
 * them to its `wp.*` globals), so they aren't installed here.
 */
const wordpress = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wordpress,
	{
		settings: {
			'import/core-modules': [
				'@wordpress/api-fetch',
				'@wordpress/block-editor',
				'@wordpress/blocks',
				'@wordpress/components',
				'@wordpress/compose',
				'@wordpress/core-data',
				'@wordpress/data',
				'@wordpress/date',
				'@wordpress/dom-ready',
				'@wordpress/editor',
				'@wordpress/element',
				'@wordpress/html-entities',
				'@wordpress/i18n',
				'@wordpress/media-utils',
				'@wordpress/preferences',
				'@wordpress/server-side-render',
			],
		},
	},
];
