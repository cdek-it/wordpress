#!/usr/bin/env bash
set -euo pipefail

# Печатает список файлов, изменённых в текущей ветке относительно базовой
# (по умолчанию origin/main), отфильтрованных по переданному pathspec.
# Используется линтерами, чтобы не гонять полную проверку по нетронутому
# легаси-коду, а проверять только то, что реально меняется в ветке.

BASE_REF="${DIFF_BASE:-origin/main}"
PATHSPEC="${1:?usage: diff-files.sh <pathspec>}"

git fetch --quiet origin "${BASE_REF#origin/}" 2>/dev/null || true

MERGE_BASE="$(git merge-base "$BASE_REF" HEAD)"

git diff --name-only --diff-filter=ACMR "$MERGE_BASE" HEAD -- "$PATHSPEC"
