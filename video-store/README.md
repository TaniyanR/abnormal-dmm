# Video Store Module

This directory contains the modular scaffolding for the video store feature, organized following MVC patterns.

## Directory Structure

```
video-store/
├── models/          # Data models and database interactions
├── services/        # Business logic layer
├── controllers/     # HTTP request/response handling
├── views/           # Frontend templates
├── VideoStoreConfig.php  # Configuration management
└── README.md        # This file
```

## Components

### Configuration (`VideoStoreConfig.php`)
Centralized configuration management for the video store module. Handles:
- Default settings for pagination, caching, and sorting
- Environment variable integration
- Runtime configuration updates

### Models (`models/`)
Data access layer for interacting with the database:
- **VideoModel.php**: Base model for video entity CRUD operations
  - Find by ID or content ID
  - Pagination support
  - Count and filtering

### Services (`services/`)
Business logic layer that orchestrates between models and controllers:
- **VideoService.php**: Core video operations service
  - Video retrieval with data enrichment
  - List videos with pagination
  - Parameter validation
  - Business rule enforcement

### Controllers (`controllers/`)
HTTP layer for handling API requests and responses:
- **VideoController.php**: API endpoint controller
  - List videos (GET /api/videos)
  - Get single video (GET /api/videos/{id})
  - JSON response formatting

### Views (`views/`)
Frontend templates for displaying video content:
- **video-list.php**: HTML/JavaScript template for browsing videos
  - Responsive grid layout
  - Pagination controls
  - AJAX loading

## Usage Examples

### Using the Service Layer

```php
require_once __DIR__ . '/video-store/services/VideoService.php';

$pdo = /* your PDO connection */;
$videoService = new VideoService($pdo);

// Get a single video
$video = $videoService->getVideoByContentId('abc123');

// List videos with pagination
$result = $videoService->listVideos([
    'limit' => 20,
    'offset' => 0,
    'filters' => ['keyword' => 'search term']
]);
```

### Using the Controller

```php
require_once __DIR__ . '/video-store/controllers/VideoController.php';

$pdo = /* your PDO connection */;
$controller = new VideoController($pdo);

// Handle list request
$response = $controller->index($_GET);
$controller->respond($response);

// Handle single video request
$response = $controller->show($videoId);
$controller->respond($response);
```

### Configuration

Set environment variables to customize behavior:

```env
VIDEO_STORE_ITEMS_PER_PAGE=20
VIDEO_STORE_MAX_ITEMS_PER_PAGE=100
VIDEO_STORE_CACHE_TTL=3600
VIDEO_STORE_ENABLE_CACHING=true
VIDEO_STORE_DEFAULT_SORT=date
```

Or configure programmatically:

```php
VideoStoreConfig::set('items_per_page', 30);
$itemsPerPage = VideoStoreConfig::get('items_per_page');
```

## Integration with Existing Code

This module is designed to work alongside the existing application structure:
- Compatible with existing `ItemRepository` and `DmmClient` classes
- Uses the same PDO connection established in `bootstrap.php`
- Can be gradually adopted without disrupting current functionality

## Future Enhancements

- Add caching layer for improved performance
- Implement additional filtering options (genre, actress, etc.)
- Add shopping cart functionality
- Integrate user reviews and ratings
- Implement search with full-text indexing

## Development Notes

- All classes follow PSR-4 autoloading conventions
- Error logging is implemented for debugging
- Input validation and sanitization is included
- Response format matches existing API conventions
