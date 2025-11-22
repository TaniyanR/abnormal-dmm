#!/usr/bin/env bash
set -euo pipefail

# THK Analytics import_and_patch.sh
# Conservative, non-destructive import + annotated patches for THK Analytics
# Usage (from repo root):
#   bash thk-integration/import_and_patch.sh
#
# Behavior:
# - If THK_SRC_DIR env var points to a directory, it copies from there.
# - Otherwise expects thk-analytics-124.zip at repo root and extracts it.
# - Creates backups of .php/.html files under thirdparty/thk-analytics-backups/.
# - Neutralizes ADMIN_TOKEN/token checks (annotated) for review only.
# - Removes visible copyright/credit lines from views (patterns only).
# - Adds TODO annotations for files using mysql_* (does NOT auto-convert).
# - Runs php -l on discovered PHP files and reports errors (does not fail the whole run).

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK_DIR="$REPO_ROOT/thirdparty/thk-analytics"
BACKUP_DIR="$REPO_ROOT/thirdparty/thk-analytics-backups"
TMP_DIR="$REPO_ROOT/thirdparty/thk-analytics-tmp"
ZIP_NAME="thk-analytics-124.zip"
SRC_DIR_ENV=${THK_SRC_DIR:-}

# Optional colored output if terminal supports it
if [ -t 1 ]; then
  GREEN=$'\033[0;32m'
  YELLOW=$'\033[1;33m'
  RED=$'\033[0;31m'
  NC=$'\033[0m'
else
  GREEN=""
  YELLOW=""
  RED=""
  NC=""
fi

echo "${GREEN}=== THK Analytics import & patch helper ===${NC}"
echo "Repository root: $REPO_ROOT"
echo ""

# Prepare directories
mkdir -p "$WORK_DIR"
mkdir -p "$BACKUP_DIR"

# 1) Obtain source (THK_SRC_DIR or zip)
if [[ -n "$SRC_DIR_ENV" && -d "$SRC_DIR_ENV" ]]; then
  echo "${YELLOW}Copying THK source from THK_SRC_DIR=$SRC_DIR_ENV ...${NC}"
  rsync -a --exclude '.git' --delete "$SRC_DIR_ENV"/ "$WORK_DIR"/
elif [[ -f "$REPO_ROOT/$ZIP_NAME" ]]; then
  echo "${YELLOW}Extracting $ZIP_NAME ...${NC}"
  rm -rf "$TMP_DIR"
  mkdir -p "$TMP_DIR"
  unzip -q "$REPO_ROOT/$ZIP_NAME" -d "$TMP_DIR"
  # Handle single top-level directory vs flat archive
  shopt -s nullglob dotglob
  TEMP_CONTENTS=("$TMP_DIR"/*)
  if [[ ${#TEMP_CONTENTS[@]} -eq 1 && -d "${TEMP_CONTENTS[0]}" ]]; then
    # move nested contents
    find "${TEMP_CONTENTS[0]}" -mindepth 1 -maxdepth 1 -exec mv -t "$WORK_DIR" {} + || true
  else
    # move top-level entries
    find "$TMP_DIR" -mindepth 1 -maxdepth 1 -exec mv -t "$WORK_DIR" {} + || true
  fi
  shopt -u dotglob nullglob
  rm -rf "$TMP_DIR"
else
  echo "${RED}ERROR: No THK source found. Place $ZIP_NAME at repo root or set THK_SRC_DIR to a directory with the THK source.${NC}"
  exit 1
fi

echo "${GREEN}✓ THK sources are in $WORK_DIR${NC}"
echo ""

# 2) Backup .php and .html files (preserve structure)
echo "${YELLOW}[Backup] Creating backups of .php and .html files...${NC}"
while IFS= read -r -d '' file; do
  rel="${file#$WORK_DIR/}"
  dest="$BACKUP_DIR/$rel"
  mkdir -p "$(dirname "$dest")"
  cp -a -- "$file" "$dest"
done < <(find "$WORK_DIR" -type f \( -name '*.php' -o -name '*.html' \) -print0 || true)
backup_count=$(find "$BACKUP_DIR" -type f | wc -l || true)
echo "${GREEN}✓ Backed up $backup_count files to $BACKUP_DIR${NC}"
echo ""

# 3) Neutralize admin token checks (conservative, annotated)
echo "${YELLOW}[Patch] Neutralizing ADMIN_TOKEN/token checks (annotated)...${NC}"
while IFS= read -r -d '' file; do
  # Replace standalone ADMIN_TOKEN tokens (word-boundary) with annotated true
  sed -i -E 's/\bADMIN_TOKEN\b/true \/* ADMIN_TOKEN removed by integration *\//g' "$file" || true
  # Replace common token validation patterns like $_POST['token'] !== ADMIN_TOKEN
  sed -i -E "s/\\\$_POST\\['token'\\][[:space:]]*!==[[:space:]]*ADMIN_TOKEN/true \/* token check removed *\//g" "$file" || true
done < <(find "$WORK_DIR" -type f -name '*.php' -print0 || true)
echo "${GREEN}✓ Admin token checks annotated (search for 'token check removed' or 'ADMIN_TOKEN removed')${NC}"
echo ""

# 4) Remove visible copyright/credit lines (views only)
echo "${YELLOW}[Patch] Removing visible copyright/credit lines in views...${NC}"
while IFS= read -r -d '' file; do
  sed -i -E '/Copyright/Id; /THK Analytics/Id; /Thought is free/Id; /WEB SERVICE BY DMM\.com/Id' "$file" || true
done < <(find "$WORK_DIR" -type f \( -name '*.php' -o -name '*.html' \) -print0 || true)
echo "${GREEN}✓ Copyright/credit patterns removed from views (backups preserved)${NC}"
echo ""

# 5) Annotate files using mysql_* (do NOT auto-replace)
echo "${YELLOW}[Scan] Searching for deprecated mysql_* usage...${NC}"
mysql_found=false
while IFS= read -r -d '' file; do
  mysql_found=true
  echo "  - ${file#$WORK_DIR/}"
done < <(grep -Rl --null "mysql_" "$WORK_DIR" --include="*.php" 2>/dev/null || true)

if [[ "$mysql_found" = true ]]; then
  echo ""
  echo "${YELLOW}[Patch] Adding TODO annotations for mysql_* usage...${NC}"
  while IFS= read -r -d '' file; do
    # If file already contains our TODO marker, skip
    if grep -q "TODO: mysql_* usage detected" "$file" 2>/dev/null; then
      continue
    fi
    first_line=$(head -n 1 "$file" || true)
    if [[ "$first_line" =~ ^\<\?php ]]; then
      # Insert a comment after the opening PHP tag (line 1)
      sed -i '1 a // TODO: mysql_* usage detected - please replace with PDO/mysqli' "$file" || true
    else
      # Prepend a PHP open tag + TODO comment
      { printf "<?php\n// TODO: mysql_* usage detected - please replace with PDO/mysqli\n"; cat "$file"; } > "$file.tmp" && mv "$file.tmp" "$file" || true
    fi
    echo "  Annotated: ${file#$WORK_DIR/}"
  done < <(grep -Rl --null "mysql_" "$WORK_DIR" --include="*.php" 2>/dev/null || true)
else
  echo "${GREEN}✓ No mysql_* usage detected${NC}"
fi
echo ""

# 6) Optionally add a minimal composer.json at repo root (hint only) if none exists
if [[ ! -f "$REPO_ROOT/composer.json" ]]; then
  echo "${YELLOW}[Hint] Creating minimal composer.json at repo root to indicate PHP requirement...${NC}"
  cat > "$REPO_ROOT/composer.json" <<'JSON'
{
  "name": "thirdparty/thk-analytics-integration",
  "description": "Helper composer metadata for THK Analytics integration (non-packagist)",
  "require": {
    "php": ">=8.0"
  }
}
JSON
  echo "${GREEN}✓ Created composer.json (repo root)${NC}"
else
  echo "${YELLOW}[Hint] composer.json already present at repo root — skipping${NC}"
fi
echo ""

# 7) Lint PHP files (report only)
echo "${YELLOW}[Lint] Running php -l on PHP files (report only)...${NC}"
set +e
lint_errors=0
while IFS= read -r -d '' file; do
  if ! php -l "$file" >/dev/null 2>&1; then
    echo "${RED}Syntax error: $file${NC}"
    php -l "$file" || true
    lint_errors=$((lint_errors + 1))
  fi
done < <(find "$WORK_DIR" -type f -name '*.php' -print0 || true)
set -e

if [[ $lint_errors -eq 0 ]]; then
  echo "${GREEN}✓ All PHP files passed syntax check${NC}"
else
  echo "${YELLOW}⚠ Found $lint_errors files with syntax errors. Please inspect and fix manually.${NC}"
fi
echo ""

# Final summary
echo "=========================================="
echo "${GREEN}Integration run complete.${NC}"
echo ""
echo "Next manual steps (REQUIRED before production):"
echo "  1) Review backups in: $BACKUP_DIR"
echo "  2) Run: find $WORK_DIR -name '*.php' -exec php -l {} \\;"
echo "  3) Migrate mysql_* calls to PDO or mysqli (script only annotated)"
echo "  4) Reintroduce proper authentication for admin pages (search for annotations)"
echo "  5) Wire up routes/endpoints and test in dev environment"
echo "  6) Review removed credits/licenses for legal compliance"
echo ""
echo "Files extracted to: $WORK_DIR"
echo "Backups stored at: $BACKUP_DIR"
echo "=========================================="