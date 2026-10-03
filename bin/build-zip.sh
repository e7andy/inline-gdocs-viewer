#!/usr/bin/env bash
# Builds the installable plugin zip from a git commit.
#
# Usage: bin/build-zip.sh [REF]
# REF defaults to HEAD. Files marked export-ignore in .gitattributes (tests,
# development configs, bin/) are left out. The zip contains one folder,
# inline-google-spreadsheet-viewer/, the plugin's original directory name, so
# it replaces existing installs. Output: dist/<slug>-<version>.zip and .sha256.
set -euo pipefail
cd "$(dirname "$0")/.."

slug=inline-google-spreadsheet-viewer
ref="${1:-HEAD}"
version=$(git show "$ref:inline-gdocs-viewer.php" | sed -n 's/^ \* \* Version: *\([^ ]*\).*/\1/p' | head -1)
zip="dist/$slug-$version.zip"

mkdir -p dist
rm -f "$zip" "$zip.sha256"
git archive --format=zip --prefix="$slug/" -o "$zip" "$ref"
(cd dist && sha256sum "$(basename "$zip")" > "$(basename "$zip").sha256")

echo "$zip"
