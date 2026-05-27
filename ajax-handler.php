<?php
/**
 * EventSnap Cloud - AJAX Controller & Asynchronous Upload Handler
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/EventManager.php';
require_once __DIR__ . '/includes/upload_processor.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'No action parameter specified.']);
    exit;
}

$db = getDBConnection();

switch ($action) {
    // 1. Guest Camera or Gallery Multi-File Upload Endpoint
    case 'upload':
        // Rate Limiter: Max 15 uploads per session per minute
        if (empty($_SESSION['upload_rate_count'])) {
            $_SESSION['upload_rate_count'] = 0;
            $_SESSION['upload_rate_start'] = time();
        }
        
        // Reset rate timer after 60 seconds
        if (time() - $_SESSION['upload_rate_start'] > 60) {
            $_SESSION['upload_rate_count'] = 0;
            $_SESSION['upload_rate_start'] = time();
        }
        
        $_SESSION['upload_rate_count']++;
        
        if ($_SESSION['upload_rate_count'] > 15) {
            echo json_encode(['success' => false, 'message' => 'Upload speed limit exceeded. Please wait a moment before snapping more photos.']);
            exit;
        }

        $eventUuid = $_POST['event_uuid'] ?? '';
        $uploaderName = trim($_POST['uploader_name'] ?? 'Anonymous Guest');
        $caption = trim($_POST['caption'] ?? '');
        $uploaderRole = $_POST['uploader_role'] ?? 'guest'; // guest, crew, owner
        
        if (empty($eventUuid)) {
            echo json_encode(['success' => false, 'message' => 'Invalid event identifier context.']);
            exit;
        }
        
        // Load Event Details
        $event = EventManager::getEventByUuid($eventUuid);
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Target event context not found.']);
            exit;
        }
        
        // Check Expiration
        if (strtotime($event['expires_at']) < time()) {
            echo json_encode(['success' => false, 'message' => 'This event has expired. Photo captures are closed.']);
            exit;
        }
        
        // Guest Name Session caching helper
        if ($uploaderRole === 'guest') {
            $_SESSION['guest_name'] = $uploaderName;
            
            // Cache session token inside DB guest_sessions to track total visitors
            if (empty($_SESSION['guest_session_token'])) {
                $_SESSION['guest_session_token'] = bin2hex(random_bytes(16));
                try {
                    $gsStmt = $db->prepare("INSERT INTO guest_sessions (event_id, session_token, guest_name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE guest_name = guest_name");
                    $gsStmt->execute([$event['id'], $_SESSION['guest_session_token'], $uploaderName]);
                } catch (PDOException $e) {
                    // Fail silently
                }
            }
        }
        
        // Process uploaded file
        $file = $_FILES['file'] ?? null;
        if (!$file) {
            echo json_encode(['success' => false, 'message' => 'No media file found in request payload.']);
            exit;
        }
        
        // Handle upload
        $result = UploadProcessor::processUpload(
            $file,
            (int)$event['id'],
            $eventUuid,
            $uploaderName,
            $uploaderRole,
            $caption,
            (bool)$event['watermark_enabled'],
            $event['watermark_text']
        );
        
        echo json_encode($result);
        exit;
        
    // 2. Fetch live slideshow photo updates
    case 'get_slideshow_feed':
        $eventUuid = $_GET['event_uuid'] ?? '';
        $lastPhotoId = (int)($_GET['last_photo_id'] ?? 0);
        
        if (empty($eventUuid)) {
            echo json_encode(['success' => false, 'message' => 'Invalid event identifier context.']);
            exit;
        }
        
        // Verify Event
        $event = EventManager::getEventByUuid($eventUuid);
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Target event context not found.']);
            exit;
        }
        
        try {
            // Retrieve only approved photos uploaded after $lastPhotoId
            $stmt = $db->prepare("
                SELECT id, file_path, uploader_name, caption, created_at 
                FROM uploads 
                WHERE event_id = ? AND is_approved = 1 AND id > ? 
                ORDER BY id ASC
            ");
            $stmt->execute([$event['id'], $lastPhotoId]);
            $photos = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'photos' => $photos
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        
    // 3. Fetch real-time live feed updates for gallery views
    case 'get_live_feed':
        $eventUuid = $_GET['event_uuid'] ?? '';
        
        if (empty($eventUuid)) {
            echo json_encode(['success' => false, 'message' => 'Invalid event identifier.']);
            exit;
        }
        
        $event = EventManager::getEventByUuid($eventUuid);
        if (!$event) {
            echo json_encode(['success' => false, 'message' => 'Event not found.']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("
                SELECT id, file_path, uploader_name, uploader_role, caption, created_at 
                FROM uploads 
                WHERE event_id = ? AND is_approved = 1 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$event['id']]);
            $photos = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'photos' => $photos
            ]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action request.']);
        exit;
}
?>
