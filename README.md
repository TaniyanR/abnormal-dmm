# Video Store - DMM/FANZA Integration

A simple PHP application for fetching and caching video/product items from the DMM (FANZA) Affiliate API.

## Features
- DMM API Integration: Fetch items from DMM ItemList API v3
- Database Caching: Store items in MariaDB for fast access
- REST API: Simple JSON API for accessing cached items
- Docker Support: Easy local development with docker-compose
- Pure PHP: No frameworks required, just PHP 8+ and MariaDB

## 🚀 Quick Start / セットアップ

This project uses Docker Compose for the database and plain PHP for the application.

### Prerequisites / 必要なもの
- Docker & Docker Compose
- PHP 8.0 or higher
- DMM Affiliate API credentials (get from: https://affiliate.dmm.com/)

### Setup Instructions / セットアップ手順

1. **Clone the repository / リポジトリをクローン**
   ```bash
   git clone https://github.com/TaniyanR/abnormal-dmm.git
   cd abnormal-dmm
   ```

2. **Configure environment variables / 環境変数を設定**
   ```bash
   cp .env.example .env
   ```
   Edit `.env` and add your DMM API credentials and DB settings (do NOT commit the real `.env`):
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

3. **Start the database / データベースを起動**
   ```bash
   docker-compose up -d
   ```
   This will:
   - Start a MariaDB container
   - Automatically create the `video_store` database
   - Initialize all required tables

4. **Start the PHP development server / PHP開発サーバーを起動**
   ```bash
   php -S localhost:8000 -t public
   ```
   The API will be available at `http://localhost:8000`.

## API Endpoints / APIエンドポイント

### 1. List Items (GET) / アイテム一覧取得
```bash
GET http://localhost:8000/api/items
```
Optional: `keyword`, `limit`, `offset`.

Example:
```bash
curl "http://localhost:8000/api/items?limit=10&offset=0"
```

### 2. Get Specific Item (GET) / 特定アイテム取得
```bash
GET http://localhost:8000/api/items/{content_id}
```

### 3. Fetch Items from DMM API (POST - Admin Only) / 管理者限定取得
```bash
curl -X POST http://localhost:8000/api/admin/fetch \
  -H "Authorization: Bearer your_admin_token" \
  -H "Content-Type: application/json" \
  -d '{"hits": 20, "offset": 1}'
```

## Project Structure

```
abnormal-dmm/
├── docker-compose.yml
├── .env.example
├── README.md
├── db/
│   └── init.sql
├── src/
│   ├── bootstrap.php
│   ├── config.php
│   ├── helpers.php
│   ├── ItemRepository.php
│   └── DmmClient.php
├── public/
│   └── index.php
└── video-store/           # Modular video store feature (MVC structure)
    ├── models/            # Data models
    ├── services/          # Business logic
    ├── controllers/       # API controllers
    ├── views/             # Frontend templates
    └── VideoStoreConfig.php
```

For more information about the video store module structure and usage, see [video-store/README.md](video-store/README.md).

## Database Schema

Tables included (examples):
- items, genres, actresses, makers
- item_genres, item_actresses, item_makers
- campaigns, fetch_logs

All tables use `utf8mb4` and `InnoDB`.

## Development Notes / 開発メモ

- Database auto-initializes using `db/init.sql`.
- Data persists in a Docker volume.
- Use a proper web server (Nginx/Apache) for production.
- Do not commit `.env` or API keys.

## Troubleshooting

- DB connection: `docker-compose ps` and check `.env` values.
- DMM API: verify `DMM_API_ID`/`DMM_AFFILIATE_ID`.
- Ports: change DB_PORT / PHP server port if in use.

## Security Notes

- Admin token is basic; replace with proper auth for production.
- Never commit real credentials.
- Use HTTPS in production.

## Contributing / ライセンス

Contributions welcome. Respect DMM's API terms.

## Credits

- WEB SERVICE BY DMM.com

```html
<a href="https://affiliate.dmm.com/api/">
  <img src="https://pics.dmm.com/af/web_service/com_135_17.gif" 
       width="135" height="17" 
       alt="WEB SERVICE BY DMM.com" />
</a>
```