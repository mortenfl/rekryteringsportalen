#!/usr/bin/env bash
# Builds installable zip files named after the version in each plugin/theme header,
# e.g. dist/rekryteringsportalen-core-1.2.0.zip. Tests are left out.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p dist
rm -f dist/*.zip

version() { grep -m1 -E '^\s*\*?\s*Version:' "$1" | sed -E 's/.*Version:\s*//' | tr -d '[:space:]'; }

build() { # <parent dir> <slug> <header file> <zip prefix>
	local v; v="$(version "$1/$2/$3")"
	( cd "$1" && zip -qr "../dist/$4-$v.zip" "$2" -x "*/tests/*" )
	echo "dist/$4-$v.zip"
}

build plugins rekryteringsportalen-core rekryteringsportalen-core.php rekryteringsportalen-core
build plugins rekryteringsportalen-xls-import rekryteringsportalen-xls-import.php rekryteringsportalen-xls-import
build themes rekryteringsportalen style.css rekryteringsportalen-tema
