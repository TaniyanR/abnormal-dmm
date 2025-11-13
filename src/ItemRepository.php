<?php
/**
 * Item Repository
 * Handles database operations for items (products/videos)
 */

class ItemRepository {
    private $pdo;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Search for items with optional filters
     *
     * @param array $filters Search filters (keyword, limit, offset)
     * @return array List of items
     */
    public function search($filters = []) {
        $keyword = $filters['keyword'] ?? '';
        $limit = $filters['limit'] ?? 20;
        $offset = $filters['offset'] ?? 0;

        $sql = "SELECT * FROM items WHERE 1=1";
        $params = [];

        // Add keyword search if provided
        if (!empty($keyword)) {
            $sql .= " AND (title LIKE :keyword OR description LIKE :keyword)";
            $params['keyword'] = '%' . $keyword . '%';
        }

        // Add sorting and pagination
        $sql .= " ORDER BY release_date DESC, created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        
        // Bind keyword parameter if it exists
        if (!empty($keyword)) {
            $stmt->bindValue(':keyword', $params['keyword'], PDO::PARAM_STR);
        }
        
        // Bind pagination parameters
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Find an item by content ID
     *
     * @param string $contentId Content ID to search for
     * @return array|false Item data or false if not found
     */
    public function findByContentId($contentId) {
        $stmt = $this->pdo->prepare("SELECT * FROM items WHERE content_id = :content_id LIMIT 1");
        $stmt->execute(['content_id' => $contentId]);
        return $stmt->fetch();
    }

    /**
     * Insert or update an item from DMM API data
     *
     * @param array $apiData Data from DMM API
     * @return int Item ID (new or existing)
     */
    public function upsertFromApi($apiData) {
        // Extract fields from API response
        $contentId = $apiData['content_id'] ?? '';
        $title = $apiData['title'] ?? '';
        $description = $apiData['iteminfo']['product_description'] ?? null;
        $productUrl = $apiData['URL'] ?? null;
        $affiliateUrl = $apiData['affiliateURL'] ?? null;
        
        // Handle images
        $imageUrlSmall = $apiData['imageURL']['small'] ?? null;
        $imageUrlLarge = $apiData['imageURL']['large'] ?? null;
        
        // Handle sample video
        $sampleVideoUrl = null;
        if (isset($apiData['sampleMovieURL']['size_476_306'])) {
            $sampleVideoUrl = $apiData['sampleMovieURL']['size_476_306'];
        } elseif (isset($apiData['sampleMovieURL']['size_560_360'])) {
            $sampleVideoUrl = $apiData['sampleMovieURL']['size_560_360'];
        }
        
        // Handle price
        $price = $apiData['prices']['price'] ?? null;
        if (is_string($price)) {
            $price = (int)preg_replace('/[^0-9]/', '', $price);
        }
        
        // Handle release date
        $releaseDate = null;
        if (isset($apiData['date'])) {
            $releaseDate = date('Y-m-d', strtotime($apiData['date']));
        }

        // Check if item exists
        $existing = $this->findByContentId($contentId);

        if ($existing) {
            // Update existing item
            $sql = "UPDATE items SET 
                title = :title,
                description = :description,
                product_url = :product_url,
                affiliate_url = :affiliate_url,
                image_url_small = :image_url_small,
                image_url_large = :image_url_large,
                sample_video_url = :sample_video_url,
                price = :price,
                release_date = :release_date,
                updated_at = CURRENT_TIMESTAMP
                WHERE content_id = :content_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'title' => $title,
                'description' => $description,
                'product_url' => $productUrl,
                'affiliate_url' => $affiliateUrl,
                'image_url_small' => $imageUrlSmall,
                'image_url_large' => $imageUrlLarge,
                'sample_video_url' => $sampleVideoUrl,
                'price' => $price,
                'release_date' => $releaseDate,
                'content_id' => $contentId
            ]);

            return $existing['id'];
        } else {
            // Insert new item
            $sql = "INSERT INTO items (
                content_id, title, description, product_url, affiliate_url,
                image_url_small, image_url_large, sample_video_url, price, release_date
            ) VALUES (
                :content_id, :title, :description, :product_url, :affiliate_url,
                :image_url_small, :image_url_large, :sample_video_url, :price, :release_date
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'content_id' => $contentId,
                'title' => $title,
                'description' => $description,
                'product_url' => $productUrl,
                'affiliate_url' => $affiliateUrl,
                'image_url_small' => $imageUrlSmall,
                'image_url_large' => $imageUrlLarge,
                'sample_video_url' => $sampleVideoUrl,
                'price' => $price,
                'release_date' => $releaseDate
            ]);

            return $this->pdo->lastInsertId();
        }
    }

    /**
     * Get total count of items
     *
     * @param string $keyword Optional keyword filter
     * @return int Total count
     */
    public function count($keyword = '') {
        $sql = "SELECT COUNT(*) FROM items WHERE 1=1";
        $params = [];

        if (!empty($keyword)) {
            $sql .= " AND (title LIKE :keyword OR description LIKE :keyword)";
            $params['keyword'] = '%' . $keyword . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn();
    }
}
