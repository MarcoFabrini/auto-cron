#!/bin/sh
# Rilascio pubblico su GitHub — rispetta i path export-ignore in .gitattributes
# (config privata e docs esclusi).
#
# Uso: scripts/publish-github.sh <remote> [--tag vX.Y.Z] [--force]
#
# Pubblica uno snapshot del repo come un nuovo commit in cima al main del remote: la storia pubblica
# cresce di un commit per rilascio e la storia privata (messaggi, autori) non esce mai. Il push è
# normale: se nel frattempo qualcuno ha aggiornato il remote, fallisce invece di sovrascrivere.
# --force ignora la storia del remote e la sostituisce con il solo snapshot (solo di proposito);
# --tag crea il tag di versione (che fa partire la pubblicazione dell'immagine con quel numero).
#
# Autore del commit: l'identità git configurata (user.name/user.email), o GIT_AUTHOR_NAME/EMAIL.
set -eu

usage() {
    echo "Uso: $0 <remote> [--tag vX.Y.Z] [--force]" >&2
    exit 2
}

GITHUB_REMOTE="${1:-}"
[ -n "$GITHUB_REMOTE" ] || usage
shift

TAG=""
FORCE=0
while [ $# -gt 0 ]; do
    case "$1" in
        --tag) [ $# -ge 2 ] || usage; TAG="$2"; shift 2 ;;
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

# Si parte dal main pubblico, se esiste: il nuovo commit lo ha come genitore e il suo albero è
# esattamente lo snapshot (indice vuoto + add -A: i file tolti qui spariscono anche lì).
CHANGED=1
if [ "$FORCE" -eq 0 ] && git fetch -q --depth=1 origin main 2>/dev/null; then
    git reset -q --soft FETCH_HEAD
    git add -A
    git diff --cached --quiet FETCH_HEAD && CHANGED=0
else
    git add -A
fi

if [ "$CHANGED" -eq 1 ]; then
    git commit -q -m "release: $(date +%Y-%m-%d)${TAG:+ ($TAG)}"
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
    git tag -a "$TAG" -m "$TAG"
    git push origin "$TAG"
fi

echo "Pubblicato su $GITHUB_REMOTE${TAG:+ con tag $TAG}"
