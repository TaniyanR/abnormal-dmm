# THK Analytics Integration Plan

This directory contains a small plan and an automated helper script to integrate the THK Analytics codebase into this repository (abnormal-dmm).

## Goals
- Import THK Analytics files into this repository under `thirdparty/thk-analytics` (or a location you prefer).
- Remove admin login screens and hard-coded copyright/credit text as requested.
- Make THK Analytics compatible with the PHP version used by this repo (per README: PHP 8.0+), and adjust configuration as needed.
- Provide an automated script that performs a best-effort import + patch (non-destructive: it creates a backup of files it modifies).

## Important notes
- This script is a best-effort helper. Because THK Analytics was provided externally (not in this repo), you'll need to place the THK Analytics source code archive (zip or tar) next to this script, or point the script to where the files live.
- The script will NOT automatically enable or wire up application-specific routes — it will import files, remove login &amp; copyright strings, and run PHP-compatibility replacements.
- Always review the changes and run the app in a development environment (Docker) before deploying to production.

## Usage

1. Add the THK Analytics source archive (zip or tar.gz) next to this script, named `thk-analytics-124.zip` or `thk-analytics.tar.gz`, OR set the environment variable THK_SRC_DIR to point to a directory containing the THK files.

2. Run the script from the repository root:

```bash
bash thk-integration/import_and_patch.sh
```

## What the script does
- Extracts the archive to `thirdparty/thk-analytics`.
- Backs up any target files it will modify under `thirdparty/thk-analytics-backups/`.
- Performs safe sed replacements to:
  - Remove admin login enforcement (token checks / login redirects) by neutralizing checks in admin entry points.
  - Remove or replace visible copyright / credits lines in view templates with an empty string or a single-line comment indicating removed by integration.
  - Update PHP code patterns known to be incompatible with PHP 8 (replace deprecated mysql_* uses, fix constructor names, add null coalescing where appropriate). This is heuristic — manual review required.
- Prints a report of changed files and suggested manual follow-ups.

## Manual follow-ups (recommended after running):
- Review `thirdparty/thk-analytics-backups/` and the new files under `thirdparty/thk-analytics/`.
- Run `php -l` (lint) or `vendor/bin/phpstan` if available against the new files.
- Replace any mysql_* calls with PDO or mysqli (script only marks them).
- Wire up routes/endpoints or include the module in bootstrap.php.
- Update configuration, e.g., database DSN in `config.php` or `.env` and integrate routes.
- Add tests/CI validations for the integrated module.
