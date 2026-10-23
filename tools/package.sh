#!/usr/bin/env bash
#
# Build installable zips of the theme, the child theme starter, Galerio Core
# and the one-off Galerio Migrate plugin into dist/, named <slug>-<version>.zip, each with a <slug>/ folder
# inside, ready for Plugins/Themes → Add New → Upload.
#
# The version comes from each package's header. The script stops if a
# package's versions disagree (header, readme, version constant).
#
# Usage: npm run package

set -euo pipefail

repo="$(cd "$(dirname "$0")/.." && pwd)"
dist="$repo/dist"

# Read a "Name: value" header line from a file.
header() {
	grep -m1 -E "^[ *]*$2:" "$1" | sed -E "s/^[ *]*$2:[[:space:]]*//" | tr -d '\r'
}

fail() {
	echo "package: $*" >&2
	exit 1
}

# Zip a folder as <slug>/ into dist/<slug>-<version>.zip, leaving out clutter.
build() {
	local dir="$1" slug="$2" version="$3"
	local zip="$dist/$slug-$version.zip"

	rm -f "$zip"
	(
		cd "$(dirname "$dir")"
		zip -qr "$zip" "$(basename "$dir")" \
			-x '*/.DS_Store' '*/Thumbs.db' '*_Conflict*' '*/node_modules/*' '*.map'
	)
	echo "  $(basename "$zip")  ($(du -h "$zip" | cut -f1))"
}

mkdir -p "$dist"
echo "Building into dist/:"

# Galerio theme.
theme="$repo/themes/galerio"
theme_version="$(header "$theme/style.css" Version)"
[ "$theme_version" = "$(header "$theme/readme.txt" Version)" ] || fail "themes/galerio: style.css and readme.txt versions differ."
[ -f "$theme/screenshot.png" ] || fail "themes/galerio: screenshot.png is missing."
[ -f "$theme/languages/galerio.pot" ] || fail "themes/galerio: languages/galerio.pot is missing. Run: npm run i18n:pot"
build "$theme" galerio "$theme_version"

# Child theme starter.
child="$repo/themes/galerio-child-starter"
build "$child" galerio-child-starter "$(header "$child/style.css" Version)"

# Galerio Core.
core="$repo/plugins/galerio-core"
core_version="$(header "$core/galerio-core.php" Version)"
[ "$core_version" = "$(header "$core/readme.txt" 'Stable tag')" ] || fail "galerio-core: plugin header and readme Stable tag differ."
grep -q "define( 'GALERIO_CORE_VERSION', '$core_version' );" "$core/galerio-core.php" || fail "galerio-core: GALERIO_CORE_VERSION doesn't match the plugin header."
[ -f "$core/languages/galerio-core.pot" ] || fail "galerio-core: languages/galerio-core.pot is missing. Run: npm run i18n:pot"
build "$core" galerio-core "$core_version"

# Galerio Migrate, with the Pods package it migrates to.
migrate="$repo/plugins/galerio-migrate"
staging="$(mktemp -d)"
trap 'rm -rf "$staging"' EXIT
cp -r "$migrate" "$staging/galerio-migrate"
mkdir -p "$staging/galerio-migrate/package"
cp "$repo/data/pods/galerio.json" "$staging/galerio-migrate/package/galerio.json"
build "$staging/galerio-migrate" galerio-migrate "$(header "$migrate/galerio-migrate.php" Version)"
