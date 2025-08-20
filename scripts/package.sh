#!/bin/bash

# Vector Bridge MVDB Indexer Packaging Script
# This script creates a distributable ZIP file for WP Engine deployment

set -euo pipefail

# Configuration
PLUGIN_DIR="vector-bridge-mvdb-indexer"
ZIP_NAME="vector-bridge-mvdb-indexer.zip"
BUILD_DIR="build"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Helper functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if we're in the right directory
if [[ ! -f "vector-bridge-mvdb-indexer.php" ]]; then
    log_error "Must run from the plugin root directory (vector-bridge-mvdb-indexer/)"
    exit 1
fi

log_info "Starting Vector Bridge MVDB Indexer packaging process..."

# Clean up old build artifacts
log_info "Cleaning up old build artifacts..."
rm -rf "../${BUILD_DIR}"
mkdir -p "../${BUILD_DIR}"

# Check for Composer
if ! command -v composer &> /dev/null; then
    log_error "Composer is required but not installed. Please install Composer first."
    exit 1
fi

# Install PHP dependencies
log_info "Installing PHP dependencies with Composer..."
composer install --no-dev --optimize-autoloader --no-interaction

# Verify vendor directory was created
if [[ ! -d "vendor" ]]; then
    log_error "Composer install failed - vendor directory not found"
    exit 1
fi

# Check if package.json exists and has build script
if [[ -f "package.json" ]] && grep -q '"build"' package.json; then
    log_info "Running JavaScript build process..."
    if command -v npm &> /dev/null; then
        npm install
        npm run build
    else
        log_warn "npm not found, skipping JavaScript build"
    fi
fi

# Create temporary directory for packaging
TEMP_DIR=$(mktemp -d)
PACKAGE_DIR="${TEMP_DIR}/${PLUGIN_DIR}"

log_info "Creating package structure in temporary directory..."
mkdir -p "${PACKAGE_DIR}"

# Copy plugin files, excluding development files
log_info "Copying plugin files..."

# Copy main plugin files
cp -r src/ "${PACKAGE_DIR}/"
cp -r assets/ "${PACKAGE_DIR}/"
cp -r vendor/ "${PACKAGE_DIR}/"

# Copy configuration and documentation files
cp vector-bridge-mvdb-indexer.php "${PACKAGE_DIR}/"
cp composer.json "${PACKAGE_DIR}/"
cp README.md "${PACKAGE_DIR}/"
cp INSTALLATION.md "${PACKAGE_DIR}/"

# Copy package.json if it exists (for reference)
if [[ -f "package.json" ]]; then
    cp package.json "${PACKAGE_DIR}/"
fi

# Remove development files from vendor if they exist
log_info "Cleaning up development files from vendor..."
find "${PACKAGE_DIR}/vendor" -name "*.md" -type f -delete 2>/dev/null || true
find "${PACKAGE_DIR}/vendor" -name "*.txt" -type f -delete 2>/dev/null || true
find "${PACKAGE_DIR}/vendor" -name "LICENSE*" -type f -delete 2>/dev/null || true
find "${PACKAGE_DIR}/vendor" -name "CHANGELOG*" -type f -delete 2>/dev/null || true
find "${PACKAGE_DIR}/vendor" -name "phpunit.xml*" -type f -delete 2>/dev/null || true
find "${PACKAGE_DIR}/vendor" -name ".git*" -type f -delete 2>/dev/null || true
find "${PACKAGE_DIR}/vendor" -type d -name "test*" -exec rm -rf {} + 2>/dev/null || true
find "${PACKAGE_DIR}/vendor" -type d -name "Test*" -exec rm -rf {} + 2>/dev/null || true
find "${PACKAGE_DIR}/vendor" -type d -name "tests" -exec rm -rf {} + 2>/dev/null || true
find "${PACKAGE_DIR}/vendor" -type d -name "Tests" -exec rm -rf {} + 2>/dev/null || true

# Create the ZIP file
log_info "Creating ZIP file..."
cd "${TEMP_DIR}"
zip -r "../${BUILD_DIR}/${ZIP_NAME}" "${PLUGIN_DIR}" \
    -x "*.git*" \
    -x "*node_modules*" \
    -x "*tests*" \
    -x "*Tests*" \
    -x "*.DS_Store" \
    -x "*/.env*" \
    -x "*/phpunit.xml*" \
    -x "*/composer.lock"

# Clean up temporary directory
rm -rf "${TEMP_DIR}"

# Get back to original directory
cd - > /dev/null

# Verify the ZIP was created
ZIP_PATH="../${BUILD_DIR}/${ZIP_NAME}"
if [[ ! -f "${ZIP_PATH}" ]]; then
    log_error "Failed to create ZIP file"
    exit 1
fi

# Get ZIP file information
ZIP_SIZE=$(du -h "${ZIP_PATH}" | cut -f1)
FILE_COUNT=$(unzip -l "${ZIP_PATH}" | tail -1 | awk '{print $2}')

log_info "Package created successfully!"
log_info "Location: ${ZIP_PATH}"
log_info "Size: ${ZIP_SIZE}"
log_info "Files: ${FILE_COUNT}"

# Show ZIP structure
log_info "ZIP structure (first 40 entries):"
unzip -l "${ZIP_PATH}" | head -40

log_info "Packaging complete! 🎉"
log_info ""
log_info "Next steps:"
log_info "1. Upload ${ZIP_NAME} to WordPress admin (Plugins > Add New > Upload)"
log_info "2. Activate the plugin"
log_info "3. Configure MVDB settings"
log_info ""
log_info "Note: The vendor/ directory is included in the ZIP for WP Engine compatibility"
