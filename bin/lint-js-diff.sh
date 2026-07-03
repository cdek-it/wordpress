#!/usr/bin/env bash
set -euo pipefail

# Прогоняет eslint (wp-scripts lint-js) только по JS-файлам src/Frontend,
# изменённым в текущей ветке. Фронтенд никогда не линтился — полный прогон
# по всему src/Frontend даёт тысячи замечаний на нетронутом легаси-коде.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

CHANGED_FILES="$("$SCRIPT_DIR/diff-files.sh" 'src/Frontend/*.js')"

if [ -z "$CHANGED_FILES" ]; then
    echo "Нет изменённых JS-файлов в src/Frontend — eslint пропущен"
    exit 0
fi

echo "Изменённые JS-файлы в src/Frontend:"
echo "$CHANGED_FILES"
echo

# shellcheck disable=SC2086
node .yarn/releases/yarn-4.8.1.cjs lint:js $CHANGED_FILES
