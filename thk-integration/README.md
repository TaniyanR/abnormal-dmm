# THK Analytics Integration

## Overview

This directory contains automation tools and documentation for integrating the THK Analytics library (version 124) into the abnormal-dmm project. The integration process is designed to be conservative and non-destructive, creating backups of all original files and applying only safe, reversible patches.

## Goals

1. **Extract**: Unpack `thk-analytics-124.zip` from the repository root into `thirdparty/thk-analytics`
2. **Backup**: Create complete backups of all PHP and HTML files before any modifications
3. **Patch**: Apply conservative, automated patches to address common integration issues:
   - Neutralize hardcoded admin token checks that may block integration
   - Remove visible copyright/credits lines for cleaner presentation
   - Add TODO comments where deprecated `mysql_*` functions are detected
4. **Validate**: Run PHP linting to catch syntax errors early
5. **Document**: Clearly identify manual follow-up work required after automation

## Usage

### Option 1: GitHub Actions Workflow (Recommended)

The integration is automated via the GitHub Actions workflow defined in `.github/workflows/integrate-thk.yml`.

**To run:**
1. Navigate to the repository on GitHub
2. Go to **Actions** tab
3. Select **THK Analytics integration** workflow
4. Click **Run workflow** button
5. Select the branch (typically `feature/integrate-thk-analytics`)
6. Click **Run workflow**

The workflow will:
- Extract the THK Analytics archive
- Create backups
- Apply patches
- Lint PHP files
- Commit and push results to `feature/integrate-thk-analytics-integrated` branch

### Option 2: Manual Script Execution

For local testing or manual control, use the `import_and_patch.sh` script:

```bash
cd /path/to/abnormal-dmm
./thk-integration/import_and_patch.sh
```

**Prerequisites:**
- Bash shell (Linux/macOS or WSL/Git Bash on Windows)
- `unzip` command available
- PHP CLI available (for linting)
- `thk-analytics-124.zip` present in repository root

## What the Automation Does

### 1. Extract THK Archive
- Creates `thirdparty/thk-analytics/` directory
- Extracts ZIP contents, handling both flat and single-directory archives
- Cleans up temporary extraction directory

### 2. Backup Original Files
- Creates `thirdparty/thk-analytics-backups/` directory
- Copies all `.php` and `.html` files with original timestamps preserved
- Directory structure mirrors source for easy comparison

### 3. Neutralize Admin Token Checks
Replaces hardcoded admin token validation that might block integration:

**Before:**
```php
if ($_POST['token'] !== ADMIN_TOKEN) {
    die("Unauthorized");
}
```

**After:**
```php
if (true /* token check removed */) {
    die("Unauthorized");
}
```

This allows integration testing while flagging areas requiring proper authentication later.

### 4. Remove Copyright/Credits Lines
Removes visible attribution lines containing:
- "Copyright"
- "THK Analytics"
- "Thought is free"
- "WEB SERVICE BY DMM.com"

This provides cleaner UI but **does NOT remove license files or terms** — only visible footer/header credits.

### 5. Add TODO for mysql_* Functions
- Scans all PHP files for deprecated `mysql_*` function calls
- Prepends TODO comment to affected files:
  ```php
  <?php // TODO: mysql_* usage detected - please replace with PDO/mysqli ?>
  ```
- **Does NOT attempt automatic replacement** (too risky for data layer code)

### 6. Lint PHP Files
Runs `php -l` on all `.php` files to catch syntax errors introduced by patches or present in original code.

## Manual Follow-Ups Required

After running the automation, the following tasks **must** be completed manually:

### 1. ✅ Verify PHP Syntax
Review lint output from the workflow or script. Fix any syntax errors:
```bash
find thirdparty/thk-analytics -name '*.php' -exec php -l {} \;
```

### 2. ✅ Replace mysql_* Functions
**Critical**: The deprecated `mysql_*` extension is removed in PHP 7+. 

Search for affected files:
```bash
grep -r "mysql_" thirdparty/thk-analytics
```

Replace with PDO or mysqli:
- **PDO** (recommended): Provides consistent interface, prepared statements
- **mysqli**: Procedural or OOP, similar to mysql_* functions

**Example migration:**
```php
// Old (mysql_*)
$conn = mysql_connect($host, $user, $pass);
mysql_select_db($db, $conn);
$result = mysql_query("SELECT * FROM users");

// New (PDO)
$conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
$stmt = $conn->query("SELECT * FROM users");
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// New (mysqli procedural)
$conn = mysqli_connect($host, $user, $pass, $db);
$result = mysqli_query($conn, "SELECT * FROM users");
```

### 3. ✅ Wire Up Application Routes
Integrate THK Analytics into the abnormal-dmm application:

- Add route handlers in your routing configuration
- Map URLs like `/thk-analytics/*` to `thirdparty/thk-analytics/` files
- Consider using a reverse proxy or framework routing

**Example (basic PHP routing):**
```php
// In your main router
if (strpos($_SERVER['REQUEST_URI'], '/thk-analytics/') === 0) {
    $file = __DIR__ . '/thirdparty/thk-analytics' . substr($_SERVER['REQUEST_URI'], strlen('/thk-analytics'));
    if (file_exists($file) && is_file($file)) {
        require $file;
        exit;
    }
}
```

### 4. ✅ Reintroduce Proper Authentication
The automation **disabled** admin token checks for integration purposes. You **must** restore authentication:

1. Review files containing `/* token check removed */` or `/* ADMIN_TOKEN removed */`
2. Implement proper authentication using your application's auth system
3. Options:
   - Session-based authentication (check `$_SESSION['user_role']`)
   - JWT tokens
   - Integration with existing admin panel authentication

**Example:**
```php
// Replace this:
if (true /* token check removed */) {

// With proper auth:
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    die("Access denied");
}
```

### 5. ✅ Review Removed Credits
The automation removed visible copyright/credits. Review for:

1. **License compliance**: Ensure any required license attribution is preserved
2. **Original backup**: Refer to `thirdparty/thk-analytics-backups/` for original text
3. **Alternative attribution**: Consider adding to a credits page or README if legally required

Check original license terms:
```bash
find thirdparty/thk-analytics-backups -name "LICENSE*" -o -name "README*"
```

### 6. ✅ Test Thoroughly
- Test all THK Analytics features in a development environment
- Verify database connections and queries work correctly
- Check admin panel functionality
- Review error logs for runtime issues

### 7. ✅ Security Review
- Audit for SQL injection vulnerabilities (especially in manually migrated mysql_* code)
- Ensure all user inputs are sanitized
- Verify file upload restrictions if present
- Check for XSS vulnerabilities in output

## File Structure After Integration

```
abnormal-dmm/
├── thk-analytics-124.zip              # Original archive (kept for reference)
├── thirdparty/
│   ├── thk-analytics/                 # Extracted and patched THK Analytics code
│   │   ├── admin/                     # Admin panel files
│   │   ├── api/                       # API endpoints
│   │   ├── includes/                  # Common includes/utilities
│   │   └── ...                        # Other THK Analytics files
│   └── thk-analytics-backups/         # Unmodified backups of all PHP/HTML files
│       └── (mirrors thk-analytics structure)
├── thk-integration/
│   ├── README.md                      # This file
│   └── import_and_patch.sh            # Manual execution script
└── .github/workflows/
    └── integrate-thk.yml              # Automated integration workflow
```

## Rollback

If integration fails or causes issues:

1. **Restore from backups:**
   ```bash
   rm -rf thirdparty/thk-analytics
   cp -r thirdparty/thk-analytics-backups thirdparty/thk-analytics
   ```

2. **Re-extract original:**
   ```bash
   rm -rf thirdparty/thk-analytics
   unzip thk-analytics-124.zip -d thirdparty/thk-analytics
   ```

3. **Review Git history:**
   ```bash
   git log --oneline -- thirdparty/
   git diff <commit-hash> -- thirdparty/thk-analytics
   ```

## Troubleshooting

### Workflow fails at "Ensure thk zip exists"
- Verify `thk-analytics-124.zip` is committed to repository root
- Check file permissions (should be readable)

### PHP lint errors
- Review error output to identify problematic files
- Check if errors existed in original code (compare with backups)
- Fix syntax errors before proceeding with integration

### mysql_* functions not working
- Ensure PHP version supports target database extension
- Install mysqli or PDO extension if missing: `php -m | grep -E "pdo|mysqli"`

### Permission errors during extraction
- Check workflow runner permissions
- Ensure `thirdparty/` directory is writable

## Contributing

When updating this integration:

1. Test changes locally with `import_and_patch.sh` first
2. Update this README if adding new patches or steps
3. Ensure workflow remains non-destructive (always create backups)
4. Document any new manual follow-up requirements

## License

This integration tooling is part of the abnormal-dmm project. THK Analytics itself may have separate license terms — refer to original archive for details.

## Support

For issues related to:
- **Integration automation**: Open issue in abnormal-dmm repository
- **THK Analytics functionality**: Refer to THK Analytics documentation
- **mysql_* migration**: See PHP documentation for PDO/mysqli migration guides
