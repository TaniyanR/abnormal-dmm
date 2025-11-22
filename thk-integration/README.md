# THK Analytics Integration

## Overview

This directory contains tools and documentation for integrating the THK Analytics package (thk-analytics-124.zip) into the abnormal-dmm project. The approach is conservative and non-destructive: the automation creates backups of original files and applies only reversible, annotated changes to make the code easier to review and adapt to PHP 8+.

## Goals

1. Extract `thk-analytics-124.zip` from the repository root into `thirdparty/thk-analytics`.
2. Backup original PHP and HTML files to `thirdparty/thk-analytics-backups`.
3. Apply conservative, automated patches:
   - Neutralize hardcoded admin token checks that may block integration (for review only).
   - Remove visible copyright/credits lines from views.
   - Add TODO comments where deprecated `mysql_*` functions are detected (annotations only).
4. Validate with PHP linting (`php -l`).
5. Document manual follow-ups required after automation.

---

## Usage

There are two ways to run the integration: GitHub Actions (recommended for reproducibility) or the local script (for testing / manual control).

### Option 1 — GitHub Actions (recommended)

A workflow is provided at `.github/workflows/integrate-thk.yml` and is triggerable via `workflow_dispatch`.

To run:
1. Go to the repository on GitHub → Actions.
2. Select "THK Analytics integration".
3. Click "Run workflow" and pick the branch to run from (e.g., `feature/integrate-thk-analytics`).
4. The workflow will:
   - Extract `thk-analytics-124.zip` (must be present at repo root).
   - Create backups under `thirdparty/thk-analytics-backups/`.
   - Apply conservative patches and annotations.
   - Run `php -l` for syntax checks.
   - Commit and push the results to `feature/integrate-thk-analytics-integrated`.

### Option 2 — Manual (local)

For local testing or controlled manual runs:

1. Place `thk-analytics-124.zip` at the repository root (or set the environment variable `THK_SRC_DIR` to point to a directory containing the THK sources).
2. From the repository root, run:
   ```bash
   bash thk-integration/import_and_patch.sh
   ```
3. Inspect backups and the modified files under `thirdparty/thk-analytics/` before committing.

Prerequisites:
- Bash (Linux/macOS or WSL/Git Bash on Windows)
- `unzip`
- PHP CLI (for linting)
- `thk-analytics-124.zip` (or THK source dir)
