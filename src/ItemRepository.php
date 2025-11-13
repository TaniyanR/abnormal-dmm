<?php
/**
 * ItemRepository - Data access layer for items
 */

class ItemRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Search items with optional filters
     *
     * @param array $filters Search filters (keyword, genre, actress, limit, offset)
     * @return array List of items
     */
    public function search(array $filters = []): array
    {
        $sql = "SELECT i.* FROM items i WHERE 1=1";
        $params = [];

        // Keyword search
        if (!empty($filters['keyword'])) {
            $sql .= " AND (i.title LIKE :keyword OR i.description LIKE :keyword)";
            $params[':keyword'] = '%' . $filters['keyword'] . '%';
        }

        // Genre filter
        if (!empty($filters['genre_id'])) {
            $sql .= " AND i.id IN (
                SELECT ig.item_id FROM item_genres ig
                INNER JOIN genres g ON ig.genre_id = g.id
                WHERE g.genre_id = :genre_id
            )";
            $params[':genre_id'] = $filters['genre_id'];
        }

        // Actress filter
        if (!empty($filters['actress_id'])) {
            $sql .= " AND i.id IN (
                SELECT ia.item_id FROM item_actresses ia
                INNER JOIN actresses a ON ia.actress_id = a.id
                WHERE a.actress_id = :actress_id
            )";
            $params[':actress_id'] = $filters['actress_id'];
        }

        // Order by
        $sql .= " ORDER BY i.release_date DESC, i.created_at DESC";

        // Limit and offset
        $limit = $filters['limit'] ?? 20;
        $offset = $filters['offset'] ?? 0;
        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find item by content_id
     *
     * @param string $contentId DMM content ID
     * @return array|null Item data or null if not found
     */
    public function find(string $contentId): ?array
    {
        $sql = "SELECT * FROM items WHERE content_id = :content_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':content_id' => $contentId]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Upsert item from DMM API response
     *
     * @param array $apiData Item data from DMM API
     * @return int Item ID
     */
    public function upsertFromApi(array $apiData): int
    {
        $this->pdo->beginTransaction();
        
        try {
            // Check if item exists
            $existing = $this->find($apiData['content_id']);
            
            if ($existing) {
                // Update existing item
                $sql = "UPDATE items SET 
                    title = :title,
                    description = :description,
                    sample_video_url = :sample_video_url,
                    price = :price,
                    release_date = :release_date,
                    affiliate_url = :affiliate_url,
                    thumbnail_url = :thumbnail_url,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE content_id = :content_id";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':content_id' => $apiData['content_id'],
                    ':title' => $apiData['title'] ?? '',
                    ':description' => $apiData['description'] ?? null,
                    ':sample_video_url' => $apiData['sample_video_url'] ?? null,
                    ':price' => $apiData['price'] ?? null,
                    ':release_date' => $apiData['release_date'] ?? null,
                    ':affiliate_url' => $apiData['affiliate_url'] ?? null,
                    ':thumbnail_url' => $apiData['thumbnail_url'] ?? null,
                ]);
                
                $itemId = $existing['id'];
            } else {
                // Insert new item
                $sql = "INSERT INTO items (
                    content_id, title, description, sample_video_url, 
                    price, release_date, affiliate_url, thumbnail_url
                ) VALUES (
                    :content_id, :title, :description, :sample_video_url,
                    :price, :release_date, :affiliate_url, :thumbnail_url
                )";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':content_id' => $apiData['content_id'],
                    ':title' => $apiData['title'] ?? '',
                    ':description' => $apiData['description'] ?? null,
                    ':sample_video_url' => $apiData['sample_video_url'] ?? null,
                    ':price' => $apiData['price'] ?? null,
                    ':release_date' => $apiData['release_date'] ?? null,
                    ':affiliate_url' => $apiData['affiliate_url'] ?? null,
                    ':thumbnail_url' => $apiData['thumbnail_url'] ?? null,
                ]);
                
                $itemId = (int)$this->pdo->lastInsertId();
            }
            
            // TODO: Handle genres, actresses, makers relationships
            
            $this->pdo->commit();
            return $itemId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get total count of items matching filters
     *
     * @param array $filters Search filters
     * @return int Total count
     */
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM items i WHERE 1=1";
        $params = [];

        if (!empty($filters['keyword'])) {
            $sql .= " AND (i.title LIKE :keyword OR i.description LIKE :keyword)";
            $params[':keyword'] = '%' . $filters['keyword'] . '%';
        }

        if (!empty($filters['genre_id'])) {
            $sql .= " AND i.id IN (
                SELECT ig.item_id FROM item_genres ig
                INNER JOIN genres g ON ig.genre_id = g.id
                WHERE g.genre_id = :genre_id
            )";
            $params[':genre_id'] = $filters['genre_id'];
        }

        if (!empty($filters['actress_id'])) {
            $sql .= " AND i.id IN (
                SELECT ia.item_id FROM item_actresses ia
                INNER JOIN actresses a ON ia.actress_id = a.id
                WHERE a.actress_id = :actress_id
            )";
            $params[':actress_id'] = $filters['actress_id'];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
    }
}
