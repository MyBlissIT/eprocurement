#!/bin/bash
# Build a correctly structured plugin ZIP for WordPress upload/update.
#
# Output: eprocurement.zip (in repo root)
# Structure: eprocurement/ at the root with all plugin files inside.
#
# WordPress expects this structure so "Upload Plugin" detects the existing
# plugin and offers "Replace current with uploaded" instead of creating
# a duplicate folder.
#
# Usage:
#   bash build-zip.sh          # builds eprocurement.zip
#   bash build-zip.sh --clean  # removes previous ZIP first

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_DIR="$SCRIPT_DIR/eprocurement"
BUILD_DIR="$SCRIPT_DIR/build"
OUTPUT="$SCRIPT_DIR/eprocurement.zip"

# Clean previous build
if [[ "${1:-}" == "--clean" ]] || [[ -f "$OUTPUT" ]]; then
    rm -f "$OUTPUT"
fi
rm -rf "$BUILD_DIR"

# Create build directory with correct structure
mkdir -p "$BUILD_DIR/eprocurement"
cp -r "$PLUGIN_DIR/"* "$BUILD_DIR/eprocurement/"

# Remove dev files that shouldn't be in production
rm -f "$BUILD_DIR/eprocurement/composer.json" \
      "$BUILD_DIR/eprocurement/composer.lock"
rm -rf "$BUILD_DIR/eprocurement/tests"

# Read version from main plugin file
VERSION=$(sed -n 's/.*Version:[[:space:]]*\([0-9.]*\).*/\1/p' "$BUILD_DIR/eprocurement/eprocurement.php" | head -1)
VERSION="${VERSION:-unknown}"

# Create ZIP (PowerShell fallback for Windows Git Bash without zip)
cd "$BUILD_DIR"
if command -v zip &>/dev/null; then
    zip -r "$OUTPUT" eprocurement/
else
    WINPATH=$(cygpath -w "$OUTPUT" 2>/dev/null || echo "$OUTPUT")
    powershell.exe -NoProfile -Command "Compress-Archive -Path 'eprocurement' -DestinationPath '$WINPATH' -Force"
fi
cd "$SCRIPT_DIR"

# Cleanup
rm -rf "$BUILD_DIR"

# Verify
echo ""
echo "=== Built eprocurement.zip (v${VERSION}) ==="
echo "Size: $(du -h "$OUTPUT" | cut -f1)"

# Show first few entries to confirm structure
VERIFY_SCRIPT=$(mktemp --suffix=.ps1 2>/dev/null || mktemp)
cat > "$VERIFY_SCRIPT" << 'PSEOF'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($args[0])
$zip.Entries | Select-Object -First 5 | ForEach-Object { Write-Host $_.FullName }
Write-Host "..."
Write-Host ("Total files: " + $zip.Entries.Count)
$zip.Dispose()
PSEOF

WINZIP=$(cygpath -w "$OUTPUT" 2>/dev/null || echo "$OUTPUT")
WINSCRIPT=$(cygpath -w "$VERIFY_SCRIPT" 2>/dev/null || echo "$VERIFY_SCRIPT")

echo ""
echo "ZIP contents (top-level):"
if command -v zipinfo &>/dev/null; then
    set +o pipefail
    zipinfo -1 "$OUTPUT" | head -5 || true
    echo "..."
    echo "Total files: $(zipinfo -1 "$OUTPUT" | wc -l || true)"
    set -o pipefail
else
    powershell.exe -NoProfile -File "$WINSCRIPT" "$WINZIP" 2>/dev/null || echo "(verification skipped)"
fi
rm -f "$VERIFY_SCRIPT"

echo ""
echo "Ready for WordPress upload or distribution."
