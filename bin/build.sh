#!/usr/bin/env bash
# Builds an installable zip of the plugin in dist/.
#
#   bin/build.sh
#
# Produces dist/<slug>.zip (attach this to a GitHub release so the built-in
# updater can install it) and dist/<slug>-<version>.zip (for archiving).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# The plugin folder is the one that contains <folder>/<folder>.php.
SLUG=""
for dir in */; do
	dir="${dir%/}"
	if [ -f "$dir/$dir.php" ] && grep -q "Plugin Name:" "$dir/$dir.php"; then
		SLUG="$dir"
		break
	fi
done
if [ -z "$SLUG" ]; then
	echo "Could not find the plugin folder." >&2
	exit 1
fi

VERSION="$(grep -m1 -E '^\s*\*\s*Version:' "$SLUG/$SLUG.php" | awk '{print $NF}')"
STABLE="$(grep -m1 -E '^Stable tag:' "$SLUG/readme.txt" | awk '{print $NF}')"
if [ "$VERSION" != "$STABLE" ]; then
	echo "Version mismatch: plugin header says $VERSION, readme.txt Stable tag says $STABLE." >&2
	exit 1
fi

# The admin screens run from compiled scripts in $SLUG/build/. Their sources
# in $SLUG/src/ ship too, so the code stays readable.
if [ ! -d node_modules ]; then
	echo "Run npm install first: the admin scripts need compiling." >&2
	exit 1
fi
npm run build --silent >/dev/null
for script in snippets editor overview global files library tools settings block-editor palette onboarding; do
	if [ ! -f "$SLUG/build/$script.js" ]; then
		echo "The build did not produce $SLUG/build/$script.js." >&2
		exit 1
	fi
done

mkdir -p dist
rm -f "dist/$SLUG.zip" "dist/$SLUG-$VERSION.zip"
zip -rq "dist/$SLUG.zip" "$SLUG" \
	-x "*.DS_Store" -x "*/.git/*" -x "*/node_modules/*" -x "*/.gitignore" -x "*/.distignore"
cp "dist/$SLUG.zip" "dist/$SLUG-$VERSION.zip"

echo "Built dist/$SLUG.zip (version $VERSION, $(du -h "dist/$SLUG.zip" | cut -f1))"
