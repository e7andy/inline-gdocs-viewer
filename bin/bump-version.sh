#!/usr/bin/env bash
# Prepares a release: sets the version everywhere it appears and turns the
# "Unreleased" section of CHANGELOG.md into a dated section.
#
# Usage: bin/bump-version.sh VERSION
# Then review the changes, commit, and release (see "Releasing" in README.md).
set -euo pipefail
cd "$(dirname "$0")/.."

version="${1:?Usage: bin/bump-version.sh VERSION}"
version="${version#v}"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]] || { echo "ERROR: '$version' is not MAJOR.MINOR.PATCH." >&2; exit 1; }
if awk -v v="$version" '$0 == "## " v || index($0, "## " v " ") == 1 { found = 1 } END { exit !found }' CHANGELOG.md; then
    echo "ERROR: CHANGELOG.md already has a $version section." >&2
    exit 1
fi

# The changes listed under "Unreleased", if any.
unreleased=$(awk '/^## / { if (found) exit; if ($0 == "## Unreleased") { found = 1; next } } found { print }' CHANGELOG.md \
    | sed '/^Nothing yet\.$/d' | tr -d '[:space:]')
if [ -z "$unreleased" ]; then
    echo "ERROR: CHANGELOG.md has nothing under '## Unreleased'. Describe the changes there first." >&2
    exit 1
fi

sed -i "s/^\( \* \* Version: *\).*/\1$version/" inline-gdocs-viewer.php
sed -i "s/const version = '[^']*';/const version = '$version';/" inline-gdocs-viewer.php
sed -i "s/^- \*\*Version:\*\* .*/- **Version:** $version/" README.md
sed -i "s/\(Project-Id-Version: Inline Google Spreadsheet Viewer \)[^\\]*/\1$version/" languages/inline-gdocs-viewer.pot
awk -v v="$version" -v d="${RELEASE_DATE:-$(date +%Y-%m-%d)}" '
    $0 == "## Unreleased" { print; print ""; print "Nothing yet."; print ""; print "## " v " - " d; next }
    { print }
' CHANGELOG.md > CHANGELOG.md.tmp && mv CHANGELOG.md.tmp CHANGELOG.md

bin/check-version.sh "$version"
echo "Next: review the changes (git diff), regenerate the .pot if strings changed, commit, then release $version."
