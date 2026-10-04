#!/usr/bin/env bash
# Prints the CHANGELOG.md section for a version, without its heading.
#
# Usage: bin/changelog-notes.sh VERSION
set -euo pipefail
cd "$(dirname "$0")/.."

version="${1:?Usage: bin/changelog-notes.sh VERSION}"
version="${version#v}"

notes=$(awk -v v="$version" '
    /^## / { if (found) exit; if ($0 == "## " v || index($0, "## " v " ") == 1) { found = 1; next } }
    found { print }
' CHANGELOG.md)

if [ -z "$(echo "$notes" | tr -d '[:space:]')" ]; then
    echo "ERROR: CHANGELOG.md has no notes for $version." >&2
    exit 1
fi
printf '%s\n' "$notes" | sed -e '/./,$!d'
