#!/usr/bin/env bash
#
# One-shot PHP compatibility reports for pasting into issues / chat.
# Usage (from plugin root, after composer install):
#   composer run compat:report
# Optional: COMPAT_REPORT_DIR=/path/to/dir bash scripts/compatibility-report.sh
#
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${ROOT}" || exit 1

PHPCS_BIN="${ROOT}/vendor/bin/phpcs"
if [ ! -x "${PHPCS_BIN}" ]; then
	echo "vendor/bin/phpcs not found. Run: composer install" >&2
	exit 1
fi

OUT_DIR="${COMPAT_REPORT_DIR:-${ROOT}/compat-report}"
mkdir -p "${OUT_DIR}"
MASTER="${OUT_DIR}/COMPATIBILITY-ALL-IN-ONE.txt"

{
	echo "Sikshya LMS (free plugin) — PHP compatibility report"
	echo "Generated: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
	echo ""
} > "${MASTER}"

EXIT_CODE=0

section() {
	{
		echo ""
		echo "================================================================================"
		echo "$1"
		echo "================================================================================"
	} | tee -a "${MASTER}"
}

section "1) PHP 7.4 — phpcs.xml (default testVersion from XML)"
if ! "${PHPCS_BIN}" -ps -d memory_limit=512M --standard=phpcs.xml --report=full 2>&1 | tee -a "${MASTER}"; then
	EXIT_CODE=1
fi

section "2) PHPCompatibilityWP — same ruleset, each testVersion (7.4–8.4)"
for v in 7.4 8.0 8.1 8.2 8.3 8.4; do
	section "   testVersion ${v}"
	if ! "${PHPCS_BIN}" -ps -d memory_limit=512M --standard=phpcs.xml --runtime-set testVersion "${v}" --report=full 2>&1 | tee -a "${MASTER}"; then
		EXIT_CODE=1
	fi
done

echo "" | tee -a "${MASTER}"
echo "Done. Paste this file into chat: ${MASTER}" | tee -a "${MASTER}"
exit "${EXIT_CODE}"
