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
				'@wordpress/block-editor',
				'@wordpress/blocks',
				'@wordpress/components',
				'@wordpress/core-data',
				'@wordpress/data',
				'@wordpress/element',
				'@wordpress/i18n',
				'@wordpress/server-side-render',
			],
		},
	},
];
