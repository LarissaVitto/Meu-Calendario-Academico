#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
find app public/api scripts -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/js/app.js
node --check public/js/data.js
echo 'Sintaxe PHP e JavaScript OK.'
