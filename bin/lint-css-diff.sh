#!/usr/bin/env bash
set -euo pipefail

# Прогоняет stylelint (wp-scripts lint-style) только по CSS/SCSS-файлам
# src/Frontend, изменённым в текущей ветке — по той же причине, что и
# lint-js-diff.sh: полный прогон по легаси-коду сейчас бессмысленен.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

CSS_FILES="$("$SCRIPT_DIR/diff-files.sh" 'src/Frontend/*.css')"
SCSS_FILES="$("$SCRIPT_DIR/diff-files.sh" 'src/Frontend/*.scss')"
CHANGED_FILES="$(printf '%s\n%s' "$CSS_FILES" "$SCSS_FILES" | sed '/^$/d')"

if [ -z "$CHANGED_FILES" ]; then
    echo "Нет изменённых CSS/SCSS-файлов в src/Frontend — stylelint пропущен"
    exit 0
fi

echo "Изменённые CSS/SCSS-файлы в src/Frontend:"
echo "$CHANGED_FILES"
echo

# shellcheck disable=SC2086
node .yarn/releases/yarn-4.8.1.cjs lint:css $CHANGED_FILES
