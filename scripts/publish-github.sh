#!/bin/sh
# Rilascio pubblico su GitHub — rispetta i path export-ignore in .gitattributes
# (config privata e docs esclusi).
#
# Uso: scripts/publish-github.sh https://github.com/utente/autocron
set -eu

GITHUB_REMOTE="${1:?Specifica il remote: scripts/publish-github.sh https://github.com/utente/autocron}"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

git archive HEAD | tar -xC "$TMP"
cd "$TMP"
git init -b main
git add .
git commit -m "release: $(date +%Y-%m-%d)"
git remote add origin "$GITHUB_REMOTE"
git push --force -u origin main

echo "Pubblicato su $GITHUB_REMOTE"
