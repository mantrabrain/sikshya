#!/usr/bin/env bash
#
# Build a WordPress-installable zip for Sikshya (free).
# Output: build/sikshya-<version>.zip (gitignored)
#
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_SLUG="sikshya"
MAIN_FILE="${PLUGIN_DIR}/sikshya.php"
BUILD_DIR="${PLUGIN_DIR}/build"

if [[ ! -f "${MAIN_FILE}" ]]; then
  echo "Error: sikshya.php not found in ${PLUGIN_DIR}" >&2
  exit 1
fi

VERSION="$(
  grep -m1 'Version:' "${MAIN_FILE}" \
    | sed 's/.*Version:[[:space:]]*//' \
    | sed 's/[[:space:]].*//' \
    | tr -d '\r'
)"
if [[ -z "${VERSION}" ]] || [[ ! "${VERSION}" =~ ^[0-9A-Za-z._-]+$ ]]; then
  echo "Error: could not parse a valid Version from sikshya.php (got: '${VERSION}')" >&2
  exit 1
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="${BUILD_DIR}/${ZIP_NAME}"

echo "==> Sikshya release build (${VERSION})"
echo "    Output: ${ZIP_PATH}"

mkdir -p "${BUILD_DIR}"

if command -v composer >/dev/null 2>&1 && [[ -f "${PLUGIN_DIR}/composer.json" ]]; then
  if [[ ! -f "${PLUGIN_DIR}/composer.lock" ]]; then
    echo "Error: composer.lock is missing. Commit composer.lock for reproducible builds." >&2
    exit 1
  fi
  echo "==> Clean vendor + composer install --no-dev"
  (cd "${PLUGIN_DIR}" && rm -rf vendor && composer install --no-dev --optimize-autoloader --no-interaction)
else
  echo "Warning: composer not found or no composer.json; using existing vendor/ if present." >&2
fi

if command -v npm >/dev/null 2>&1 && [[ -f "${PLUGIN_DIR}/package.json" ]]; then
  echo "==> plugin root: npm ci && npm run build"
  (cd "${PLUGIN_DIR}" && npm ci && npm run build)
else
  echo "Warning: npm not found; zip will use existing built assets." >&2
fi

STAGE_PARENT="$(mktemp -d "${TMPDIR:-/tmp}/sikshya-build.XXXXXX")"
STAGE_DIR="${STAGE_PARENT}/${PLUGIN_SLUG}"
mkdir -p "${STAGE_DIR}"

EXCLUDE_FILE="$(mktemp)"
trap 'rm -rf "${STAGE_PARENT}"; rm -f "${EXCLUDE_FILE}"' EXIT

cat >"${EXCLUDE_FILE}" <<'EOF'
# This list MUST stay in sync with .distignore (10up/action-wordpress-plugin-deploy
# uses .distignore; build.sh uses this. Same rules, different file formats.)
#
# Anything that is NOT needed at runtime on a customer's site belongs here.

# VCS / CI
.git/
.github/
.gitignore
.gitattributes
.distignore
.wordpress-org/

# Node / Composer dev metadata (vendor/ is included — composer install --no-dev ran above)
node_modules/
package.json
package-lock.json
yarn.lock
pnpm-lock.yaml
npm-debug.log*
composer.json
composer.lock
composer.phar

# PHP test infrastructure
tests/
phpcs.xml
phpcs.xml.dist
phpunit.xml
phpunit.xml.dist
phpunit.integration.xml
.phpunit.result.cache
.phpunit-integration.result.cache

# Playwright / E2E test infrastructure
e2e/
tests-e2e/
playwright.config.ts
playwright-report/
test-results/
playwright/

# Vite / lint / type-check configs
# NOTE: `client/` (React TypeScript source) IS shipped — required by GPL
# (provide source alongside compiled binaries) and the WordPress.org
# "no obfuscated code" rule. `*.map` source maps ARE also shipped so
# users can debug what they receive.
vite.config.ts
vite.config.js
tsconfig.json
tsconfig.node.json
tailwind.config.js
tailwind.config.cjs
postcss.config.js
postcss.config.cjs
babel.config.cjs
babel.config.js
eslint.config.js
.eslintrc
.eslintrc.json
.eslintrc.cjs
.prettierrc
.prettierrc.json
prettier.config.js

# Build artifacts (NOT source maps — those ship)
build/
builder/
compat-report/
.vite/
assets/.vite/

# Build / release helpers
build.sh
copy.sh
scripts/

# Internal documentation (not for end users)
docs/
README.md
AUDIT_REGRESSION_TESTS.md
SERVER_SIDE_HARDENING.md
UNUSED_SETTINGS_ANALYSIS.md
CHANGELOG.md
CONTRIBUTING.md
DEVELOPMENT.md

# Local config / OS junk
.env
.env.*
.idea/
.vscode/
.editorconfig
.DS_Store
Thumbs.db
desktop.ini
*.log
*.bak
*.swp
*.swo
*~
EOF

echo "==> Staging plugin into ${PLUGIN_SLUG}/"
rsync -a --delete \
  --exclude-from="${EXCLUDE_FILE}" \
  "${PLUGIN_DIR}/" "${STAGE_DIR}/"

if [[ ! -f "${STAGE_DIR}/sikshya.php" ]]; then
  echo "Error: staged copy missing sikshya.php" >&2
  exit 1
fi

if [[ ! -f "${STAGE_DIR}/vendor/autoload.php" ]]; then
  echo "Error: staged copy missing vendor/autoload.php. Run: composer install --no-dev" >&2
  exit 1
fi

echo "==> Creating zip (WordPress expects a single top-level folder)"
(
  cd "${STAGE_PARENT}"
  rm -f "${ZIP_PATH}"
  zip -r -q "${ZIP_PATH}" "${PLUGIN_SLUG}"
)

echo "==> Done: ${ZIP_PATH} ($(du -sh "${ZIP_PATH}" | cut -f1))"

