/**
 * Playwright configuration for Galerio end-to-end tests.
 *
 * Runs against the wp-env test environment (`npm run env:test:start` and
 * `npm run seed:test`), which is separate from the development site.
 */
const path = require( 'path' );
const { defineConfig, devices } = require( '@playwright/test' );

const baseURL = process.env.WP_BASE_URL || 'http://localhost:8889';
const artifacts = path.join( __dirname, '../../artifacts' );

process.env.WP_BASE_URL = baseURL;
process.env.STORAGE_STATE_PATH =
	process.env.STORAGE_STATE_PATH ||
	path.join( artifacts, 'storage-states/admin.json' );

module.exports = defineConfig( {
	testDir: './specs',
	outputDir: path.join( artifacts, 'test-results' ),
	globalSetup: require.resolve( './global-setup.js' ),
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: process.env.CI ? [ [ 'github' ], [ 'list' ] ] : 'list',
	use: {
		baseURL,
		storageState: process.env.STORAGE_STATE_PATH,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
