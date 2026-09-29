#!/bin/bash
# Warleek – Veröffentlichung bauen und zu GitHub schieben.
#
#   bin/release.sh            baut die Pakete und zeigt, was passieren würde
#   bin/release.sh --publish  legt Tag und Release an und lädt die Pakete hoch
#
# Versionen stehen im Plugin-Header und in der style.css. Dieses Skript prüft,
# dass alle Stellen zusammenpassen, bevor irgendetwas veröffentlicht wird.
set -euo pipefail
cd "$(dirname "$0")/.."

REPO=${WARLEEK_REPO:-ppschauss/warleek}
PUBLISH=0
[[ "${1:-}" == "--publish" ]] && PUBLISH=1

fehler() { echo "✗ $*" >&2; exit 1; }

# --- Versionen einsammeln
CORE_HEADER=$(grep -m1 '^ \* Version:' plugin/warleek-core/warleek-core.php | tr -d ' ' | cut -d: -f2)
CORE_CONST=$(grep -m1 "define( 'WARLEEK_CORE_VERSION'" plugin/warleek-core/warleek-core.php | sed "s/.*'\([0-9.]*\)'.*/\1/")
CORE_README=$(grep -m1 '^Stable tag:' plugin/warleek-core/readme.txt | awk '{print $3}')
THEME_CSS=$(grep -m1 '^Version:' theme/warleek/style.css | awk '{print $2}')
THEME_CONST=$(grep -m1 "define( 'WARLEEK_VERSION'" theme/warleek/functions.php | sed "s/.*'\([0-9.]*\)'.*/\1/")

echo "Plugin  Header $CORE_HEADER · Konstante $CORE_CONST · readme $CORE_README"
echo "Theme   style.css $THEME_CSS · Konstante $THEME_CONST"

[[ "$CORE_HEADER" == "$CORE_CONST" && "$CORE_HEADER" == "$CORE_README" ]] \
  || fehler "Plugin-Versionen stimmen nicht überein."
[[ "$THEME_CSS" == "$THEME_CONST" ]] || fehler "Theme-Versionen stimmen nicht überein."

VERSION="$CORE_HEADER"
TAG="v$VERSION"

# --- Arbeitsverzeichnis muss sauber sein
[[ -z "$(git status --porcelain)" ]] || fehler "Es gibt uncommittete Änderungen."
if git rev-parse "$TAG" >/dev/null 2>&1; then fehler "Tag $TAG existiert bereits."; fi

# --- Pakete bauen
rm -rf dist && mkdir -p dist
(cd theme && zip -qr "../dist/warleek-theme-$VERSION.zip" warleek)
(cd plugin && zip -qr "../dist/warleek-core-$VERSION.zip" warleek-core -x 'warleek-core/tests/*')

BASE="https://github.com/$REPO/releases/download/$TAG"
NOTES=$(awk "/^= $VERSION =/{flag=1;next}/^= [0-9]/{flag=0}flag" plugin/warleek-core/readme.txt | sed '/^$/d')
[[ -n "$NOTES" ]] || NOTES="Siehe readme.txt."

cat > dist/warleek-update.json <<JSON
{
  "plugin": {
    "version": "$VERSION",
    "package": "$BASE/warleek-core-$VERSION.zip",
    "requires": "6.6",
    "requires_php": "8.1",
    "tested": "7.1"
  },
  "theme": {
    "version": "$THEME_CSS",
    "package": "$BASE/warleek-theme-$THEME_CSS.zip",
    "requires": "6.6",
    "requires_php": "8.1"
  },
  "changelog": "https://github.com/$REPO/releases/tag/$TAG",
  "notes": $(printf '%s' "$NOTES" | python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))')
}
JSON

echo
ls -la dist/
echo
echo "Änderungen für $VERSION:"
echo "$NOTES"
echo

if [[ $PUBLISH -eq 0 ]]; then
  echo "Trockenlauf – nichts veröffentlicht. Zum Veröffentlichen: bin/release.sh --publish"
  exit 0
fi

command -v gh >/dev/null || fehler "gh (GitHub CLI) fehlt."
gh auth status >/dev/null 2>&1 || fehler "gh ist nicht angemeldet."

git tag -a "$TAG" -m "Warleek $VERSION"
git push origin "$TAG"
printf '%s\n' "$NOTES" > dist/notes.md
gh release create "$TAG" --repo "$REPO" --title "Warleek $VERSION" --notes-file dist/notes.md \
  "dist/warleek-core-$VERSION.zip" "dist/warleek-theme-$THEME_CSS.zip" dist/warleek-update.json
echo "✓ Veröffentlicht: https://github.com/$REPO/releases/tag/$TAG"
