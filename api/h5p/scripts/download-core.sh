#!/bin/sh
# Downloads the H5P core (h5p-php-library) and editor (h5p-editor-php-library)
# client files that Lumi serves under /h5p/core and /h5p/editor.
#
# The defaults are the commits Lumi pins in scripts/install.sh of
# h5p-nodejs-library v10.0.x. Override with arguments:
#   scripts/download-core.sh [core-ref] [editor-ref] [target-dir]
set -eu

CORE_REF="${1:-2aeb0b83fa603e331381b3a6b8bf42c3773ba140}"
EDITOR_REF="${2:-ab2daa18bd61b19e7f8729e22eec88f3b637a868}"
TARGET="${3:-$(dirname "$0")/../h5p}"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "Downloading H5P core $CORE_REF and editor $EDITOR_REF into $TARGET"
mkdir -p "$TARGET/core" "$TARGET/editor"
rm -rf "$TARGET/core/"* "$TARGET/editor/"*

curl -fsSL "https://github.com/h5p/h5p-php-library/archive/$CORE_REF.zip" -o "$TMP/core.zip"
curl -fsSL "https://github.com/h5p/h5p-editor-php-library/archive/$EDITOR_REF.zip" -o "$TMP/editor.zip"
unzip -q "$TMP/core.zip" -d "$TMP/core"
unzip -q "$TMP/editor.zip" -d "$TMP/editor"
cp -R "$TMP/core/h5p-php-library-$CORE_REF/." "$TARGET/core/"
cp -R "$TMP/editor/h5p-editor-php-library-$EDITOR_REF/." "$TARGET/editor/"

# PHP sources are not needed at runtime.
find "$TARGET/core" "$TARGET/editor" -maxdepth 1 -name '*.php' -delete
echo "$CORE_REF" > "$TARGET/core/.ref"
echo "$EDITOR_REF" > "$TARGET/editor/.ref"
echo "Done."
