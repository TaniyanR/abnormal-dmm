#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(pwd)"
WORK_DIR="$REPO_ROOT/thirdparty/thk-analytics"
BACKUP_DIR="$REPO_ROOT/thirdparty/thk-analytics-backups"
SRC_ARCHIVE="thk-analytics-124.zip"
SRC_DIR_ENV=${THK_SRC_DIR:-}

echo "THK Analytics integration helper"
mkdir -p "$WORK_DIR"
mkdir -p "$BACKUP_DIR"

if [[ -n "$SRC_DIR_ENV" && -d "$SRC_DIR_ENV" ]]; then
  echo "Copying THK files from THK_SRC_DIR=$SRC_DIR_ENV"
  rsync -a --exclude '.git' "$SRC_DIR_ENV/" "$WORK_DIR/"
elif [[ -f "$SRC_ARCHIVE" ]]; then
  echo "Extracting $SRC_ARCHIVE to $WORK_DIR"
  unzip -q "$SRC_ARCHIVE" -d "$WORK_DIR-tmp"
  # Move contents properly
  count=$(ls -A "$WORK_DIR-tmp" | wc -l)
  if [ "$count" -eq 1 ] ; then
    item=$(ls -A "$WORK_DIR-tmp")
    if [ -d "$WORK_DIR-tmp/$item" ]; then
      mv "$WORK_DIR-tmp/$item"/* "$WORK_DIR" || true
    else
      mv "$WORK_DIR-tmp"/* "$WORK_DIR" || true
    fi
  else
    mv "$WORK_DIR-tmp"/* "$WORK_DIR" || true
  fi
  rm -rf "$WORK_DIR-tmp"
else
  echo "No THK source found. Place thk-analytics-124.zip next to this script or set THK_SRC_DIR to a directory with the THK source." >&2
  exit 1
fi

# Backup files that will be modified (views and public PHP files)
find "$WORK_DIR" -type f \( -name '*.php' -o -name '*.html' \) | while read -r f; do
  dest="$BACKUP_DIR/"$(echo "$f" | sed -e "s#^$WORK_DIR/##")
  mkdir -p "$(dirname "$dest")"
  cp -a "$f" "$dest"
done

echo "Backed up files to $BACKUP_DIR"

# 1) Neutralize admin token checks conservatively
find "$WORK_DIR" -type f -name '*.php' | while read -r file; do
  sed -i.bak -E 's/ADMIN_TOKEN/true \/\* ADMIN_TOKEN removed by integration \/\*/g' "$file" || true
  sed -i.bak -E "s/\$_POST\['token'\]\s*!==\s*ADMIN_TOKEN/true \/\* token check removed \/\*/g" "$file" || true
  rm -f "$file.bak"
done

# 2) Remove copyright / credit lines in views
find "$WORK_DIR" -type f \( -name '*.html' -o -name '*.php' \) | while read -r file; do
  sed -i '/Copyright/d; /THK Analytics/d; /Thought is free/d; /WEB SERVICE BY DMM.com/d' "$file" || true
done

# 3) Add TODO comment where mysql_ functions are used
find "$WORK_DIR" -type f -name '*.php' | while read -r file; do
  if grep -q "mysql_" "$file" ; then
    echo "Found mysql_ usage in $file — adding TODO comment"
    # Insert comment after the opening PHP tag if it exists, or at the beginning
    if head -n 1 "$file" | grep -q '^<?php'; then
      sed -i '1 a // TODO: mysql_* usage detected — please replace with PDO/mysqli' "$file" || true
    else
      printf "<?php\n// TODO: mysql_* usage detected — please replace with PDO/mysqli\n" | cat - "$file" > "$file.new" && mv "$file.new" "$file" || true
    fi
  fi
done

# 4) Create composer.json hint if not present
if [[ ! -f "$REPO_ROOT/composer.json" ]]; then
  cat > "$REPO_ROOT/composer.json" <<'JSON'
{
  "name": "thirdparty/thk-analytics-integration",
  "description": "Helper composer metadata for THK Analytics integration (non-packagist)",
  "require": {
    "php": ">=8.0"
  }
}
JSON
  echo "Created composer.json with php >=8.0 requirement"
fi

echo "Integration helper completed. Review thirdparty/thk-analytics and backups."
exit 0
