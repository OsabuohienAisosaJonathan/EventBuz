<?php
/**
 * EventSnap Cloud - Robust File Upload & Media Processor
 * Handles validation, md5 duplicate matching, scaling compression, and watermarking.
 */

class UploadProcessor {
    /**
     * Validates, processes, watermarks, compresses, and saves a file upload.
     */
    public static function processUpload(
        array $file,
        int $eventId,
        string $eventUuid,
        string $uploaderName,
        string $uploaderRole,
        string $caption = '',
        bool $watermarkEnabled = false,
        string $watermarkText = 'EventSnap'
    ): array {
        $db = getDBConnection();
        
        // Basic limits
        $maxSizeBytes = 10 * 1024 * 1024; // 10MB raw limit
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload failed with error code: ' . $file['error']];
        }
        
        if ($file['size'] > $maxSizeBytes) {
            return ['success' => false, 'message' => 'File size exceeds maximum 10MB raw limit.'];
        }
        
        // Validate MIME type
        $tmpPath = $file['tmp_name'];
        $mimeType = mime_content_type($tmpPath);
        if (!in_array($mimeType, $allowedMimes)) {
            return ['success' => false, 'message' => 'Invalid file format. Only JPEG, PNG and WEBP images are supported.'];
        }
        
        // 1. Duplicate Detection using md5_file hash
        $fileHash = md5_file($tmpPath);
        try {
            $dupStmt = $db->prepare("SELECT id FROM uploads WHERE event_id = ? AND file_hash = ? LIMIT 1");
            $dupStmt->execute([$eventId, $fileHash]);
            if ($dupStmt->fetch()) {
                return ['success' => false, 'message' => 'Duplicate image detected! This memory is already saved in the gallery.'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Duplicate check failed: ' . $e->getMessage()];
        }
        
        // 2. Set Target Path Setup
        $eventDir = __DIR__ . '/../uploads/' . $eventUuid . '/';
        if (!file_exists($eventDir)) {
            mkdir($eventDir, 0777, true);
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (empty($extension)) {
            // Deduce from mime
            $extension = ($mimeType === 'image/png') ? 'png' : (($mimeType === 'image/webp') ? 'webp' : 'jpg');
        }
        
        $uniqueName = 'snap_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $targetPath = $eventDir . $uniqueName;
        $dbPath = 'uploads/' . $eventUuid . '/' . $uniqueName;
        
        // 3. Compress & Process Image with GD to save storage space
        $processResult = self::compressAndWatermarkImage($tmpPath, $targetPath, $mimeType, $watermarkEnabled, $watermarkText);
        if (!$processResult['success']) {
            return ['success' => false, 'message' => 'Image processing failed: ' . $processResult['message']];
        }
        
        // 4. Save metadata to MySQL DB
        $finalSize = (int)filesize($targetPath);
        $captionClean = trim(strip_tags($caption));
        $uploaderNameClean = trim(strip_tags($uploaderName));
        if (empty($uploaderNameClean)) {
            $uploaderNameClean = 'Anonymous Guest';
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO uploads (event_id, uploader_name, uploader_role, file_path, original_name, file_type, file_size, caption, file_hash, is_approved)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $eventId, $uploaderNameClean, $uploaderRole, $dbPath, $file['name'], $mimeType, $finalSize, $captionClean, $fileHash
            ]);
            
            return [
                'success' => true,
                'message' => 'Memory saved successfully!',
                'file_path' => $dbPath,
                'upload_id' => $db->lastInsertId()
            ];
        } catch (PDOException $e) {
            // Remove file if database insertion failed
            if (file_exists($targetPath)) {
                @unlink($targetPath);
            }
            return ['success' => false, 'message' => 'Failed to save metadata: ' . $e->getMessage()];
        }
    }

    /**
     * Compresses image and injects translucent watermark using PHP GD.
     */
    private static function compressAndWatermarkImage(
        string $sourceFile,
        string $targetFile,
        string $mimeType,
        bool $watermarkEnabled = false,
        string $watermarkText = 'EventSnap'
    ): array {
        // Load image resource based on MIME
        switch ($mimeType) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($sourceFile);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($sourceFile);
                // Maintain alpha transparency support for input pngs
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($sourceFile);
                break;
            default:
                return ['success' => false, 'message' => 'Unsupported GD image driver.'];
        }
        
        if (!$image) {
            return ['success' => false, 'message' => 'Failed to create image resource. Image file might be corrupted.'];
        }
        
        // Get dimensions
        $width = imagesx($image);
        $height = imagesy($image);
        
        // 1. Downscale large images (Maximum width 2048px for guest snap optimization)
        $maxDimension = 2048;
        if ($width > $maxDimension || $height > $maxDimension) {
            if ($width > $height) {
                $newWidth = $maxDimension;
                $newHeight = (int)($height * ($maxDimension / $width));
            } else {
                $newHeight = $maxDimension;
                $newWidth = (int)($width * ($maxDimension / $height));
            }
            
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Keep transparent layers if working with webp/png
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);
                $transparent = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
                imagefill($resizedImage, 0, 0, $transparent);
            }
            
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
            $width = $newWidth;
            $height = $newHeight;
        }
        
        // 2. Inject Watermark if requested
        if ($watermarkEnabled && !empty($watermarkText)) {
            // Elegant translucent watermark at bottom-right corner
            // Since custom TTF files might be missing on arbitrary Windows XAMPP environments, we use robust GD built-in fonts or clean rects.
            // Let's use imagechar or imagestring with font 5 (largest standard GD font)
            $fontNum = 5;
            $fontWidth = imagefontwidth($fontNum);
            $fontHeight = imagefontheight($fontNum);
            
            $padding = 20;
            $watermarkString = " " . $watermarkText . " ";
            $stringLen = strlen($watermarkString);
            
            $watermarkWidth = ($stringLen * $fontWidth) + 16;
            $watermarkHeight = $fontHeight + 12;
            
            // Coordinates: Bottom right
            $x = $width - $watermarkWidth - $padding;
            $y = $height - $watermarkHeight - $padding;
            
            // Fallback safety to ensure it fits in tiny photos
            if ($x < 0) $x = 10;
            if ($y < 0) $y = 10;
            
            // 3. Draw a translucent slate background container for the watermark
            // Allocate black color with 50% opacity (127 * 0.5 = 63)
            $bgColor = imagecolorallocatealpha($image, 15, 15, 25, 60);
            imagefilledrectangle($image, $x, $y, $x + $watermarkWidth, $y + $watermarkHeight, $bgColor);
            
            // Allocate white text with slight glow
            $textColor = imagecolorallocatealpha($image, 255, 255, 255, 20);
            imagestring($image, $fontNum, $x + 8, $y + 6, $watermarkString, $textColor);
        }
        
        // 4. Save file to target location with compression quality
        $saveSuccess = false;
        switch ($mimeType) {
            case 'image/jpeg':
                // Compress JPEGs to 82% quality (excellent quality-to-size balance)
                $saveSuccess = imagejpeg($image, $targetFile, 82);
                break;
            case 'image/png':
                // Compress PNGs to 6 compression level (balanced speed and compression)
                $saveSuccess = imagepng($image, $targetFile, 6);
                break;
            case 'image/webp':
                // Compress WebPs to 80% quality
                $saveSuccess = imagewebp($image, $targetFile, 80);
                break;
        }
        
        // Free memory resource
        imagedestroy($image);
        
        if ($saveSuccess) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Failed to execute image compression write operation.'];
        }
    }
}
?>
