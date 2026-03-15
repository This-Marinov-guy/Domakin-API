#!/usr/bin/env bash
# Merge main into master, resolving any conflicts by keeping main's version.
# Use this instead of rebasing to avoid repeated conflict resolution.
set -e
cd "$(dirname "$0")/.."
git checkout master
git merge main -X theirs -m "Merge main into master (accept main on conflicts)"
echo "Done. Push with: git push origin master"
