#!/bin/bash

# THK Analytics Import and Patch Script
# This script extracts the THK Analytics ZIP archive and applies best-effort patches
# Usage: ./import_and_patch.sh

set -e  # Exit on error

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== THK Analytics Integration Script ===${NC}"

# Change to repository root
cd "$(dirname "$0")/.."
REPO_ROOT=$(pwd)

echo -e "${YELLOW}Repository root: ${REPO_ROOT}${NC}"

# Check if ZIP file exists
if [ ! -f "thk-analytics-124.zip" ]; then
    echo -e "${RED}Error: thk-analytics-124.zip not found in repo root${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Found thk-analytics-124.zip${NC}"

# Create target directory
echo -e "${YELLOW}Creating thirdparty/thk-analytics directory...${NC}"
mkdir -p thirdparty/thk-analytics

# Extract archive
echo -e "${YELLOW}Extracting THK Analytics archive...${NC}"
unzip -q thk-analytics-124.zip -d thirdparty/thk-analytics-temp

# If zip contains a single top-level directory, move its contents up
shopt -s nullglob dotglob
TEMP_CONTENTS=(thirdparty/thk-analytics-temp/*)
if [ ${#TEMP_CONTENTS[@]} -eq 1 ] && [ -d "${TEMP_CONTENTS[0]}" ]; then
    echo -e "${YELLOW}Moving nested directory contents...${NC}"
    find "${TEMP_CONTENTS[0]}" -mindepth 1 -maxdepth 1 -exec mv -t thirdparty/thk-analytics/ {} + || true
else
    echo -e "${YELLOW}Moving archive contents...${NC}"
    find thirdparty/thk-analytics-temp -mindepth 1 -maxdepth 1 -exec mv -t thirdparty/thk-analytics/ {} + || true
fi

# Cleanup temp directory
rm -rf thirdparty/thk-analytics-temp

echo -e "${GREEN}✓ Extraction complete${NC}"

# Backup original files
echo -e "${YELLOW}Backing up original PHP and HTML files...${NC}"
mkdir -p thirdparty/thk-analytics-backups

find thirdparty/thk-analytics -type f \( -name '*.php' -o -name '*.html' \) -exec bash -c 'f="$0"; dest="thirdparty/thk-analytics-backups/${f#thirdparty/thk-analytics/}"; mkdir -p "$(dirname "$dest")"; cp -a "$f" "$dest"' {} \;

echo -e "${GREEN}✓ Backups created in thirdparty/thk-analytics-backups/${NC}"

# Apply patches
echo -e "${YELLOW}Applying patches...${NC}"

# 1. Neutralize admin token checks
echo -e "${YELLOW}  - Neutralizing ADMIN_TOKEN checks...${NC}"
find thirdparty/thk-analytics -type f -name '*.php' -print0 | xargs -0 sed -i -E "s/ADMIN_TOKEN/true \/\* ADMIN_TOKEN removed by integration \/\*/g" || true
find thirdparty/thk-analytics -type f -name '*.php' -print0 | xargs -0 sed -i -E "s/\$_POST\['token'\]\s*!==\s*ADMIN_TOKEN/true \/\* token check removed \/\*/g" || true

# 2. Remove copyright lines
echo -e "${YELLOW}  - Removing copyright lines...${NC}"
find thirdparty/thk-analytics -type f \( -name '*.html' -o -name '*.php' \) -print0 | xargs -0 sed -i -E '/Copyright/Id; /THK Analytics/Id; /Thought is free/Id; /WEB SERVICE BY DMM.com/Id' || true

# 3. Add TODO for mysql_* usage
echo -e "${YELLOW}  - Detecting deprecated mysql_* functions...${NC}"
MYSQL_FILES=$(grep -Rl "mysql_" thirdparty/thk-analytics --include="*.php" || true)

if [ -n "$MYSQL_FILES" ]; then
    echo -e "${YELLOW}    Found mysql_* usage in:${NC}"
    echo "$MYSQL_FILES" | while read -r file; do
        echo "      - $file"
    done
    
    echo -e "${YELLOW}  - Adding TODO comments for mysql_* usage...${NC}"
    find thirdparty/thk-analytics -type f -name '*.php' | while read -r file; do
        if grep -q "mysql_" "$file"; then
            # Only add TODO if file doesn't already start with <?php
            if ! head -n 1 "$file" | grep -q "^<?php"; then
                printf "<?php // TODO: mysql_* usage detected - please replace with PDO/mysqli ?>\n" | cat - "$file" > "$file.new" && mv "$file.new" "$file" || true
            else
                # Insert TODO after the opening PHP tag
                sed -i '1 a // TODO: mysql_* usage detected - please replace with PDO/mysqli' "$file" || true
            fi
        fi
    done
else
    echo -e "${GREEN}    No mysql_* functions detected${NC}"
fi

echo -e "${GREEN}✓ Patches applied${NC}"

# Add composer.json if absent
if [ ! -f "thirdparty/thk-analytics/composer.json" ]; then
    echo -e "${YELLOW}Creating basic composer.json...${NC}"
    cat > thirdparty/thk-analytics/composer.json << 'EOF'
{
    "name": "thk/analytics",
    "description": "THK Analytics integration for abnormal-dmm",
    "type": "library",
    "require": {
        "php": ">=8.0"
    },
    "autoload": {
        "psr-4": {
            "THK\\Analytics\\": "src/"
        }
    }
}
EOF
    echo -e "${GREEN}✓ Created composer.json${NC}"
fi

# Lint PHP files
echo -e "${YELLOW}Linting PHP files...${NC}"
set +e  # Don't exit on lint errors
HAS_LINT_ERRORS=false

while IFS= read -r file; do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo -e "${RED}  Syntax error in: $file${NC}"
        php -l "$file"
        HAS_LINT_ERRORS=true
    fi
done < <(find thirdparty/thk-analytics -type f -name '*.php')

set -e

if [ "$HAS_LINT_ERRORS" = false ]; then
    echo -e "${GREEN}✓ All PHP files passed syntax check${NC}"
else
    echo -e "${YELLOW}⚠ Some PHP files have syntax errors (see above)${NC}"
fi

# Summary
echo -e "\n${GREEN}=== Integration Complete ===${NC}"
echo -e "${YELLOW}Next steps:${NC}"
echo "  1. Review changes in thirdparty/thk-analytics/"
echo "  2. Address mysql_* function usage (see TODO comments)"
echo "  3. Review removed copyright/license text"
echo "  4. Wire up routes and endpoints"
echo "  5. Run security audit"
echo "  6. Test thoroughly"
echo ""
echo -e "${YELLOW}Backups available in: thirdparty/thk-analytics-backups/${NC}"
echo -e "${YELLOW}See thk-integration/README.md for detailed follow-up instructions${NC}"
