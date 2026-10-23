#!/usr/bin/env bash
#
# Build the migration trial site (wp-env, port 8890) from a live site's
# WordPress export and Pods package, as the site was before migrating.
#
# The site is emptied first, so the importer can keep each post's original
# ID: Pods stores relationships as post IDs, and the migration needs them to
# match the live site's. Images are downloaded from the live site.
#
# Usage:
#   tools/trial-import.sh <export.xml> <pods-package.json>
# Paths are relative to data/, e.g.
#   tools/trial-import.sh wp/galerionet.WordPress.2026-10-23.xml pods/live/galerio.net-pods-package-2026-10-23.json

set -euo pipefail

cd "$(dirname "$0")/.."

if [ $# -ne 2 ]; then
	sed -n '3,14p' "$0" >&2
	exit 1
fi

DATA=wp-content/galerio-data
EXPORT="$DATA/$1"
PACKAGE="$DATA/$2"

wp() {
	npx wp-env --config=.wp-env.trial.json run cli wp "$@"
}

echo "==> Emptying the site"
wp site empty --uploads --yes
wp db query "DELETE FROM wp_posts; DELETE FROM wp_postmeta;"
# New posts (Pods' own settings, made by the package import) start far above the
# live site's IDs, leaving those free for the importer.
wp db query "ALTER TABLE wp_posts AUTO_INCREMENT = 100000"
# Pods caches its settings; forget the ones just deleted.
wp cache flush
wp transient delete --all
wp db query "DELETE FROM wp_options WHERE option_name LIKE '%pods%cache%' OR option_name LIKE '_transient%pods%'"

echo "==> Activating plugins"
wp plugin activate pods wordpress-importer galerio-core
if ! output=$( wp pods-legacy-api activate-component --component=migrate-packages 2>&1 ); then
	[[ "$output" == *"already active"* ]] || { echo "$output" >&2; exit 1; }
fi

echo "==> Importing the live site's Pods package"
wp galerio pods import "$PACKAGE"

echo "==> Importing the export"
wp rewrite structure '/%postname%/' --hard
wp import "$EXPORT" --authors=create

echo "==> Setting up the site as the live one"
# An export carries content, not settings: use the Galerio theme (the live
# site's saved templates were exported for it) and its title, and a page
# called Home as the front page.
wp theme activate galerio
wp option update blogname "$( grep -m1 -oP '(?<=<title>)[^<]+' "data/$1" )"
home=$( wp post list --post_type=page --name=home --field=ID --format=ids )
if [ -n "$home" ]; then
	wp option update show_on_front page
	wp option update page_on_front "$home"
fi

echo "==> Done: http://localhost:8890"
