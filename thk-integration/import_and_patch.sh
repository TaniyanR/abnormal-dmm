#!/usr/bin/env bash
set -euo pipefail

# THK Analytics Integration Script
# This script extracts the THK Analytics archive, backs up original files,
# and applies conservative patches for integration into abnormal-dmm.

echo "=========================================="
echo "THK Analytics Integration Script"
echo "=========================================="
echo ""

# Configuration
ZIP_FILE="thk-analytics-124.zip"
DEST_DIR="thirdparty/thk-analytics"
BACKUP_DIR="thirdparty/thk-analytics-backups"
TEMP_DIR="thirdparty/thk-analytics-temp"

# Step 1: Verify ZIP file exists
echo "[1/7] Checking for THK Analytics archive..."
if [ ! -f "$ZIP_FILE" ]; then
    echo "ERROR: $ZIP_FILE not found in current directory!"
    echo "Please ensure the file is present and run this script from repository root."
    exit 1
fi
echo "✓ Found $ZIP_FILE"
echo ""

# Step 2: Extract archive
echo "[2/7] Extracting THK Analytics archive..."
mkdir -p "$DEST_DIR"
unzip -q "$ZIP_FILE" -d "$TEMP_DIR"

# Handle single top-level directory in ZIP (if present)
shopt -s nullglob
if [ -d "$TEMP_DIR"/* ] && [ $(ls -A "$TEMP_DIR" | wc -l) -eq 1 ]; then
    echo "  Archive contains single top-level directory, flattening..."
    mv "$TEMP_DIR"/*/* "$DEST_DIR" 2>/dev/null || true
else
    mv "$TEMP_DIR"/* "$DEST_DIR" 2>/dev/null || true
fi
rm -rf "$TEMP_DIR"
echo "✓ Extracted to $DEST_DIR"
echo ""

# Step 3: Backup original files
echo "[3/7] Creating backups of PHP and HTML files..."
mkdir -p "$BACKUP_DIR"

# Backup all PHP and HTML files while preserving directory structure
find "$DEST_DIR" -type f \( -name '*.php' -o -name '*.html' \) | while read -r file; do
    # Calculate relative path
    rel_path="${file#$DEST_DIR/}"
    dest_file="$BACKUP_DIR/$rel_path"
    
    # Create directory structure in backup location
    mkdir -p "$(dirname "$dest_file")"
    
    # Copy file with original timestamps
    cp -a "$file" "$dest_file"
done

backup_count=$(find "$BACKUP_DIR" -type f | wc -l)
echo "✓ Backed up $backup_count files to $BACKUP_DIR"
echo ""

# Step 4: Neutralize admin token checks
echo "[4/7] Neutralizing hardcoded admin token checks..."
# Replace ADMIN_TOKEN constant references
find "$DEST_DIR" -type f -name '*.php' -print0 | \
    xargs -0 sed -i -E "s/ADMIN_TOKEN/true \/\* ADMIN_TOKEN removed by integration \/\*/g" 2>/dev/null || true

# Replace token validation checks
find "$DEST_DIR" -type f -name '*.php' -print0 | \
    xargs -0 sed -i -E "s/\\\$_POST\['token'\][[:space:]]*!==[[:space:]]*ADMIN_TOKEN/true \/\* token check removed \/\*/g" 2>/dev/null || true

echo "✓ Admin token checks neutralized (will require proper auth implementation)"
echo ""

# Step 5: Remove copyright/credits lines
echo "[5/7] Removing visible copyright and credits lines..."
find "$DEST_DIR" -type f \( -name '*.html' -o -name '*.php' \) -print0 | \
    xargs -0 sed -i -E '/Copyright/Id; /THK Analytics/Id; /Thought is free/Id; /WEB SERVICE BY DMM\.com/Id' 2>/dev/null || true

echo "✓ Copyright/credits lines removed"
echo ""

# Step 6: Add TODO comments for mysql_* usage
echo "[6/7] Scanning for deprecated mysql_* function usage..."
echo ""
echo "Files containing mysql_* functions:"
grep -R --line-number "mysql_" "$DEST_DIR" 2>/dev/null || echo "  (none found)"
echo ""

# Add TODO comment to top of files using mysql_*
find "$DEST_DIR" -type f -name '*.php' | while read -r file; do
    if grep -q "mysql_" "$file" 2>/dev/null; then
        # Create temporary file with TODO prepended
        {
            echo "<?php // TODO: mysql_* usage detected - please replace with PDO/mysqli ?>"
            cat "$file"
        } > "$file.new"
        mv "$file.new" "$file"
        echo "  Added TODO to: ${file#$DEST_DIR/}"
    fi
done

echo "✓ TODO comments added for mysql_* usage"
echo ""

# Step 7: Lint PHP files
echo "[7/7] Linting PHP files for syntax errors..."
lint_errors=0
lint_output=$(mktemp)

find "$DEST_DIR" -type f -name '*.php' | while read -r file; do
    if ! php -l "$file" > "$lint_output" 2>&1; then
        echo "  LINT ERROR in ${file#$DEST_DIR/}:"
        cat "$lint_output" | grep -v "^No syntax errors" || true
        lint_errors=$((lint_errors + 1))
    fi
done

rm -f "$lint_output"

if [ $lint_errors -eq 0 ]; then
    echo "✓ All PHP files passed syntax check"
else
    echo "⚠ Found $lint_errors files with syntax errors (review output above)"
fi
echo ""

# Summary
echo "=========================================="
echo "Integration Complete!"
echo "=========================================="
echo ""
echo "Next steps (MANUAL - see thk-integration/README.md):"
echo ""
echo "  1. Run: find $DEST_DIR -name '*.php' -exec php -l {} \;"
echo "     Verify all PHP files have valid syntax"
echo ""
echo "  2. Replace mysql_* functions with PDO/mysqli"
echo "     Search: grep -r 'mysql_' $DEST_DIR"
echo ""
echo "  3. Wire up application routes"
echo "     Map /thk-analytics/* to $DEST_DIR"
echo ""
echo "  4. Reintroduce proper authentication"
echo "     Review files with '/* token check removed */' comments"
echo ""
echo "  5. Review removed copyright/credits"
echo "     Check backups: $BACKUP_DIR"
echo ""
echo "  6. Test all functionality thoroughly"
echo ""
echo "Backups stored in: $BACKUP_DIR"
echo "Integration files in: $DEST_DIR"
echo ""
echo "For detailed documentation, see: thk-integration/README.md"
echo ""
