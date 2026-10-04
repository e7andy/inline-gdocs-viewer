#!/usr/bin/env bash
# Installs a built plugin zip into the wp-env development site and checks
# that it activates, reports the right version, renders a table, and serves
# its bundled assets.
#
# Usage: bin/smoke-test-zip.sh dist/inline-gdocs-viewer-X.Y.Z.zip
#
# Uses .wp-env.override.json to load the unzipped plugin instead of this
# checkout, and removes it again afterwards. The table comes from the
# fixture CSV served by tests/e2e/mu-plugin.php (https://example.test/).
set -euo pipefail
cd "$(dirname "$0")/.."
export MSYS_NO_PATHCONV=1

zip="${1:?Usage: bin/smoke-test-zip.sh ZIP}"
slug=inline-google-spreadsheet-viewer
version=$(unzip -p "$zip" "$slug/inline-gdocs-viewer.php" | sed -n 's/^ \* \* Version: *\([^ ]*\).*/\1/p' | head -1)
dir="dist/smoke"

rm -rf "$dir"
mkdir -p "$dir"
unzip -q "$zip" -d "$dir"

cleanup() {
    rm -f .wp-env.override.json
    if [ "${KEEP_ENV:-}" != "1" ]; then
        npx wp-env start > /dev/null 2>&1 || true # back to the checkout's plugin
    fi
}
trap cleanup EXIT

printf '{ "plugins": [ "./%s/%s" ] }\n' "$dir" "$slug" > .wp-env.override.json
npx wp-env start > /dev/null

wp() { npx wp-env run cli wp "$@" 2>/dev/null; }

status=$(wp plugin get "$slug" --field=status)
[ "$status" = "active" ] || { echo "ERROR: plugin status is '$status', expected active." >&2; exit 1; }
installed=$(wp plugin get "$slug" --field=version)
[ "$installed" = "$version" ] || { echo "ERROR: installed version is '$installed', expected $version." >&2; exit 1; }
echo "Plugin $slug $installed is active."

html=$(wp eval 'echo do_shortcode( "[gdoc key=\"https://example.test/goals.csv\" use_cache=\"no\"]" );')
echo "$html" | grep -q 'class="igsv-table' || { echo "ERROR: shortcode did not render a table: $html" >&2; exit 1; }
echo "$html" | grep -q 'Ninjas' || { echo "ERROR: table is missing data." >&2; exit 1; }
echo "Shortcode renders a table."

base=$(wp option get siteurl)
for asset in assets/vendor/datatables/dataTables.min.js assets/vendor/pdfmake/vfs_fonts.js igsv-datatables.js igsv-gvizcharts.js; do
    code=$(curl -s -o /dev/null -w '%{http_code}' "$base/wp-content/plugins/$slug/$asset" || true)
    [ "$code" = "200" ] || { echo "ERROR: $asset returned HTTP $code." >&2; exit 1; }
done
echo "Bundled assets are served."

log=$(wp eval 'echo file_exists( WP_CONTENT_DIR . "/debug.log" ) ? file_get_contents( WP_CONTENT_DIR . "/debug.log" ) : "";')
if echo "$log" | grep -E "PHP (Warning|Notice|Deprecated|Fatal)" | grep -q "$slug"; then
    echo "ERROR: PHP errors from the plugin:" >&2
    echo "$log" | grep "$slug" >&2
    exit 1
fi
echo "Smoke test passed."
