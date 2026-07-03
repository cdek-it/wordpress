#!/usr/bin/env bash
set -euo pipefail

# Прогоняет phpcs только по PHP-файлам src/, изменённым в текущей ветке
# относительно базовой ветки. WordPress-Extra на всём src/ даёт тысячи
# замечаний на давно не форматированном коде — блокировать хотим только
# новые/изменённые файлы, не весь репозиторий разом.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

CHANGED_FILES="$("$SCRIPT_DIR/diff-files.sh" 'src/*.php')"

if [ -z "$CHANGED_FILES" ]; then
    echo "Нет изменённых PHP-файлов в src/ — phpcs пропущен"
    exit 0
fi

echo "Изменённые PHP-файлы в src/:"
echo "$CHANGED_FILES"
echo

# shellcheck disable=SC2086
vendor/bin/phpcs $CHANGED_FILES
