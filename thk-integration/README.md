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

---

## What the automation does

- Extracts archive into `thirdparty/thk-analytics/`, handling both flat and single-directory ZIPs.
- Copies all `.php` and `.html` files to `thirdparty/thk-analytics-backups/` (preserving structure).
- Neutralizes obvious `ADMIN_TOKEN`/token checks with annotated replacements to allow review (these must be re-secured manually).
- Removes visible copyright/credit lines from templates/views (does not remove license files).
- Adds TODO comments for files using `mysql_*` functions; it does not attempt automatic conversion.
- Runs `php -l` on PHP files and reports syntax issues (does not fail the entire run).
- Optionally adds a minimal `composer.json` hint if none is present, to indicate PHP 8.0+.

---

## Manual follow-ups (required)

After running the automation, the following manual tasks are mandatory before any production use:

1. PHP syntax & lint
   ```bash
   find thirdparty/thk-analytics -name '*.php' -exec php -l {} \;
   ```
   Fix any syntax errors reported (some may have existed in original code).

2. Replace `mysql_*` functions
   - Search:
     ```bash
     grep -R "mysql_" thirdparty/thk-analytics || true
     ```
   - Migrate to PDO (recommended) or `mysqli`. Do not leave deprecated calls in production.

3. Reintroduce proper authentication
   - Search for markers (e.g., `/* token check removed */` or other annotations) and restore authentication using your application's auth system.
   - Options: session-based auth, JWT, or integrate with existing admin panel auth.

4. Wire up routes/endpoints
   - Integrate THK entry points into application routing.
   - Map URLs (e.g., `/thk-analytics/*`) to files under `thirdparty/thk-analytics/` as desired.

5. Review removed credits & license obligations
   - Ensure license attributions are preserved if legally required.
   - Backups are available under `thirdparty/thk-analytics-backups/`.

6. Security audit & testing
   - Audit SQL queries for injection vulnerabilities (especially after `mysql_*` migration).
   - Check for XSS and unsafe file operations.
   - Test all features in a development environment.

---

## File layout after integration (expected)

```
abnormal-dmm/
├── thk-analytics-124.zip
├── thirdparty/
│   ├── thk-analytics/                 # Extracted and patched THK Analytics
│   └── thk-analytics-backups/         # Backups of original PHP/HTML files
├── thk-integration/
│   ├── README.md                      # This file
│   └── import_and_patch.sh            # Helper script
└── .github/
    └── workflows/
        └── integrate-thk.yml          # Automation workflow
```

---

## Rollback

To revert to the original state:

```bash
rm -rf thirdparty/thk-analytics
cp -a thirdparty/thk-analytics-backups/* thirdparty/thk-analytics/
# or re-extract the original
unzip thk-analytics-124.zip -d thirdparty/thk-analytics
```

Also use `git` to inspect and revert commits if needed:
```bash
git log --oneline -- thirdparty/
git checkout <commit> -- thirdparty/thk-analytics
```

---

## Troubleshooting

- "Ensure thk zip exists" failure:
  - Confirm `thk-analytics-124.zip` is at repository root and readable.

- PHP lint errors:
  - Compare with backups to determine whether errors were pre-existing.
  - Fix syntax issues before functional testing.

- Permission issues extracting files:
  - Ensure the runner or local user has write permissions to `thirdparty/`.

- `mysql_*` still failing:
  - Ensure appropriate PHP extensions (`mysqli`, `pdo_mysql`) are available, but prefer migrating to PDO/mysqli APIs.

---

## Contributing / Updating this integration

- Test changes locally with `import_and_patch.sh` before updating the workflow.
- Keep patches conservative and non-destructive — always create backups.
- Document any new automated patches in this README.

---

## Support & License

- THK Analytics may have separate license terms—review the original archive and backups for details.
- For integration tooling issues: open an issue in this repository.
- For THK Analytics functional issues: refer to THK documentation.

---

## Next steps (suggested)
- Run the import locally first, review diffs, then commit to `feature/integrate-thk-analytics-integrated`.
- Perform mysql_* migrations and restore proper authentication before any staging/production tests.
