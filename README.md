# Video Store - DMM/FANZA Integration

A simple PHP application for fetching and caching video/product items from the DMM (FANZA) Affiliate API.

## Features

- **DMM API Integration**: Fetch items from DMM ItemList API v3
- **Database Caching**: Store items in MariaDB for fast access
- **REST API**: Simple JSON API for accessing cached items
- **Docker Support**: Easy local development with docker-compose
- **Pure PHP**: No frameworks required, just PHP 8+ and MariaDB

## Prerequisites

- PHP 8.0 or higher
- Docker and Docker Compose
- DMM Affiliate API credentials ([Get them here](https://affiliate.dmm.com/api/))

## Project Structure

```
abnormal-dmm/
├── docker-compose.yml     # Docker services configuration
├── .env.example          # Environment variables template
├── README.md             # This file
├── db/
│   └── init.sql         # Database schema
├── src/
│   ├── bootstrap.php    # Application bootstrap
│   ├── config.php       # Configuration
│   ├── helpers.php      # Helper functions
│   ├── ItemRepository.php  # Database operations
│   └── DmmClient.php    # DMM API client
└── public/
    └── index.php        # Front controller / API endpoints
```

## Setup Instructions

### 1. Clone the Repository

```bash
git clone https://github.com/TaniyanR/abnormal-dmm.git
cd abnormal-dmm
```

### 2. Configure Environment Variables

Copy the example environment file and edit it with your settings:

```bash
cp .env.example .env
```

Edit `.env` and add your DMM API credentials:

```env
# Database Configuration
MYSQL_ROOT_PASSWORD=rootpassword
DB_NAME=video_store
DB_USER=video_user
DB_PASSWORD=videopass
DB_HOST=127.0.0.1
DB_PORT=3306

# DMM API Configuration
DMM_API_ID=your_dmm_api_id_here
DMM_AFFILIATE_ID=your_affiliate_id_here

# Admin Authentication
ADMIN_TOKEN=your_secure_admin_token_here
```

### 3. Start Database with Docker Compose

Start the MariaDB service:

```bash
docker-compose up -d
```

This will:
- Download and start MariaDB container
- Automatically run `db/init.sql` to create the database and tables
- Expose the database on port 3306 (or your configured DB_PORT)

Verify the database is running:

```bash
docker-compose ps
```

### 4. Start PHP Built-in Server

Start the PHP development server:

```bash
php -S localhost:8000 -t public
```

The API will be available at `http://localhost:8000`

## API Endpoints

### 1. List Items (GET)

Get a list of cached items with optional search:

```bash
# Get all items (default: 20 items)
curl http://localhost:8000/api/items

# Search items with keyword
curl "http://localhost:8000/api/items?keyword=example"

# Pagination
curl "http://localhost:8000/api/items?limit=10&offset=0"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [...],
    "total": 100,
    "limit": 20,
    "offset": 0
  }
}
```

### 2. Get Single Item (GET)

Get a specific item by content_id:

```bash
curl http://localhost:8000/api/items/{content_id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "content_id": "example_id",
    "title": "Example Title",
    "url": "...",
    "affiliate_url": "...",
    "image_url": "...",
    ...
  }
}
```

### 3. Fetch Items from DMM API (POST - Admin Only)

Fetch new items from DMM API and cache them in the database:

```bash
curl -X POST http://localhost:8000/admin/fetch-items \
  -H "Authorization: Bearer your_admin_token" \
  -H "Content-Type: application/json" \
  -d '{"hits": 20, "offset": 1}'
```

**Response:**
```json
{
  "success": true,
  "message": "Successfully fetched and stored 20 items",
  "data": {
    "items_processed": 20,
    "total_result_count": 50000
  }
}
```

## Database Schema

The application uses the following tables:

- **items**: Main table for video/product items
- **genres**: Genre categories
- **actresses**: Performer information
- **makers**: Studio/maker information
- **item_genres**: Many-to-many relationship between items and genres
- **item_actresses**: Many-to-many relationship between items and actresses
- **item_makers**: Many-to-many relationship between items and makers
- **campaigns**: Campaign/special offer information
- **fetch_logs**: Logs of API fetch operations

All tables use `utf8mb4` character set and `InnoDB` engine.

## Development Tips

### Viewing Database Data

Connect to the database:

```bash
# Using Docker
docker-compose exec mariadb mysql -u video_user -p video_store

# Or from host (if port is exposed)
mysql -h 127.0.0.1 -P 3306 -u video_user -p video_store
```

### Checking Logs

View PHP error logs:

```bash
tail -f /var/log/php_errors.log
```

View Docker logs:

```bash
docker-compose logs -f mariadb
```

### Stopping Services

Stop the database:

```bash
docker-compose down
```

Stop and remove volumes (⚠️ This will delete all data):

```bash
docker-compose down -v
```

## Security Notes

⚠️ **Important Security Considerations:**

1. **Admin Token**: The `ADMIN_TOKEN` provides basic protection for admin endpoints. For production use, implement a proper authentication system (OAuth, JWT, etc.).

2. **API Keys**: Never commit your `.env` file or API keys to version control. The `.env.example` file is provided as a template only.

3. **HTTPS**: Always use HTTPS in production to protect API credentials and data in transit.

4. **Input Validation**: While basic validation is included, consider adding more robust input validation for production use.

5. **Rate Limiting**: Implement rate limiting to prevent API abuse.

## DMM API Documentation

For more information about the DMM Affiliate API:
- [DMM Affiliate API Documentation](https://affiliate.dmm.com/api/)
- [API Registration](https://affiliate.dmm.com/)

## Troubleshooting

### Database Connection Failed

- Verify Docker container is running: `docker-compose ps`
- Check database credentials in `.env`
- Ensure DB_HOST is set to `127.0.0.1` (not `localhost`)

### DMM API Request Failed

- Verify your DMM_API_ID and DMM_AFFILIATE_ID are correct
- Check if your API credentials are active
- Review error logs for detailed error messages

### Port Already in Use

If port 3306 or 8000 is already in use:
- Change `DB_PORT` in `.env` and restart Docker
- Use a different port for PHP server: `php -S localhost:8001 -t public`

## License

This project is for educational and development purposes. Please comply with DMM's API terms of service and affiliate program guidelines.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Credits

- **WEB SERVICE BY DMM.com**

```html
<a href="https://affiliate.dmm.com/api/">
  <img src="https://pics.dmm.com/af/web_service/com_135_17.gif" 
       width="135" height="17" 
       alt="WEB SERVICE BY DMM.com" />
</a>
```
