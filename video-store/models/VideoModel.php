<?php
/**
 * VideoModel.php
 * 
 * Base model class for video entity operations.
 * This model provides basic CRUD operations and query building for video items.
 */

class VideoModel
{
    /**
     * @var PDO Database connection
     */
    protected $pdo;

    /**
     * @var string Table name
     */
    protected $table = 'items';

    /**
     * Constructor
     * 
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Find a video by ID
     * 
     * @param int $id Video ID
     * @return array|null Video data or null if not found
     */
    public function findById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    /**
     * Find a video by content ID
     * 
     * @param string $contentId Content ID
     * @return array|null Video data or null if not found
     */
    public function findByContentId($contentId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE content_id = ?");
        $stmt->execute([$contentId]);
        $result = $stmt->fetch();
        
        return $result ?: null;
    }

    /**
     * Get all videos with pagination
     * 
     * @param int $limit Number of items per page
     * @param int $offset Offset for pagination
     * @return array List of videos
     */
    public function all($limit = 20, $offset = 0)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} ORDER BY release_date DESC, created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$limit, $offset]);
        
        return $stmt->fetchAll();
    }

    /**
     * Count total videos
     * 
     * @param array $filters Optional filters
     * @return int Total count
     */
    public function count($filters = [])
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        // Add filter conditions if needed
        if (!empty($filters['keyword'])) {
            $keyword = '%' . $filters['keyword'] . '%';
            $sql .= " WHERE (title LIKE ? OR description LIKE ? OR content_id LIKE ?)";
            $params = [$keyword, $keyword, $keyword];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
    }
}
