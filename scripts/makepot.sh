#!/usr/bin/env bash
#
# Generate Sikshya (free) translation template (.pot) — same pattern as Sikshya:
#   1) Build admin UI (Vite → assets/admin/react/)
#   2) Babel + @wordpress/babel-plugin-makepot on client/src → languages/sikshya-js.pot
#   3) wp i18n make-pot (PHP + non-React assets)
#   4) wp i18n make-pot --merge=sikshya-js.pot, then remove the JS fragment
#
# Output: languages/sikshya.pot
#
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOMAIN="sikshya"
OUT_DIR="${PLUGIN_DIR}/languages"
OUT_FILE="${OUT_DIR}/${DOMAIN}.pot"
JS_POT="${OUT_DIR}/${DOMAIN}-js.pot"

mkdir -p "${OUT_DIR}"
rm -f "${JS_POT}"

WP_BIN="$(command -v wp || true)"
if [[ -z "${WP_BIN}" ]]; then
  echo "Error: wp-cli not found. Install wp-cli or ensure 'wp' is on PATH." >&2
  exit 1
fi

if [[ "${SKIP_SIKSHYA_ADMIN_BUILD:-}" != "1" ]] && [[ -f "${PLUGIN_DIR}/package.json" ]] && command -v npm >/dev/null 2>&1; then
  echo "==> Building admin UI (npm run build at plugin root)"
  (cd "${PLUGIN_DIR}" && npm ci && npm run build)
else
  echo "==> Skipping admin UI build (set SKIP_SIKSHYA_ADMIN_BUILD=1 to force-skip; needs package.json + npm at plugin root)" >&2
fi

if [[ -x "${PLUGIN_DIR}/node_modules/.bin/babel" ]] && [[ -d "${PLUGIN_DIR}/client/src" ]]; then
  echo "==> Extracting JS/TS strings (babel + @wordpress/babel-plugin-makepot on client/src)"
  (cd "${PLUGIN_DIR}" && ./node_modules/.bin/babel client/src \
    --extensions ".ts,.tsx,.js,.jsx" \
    --ignore "client/src/**/vite-env.d.ts" \
    --out-file /dev/null)
  if [[ ! -f "${JS_POT}" ]]; then
    echo "Note: no ${DOMAIN}-js.pot (no __ / _n / _x calls in client/ yet, or babel skipped all files)." >&2
  fi
else
  echo "Warning: root node_modules/.bin/babel missing or no client/src — JS strings not merged." >&2
  echo "         Run: cd \"${PLUGIN_DIR}\" && npm ci" >&2
fi

echo "==> Generating PHP/strings POT: ${OUT_FILE}"

WP_MEMORY_LIMIT=1G php -d memory_limit=1G "${WP_BIN}" --allow-root i18n make-pot "${PLUGIN_DIR}" "${OUT_FILE}" \
  --domain="${DOMAIN}" \
  --location \
  --include="src,includes,templates,assets" \
  --exclude="vendor,node_modules,client,tests,build,.git,.wordpress-org,assets/admin/react" \
  --headers='{"Language-Team":"Sikshya Team <team@sikshya.com>","Last-Translator":"Sikshya Team <team@sikshya.com>","Language":"en_US","Plural-Forms":"nplurals=2; plural=(n != 1);"}'

if [[ -f "${JS_POT}" ]]; then
  echo "==> Merging admin JS template: ${JS_POT}"
  WP_MEMORY_LIMIT=1G php -d memory_limit=1G "${WP_BIN}" --allow-root i18n make-pot "${PLUGIN_DIR}" "${OUT_FILE}" \
    --domain="${DOMAIN}" \
    --location \
    --merge="${JS_POT}"
  rm -f "${JS_POT}"
fi

echo "==> Done"
