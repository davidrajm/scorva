#!/usr/bin/env bash
#
# Build a WordPress-installable ZIP for Scorva (project-reviews).
#
# Usage:
#   ./bin/release.sh              # version from project-reviews.php
#   ./bin/release.sh 1.0.0        # override zip name only
#   ./bin/release.sh --skip-tests
#   ./bin/release.sh --dry-run      # show plan, no build/zip
#
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PLUGIN_SLUG="project-reviews"
MAIN_FILE="project-reviews.php"
DIST_DIR="$ROOT/dist"

SKIP_TESTS=0
SKIP_BUILD=0
DRY_RUN=0
NO_RESTORE_DEV=0
VERSION_OVERRIDE=""

usage() {
    sed -n '2,10p' "$0" | sed 's/^# \{0,1\}//'
    exit "${1:-0}"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        -h|--help) usage 0 ;;
        --skip-tests) SKIP_TESTS=1 ;;
        --skip-build) SKIP_BUILD=1 ;;
        --dry-run) DRY_RUN=1 ;;
        --no-restore-dev) NO_RESTORE_DEV=1 ;;
        --version)
            shift
            VERSION_OVERRIDE="${1:?--version requires a value}"
            ;;
        --version=*)
            VERSION_OVERRIDE="${1#*=}"
            ;;
        -*)
            echo "Unknown option: $1" >&2
            usage 1
            ;;
        *)
            if [[ -z "$VERSION_OVERRIDE" ]]; then
                VERSION_OVERRIDE="$1"
            else
                echo "Unexpected argument: $1" >&2
                usage 1
            fi
            ;;
    esac
    shift
done

if [[ ! -f "$MAIN_FILE" ]]; then
    echo "Run from plugin root (missing $MAIN_FILE)." >&2
    exit 1
fi

read_version_from_header() {
    grep -E '^[[:space:]]*\*[[:space:]]*Version:' "$MAIN_FILE" \
        | head -1 \
        | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//'
}

HEADER_VERSION="$(read_version_from_header)"
if [[ -z "$HEADER_VERSION" ]]; then
    echo "Could not read Version from $MAIN_FILE." >&2
    exit 1
fi

VERSION="${VERSION_OVERRIDE:-$HEADER_VERSION}"
if [[ "$VERSION" != "$HEADER_VERSION" && -z "$VERSION_OVERRIDE" ]]; then
    :
fi

if [[ -n "$VERSION_OVERRIDE" && "$VERSION_OVERRIDE" != "$HEADER_VERSION" ]]; then
    echo "Note: zip tag is $VERSION_OVERRIDE but $MAIN_FILE still says $HEADER_VERSION — bump the header before publishing." >&2
fi

ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"

echo "==> Scorva release build"
echo "    Plugin root: $ROOT"
echo "    Version:     $VERSION (header: $HEADER_VERSION)"
echo "    Output:      $ZIP_PATH"

if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "    (dry-run — no commands executed)"
    exit 0
fi

need_cmd() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Required command not found: $1" >&2
        exit 1
    fi
}

need_cmd composer
need_cmd npm
need_cmd zip

if [[ "$SKIP_TESTS" -eq 0 ]]; then
    echo "==> composer test"
    composer test
else
    echo "==> skipping composer test (--skip-tests)"
fi

if [[ "$SKIP_BUILD" -eq 0 ]]; then
    if [[ -f package-lock.json ]]; then
        echo "==> npm ci && npm run build"
        npm ci
    else
        echo "==> npm install && npm run build"
        npm install
    fi
    npm run build
else
    echo "==> skipping npm build (--skip-build)"
    if [[ ! -f build/coordinator.js ]]; then
        echo "build/coordinator.js missing — run npm run build or drop --skip-build." >&2
        exit 1
    fi
fi

echo "==> composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader

restore_dev_deps() {
    if [[ "$NO_RESTORE_DEV" -eq 0 ]]; then
        echo "==> composer install (restore dev dependencies)"
        composer install
    fi
}

trap restore_dev_deps EXIT

if [[ ! -f vendor/autoload.php ]]; then
    echo "vendor/autoload.php missing after composer install." >&2
    exit 1
fi

for asset in build/coordinator.js build/reviewer.js build/landing.js; do
    if [[ ! -f "$asset" ]]; then
        echo "Missing built asset: $asset — run npm run build." >&2
        exit 1
    fi
done

mkdir -p "$DIST_DIR"
rm -f "$ZIP_PATH"

echo "==> creating zip"
# Run from wp-content/plugins so the archive root is project-reviews/
PARENT="$(dirname "$ROOT")"
(
    cd "$PARENT"
    zip -r "$ZIP_PATH" "$PLUGIN_SLUG" \
        -x "${PLUGIN_SLUG}/node_modules/*" \
        -x "${PLUGIN_SLUG}/tests/*" \
        -x "${PLUGIN_SLUG}/test-results/*" \
        -x "${PLUGIN_SLUG}/playwright-report/*" \
        -x "${PLUGIN_SLUG}/_bmad-output/*" \
        -x "${PLUGIN_SLUG}/_bmad/*" \
        -x "${PLUGIN_SLUG}/.git/*" \
        -x "${PLUGIN_SLUG}/.github/*" \
        -x "${PLUGIN_SLUG}/dist/*" \
        -x "${PLUGIN_SLUG}/src/*" \
        -x "${PLUGIN_SLUG}/.phpunit.result.cache" \
        -x "${PLUGIN_SLUG}/tests/e2e/.env.local" \
        -x "${PLUGIN_SLUG}/tests/e2e/.walkthrough-state.json" \
        -x "${PLUGIN_SLUG}/package.json" \
        -x "${PLUGIN_SLUG}/package-lock.json" \
        -x "${PLUGIN_SLUG}/webpack.config.js" \
        -x "${PLUGIN_SLUG}/postcss.config.js" \
        -x "${PLUGIN_SLUG}/tailwind.config.js" \
        -x "${PLUGIN_SLUG}/playwright.config.ts" \
        -x "${PLUGIN_SLUG}/browser-sync.config.js" \
        -x "${PLUGIN_SLUG}/phpunit.xml" \
        -x "${PLUGIN_SLUG}/docs/sop/screenshots/202*/*" \
        -x "${PLUGIN_SLUG}/docs/sop/screenshots/202*/*/*"
)

BYTES="$(wc -c < "$ZIP_PATH" | tr -d ' ')"
echo "==> done: $ZIP_PATH ($BYTES bytes)"
echo "    Install: WordPress → Plugins → Add New → Upload Plugin"
