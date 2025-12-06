#!/bin/bash
set -e

VERSION=${1:-"dev"}
PLUGIN_SLUG="llmstxt-wp"
BUILD_DIR="dist"
ZIP_FILE="${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

echo "Building ${PLUGIN_SLUG} v${VERSION}..."

# Clean previous builds
rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}"

# Create ZIP excluding dev files
zip -r "${ZIP_FILE}" . \
    -x "*.git*" \
    -x "tests/*" \
    -x "vendor/*" \
    -x ".github/*" \
    -x "scripts/*" \
    -x "composer.*" \
    -x "phpunit.xml*" \
    -x "phpcs.xml*" \
    -x "Dockerfile" \
    -x "docker-compose.yml" \
    -x ".env*" \
    -x "coverage/*" \
    -x "*.md" \
    -x "junit.xml" \
    -x "dist/*"

echo "Created: ${ZIP_FILE}"
ls -lh "${ZIP_FILE}"
