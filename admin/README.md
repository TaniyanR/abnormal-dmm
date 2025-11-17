# Admin API Settings UI

This admin interface allows you to configure DMM API fetch settings and manually trigger data fetches from the DMM API.

## Features

- **API Fetch Configuration**: Configure parameters for automatic and manual API fetches
- **Manual Fetch Trigger**: Manually trigger a fetch from the DMM API
- **Settings Persistence**: All settings are stored in the database
- **Admin Authentication**: Protected by admin token authentication

## Access

The admin UI is located at:
```
/admin/api_settings.php
```

## Settings

### API_RUN_INTERVAL
- **Description**: Interval between automatic fetches (in seconds)
- **Options**: 1h, 3h, 6h, 12h, 24h
- **Default**: 3600 (1 hour)

### API_FETCH_COUNT
- **Description**: Number of items to fetch per API request (hits parameter)
- **Range**: 1-1000
- **Note**: Stored values are capped at 100
- **Default**: 20

### API_FETCH_TOTAL
- **Description**: Total number of items to fetch (will make multiple requests with offset)
- **Range**: 1-1000
- **Default**: 100

### API_SORT
- **Description**: Sort order for API results
- **Default**: date

### API_GTE_DATE / API_LTE_DATE
- **Description**: Optional date range filters (YYYY-MM-DD format)
- **Default**: Empty (no filter)

### API_SITE
- **Description**: DMM site identifier
- **Default**: FANZA

### API_SERVICE
- **Description**: Service type
- **Default**: digital

### API_FLOOR
- **Description**: Floor/category identifier
- **Default**: videoa

## Manual Fetch

The "Run manual fetch" button allows you to trigger an immediate fetch from the DMM API.

**Requirements:**
- Admin token must be set in environment (.env) or provided in the input field
- The token is authenticated against the `ADMIN_TOKEN` environment variable

**Process:**
1. Enter your admin token in the field (or leave blank if already set in config)
2. Click "Run manual fetch"
3. The system will call `/api/admin/fetch` with the configured token
4. Results will be displayed in the "Manual Fetch Output" section

## Authentication

The admin interface requires authentication:
- Settings form submission: Uses hidden token field from server-side config
- Manual fetch: Uses token from input field or server-side default

Set the admin token in your `.env` file:
```env
ADMIN_TOKEN=your_secure_admin_token_here
```

## Deployment

### Production Setup

In production, serve the admin interface through your main web server:

**Nginx Example:**
```nginx
location /admin/ {
    root /path/to/abnormal-dmm;
    index api_settings.php;
    try_files $uri $uri/ =404;
    
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

**Apache Example:**
```apache
<Directory /path/to/abnormal-dmm/admin>
    AllowOverride All
    Require all granted
</Directory>
```

### Development Testing

For local testing, you can run a PHP development server:

```bash
# Start database
docker compose up -d

# Start PHP server (from project root)
php -S localhost:8000

# Access admin UI
open http://localhost:8000/admin/api_settings.php
```

## Database

The admin UI automatically creates an `api_settings` table on first use:

```sql
CREATE TABLE IF NOT EXISTS `api_settings` (
    `key` VARCHAR(64) PRIMARY KEY,
    `value` TEXT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

## Security Notes

- Always use HTTPS in production
- Keep admin token secure and complex
- The admin UI should be behind additional authentication in production (e.g., HTTP Basic Auth, IP whitelist)
- Never commit the `.env` file with real credentials
