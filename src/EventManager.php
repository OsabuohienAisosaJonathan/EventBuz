<?php
/**
 * EventSnap Cloud - Event Manager Logic Class
 */

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class EventManager {
    /**
     * Creates a new Event, generates its unique UUID, creates storage subdirectories, and compiles its QR Code.
     */
    public static function createEvent(
        int $ownerId,
        string $name,
        string $type,
        string $date,
        string $venue,
        string $description,
        ?array $bannerFile,
        int $isPublicGallery = 0,
        int $watermarkEnabled = 0,
        string $watermarkText = 'EventSnap'
    ): array {
        $db = getDBConnection();
        
        $name = trim($name);
        $type = trim($type);
        $venue = trim($venue);
        $description = trim($description);
        $watermarkText = trim($watermarkText);
        
        if (empty($name) || empty($type) || empty($date) || empty($venue)) {
            return ['success' => false, 'message' => 'All core fields are required.'];
        }
        
        // Generate secure 36-character UUID
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        // Expiration is default 7 days after the event date
        $expiresAt = date('Y-m-d H:i:s', strtotime($date . ' + 7 days'));
        
        // Process Banner Image Upload or use standard default asset
        $bannerPath = 'assets/default-banner.jpg';
        if ($bannerFile && $bannerFile['error'] === UPLOAD_ERR_OK) {
            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            $fileMime = mime_content_type($bannerFile['tmp_name']);
            
            if (!in_array($fileMime, $allowedMimes)) {
                return ['success' => false, 'message' => 'Invalid banner image format. Allowed: JPG, PNG, WEBP.'];
            }
            
            // Create event directory
            $eventDir = __DIR__ . '/../uploads/' . $uuid . '/';
            if (!file_exists($eventDir)) {
                mkdir($eventDir, 0777, true);
            }
            
            $extension = pathinfo($bannerFile['name'], PATHINFO_EXTENSION);
            $bannerName = 'banner_' . time() . '.' . $extension;
            $targetBannerPath = $eventDir . $bannerName;
            
            if (move_uploaded_file($bannerFile['tmp_name'], $targetBannerPath)) {
                $bannerPath = 'uploads/' . $uuid . '/' . $bannerName;
            }
        }
        
        try {
            $db->beginTransaction();
            
            // 1. Insert Event Record
            $stmt = $db->prepare("
                INSERT INTO events (event_uuid, owner_id, name, type, date, venue, description, banner_path, is_public_gallery, watermark_enabled, watermark_text, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $uuid, $ownerId, $name, $type, $date, $venue, $description, $bannerPath, $isPublicGallery, $watermarkEnabled, $watermarkText, $expiresAt
            ]);
            
            $eventId = $db->lastInsertId();
            
            // 2. Generate QR Code
            $qrResult = self::generateQrCode($uuid, $eventId);
            if (!$qrResult['success']) {
                $db->rollBack();
                return ['success' => false, 'message' => 'QR Code compilation failed: ' . $qrResult['message']];
            }
            
            $db->commit();
            return ['success' => true, 'message' => 'Event configured successfully!', 'uuid' => $uuid];
        } catch (PDOException $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Event registry failed: ' . $e->getMessage()];
        }
    }

    /**
     * Edits an existing event details
     */
    public static function editEvent(
        int $eventId,
        int $ownerId,
        string $name,
        string $type,
        string $date,
        string $venue,
        string $description,
        ?array $bannerFile,
        int $isPublicGallery,
        int $watermarkEnabled,
        string $watermarkText
    ): array {
        $db = getDBConnection();
        
        $name = trim($name);
        $type = trim($type);
        $venue = trim($venue);
        $description = trim($description);
        $watermarkText = trim($watermarkText);
        
        if (empty($name) || empty($type) || empty($date) || empty($venue)) {
            return ['success' => false, 'message' => 'All core fields are required.'];
        }
        
        try {
            // Verify event belongs to owner
            $chkStmt = $db->prepare("SELECT event_uuid, banner_path FROM events WHERE id = ? AND owner_id = ?");
            $chkStmt->execute([$eventId, $ownerId]);
            $event = $chkStmt->fetch();
            
            if (!$event) {
                return ['success' => false, 'message' => 'Event context not found or permission denied.'];
            }
            
            $uuid = $event['event_uuid'];
            $bannerPath = $event['banner_path'];
            
            // Process Banner Image Upload if provided
            if ($bannerFile && $bannerFile['error'] === UPLOAD_ERR_OK) {
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $fileMime = mime_content_type($bannerFile['tmp_name']);
                
                if (in_array($fileMime, $allowedMimes)) {
                    $eventDir = __DIR__ . '/../uploads/' . $uuid . '/';
                    if (!file_exists($eventDir)) {
                        mkdir($eventDir, 0777, true);
                    }
                    
                    // Remove old banner if it is not default
                    if ($bannerPath !== 'assets/default-banner.jpg' && file_exists(__DIR__ . '/../' . $bannerPath)) {
                        @unlink(__DIR__ . '/../' . $bannerPath);
                    }
                    
                    $extension = pathinfo($bannerFile['name'], PATHINFO_EXTENSION);
                    $bannerName = 'banner_' . time() . '.' . $extension;
                    $targetBannerPath = $eventDir . $bannerName;
                    
                    if (move_uploaded_file($bannerFile['tmp_name'], $targetBannerPath)) {
                        $bannerPath = 'uploads/' . $uuid . '/' . $bannerName;
                    }
                }
            }
            
            $expiresAt = date('Y-m-d H:i:s', strtotime($date . ' + 7 days'));
            
            $stmt = $db->prepare("
                UPDATE events 
                SET name = ?, type = ?, date = ?, venue = ?, description = ?, banner_path = ?, is_public_gallery = ?, watermark_enabled = ?, watermark_text = ?, expires_at = ?
                WHERE id = ? AND owner_id = ?
            ");
            $stmt->execute([
                $name, $type, $date, $venue, $description, $bannerPath, $isPublicGallery, $watermarkEnabled, $watermarkText, $expiresAt, $eventId, $ownerId
            ]);
            
            return ['success' => true, 'message' => 'Event updated successfully!'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Event modification failed: ' . $e->getMessage()];
        }
    }

    /**
     * Completely purges an Event, files, uploads metadata, and QR records.
     */
    public static function deleteEvent(int $eventId, int $ownerId): array {
        $db = getDBConnection();
        try {
            $stmt = $db->prepare("SELECT event_uuid FROM events WHERE id = ? AND owner_id = ?");
            $stmt->execute([$eventId, $ownerId]);
            $event = $stmt->fetch();
            
            if (!$event) {
                return ['success' => false, 'message' => 'Event context not found or permission denied.'];
            }
            
            $uuid = $event['event_uuid'];
            
            // Delete files physically
            $eventDir = __DIR__ . '/../uploads/' . $uuid;
            self::recursiveDeleteDirectory($eventDir);
            
            // Cascading database delete will purge uploads, qr_codes, sessions, and notifications via Foreign Keys
            $delStmt = $db->prepare("DELETE FROM events WHERE id = ?");
            $delStmt->execute([$eventId]);
            
            return ['success' => true, 'message' => 'Event and all aggregated files purged successfully.'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Purge operations encountered database errors: ' . $e->getMessage()];
        }
    }

    /**
     * Generates a QR Code image on disk and links to database qr_codes table.
     */
    public static function generateQrCode(string $uuid, int $eventId): array {
        $db = getDBConnection();
        
        $qrDir = __DIR__ . '/../uploads/' . $uuid . '/';
        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0777, true);
        }
        
        $qrFilename = 'qr_' . time() . '.png';
        $savePath = $qrDir . $qrFilename;
        $dbQrPath = 'uploads/' . $uuid . '/' . $qrFilename;
        
        // Point redirect directly to Guest Camera Interface
        $redirectUrl = BASE_URL . 'guest-upload.php?id=' . $uuid;
        
        try {
            // Build QR Code Options
            $options = new QROptions([
                'version'      => 6,
                'outputInterface' => \chillerlan\QRCode\Output\QRGdImage::class,
                'outputType'   => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'     => QRCode::ECC_M,
                'scale'        => 6,
                'imageTransparent' => false,
            ]);
            
            $qrcode = new QRCode($options);
            $qrcode->render($redirectUrl, $savePath);
            
            // Verify creation
            if (!file_exists($savePath)) {
                return ['success' => false, 'message' => 'Server could not write PNG file onto storage paths.'];
            }
            
            // Link to Database qr_codes table
            $stmt = $db->prepare("INSERT INTO qr_codes (event_id, qr_path, redirect_url) VALUES (?, ?, ?)");
            $stmt->execute([$eventId, $dbQrPath, $redirectUrl]);
            
            return ['success' => true, 'qr_path' => $dbQrPath];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Helper to retrieve all events owned by Host
     */
    public static function getEventsByOwner(int $ownerId): array {
        $db = getDBConnection();
        try {
            $stmt = $db->prepare("
                SELECT e.*, 
                       (SELECT COUNT(*) FROM uploads WHERE event_id = e.id) as total_uploads,
                       q.qr_path 
                FROM events e
                LEFT JOIN qr_codes q ON q.event_id = e.id
                WHERE e.owner_id = ?
                ORDER BY e.date DESC
            ");
            $stmt->execute([$ownerId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Helper to load individual event profile by public UUID
     */
    public static function getEventByUuid(string $uuid): ?array {
        $db = getDBConnection();
        try {
            $stmt = $db->prepare("
                SELECT e.*, u.name as owner_name, q.qr_path 
                FROM events e
                JOIN users u ON u.id = e.owner_id
                LEFT JOIN qr_codes q ON q.event_id = e.id
                WHERE e.event_uuid = ?
            ");
            $stmt->execute([$uuid]);
            $res = $stmt->fetch();
            return $res ? $res : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Compiles detailed metrics for Event Analytics
     */
    public static function getEventAnalytics(int $eventId): array {
        $db = getDBConnection();
        $metrics = [
            'total_uploads' => 0,
            'guest_uploads' => 0,
            'crew_uploads' => 0,
            'storage_bytes' => 0,
            'total_visitors' => 0
        ];
        
        try {
            // Totals and sub-roles
            $stmt = $db->prepare("
                SELECT uploader_role, COUNT(*), SUM(file_size) 
                FROM uploads 
                WHERE event_id = ? 
                GROUP BY uploader_role
            ");
            $stmt->execute([$eventId]);
            $rows = $stmt->fetchAll();
            
            foreach ($rows as $r) {
                $metrics['total_uploads'] += $r['COUNT(*)'];
                $metrics['storage_bytes'] += $r['SUM(file_size)'];
                if ($r['uploader_role'] === 'guest') {
                    $metrics['guest_uploads'] = $r['COUNT(*)'];
                } elseif ($r['uploader_role'] === 'crew') {
                    $metrics['crew_uploads'] = $r['COUNT(*)'];
                }
            }
            
            // Unique visitors based on session tokens
            $vStmt = $db->prepare("SELECT COUNT(*) FROM guest_sessions WHERE event_id = ?");
            $vStmt->execute([$eventId]);
            $metrics['total_visitors'] = $vStmt->fetchColumn();
            
        } catch (PDOException $e) {
            // return empty metrics
        }
        
        return $metrics;
    }

    /**
     * Recursively purges static file folders
     */
    private static function recursiveDeleteDirectory(string $dir): bool {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!self::recursiveDeleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }
}
?>
