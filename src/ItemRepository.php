<?php
/**
 * Item Repository
 * 
 * Handles database operations for items (videos/products)
 */

class ItemRepository {
    private $pdo;
    
    /**
     * Initialize repository with PDO connection
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Search items with optional filters
     * 
     * @param array $filters Search filters (keyword, limit, offset)
     * @return array List of items
     */
    public function search($filters = []) {
        $sql = 'SELECT * FROM items WHERE 1=1';
        $params = [];
        
        // Keyword search in title and description
        if (!empty($filters['keyword'])) {
            $sql .= ' AND (title LIKE :keyword OR description LIKE :keyword)';
            $params[':keyword'] = '%' . $filters['keyword'] . '%';
        }
        
        // Order by release date descending
        $sql .= ' ORDER BY release_date DESC, created_at DESC';
        
        // Pagination
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
        $sql .= ' LIMIT :limit OFFSET :offset';
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Find item by content_id
     * 
     * @param string $contentId Content ID from DMM API
     * @return array|null Item data or null if not found
     */
    public function findByContentId($contentId) {
        $stmt = $this->pdo->prepare('SELECT * FROM items WHERE content_id = :content_id LIMIT 1');
        $stmt->execute([':content_id' => $contentId]);
        return $stmt->fetch();
    }
    
    /**
     * Upsert item from DMM API response
     * 
     * Inserts or updates item data from DMM API
     * 
     * @param array $apiItem Item data from DMM API
     * @return int Item ID
     */
    public function upsertFromApi($apiItem) {
        // Extract data from API response
        $contentId = $apiItem['content_id'] ?? null;
        $title = $apiItem['title'] ?? '';
        $url = $apiItem['URL'] ?? null;
        $affiliateUrl = $apiItem['affiliateURL'] ?? null;
        $imageUrl = $apiItem['imageURL']['large'] ?? ($apiItem['imageURL']['small'] ?? null);
        
        // Handle sample images
        $sampleImageUrls = null;
        if (!empty($apiItem['sampleImageURL']['sample_s']['image'])) {
            $sampleImageUrls = json_encode($apiItem['sampleImageURL']['sample_s']['image']);
        }
        
        // Handle sample movie
        $sampleMovieUrl = $apiItem['sampleMovieURL']['size_720_480'] ?? 
                         ($apiItem['sampleMovieURL']['size_560_360'] ?? null);
        
        $description = $apiItem['iteminfo']['keyword'] ?? null;
        
        // Parse release date
        $releaseDate = null;
        if (!empty($apiItem['date'])) {
            $releaseDate = date('Y-m-d', strtotime($apiItem['date']));
        }
        
        // Parse prices
        $price = !empty($apiItem['prices']['price']) ? (int)str_replace('¥', '', $apiItem['prices']['price']) : null;
        $listPrice = !empty($apiItem['prices']['list_price']) ? (int)str_replace('¥', '', $apiItem['prices']['list_price']) : null;
        
        $volume = $apiItem['volume'] ?? null;
        $reviewCount = $apiItem['review']['count'] ?? 0;
        $reviewAverage = $apiItem['review']['average'] ?? 0.0;
        $stock = $apiItem['stock'] ?? null;
        
        // Check if item exists
        $existingItem = $this->findByContentId($contentId);
        
        if ($existingItem) {
            // Update existing item
            $sql = 'UPDATE items SET 
                    title = :title,
                    url = :url,
                    affiliate_url = :affiliate_url,
                    image_url = :image_url,
                    sample_image_urls = :sample_image_urls,
                    sample_movie_url = :sample_movie_url,
                    description = :description,
                    release_date = :release_date,
                    price = :price,
                    list_price = :list_price,
                    volume = :volume,
                    review_count = :review_count,
                    review_average = :review_average,
                    stock = :stock,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE content_id = :content_id';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':url' => $url,
                ':affiliate_url' => $affiliateUrl,
                ':image_url' => $imageUrl,
                ':sample_image_urls' => $sampleImageUrls,
                ':sample_movie_url' => $sampleMovieUrl,
                ':description' => $description,
                ':release_date' => $releaseDate,
                ':price' => $price,
                ':list_price' => $listPrice,
                ':volume' => $volume,
                ':review_count' => $reviewCount,
                ':review_average' => $reviewAverage,
                ':stock' => $stock,
                ':content_id' => $contentId
            ]);
            
            return $existingItem['id'];
        } else {
            // Insert new item
            $sql = 'INSERT INTO items (
                    content_id, title, url, affiliate_url, image_url,
                    sample_image_urls, sample_movie_url, description,
                    release_date, price, list_price, volume,
                    review_count, review_average, stock
                    ) VALUES (
                    :content_id, :title, :url, :affiliate_url, :image_url,
                    :sample_image_urls, :sample_movie_url, :description,
                    :release_date, :price, :list_price, :volume,
                    :review_count, :review_average, :stock
                    )';
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':content_id' => $contentId,
                ':title' => $title,
                ':url' => $url,
                ':affiliate_url' => $affiliateUrl,
                ':image_url' => $imageUrl,
                ':sample_image_urls' => $sampleImageUrls,
                ':sample_movie_url' => $sampleMovieUrl,
                ':description' => $description,
                ':release_date' => $releaseDate,
                ':price' => $price,
                ':list_price' => $listPrice,
                ':volume' => $volume,
                ':review_count' => $reviewCount,
                ':review_average' => $reviewAverage,
                ':stock' => $stock
            ]);
            
            return $this->pdo->lastInsertId();
        }
    }
    
    /**
     * Get total count of items
     * 
     * @param array $filters Optional filters (keyword)
     * @return int Total count
     */
    public function count($filters = []) {
        $sql = 'SELECT COUNT(*) as total FROM items WHERE 1=1';
        $params = [];
        
        if (!empty($filters['keyword'])) {
            $sql .= ' AND (title LIKE :keyword OR description LIKE :keyword)';
            $params[':keyword'] = '%' . $filters['keyword'] . '%';
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return (int)$result['total'];
    }
}
