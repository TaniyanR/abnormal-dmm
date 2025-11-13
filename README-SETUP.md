# DMM Video Store API - Setup Guide

This is the initial scaffolding for the abnormal-dmm Video Store DMM/FANZA integration project.

**Branch:** `feature/dmm-scaffold`

## 🚀 Quick Start

### Prerequisites

- Docker and Docker Compose
- PHP 8.0 or higher
- Git

### 1. Clone and Setup

```bash
# Clone the repository
git clone https://github.com/TaniyanR/abnormal-dmm.git
cd abnormal-dmm

# Copy environment configuration
cp .env.example .env
```

### 2. Configure Environment

Edit `.env` file and set your credentials:

```bash
# Database credentials
DB_PASSWORD=your_secure_password
DB_ROOT_PASSWORD=your_root_password

# DMM API credentials (obtain from https://affiliate.dmm.com/)
DMM_API_ID=your_dmm_api_id
DMM_AFFILIATE_ID=your_affiliate_id

# Admin authentication token (generate a secure random string)
ADMIN_TOKEN=your_secure_admin_token
```

### 3. Start Database

```bash
# Start MariaDB with Docker Compose
docker-compose up -d

# Wait for database initialization (about 10-15 seconds)
docker-compose logs -f db
# Press Ctrl+C when you see "ready for connections"
```

### 4. Start PHP Development Server

```bash
# Start PHP built-in server
php -S localhost:8000 -t public/

# Server will be available at http://localhost:8000
```

## 📡 API Endpoints

### Public Endpoints

#### Search Items
```bash
GET /api/items
Query parameters:
  - keyword: Search keyword (optional)
  - genre_id: Filter by genre ID (optional)
  - actress_id: Filter by actress ID (optional)
  - limit: Number of items (default: 20, max: 100)
  - offset: Pagination offset (default: 0)

Example:
curl "http://localhost:8000/api/items?keyword=sample&limit=10"
```

#### Get Item Details
```bash
GET /api/items/{content_id}

Example:
curl "http://localhost:8000/api/items/abc123"
```

### Admin Endpoints

#### Fetch Items from DMM API
```bash
POST /api/admin/fetch
Headers:
  - X-Admin-Token: your_admin_token
Body (JSON):
  {
    "hits": 20,
    "offset": 1
  }

Example:
curl -X POST "http://localhost:8000/api/admin/fetch" \
  -H "X-Admin-Token: your_admin_token" \
  -H "Content-Type: application/json" \
  -d '{"hits": 20, "offset": 1}'
```

## 🗄️ Database Schema

The database includes the following tables:

- **items** - Video/product information
- **genres** - Genre categories
- **actresses** - Actress/performer information
- **item_genres** - Item-Genre relationships
- **item_actresses** - Item-Actress relationships
- **makers** - Production companies
- **item_makers** - Item-Maker relationships
- **campaigns** - Campaign/promotion information
- **fetch_logs** - API fetch operation logs

## 🔧 Development

### Check Database

```bash
# Access MariaDB CLI
docker-compose exec db mysql -u root -p video_store
# Enter password: DB_ROOT_PASSWORD from .env

# Show tables
SHOW TABLES;

# Query items
SELECT * FROM items LIMIT 10;
```

### View Logs

```bash
# Docker logs
docker-compose logs -f db

# PHP error log
tail -f /path/to/php/error.log
```

### Stop Services

```bash
# Stop Docker services
docker-compose down

# Stop Docker and remove volumes (WARNING: deletes all data)
docker-compose down -v
```

## 📝 Project Structure

```
abnormal-dmm/
├── docker-compose.yml          # Docker Compose configuration
├── .env.example                # Environment template
├── db/
│   └── init.sql               # Database initialization script
├── php/
│   └── config.php             # Configuration loader
├── public/
│   └── index.php              # API entry point
└── src/
    ├── bootstrap.php          # Application bootstrap
    ├── helpers.php            # Helper functions
    ├── ItemRepository.php     # Item data access layer
    └── DmmClient.php          # DMM API client
```

## ⚠️ Important Notes

- **Security**: Never commit your `.env` file with real credentials
- **Admin Token**: Generate a secure random token for production
- **DMM API**: Respect API rate limits and terms of service
- **HTTPS**: Use HTTPS in production environments
- **Database**: Backup your database regularly

## 🔐 Security Checklist

- [ ] `.env` file is not committed to Git
- [ ] Admin token is a secure random string (at least 32 characters)
- [ ] Database passwords are strong and unique
- [ ] PHP error display is disabled in production (`APP_DEBUG=false`)
- [ ] HTTPS is enabled in production

## 📚 Next Steps

1. Test API endpoints with sample data
2. Implement frontend interface
3. Add user authentication
4. Set up automated DMM API fetching
5. Implement caching layer
6. Add comprehensive error handling
7. Set up monitoring and logging

## 🆘 Troubleshooting

### Database connection failed
- Check Docker is running: `docker ps`
- Verify database credentials in `.env`
- Ensure database is initialized: `docker-compose logs db`

### DMM API errors
- Verify API credentials are correct
- Check API endpoint is accessible
- Review fetch_logs table for error details

### Permission errors
- Ensure PHP has write permissions for logs
- Check Docker volume permissions

## 📄 License

See main project README for license information.

## 🔗 Resources

- [DMM Affiliate API Documentation](https://affiliate.dmm.com/api/)
- [PHP PDO Documentation](https://www.php.net/manual/en/book.pdo.php)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
