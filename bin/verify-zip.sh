#!/usr/bin/env bash
# Checks the contents of a built plugin zip.
#
# Usage: bin/verify-zip.sh dist/inline-google-spreadsheet-viewer-X.Y.Z.zip
set -euo pipefail

zip="${1:?Usage: bin/verify-zip.sh ZIP}"
slug=inline-google-spreadsheet-viewer
list=$(unzip -Z1 "$zip")
errors=0

fail() { echo "ERROR: $*" >&2; errors=$((errors + 1)); }

# Everything must be inside the plugin folder.
if echo "$list" | grep -v "^$slug/" | grep -q .; then
    fail "files outside $slug/: $(echo "$list" | grep -v "^$slug/" | head -3 | tr '\n' ' ')"
fi

# Files the plugin needs at run time, and the license files it must ship.
for f in inline-gdocs-viewer.php uninstall.php index.php igsv-datatables.js igsv-gvizcharts.js \
         inline-gdocs-viewer.css lib/vistable.php lib/visparser.php lib/visformat.php \
         languages/inline-gdocs-viewer.pot languages/datatables-en-US.json \
         LICENSE README.md CHANGELOG.md assets/vendor/README.md \
         assets/vendor/datatables/dataTables.min.js assets/vendor/pdfmake/LICENSE-Roboto-Apache-2.0.txt; do
    echo "$list" | grep -qx "$slug/$f" || fail "missing $f"
done
for dir in $(echo "$list" | sed -n "s|^$slug/assets/vendor/\([^/]*\)/.*|\1|p" | sort -u); do
    echo "$list" | grep -q "^$slug/assets/vendor/$dir/LICENSE" || fail "assets/vendor/$dir has no license file"
done

# Development files must not be shipped.
for pattern in '/tests/' '/vendor/' '/node_modules/' '/\.github/' '/bin/' '/\.wp-env' '/composer\.' '/package' \
               '/phpunit\.xml' '/phpcs\.xml' '/playwright\.config' '/CLAUDE\.md' '/\.git'; do
    if echo "$list" | grep -v "/assets/vendor/" | grep -q -- "$pattern"; then
        fail "development file shipped: $(echo "$list" | grep -v "/assets/vendor/" | grep -- "$pattern" | head -1)"
    fi
done

# The sum file must match.
if [ -f "$zip.sha256" ]; then
    (cd "$(dirname "$zip")" && sha256sum -c --quiet "$(basename "$zip").sha256") || fail "checksum does not match"
fi

if [ "$errors" -gt 0 ]; then
    exit 1
fi
echo "$zip: $(echo "$list" | grep -vc '/$') files, contents OK."
