#!/usr/bin/env bash
set -euo pipefail

# THK Analytics import_and_patch.sh
# Conservative, non-destructive import + best-effort patches for THK Analytics
# Usage: run from repository root:
#   bash thk-integration/import_and_patch.sh

REPO_ROOT="$(pwd)"
ZIP_FILE="thk-analytics-124.zip"
DEST_DIR="$REPO_ROOT/thirdparty/thk-analytics"
BACKUP_DIR="$REPO_ROOT/thirdparty/thk-analytics-backups"
TEMP_DIR="$REPO_ROOT/thirdparty/thk-analytics-temp"

# Colors (optional)
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}=== THK Analytics import & patch helper ===${NC}"
echo "Repository root: $REPO_ROOT"
echo ""

# 1) Check archive exists
echo -e "${YELLOW}[1/7] Checking archive...${NC}"
if [[ ! -f "$ZIP_FILE" ]]; then
  echo -e "${RED}ERROR: $ZIP_FILE not found in repository root. Place it there or set THK_SRC_DIR to a directory with the THK sources.${NC}"
  exit 1
fi
echo "✓ Found $ZIP_FILE"
echo ""

# 2) Extract archive
echo -e "${YELLOW}[2/7] Extracting archive...${NC}"
mkdir -p "$DEST_DIR"
rm -rf "$TEMP_DIR"
unzip -q "$ZIP_FILE" -d "$TEMP_DIR"

# Handle single top-level directory vs flat archive (bash-only)
shopt -s nullglob dotglob
TEMP_CONTENTS=("$TEMP_DIR"/*)
if [[ ${#TEMP_CONTENTS[@]} -eq 1 && -d "${TEMP_CONTENTS[0]}" ]]; then
  echo "  Archive contains a single top-level directory — moving its contents."
  find "${TEMP_CONTENTS[0]}" -mindepth 1 -maxdepth 1 -exec mv -t "$DEST_DIR" {} + || true
else
  echo "  Moving top-level entries from archive."
  find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -exec mv -t "$DEST_DIR" {} + || true
fi
shopt -u dotglob nullglob
rm -rf "$TEMP_DIR"
echo "✓ Extracted to $DEST_DIR"
echo ""

# 3) Backup originals (PHP & HTML)
echo -e "${YELLOW}[3/7] Backing up .php and .html files...${NC}"
mkdir -p "$BACKUP_DIR"
# Use find -print0 to handle spaces
while IFS= read -r -d '' file; do
  rel="${file#$DEST_DIR/}"
  dest="$BACKUP_DIR/$rel"
  mkdir -p "$(dirname "$dest")"
  cp -a -- "$file" "$dest"
done < <(find "$DEST_DIR" -type f \( -name '*.php' -o -name '*.html' \) -print0 || true)

backup_count=$(find "$BACKUP_DIR" -type f | wc -l || true)
echo "✓ Backed up $backup_count files to $BACKUP_DIR"
echo ""

# 4) Neutralize admin token checks (conservative)
echo -e "${YELLOW}[4/7] Neutralizing admin token checks...${NC}"
# Replace ADMIN_TOKEN identifiers with a true marker (annotated)
# Use perl or sed; keep simple sed with regexp variants
while IFS= read -r -d '' file; do
  # Replace standalone ADMIN_TOKEN tokens
  sed -i -E 's/\bADMIN_TOKEN\b/true \/* ADMIN_TOKEN removed by integration *\//g' "$file" || true
  # Replace common token check pattern: $_POST['token'] !== ADMIN_TOKEN
  sed -i -E "s/\\\$_POST\\['token'\\][[:space:]]*!==[[:space:]]*ADMIN_TOKEN/true \/* token check removed *\//g" "$file" || true
done < <(find "$DEST_DIR" -type f -name '*.php' -print0 || true)
echo "✓ Admin token checks neutralized (review required to reintroduce auth)"
echo ""

# 5) Remove visible copyright / credits in views
echo -e "${YELLOW}[5/7] Removing visible copyright/credit lines...${NC}"
while IFS= read -r -d '' file; do
  # Use case-insensitive removal of lines matching patterns
  sed -i -E '/Copyright/Id; /THK Analytics/Id; /Thought is free/Id; /WEB SERVICE BY DMM\.com/Id' "$file" || true
done < <(find "$DEST_DIR" -type f \( -name '*.php' -o -name '*.html' \) -print0 || true)
echo "✓ Copyright/credit lines removed from views (backups preserved)"
echo ""

# 6) Add TODO comments for mysql_* usage (do NOT attempt auto-replacement)
echo -e "${YELLOW}[6/7] Scanning for deprecated mysql_* usage and annotating...${NC}"
mysql_files=$(grep -R --line-number --null "mysql_" "$DEST_DIR" 2>/dev/null || true)
if [[ -z "$mysql_files" ]]; then
  echo "  No mysql_* usage detected."
else
  # Print list
  echo "Files containing mysql_*:"
  echo "$mysql_files" | tr '\0' '\n' || true

  # Annotate files conservatively
  while IFS= read -r -d '' file; do
    if [[ ! -f "$file" ]]; then
      continue
    fi
    # If first line begins with <?php, insert a comment after the opening tag; else prepend a PHP open with TODO
    first_line=$(head -n 1 "$file" || true)
    if [[ "$first_line" =~ ^\<\?php ]]; then
      # Insert after first line
      sed -i '1 a // TODO: mysql_* usage detected - please replace with PDO/mysqli' "$file" || true
    else
      # Prepend a php open + comment
      { printf "<?php\n// TODO: mysql_* usage detected - please replace with PDO/mysqli\n"; cat "$file"; } > "$file.tmp" && mv "$file.tmp" "$file" || true
    fi
    echo "  Annotated: ${file#$DEST_DIR/}"
  done < <(grep -Rl --null "mysql_" "$DEST_DIR" --include="*.php" 2>/dev/null || true)
fi
echo "✓ mysql_* annotation pass complete"
echo ""

# 7) Optionally add a minimal composer.json hint if not present in thirdparty module
if [[ ! -f "$DEST_DIR/composer.json" && ! -f "$REPO_ROOT/composer.json" ]]; then
  echo -e "${YELLOW}[7/7] Creating a helper composer.json at repo root to hint PHP 8 requirement...${NC}"
  cat > "$REPO_ROOT/composer.json" <<'JSON'
{
  "name": "thirdparty/thk-analytics-integration",
  "description": "Helper composer metadata for THK Analytics integration (non-packagist)",
  "require": {
    "php": ">=8.0"
  }
}
JSON
  echo "✓ Created composer.json (repo root)"
else
  echo -e "${YELLOW}[7/7] Skipping composer.json creation (already present)${NC}"
fi
echo ""

# Lint PHP files (report only; do not fail the script)
echo -e "${YELLOW}Linting PHP files for syntax errors...${NC}"
lint_errors=0
while IFS= read -r -d '' file; do
  if ! php -l "$file" >/dev/null 2>&1; then
    echo -e "${RED}  Syntax error: $file${NC}"
    php -l "$file" || true
    lint_errors=$((lint_errors + 1))
  fi
done < <(find "$DEST_DIR" -type f -name '*.php' -print0 || true)

if [[ $lint_errors -eq 0 ]]; then
  echo -e "${GREEN}✓ All PHP files passed syntax check${NC}"
else
  echo -e "${YELLOW}⚠ Found $lint_errors files with syntax errors. Inspect and fix manually.${NC}"
fi
echo ""

# Final summary
echo "=========================================="
echo -e "${GREEN}Integration run complete.${NC}"
echo ""
echo "Next manual steps (required):"
echo "  1) Review backups in: $BACKUP_DIR"
echo "  2) Run: find $DEST_DIR -name '*.php' -exec php -l {} \\;"
echo "  3) Replace mysql_* calls with PDO or mysqli (script only annotated)"
echo "  4) Reintroduce proper authentication for admin pages"
echo "  5) Wire up routes and test in dev environment"
echo "  6) Review removed credits/licenses for compliance"
echo ""
echo "Files extracted to: $DEST_DIR"
echo "Backups stored at: $BACKUP_DIR"
echo "=========================================="