#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   bash scripts/check_thk_php_syntax.sh
# or specify repo root:
#   bash scripts/check_thk_php_syntax.sh /path/to/repo

REPO_ROOT="${1:-$(pwd)}"
TARGET="$REPO_ROOT/thirdparty/thk-analytics"

if [[ ! -d "$TARGET" ]]; then
  echo "ERROR: target directory not found: $TARGET"
  exit 1
fi

OUT_DIR="$REPO_ROOT/tmp-thk-syntax-check"
mkdir -p "$OUT_DIR"
FAIL_FILE="$OUT_DIR/failures.txt"
REPORT_FILE="$OUT_DIR/syntax_report.txt"
rm -f "$FAIL_FILE" "$REPORT_FILE"

echo "Running PHP syntax checks under: $TARGET"
echo "Failures will be written to: $FAIL_FILE"
echo "Full report will be written to: $REPORT_FILE"
echo ""

count_total=0
count_fail=0

# iterate PHP files
while IFS= read -r -d '' file; do
  count_total=$((count_total+1))
  # run php -l
  if php -l "$file" > /dev/null 2>&1; then
    echo "[OK]    $file" >> "$REPORT_FILE"
  else
    echo "[ERROR] $file" >> "$REPORT_FILE"
    php -l "$file" >> "$REPORT_FILE" 2>&1 || true
    echo "$file" >> "$FAIL_FILE"
    count_fail=$((count_fail+1))
  fi
done < <(find "$TARGET" -type f -name '*.php' -print0)

echo ""
echo "Summary:"
echo "  Total PHP files checked: $count_total"
echo "  Files with syntax errors: $count_fail"
echo ""
echo "Report files:"
echo "  $REPORT_FILE"
echo "  $FAIL_FILE (only present if there are failures)"
echo ""
if [[ $count_fail -gt 0 ]]; then
  echo "First few failure details from report:"
  echo "-------------------------------------"
  sed -n '1,200p' "$REPORT_FILE" | grep -nE '\[ERROR\]|\bParse error\b|\bFatal error\b' || true
  echo "-------------------------------------"
else
  echo "No syntax errors detected."
fi

# exit with non-zero if failures found so CI can catch it if desired
if [[ $count_fail -gt 0 ]]; then
  exit 2
fi
