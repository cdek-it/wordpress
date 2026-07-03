#!/usr/bin/env bash
set -euo pipefail

# Прогоняет phpcs только по PHP-файлам src/, изменённым в текущей ветке
# относительно базовой ветки. WordPress-Extra на всём src/ даёт тысячи
# замечаний на давно не форматированном коде — блокировать хотим только
# новые/изменённые файлы, не весь репозиторий разом.

BASE_REF="${PHPCS_DIFF_BASE:-origin/main}"

git fetch --quiet origin "${BASE_REF#origin/}" 2>/dev/null || true

MERGE_BASE="$(git merge-base "$BASE_REF" HEAD)"

CHANGED_FILES="$(git diff --name-only --diff-filter=ACMR "$MERGE_BASE" HEAD -- 'src/*.php')"

if [ -z "$CHANGED_FILES" ]; then
    echo "Нет изменённых PHP-файлов в src/ — phpcs пропущен"
    exit 0
fi

echo "Изменённые PHP-файлы в src/:"
echo "$CHANGED_FILES"
echo

# shellcheck disable=SC2086
vendor/bin/phpcs $CHANGED_FILES
