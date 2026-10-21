/**
 * Log in once as admin and save the session for every test.
 */
const { request } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );

module.exports = async ( config ) => {
	const { storageState, baseURL } = config.projects[ 0 ].use;
	const requestContext = await request.newContext( { baseURL } );
	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath: storageState,
	} );

	await requestUtils.setupRest();
	await requestContext.dispose();
};
