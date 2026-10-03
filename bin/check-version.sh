#!/usr/bin/env bash
# Checks that every place that states the plugin's version agrees.
#
# Usage: bin/check-version.sh [VERSION]
# Without VERSION, the version in the plugin header is checked against the rest.
set -euo pipefail
cd "$(dirname "$0")/.."

header=$(sed -n 's/^ \* \* Version: *\([^ ]*\).*/\1/p' inline-gdocs-viewer.php | head -1)
version="${1:-$header}"
version="${version#v}"
errors=0

fail() { echo "ERROR: $*" >&2; errors=$((errors + 1)); }

[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]] || fail "'$version' is not a semantic version (MAJOR.MINOR.PATCH)."
[ "$header" = "$version" ] || fail "Plugin header says Version: $header, expected $version."
grep -q "const version = '$version';" inline-gdocs-viewer.php || fail "const version in inline-gdocs-viewer.php is not '$version'."
grep -q "^- \*\*Version:\*\* $version\$" README.md || fail "README.md does not say Version: $version."
grep -q "^## $version\b" CHANGELOG.md || fail "CHANGELOG.md has no '## $version' section."
grep -qF "\"Project-Id-Version: Inline Google Spreadsheet Viewer $version\\n\"" languages/inline-gdocs-viewer.pot \
    || fail "languages/inline-gdocs-viewer.pot is not for version $version (regenerate it; see CLAUDE.md)."

if [ "$errors" -gt 0 ]; then
    exit 1
fi
echo "Version $version is consistent."
