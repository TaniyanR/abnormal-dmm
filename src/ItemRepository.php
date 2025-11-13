<?php
/**
 * src/ItemRepository.php
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
     * @param array $filters ['keyword' => string, 'genre_id' => int|null, 'actress_id' => int|null, 'limit' => int, 'offset' => int]
     * @return array List of items
     */
    public function search($filters = []) {
        $keyword = $filters['keyword'] ?? '';
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        // sanitize
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $sql = 'SELECT i.* FROM items i WHERE 1=1';
        $params = [];

        // Keyword search
        if (!empty($keyword)) {
            $sql .= ' AND (i.title LIKE :keyword OR i.description LIKE :keyword OR i.content_id LIKE :keyword)';
            $params[':keyword'] = '%' . $keyword . '%';
        }

        // Genre filter (by external genre id or internal id)
        if (!empty($filters['genre_id'])) {
            $sql .= ' AND i.id IN (
                SELECT ig.item_id FROM item_genres ig
                INNER JOIN genres g ON ig.genre_id = g.id
                WHERE (g.id = :genre_internal_id OR g.dmm_id = :genre_external_id OR g.genre_id = :genre_external_text)
            )';
            // Bind same value to possible keys; repository caller may pass numeric or string
            $params[':genre_internal_id'] = $filters['genre_id'];
            $params[':genre_external_id'] = $filters['genre_id'];
            $params[':genre_external_text'] = $filters['genre_id'];
        }

        // Actress filter
        if (!empty($filters['actress_id'])) {
            $sql .= ' AND i.id IN (
                SELECT ia.item_id FROM item_actresses ia
                INNER JOIN actresses a ON ia.actress_id = a.id
                WHERE (a.id = :actress_internal_id OR a.dmm_id = :actress_external_id OR a.actress_id = :actress_external_text)
            )';
            $params[':actress_internal_id'] = $filters['actress_id'];
            $params[':actress_external_id'] = $filters['actress_id'];
            $params[':actress_external_text'] = $filters['actress_id'];
        }

        $sql .= ' ORDER BY i.release_date DESC, i.created_at DESC';
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);

        // Bind dynamic params
        foreach ($params as $k => $v) {
            // decide type
            if (is_int($v)) {
                $stmt->bindValue($k, $v, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Upsert item from DMM API response
     *
     * Inserts or updates item data from DMM API
     *
     * @param array $apiItem Item data from DMM API
     * @return int|false Item ID on success, false on failure
     */
    public function upsertFromApi($apiItem) {
        $contentId = $apiItem['content_id'] ?? $apiItem['cid'] ?? null;
        if (empty($contentId)) {
            return false;
        }

        // Normalize fields from various possible API shapes
        $title = $apiItem['title'] ?? ($apiItem['title_short'] ?? '');
        $url = $apiItem['URL'] ?? ($apiItem['url'] ?? null);
        $affiliateUrl = $apiItem['affiliateURL'] ?? ($apiItem['affiliate_url'] ?? null);

        // Images
        $imageSmall = $apiItem['imageURL']['small'] ?? $apiItem['images']['small'] ?? null;
        $imageLarge = $apiItem['imageURL']['large'] ?? $apiItem['images']['large'] ?? null;
        $imageList = null;
        if (isset($apiItem['imageURL'])) {
            $imageList = json_encode($apiItem['imageURL']);
        } elseif (isset($apiItem['images'])) {
            $imageList = json_encode($apiItem['images']);
        }

        // Sample images/movies
        $sampleImages = null;
        if (!empty($apiItem['sampleImageURL'])) {
            $sampleImages = json_encode($apiItem['sampleImageURL']);
        } elseif (!empty($apiItem['sample_images'])) {
            $sampleImages = json_encode($apiItem['sample_images']);
        }

        $sampleMovies = null;
        if (!empty($apiItem['sampleMovieURL'])) {
            $sampleMovies = json_encode($apiItem['sampleMovieURL']);
        } elseif (!empty($apiItem['sample_movies'])) {
            $sampleMovies = json_encode($apiItem['sample_movies']);
        }

        // Description / iteminfo
        $description = $apiItem['iteminfo']['product_description'] ?? $apiItem['description'] ?? ($apiItem['item_description'] ?? null);
        $iteminfo = null;
        if (!empty($apiItem['iteminfo'])) {
            $iteminfo = json_encode($apiItem['iteminfo']);
        }

        // Prices
        $price = null;
        if (!empty($apiItem['prices']['price'])) {
            $price = (int)preg_replace('/[^0-9]/', '', (string)$apiItem['prices']['price']);
        } elseif (!empty($apiItem['price'])) {
            $price = (int)$apiItem['price'];
        }

        $listPrice = null;
        if (!empty($apiItem['prices']['list_price'])) {
            $listPrice = (int)preg_replace('/[^0-9]/', '', (string)$apiItem['prices']['list_price']);
        }

        $volume = $apiItem['volume'] ?? null;

        // Reviews
        $reviewCount = $apiItem['review']['count'] ?? ($apiItem['review_count'] ?? 0);
        $reviewAverage = $apiItem['review']['average'] ?? ($apiItem['review_average'] ?? 0.0);

        // Release date
        $releaseDate = null;
        if (!empty($apiItem['date'])) {
            $releaseDate = date('Y-m-d', strtotime($apiItem['date']));
        } elseif (!empty($apiItem['release_date'])) {
            $releaseDate = date('Y-m-d', strtotime($apiItem['release_date']));
        }

        // Stock/delivery/campaign
        $stock = $apiItem['stock'] ?? null;
        $campaign = !empty($apiItem['campaign']) ? json_encode($apiItem['campaign']) : null;
        $deliveries = !empty($apiItem['deliveries']) ? json_encode($apiItem['deliveries']) : null;

        // Attempt to find existing
        $existing = $this->findByContentId($contentId);

        try {
            if ($existing) {
                $sql = 'UPDATE items SET
                    title = :title,
                    url = :url,
                    affiliate_url = :affiliate_url,
                    image_list = :image_list,
                    image_small = :image_small,
                    image_large = :image_large,
                    sample_images = :sample_images,
                    sample_movies = :sample_movies,
                    description = :description,
                    iteminfo = :iteminfo,
                    price_min = :price_min,
                    price_list_min = :price_list_min,
                    volume = :volume,
                    review_count = :review_count,
                    review_average = :review_average,
                    stock = :stock,
                    campaign = :campaign,
                    deliveries = :deliveries,
                    release_date = :release_date,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE content_id = :content_id';
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':title' => $title,
                    ':url' => $url,
                    ':affiliate_url' => $affiliateUrl,
                    ':image_list' => $imageList,
                    ':image_small' => $imageSmall,
                    ':image_large' => $imageLarge,
                    ':sample_images' => $sampleImages,
                    ':sample_movies' => $sampleMovies,
                    ':description' => $description,
                    ':iteminfo' => $iteminfo,
                    ':price_min' => $price,
                    ':price_list_min' => $listPrice,
                    ':volume' => $volume,
                    ':review_count' => $reviewCount,
                    ':review_average' => $reviewAverage,
                    ':stock' => $stock,
                    ':campaign' => $campaign,
                    ':deliveries' => $deliveries,
                    ':release_date' => $releaseDate,
                    ':content_id' => $contentId
                ]);

                return (int)$existing['id'];
            } else {
                $sql = 'INSERT INTO items (
                        content_id, title, url, affiliate_url, image_list, image_small, image_large,
                        sample_images, sample_movies, description, iteminfo,
                        price_min, price_list_min, volume, review_count, review_average, stock,
                        campaign, deliveries, release_date, created_at, updated_at
                    ) VALUES (
                        :content_id, :title, :url, :affiliate_url, :image_list, :image_small, :image_large,
                        :sample_images, :sample_movies, :description, :iteminfo,
                        :price_min, :price_list_min, :volume, :review_count, :review_average, :stock,
                        :campaign, :deliveries, :release_date, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    )';

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':content_id' => $contentId,
                    ':title' => $title,
                    ':url' => $url,
                    ':affiliate_url' => $affiliateUrl,
                    ':image_list' => $imageList,
                    ':image_small' => $imageSmall,
                    ':image_large' => $imageLarge,
                    ':sample_images' => $sampleImages,
                    ':sample_movies' => $sampleMovies,
                    ':description' => $description,
                    ':iteminfo' => $iteminfo,
                    ':price_min' => $price,
                    ':price_list_min' => $listPrice,
                    ':volume' => $volume,
                    ':review_count' => $reviewCount,
                    ':review_average' => $reviewAverage,
                    ':stock' => $stock,
                    ':campaign' => $campaign,
                    ':deliveries' => $deliveries,
                    ':release_date' => $releaseDate
                ]);

                return (int)$this->pdo->lastInsertId();
            }
        } catch (Exception $e) {
            error_log('ItemRepository::upsertFromApi error: ' . $e->getMessage());
            return false;
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
            $sql .= ' AND (title LIKE :keyword OR description LIKE :keyword OR content_id LIKE :keyword)';
            $params[':keyword'] = '%' . $filters['keyword'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['total'] ?? 0);
    }
}