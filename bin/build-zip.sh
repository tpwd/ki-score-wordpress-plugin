#!/usr/bin/env bash
# Builds dist/kiscore.zip for "Plugins → Add New → Upload Plugin". Only the
# files WordPress needs go into it.
set -euo pipefail
cd "$(dirname "$0")/.."

version=$(sed -n 's/^ \* Version: *//p' kiscore.php)
if ! grep -q "define( 'KISCORE_VERSION', '$version' );" kiscore.php || ! grep -q "^Stable tag: $version$" readme.txt; then
	echo "Version $version differs between the plugin header, KISCORE_VERSION and readme.txt" >&2
	exit 1
fi

rm -rf dist
mkdir -p dist/kiscore/languages
cp kiscore.php uninstall.php readme.txt LICENSE dist/kiscore/
cp -R includes dist/kiscore/
cp languages/*.pot languages/*.po languages/*.mo dist/kiscore/languages/
(cd dist && zip -qr kiscore.zip kiscore)
echo "dist/kiscore.zip ($version)"
