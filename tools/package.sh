#!/usr/bin/env bash
#
# Build installable zips of the theme, the child theme starter, the galerio.net and swansea.art child themes, Galerio Core,
# Galerio Blocks, Galerio Media and the one-off Galerio Migrate plugin into dist/, named <slug>-<version>.zip, each with a <slug>/ folder
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

# galerio.net's child theme.
net="$repo/themes/galerio-net"
build "$net" galerio-net "$(header "$net/style.css" Version)"

# swansea.art's child theme.
swansea="$repo/themes/swansea-art"
build "$swansea" swansea-art "$(header "$swansea/style.css" Version)"

# Galerio Core.
core="$repo/plugins/galerio-core"
core_version="$(header "$core/galerio-core.php" Version)"
[ "$core_version" = "$(header "$core/readme.txt" 'Stable tag')" ] || fail "galerio-core: plugin header and readme Stable tag differ."
grep -q "define( 'GALERIO_CORE_VERSION', '$core_version' );" "$core/galerio-core.php" || fail "galerio-core: GALERIO_CORE_VERSION doesn't match the plugin header."
[ -f "$core/languages/galerio-core.pot" ] || fail "galerio-core: languages/galerio-core.pot is missing. Run: npm run i18n:pot"
# Galerio Core carries the Pods package it expects, for `wp galerio pods import`.
core_staging="$(mktemp -d)"
cp -r "$core" "$core_staging/galerio-core"
mkdir -p "$core_staging/galerio-core/package"
cp "$repo/data/pods/galerio.json" "$core_staging/galerio-core/package/galerio.json"
build "$core_staging/galerio-core" galerio-core "$core_version"
rm -rf "$core_staging"

# Galerio Blocks, compiled fresh so the zip never carries a stale build.
blocks="$repo/plugins/galerio-blocks"
blocks_version="$(header "$blocks/galerio-blocks.php" Version)"
[ "$blocks_version" = "$(header "$blocks/readme.txt" 'Stable tag')" ] || fail "galerio-blocks: plugin header and readme Stable tag differ."
grep -q "define( 'GALERIO_BLOCKS_VERSION', '$blocks_version' );" "$blocks/galerio-blocks.php" || fail "galerio-blocks: GALERIO_BLOCKS_VERSION doesn't match the plugin header."
[ -f "$blocks/languages/galerio-blocks.pot" ] || fail "galerio-blocks: languages/galerio-blocks.pot is missing. Run: npm run i18n:pot"
(cd "$repo" && npm run --silent build >/dev/null) || fail "galerio-blocks: npm run build failed."
build "$blocks" galerio-blocks "$blocks_version"

# Galerio Media.
media="$repo/plugins/galerio-media"
media_version="$(header "$media/galerio-media.php" Version)"
[ "$media_version" = "$(header "$media/readme.txt" 'Stable tag')" ] || fail "galerio-media: plugin header and readme Stable tag differ."
grep -q "define( 'GALERIO_MEDIA_VERSION', '$media_version' );" "$media/galerio-media.php" || fail "galerio-media: GALERIO_MEDIA_VERSION doesn't match the plugin header."
[ -f "$media/languages/galerio-media.pot" ] || fail "galerio-media: languages/galerio-media.pot is missing. Run: npm run i18n:pot"
build "$media" galerio-media "$media_version"

# Galerio Migrate, with the Pods package it migrates to.
migrate="$repo/plugins/galerio-migrate"
staging="$(mktemp -d)"
trap 'rm -rf "$staging"' EXIT
cp -r "$migrate" "$staging/galerio-migrate"
mkdir -p "$staging/galerio-migrate/package"
cp "$repo/data/pods/galerio.json" "$staging/galerio-migrate/package/galerio.json"
build "$staging/galerio-migrate" galerio-migrate "$(header "$migrate/galerio-migrate.php" Version)"
