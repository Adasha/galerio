#!/usr/bin/env bash
#
# Load a live site's database dump into its local copy, made safe to run on
# this machine: URLs point here, live-only plugins are off, and nobody's
# live password works. Images are served from the live site (see
# tools/local-copy-mu/remote-uploads.php), so none are downloaded.
#
# Usage: tools/local-copy.sh <config> <dump.sql> <live URL>
#   tools/local-copy.sh .wp-env.swansea.json data/site/swansea-art.sql https://swansea.art

set -euo pipefail

cd "$(dirname "$0")/.."

if [ $# -ne 3 ]; then
	sed -n '3,11p' "$0" >&2
	exit 1
fi

CONFIG="$1"
DUMP="$2"
LIVE="$3"

wp() {
	npx wp-env --config="$CONFIG" run cli wp "$@"
}

LOCAL=$( wp option get home 2>/dev/null | tail -1 )
LOCAL=${LOCAL:-http://localhost:$( grep -oP '"port":\s*\K\d+' "$CONFIG" )}

echo "==> Importing $DUMP"
wp db import "wp-content/galerio-data/${DUMP#data/}"

echo "==> Pointing URLs at $LOCAL"
wp search-replace "$LIVE" "$LOCAL" --skip-columns=guid --all-tables --report-changed-only
wp search-replace "${LIVE/https:/http:}" "$LOCAL" --skip-columns=guid --all-tables --report-changed-only
# Images stay on the live site (served by tools/local-copy-mu/remote-uploads.php).
wp search-replace "$LOCAL/wp-content/uploads" "$LIVE/wp-content/uploads" --all-tables --report-changed-only

echo "==> Switching off live-only plugins"
for plugin in hostinger litespeed-cache jetpack-boost google-analytics-for-wordpress wp-sweep; do
	wp plugin deactivate "$plugin" --quiet 2>/dev/null || true
done
wp option update blog_public 0

echo "==> Scrambling passwords, and setting admin/password for the first administrator"
for id in $( wp user list --field=ID ); do
	wp user update "$id" --user_pass="$( openssl rand -hex 24 )" --skip-email --quiet
done
admin=$( wp user list --role=administrator --field=user_login --number=1 )
wp user update "$admin" --user_pass=password --skip-email --quiet
echo "Log in as $admin / password"

wp cache flush
wp rewrite flush

echo "==> Done: $LOCAL"
