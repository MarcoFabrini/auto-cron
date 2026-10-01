#!/bin/sh
set -eu

usage() {
    echo "Uso: $0 <remote> [--tag vX.Y.Z] [--message \"testo\"] [--force]" >&2
    exit 2
}

GITHUB_REMOTE="${1:-}"
[ -n "$GITHUB_REMOTE" ] || usage
shift

TAG=""
MESSAGE=""
FORCE=0
while [ $# -gt 0 ]; do
    case "$1" in
        --tag) [ $# -ge 2 ] || usage; TAG="$2"; shift 2 ;;
        --message) [ $# -ge 2 ] || usage; MESSAGE="$2"; shift 2 ;;
        --force) FORCE=1; shift ;;
        *) usage ;;
    esac
done

if [ -n "$TAG" ] && ! printf '%s' "$TAG" | grep -Eq '^v[0-9]+\.[0-9]+\.[0-9]+([-+].+)?$'; then
    echo "Il tag deve avere la forma vX.Y.Z (es. v0.2.0): '$TAG'" >&2
    exit 2
fi

if [ "$FORCE" -eq 1 ]; then
    printf "ATTENZIONE: --force sovrascrive la storia di %s. Continuare? [scrivi 'si'] " "$GITHUB_REMOTE" >&2
    read -r answer
    [ "$answer" = "si" ] || { echo "Annullato." >&2; exit 1; }
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

git archive HEAD | tar -xC "$TMP"
cd "$TMP"
git init -q -b main
git remote add origin "$GITHUB_REMOTE"

CHANGED=1
if [ "$FORCE" -eq 0 ] && git fetch -q --depth=1 origin main 2>/dev/null; then
    git reset -q --soft FETCH_HEAD
    git add -A
    git diff --cached --quiet FETCH_HEAD && CHANGED=0
else
    git add -A
fi

if [ "$CHANGED" -eq 1 ]; then
    git commit -q -m "${MESSAGE:-release: $(date +%Y-%m-%d)${TAG:+ ($TAG)}}"
    if [ "$FORCE" -eq 1 ]; then
        git push --force -u origin main
    else
        git push -u origin main
    fi
else
    echo "Nessuna modifica rispetto a $GITHUB_REMOTE: main resta com'è."
    [ -n "$TAG" ] || exit 0
fi

if [ -n "$TAG" ]; then
    git tag -a "$TAG" -m "${MESSAGE:-$TAG}"
    git push origin "$TAG"
fi

echo "Pubblicato su $GITHUB_REMOTE${TAG:+ con tag $TAG}"
