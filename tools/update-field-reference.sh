#!/usr/bin/env bash
#
# Regenerate the field reference in README.md from the field registry, using
# the test site, which keeps the default type names (the development site may
# rename them). A PHPUnit test checks that the two agree.
#
# Usage: tools/update-field-reference.sh

set -euo pipefail

cd "$(dirname "$0")/.."

REFERENCE=$( npx wp-env --config=.wp-env.test.json run cli wp galerio fields list --format=markdown 2>/dev/null ) \
node -e '
	const fs = require( "fs" );
	const start = "<!-- field-reference:start -->";
	const end = "<!-- field-reference:end -->";
	const readme = fs.readFileSync( "README.md", "utf8" );
	const from = readme.indexOf( start );
	const to = readme.indexOf( end );
	if ( from < 0 || to < from ) {
		console.error( "README.md has no field reference markers." );
		process.exit( 1 );
	}
	const reference = process.env.REFERENCE.trim();
	fs.writeFileSync(
		"README.md",
		readme.slice( 0, from + start.length ) + "\n\n" + reference + "\n\n" + readme.slice( to )
	);
	console.log( "Updated the field reference in README.md." );
'
