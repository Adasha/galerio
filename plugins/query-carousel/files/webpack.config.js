/**
 * webpack.config.js
 *
 * Extends the default @wordpress/scripts Webpack configuration to:
 *  1. Declare both `src/index.js` (editor bundle) and `src/view.js`
 *     (frontend bundle) as separate entry points.
 *  2. Keep `embla-carousel` bundled inside `view.js` — it is NOT listed
 *     as a WordPress script dependency because it is not available in the
 *     global `wp` namespace. Webpack tree-shakes it automatically.
 *
 * This file is optional. If you don't include it, you can pass entry points
 * directly to `wp-scripts build` in package.json scripts instead:
 *   "build": "wp-scripts build src/index.js src/view.js"
 *
 * The explicit config file is provided here for extensibility (e.g. adding
 * custom Webpack plugins or aliases in a team project).
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		// Editor-side bundle: block registration, Edit component, editor styles.
		index: './src/index.js',
		// Frontend-side bundle: Embla initialisation, navigation, a11y.
		view: './src/view.js',
	},
};
