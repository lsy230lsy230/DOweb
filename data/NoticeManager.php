<?php
class NoticeManager {
    private $pdo;
    
    public function __construct() {
        $this->connect();
    }
    
    private function connect() {
        $database_url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if (!$database_url) {
            throw new Exception('DATABASE_URL environment variable not set');
        }
        
        try {
            $this->pdo = new PDO($database_url);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * 공지사항 생성
     */
    public function createNotice($title, $content, $imagePath = null, $isPinned = false) {
        $sql = "INSERT INTO notices (title, content, image_path, is_pinned, created_at, updated_at) 
                VALUES (:title, :content, :image_path, :is_pinned, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':content' => $content,
            ':image_path' => $imagePath,
            ':is_pinned' => $isPinned
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 모든 공지사항 조회 (고정글 우선, 최신순)
     */
    public function getAllNotices($limit = null) {
        $sql = "SELECT * FROM notices ORDER BY is_pinned DESC, created_at DESC";
        if ($limit) {
            $sql .= " LIMIT :limit";
        }
        
        $stmt = $this->pdo->prepare($sql);
        if ($limit) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * 고정된 공지사항만 조회
     */
    public function getPinnedNotices() {
        $sql = "SELECT * FROM notices WHERE is_pinned = true ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * 최신 공지사항 조회 (메인 페이지용)
     */
    public function getRecentNotices($limit = 5) {
        $sql = "SELECT * FROM notices ORDER BY is_pinned DESC, created_at DESC LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * 특정 공지사항 조회
     */
    public function getNoticeById($id) {
        $sql = "SELECT * FROM notices WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch();
    }
    
    /**
     * 공지사항 수정
     */
    public function updateNotice($id, $title, $content, $imagePath = null, $isPinned = false) {
        $sql = "UPDATE notices SET title = :title, content = :content, image_path = :image_path, 
                is_pinned = :is_pinned, updated_at = NOW() WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':title' => $title,
            ':content' => $content,
            ':image_path' => $imagePath,
            ':is_pinned' => $isPinned
        ]);
    }
    
    /**
     * 공지사항 삭제
     */
    public function deleteNotice($id) {
        // 이미지 파일도 함께 삭제
        $notice = $this->getNoticeById($id);
        if ($notice && $notice['image_path']) {
            $imagePath = __DIR__ . '/../' . $notice['image_path'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        $sql = "DELETE FROM notices WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * 고정 상태 토글
     */
    public function togglePin($id) {
        $sql = "UPDATE notices SET is_pinned = NOT is_pinned, updated_at = NOW() WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    /**
     * 이미지 업로드 처리
     */
    public function uploadImage($file) {
        $uploadDir = __DIR__ . '/../uploads/notices/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('허용되지 않는 파일 형식입니다. (JPG, PNG, GIF, WebP만 가능)');
        }
        
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            throw new Exception('파일 크기가 너무 큽니다. (최대 5MB)');
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('notice_') . '.' . $extension;
        $uploadPath = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('파일 업로드에 실패했습니다.');
        }
        
        return 'uploads/notices/' . $filename;
    }
    
    /**
     * 공지사항 통계
     */
    public function getNoticeStats() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN is_pinned = true THEN 1 END) as pinned,
                    COUNT(CASE WHEN created_at >= NOW() - INTERVAL '7 days' THEN 1 END) as recent
                FROM notices";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch();
    }
}
?>