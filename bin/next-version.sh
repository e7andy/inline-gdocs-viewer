#!/usr/bin/env bash
# Prints the version that follows CURRENT.
#
# Usage: bin/next-version.sh CURRENT patch|minor|major
#   1.2.3 patch -> 1.2.4    1.2.3 minor -> 1.3.0    1.2.3 major -> 2.0.0
# A pre-release (1.3.0-beta.1) is followed by its final version for "patch".
set -euo pipefail

current="${1:?Usage: bin/next-version.sh CURRENT patch|minor|major}"
bump="${2:?Usage: bin/next-version.sh CURRENT patch|minor|major}"
current="${current#v}"

if ! [[ "$current" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)(-.+)?$ ]]; then
    echo "ERROR: '$current' is not a version like 1.2.3." >&2
    exit 1
fi
major="${BASH_REMATCH[1]}"
minor="${BASH_REMATCH[2]}"
patch="${BASH_REMATCH[3]}"
suffix="${BASH_REMATCH[4]}"

case "$bump" in
    patch)
        if [ -n "$suffix" ]; then
            echo "$major.$minor.$patch"
        else
            echo "$major.$minor.$((patch + 1))"
        fi
        ;;
    minor) echo "$major.$((minor + 1)).0" ;;
    major) echo "$((major + 1)).0.0" ;;
    *)
        echo "ERROR: bump must be patch, minor, or major, not '$bump'." >&2
        exit 1
        ;;
esac
