# THK Analytics Integration

## Overview

This directory contains automation tools and documentation for integrating the THK Analytics library (version 124) into the abnormal-dmm project. The integration process is designed to be conservative and non-destructive: it creates backups of original files and applies only safe, reversible patches to make the code reviewable and easier to adapt to PHP 8+.

## Goals

1. Extract `thk-analytics-124.zip` from the repository root into `thirdparty/thk-analytics`.
2. Backup original PHP and HTML files to `thirdparty/thk-analytics-backups`.
3. Apply conservative, automated patches:
   - Neutralize hardcoded admin token checks that may block integration (for review only).
   - Remove visible copyright/credits lines from views.
   - Add TODO comments where deprecated `mysql_*` functions are detected.
4. Validate with PHP linting (`php -l`).
5. Document manual follow-ups required after automation.

---

## Usage

There are two ways to run the integration: GitHub Actions (recommended for consistency) or the local script.

### Option 1 — GitHub Actions (recommended)

A workflow is provided at `.github/workflows/integrate-thk.yml`. It is triggerable via `workflow_dispatch`.

To run:
1. Go to the repository on GitHub → Actions.
2. Select "THK Analytics integration".
3. Click "Run workflow" and pick the branch (e.g., `feature/integrate-thk-analytics`).
4. The workflow will extract the archive, create backups, apply patches, run lint, and push results to the `feature/integrate-thk-analytics-integrated` branch.

### Option 2 — Manual (local)

For local testing or manual control, use the script:

```bash
cd /path/to/abnormal-dmm
bash thk-integration/import_and_patch.sh
```

Prerequisites:
- Bash (Linux/macOS or WSL/Git Bash on Windows)
- `unzip`
- PHP CLI (for linting)
- `thk-analytics-124.zip` present in repository root (or set `THK_SRC_DIR` to a directory containing THK sources)

---

## What the automation does

1. Extracts archive into `thirdparty/thk-analytics/`, handling both flat and single-directory ZIPs.
2. Creates `thirdparty/thk-analytics-backups/` and copies all `.php` and `.html` files preserving structure.
3. Neutralizes ADMIN_TOKEN/token checks conservatively to allow review/testing. These are annotated so they can be found and restored to proper auth later.
4. Removes visible copyright/credit lines (patterns: "Copyright", "THK Analytics", "Thought is free", "WEB SERVICE BY DMM.com") from views only — license files and terms are not removed.
5. Adds TODO comments where `mysql_*` functions are present; it does NOT attempt automatic database-layer rewrites.
6. Runs `php -l` on discovered PHP files to surface syntax errors.
7. Leaves backups for rollback and review.

---

## Manual follow-ups (required)

After automation completes, you must perform these manual steps before considering any production use:

1. PHP syntax & lint
   ```bash
   find thirdparty/thk-analytics -name '*.php' -exec php -l {} \;
   ```
   Fix any syntax issues reported (some may pre-exist in original code).

2. Replace `mysql_*` functions
   - Search:
     ```bash
     grep -R "mysql_" thirdparty/thk-analytics || true
     ```
   - Migrate to PDO (recommended) or mysqli. Do not leave deprecated calls.

3. Reintroduce proper authentication
   - The automation neutralizes token checks for integration/testing only.
   - Search for `/* token check removed */` or similar markers and restore real auth using your app's auth mechanism (session, JWT, etc.).

4. Wire up routes/endpoints
   - Integrate THK entry points into your app router or map paths (e.g., `/thk-analytics/*`) to `thirdparty/thk-analytics/`.

5. Review removed credits & licenses
   - Ensure license obligations are honored. Backups are under `thirdparty/thk-analytics-backups/`.

6. Security audit & testing
   - Audit for SQL injection vulnerabilities, XSS, unsafe file operations.
   - Test all features in a development environment (Docker recommended).

---

## File structure after integration (expected)

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
cp -a thirdparty/thk-analytics-backups thirdparty/thk-analytics
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
  - Confirm `thk-analytics-124.zip` is committed to the repository root and readable.

- PHP lint errors:
  - Compare with backups to see if issues pre-existed.
  - Fix syntax issues before further integration.

- Permission errors extracting files:
  - Ensure the runner or local user has write permissions to `thirdparty/`.

- `mysql_*` still failing:
  - Ensure `mysqli`/`pdo_mysql` extensions are available, but prefer migrating to PDO/mysqli APIs.

---

## Contributing / Updating this integration

- Test updates locally with `import_and_patch.sh` before updating the workflow.
- Keep changes conservative and non-destructive — always create backups.
- Document any new automated patches clearly in this README.

---

## License & Support

- THK Analytics may have its own license terms included in the original archive — review them in `thirdparty/thk-analytics-backups/`.
- For integration automation issues: open an issue in this repository.
- For THK Analytics feature issues: refer to THK Analytics documentation.
