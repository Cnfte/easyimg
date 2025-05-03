<?php
class Image {
    public static function upload($file, $userId) {
        // 验证文件
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("文件上传出错: " . $file['error']);
        }
        
        // 检查文件大小
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("文件大小超过限制");
        }
        
        // 检查文件类型
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, ALLOWED_EXTENSIONS)) {
            throw new Exception("不支持的文件类型");
        }
        
        // 生成安全文件名
        $newFilename = self::generateFilename($fileExt);
        $uploadPath = UPLOAD_DIR . $newFilename;
        
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception("文件移动失败");
        }
        
        // 保存到数据库
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO images (user_id, filename, original_name, file_size, file_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $newFilename, $file['name'], $file['size'], $file['type']]);
        
        return $db->lastInsertId();
    }
    
    public static function delete($imageId, $userId) {
        $db = Database::getInstance();
        
        // 获取文件信息
        $stmt = $db->prepare("SELECT filename FROM images WHERE id = ? AND user_id = ?");
        $stmt->execute([$imageId, $userId]);
        $image = $stmt->fetch();
        
        if (!$image) {
            throw new Exception("图片不存在或无权删除");
        }
        
        // 删除文件
        $filePath = UPLOAD_DIR . $image['filename'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // 删除数据库记录
        $stmt = $db->prepare("DELETE FROM images WHERE id = ?");
        $stmt->execute([$imageId]);
    }
    
    public static function getUserImages($userId, $page = 1, $perPage = 12) {
        $db = Database::getInstance();
        $offset = ($page - 1) * $perPage;
        
        $stmt = $db->prepare("SELECT * FROM images WHERE user_id = ? ORDER BY upload_date DESC LIMIT ? OFFSET ?");
        $stmt->execute([$userId, $perPage, $offset]);
        return $stmt->fetchAll();
    }
    
    public static function countUserImages($userId) {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM images WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
    
    private static function generateFilename($extension) {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }
}