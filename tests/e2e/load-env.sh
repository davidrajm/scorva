#!/usr/bin/env bash
# Load tests/e2e/.env.local into the current shell (bash or zsh).
# Usage:  source tests/e2e/load-env.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env.local"

if [[ ! -f "${ENV_FILE}" ]]; then
	echo "Missing ${ENV_FILE}" >&2
	echo "Copy tests/e2e/env.example to tests/e2e/.env.local and edit PR_E2E_BASE_URL." >&2
	return 1 2>/dev/null || exit 1
fi

while IFS= read -r line || [[ -n "${line}" ]]; do
	line="${line%%#*}"
	line="$(echo "${line}" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
	[[ -z "${line}" ]] && continue
	[[ "${line}" != *"="* ]] && continue
	export "${line?}"
done < "${ENV_FILE}"

echo "Loaded E2E env from .env.local"
echo "  PR_E2E_BASE_URL=${PR_E2E_BASE_URL:-}"
