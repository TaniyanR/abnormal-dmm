# THK Analytics Integration

## Overview

This directory contains tools and documentation for integrating the THK Analytics package (thk-analytics-124.zip) into the abnormal-dmm project.

## Integration Plan

The integration follows a conservative, non-destructive approach:

1. **Extract** the THK Analytics ZIP archive to `thirdparty/thk-analytics`
2. **Backup** original PHP and HTML files to `thirdparty/thk-analytics-backups`
3. **Apply automated patches**:
   - Neutralize ADMIN_TOKEN checks (replace with true and add comments)
   - Remove copyright notices (Copyright, THK Analytics, "Thought is free", "WEB SERVICE BY DMM.com")
   - Add TODO comments for deprecated mysql_* function usage
4. **Lint** PHP files to check for syntax errors
5. **Commit** changes to version control

## Usage

### Automated Integration (GitHub Actions)

The integration can be run automatically via GitHub Actions workflow:

1. Navigate to Actions tab in the GitHub repository
2. Select "THK Analytics integration" workflow
3. Click "Run workflow" button
4. Monitor the workflow execution

The workflow will:
- Extract the archive
- Create backups
- Apply patches
- Commit and push changes to `feature/integrate-thk-analytics-integrated` branch

### Manual Integration (Shell Script)

For local testing or manual execution:

```bash
cd thk-integration
chmod +x import_and_patch.sh
./import_and_patch.sh
```

The script performs the same operations as the GitHub Actions workflow.

## Post-Integration Manual Follow-ups

After the automated integration completes, the following manual tasks are required:

### 1. Replace Deprecated mysql_* Functions

The THK Analytics code may use deprecated `mysql_*` functions. Search for them:

```bash
grep -R "mysql_" thirdparty/thk-analytics
```

Replace with modern alternatives:
- `mysql_connect()` → Use PDO or `mysqli_connect()`
- `mysql_query()` → Use PDO prepared statements or `mysqli_query()`
- `mysql_fetch_array()` → Use `mysqli_fetch_array()` or PDO methods
- `mysql_close()` → Use `mysqli_close()` or PDO connection cleanup

### 2. Review Removed Copyright/License Text

The integration removes the following text patterns:
- Lines containing "Copyright"
- Lines containing "THK Analytics"
- Lines containing "Thought is free"
- Lines containing "WEB SERVICE BY DMM.com"

**Action Required**: Review the removed text to ensure:
- Required license attributions are preserved elsewhere
- No legal requirements are violated
- Consider adding appropriate credits in a central location

### 3. Wire Up Routes and Endpoints

The THK Analytics code needs to be integrated into the application routing:

- Review entry points in `thirdparty/thk-analytics/`
- Add routes in your application router
- Configure access controls and authentication
- Test endpoints individually

### 4. Linting and Code Quality

Run PHP linting and fix any issues:

```bash
find thirdparty/thk-analytics -type f -name '*.php' -exec php -l {} \;
```

Consider running additional code quality tools:
- PHPStan or Psalm for static analysis
- PHP_CodeSniffer for coding standards
- Security scanners for vulnerabilities

### 5. Configuration Review

Check for hardcoded values that need configuration:
- Database credentials
- API endpoints
- File paths
- Admin tokens (now patched but may need proper implementation)

### 6. Testing

- Test each THK Analytics feature individually
- Verify data flow between abnormal-dmm and THK Analytics components
- Check for cross-site scripting (XSS) vulnerabilities
- Validate user input sanitization
- Test error handling

### 7. Security Audit

The automated patches neutralize admin token checks. **This is intentional for integration testing but requires proper implementation**:

- Implement proper authentication/authorization
- Review session management
- Audit SQL queries for injection vulnerabilities
- Check file upload/download security
- Validate all user inputs

### 8. Documentation

Update project documentation:
- API endpoints added by THK Analytics
- Configuration options
- Usage examples
- Troubleshooting guide

## Files Modified by Integration

- `thirdparty/thk-analytics/` - Extracted and patched THK Analytics code
- `thirdparty/thk-analytics-backups/` - Unmodified original files

## Rollback Procedure

If issues arise, original files can be restored:

```bash
cp -a thirdparty/thk-analytics-backups/* thirdparty/thk-analytics/
```

## Additional Resources

- Original ZIP: `thk-analytics-124.zip` (in repository root)
- Backup location: `thirdparty/thk-analytics-backups/`
- GitHub Actions workflow: `.github/workflows/integrate-thk.yml`
- Integration script: `thk-integration/import_and_patch.sh`
