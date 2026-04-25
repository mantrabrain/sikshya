#!/usr/bin/env bash
#
# Generate Sikshya (free) translation template (.pot).
# Output: languages/sikshya.pot
#
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DOMAIN="sikshya"
OUT_DIR="${PLUGIN_DIR}/languages"
OUT_FILE="${OUT_DIR}/${DOMAIN}.pot"

mkdir -p "${OUT_DIR}"

WP_BIN="$(command -v wp || true)"
if [[ -z "${WP_BIN}" ]]; then
  echo "Error: wp-cli not found. Install wp-cli or ensure 'wp' is on PATH." >&2
  exit 1
fi

echo "==> Generating POT: ${OUT_FILE}"

WP_MEMORY_LIMIT=1G php -d memory_limit=1G "${WP_BIN}" --allow-root i18n make-pot "${PLUGIN_DIR}" "${OUT_FILE}" \
  --domain="${DOMAIN}" \
  --location \
  --include="src,includes,templates,assets,admin-ui/src" \
  --exclude="vendor,node_modules,admin-ui/node_modules,tests,build,.git,.wordpress-org" \
  --headers='{"Language-Team":"Sikshya Team <team@sikshya.com>","Last-Translator":"Sikshya Team <team@sikshya.com>","Language":"en_US","Plural-Forms":"nplurals=2; plural=(n != 1);"}'

echo "==> Done"

