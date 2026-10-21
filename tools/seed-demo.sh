#!/usr/bin/env bash
#
# Prepare a wp-env site for Galerio development: import the Pods package and
# create demo content. Safe to re-run.
#
# Usage:
#   tools/seed-demo.sh            seed the development environment (port 8888)
#   tools/seed-demo.sh --test     seed the test environment (port 8889)
#   tools/seed-demo.sh --reset    delete demo content, then seed again

set -euo pipefail

cd "$(dirname "$0")/.."

CONFIG_ARGS=()
SEED_ARGS=()
for arg in "$@"; do
	case "$arg" in
		--test) CONFIG_ARGS=( --config=.wp-env.test.json ) ;;
		--reset) SEED_ARGS+=( reset ) ;;
		*) echo "Unknown option: $arg" >&2; exit 1 ;;
	esac
done

wp() {
	npx wp-env "${CONFIG_ARGS[@]}" run cli wp "$@"
}

PODS_PACKAGE=wp-content/galerio-data/pods/galerio.json

echo "==> Activating plugins and theme"
wp plugin activate pods galerio-core
wp theme activate galerio

# Pods exits with an error when a component is already active, so treat that
# case as success to keep the script re-runnable.
activate_pods_component() {
	local output
	if ! output=$( wp pods-legacy-api activate-component --component="$1" 2>&1 ); then
		if [[ "$output" != *"already active"* ]]; then
			echo "$output" >&2
			return 1
		fi
	fi
	echo "Pods component '$1' is active."
}

echo "==> Enabling Pods components"
activate_pods_component migrate-packages
activate_pods_component templates

echo "==> Importing Pods package"
# Use Galerio's importer rather than Pods' own, as it also repairs the
# package's bidirectional relationships.
wp galerio pods import "$PODS_PACKAGE"

echo "==> Configuring site"
wp rewrite structure '/%postname%/' --hard
wp option update blogname 'Galerio Demo'
wp option update blogdescription 'A gallery built with Galerio'

echo "==> Creating demo content"
wp eval-file wp-content/galerio-tools/seed/demo-content.php "${SEED_ARGS[@]}"

wp rewrite flush --hard
echo "==> Done"
