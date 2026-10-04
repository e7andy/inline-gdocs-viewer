#!/usr/bin/env bash
# Builds the installable plugin zip from a git commit.
#
# Usage: bin/build-zip.sh [REF]
# REF defaults to HEAD. Files marked export-ignore in .gitattributes (tests,
# development configs, bin/) are left out.
#
# The zip is named after the plugin (inline-gdocs-viewer-<version>.zip), but
# the folder inside keeps the plugin's original directory name,
# inline-google-spreadsheet-viewer/, so that installing it upgrades existing
# installs instead of adding a second copy. Output: the zip and its .sha256.
set -euo pipefail
cd "$(dirname "$0")/.."

name=inline-gdocs-viewer
slug=inline-google-spreadsheet-viewer
ref="${1:-HEAD}"
version=$(git show "$ref:inline-gdocs-viewer.php" | sed -n 's/^ \* \* Version: *\([^ ]*\).*/\1/p' | head -1)
zip="dist/$name-$version.zip"

mkdir -p dist
rm -f "$zip" "$zip.sha256"
git archive --format=zip --prefix="$slug/" -o "$zip" "$ref"
(cd dist && sha256sum "$(basename "$zip")" > "$(basename "$zip").sha256")

echo "$zip"
