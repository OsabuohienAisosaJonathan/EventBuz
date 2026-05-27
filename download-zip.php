<?php
/**
 * EventSnap Cloud - Event Gallery ZIP Exporter
 * Securely bundles all uploaded event photos into a single ZIP file for Host download.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/EventManager.php';

// Auth protection: Requires Host or Admin role
Auth::requireLogin();
if (!in_array($_SESSION['user_role'], ['owner', 'admin'])) {
    die("Unauthorized access.");
}

$eventId = (int)($_GET['id'] ?? 0);
$ownerId = $_SESSION['user_id'];
$db = getDBConnection();

try {
    // Verify Event ownership (skip validation check for Platform Admins)
    if ($_SESSION['user_role'] === 'admin') {
        $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$eventId]);
    } else {
        $stmt = $db->prepare("SELECT * FROM events WHERE id = ? AND owner_id = ?");
        $stmt->execute([$eventId, $ownerId]);
    }
    
    $event = $stmt->fetch();
    
    if (!$event) {
        die("Event context not found or permission denied.");
    }
    
    $eventUuid = $event['event_uuid'];
    
    // Fetch all approved photos in the event
    $upStmt = $db->prepare("SELECT file_path, original_name FROM uploads WHERE event_id = ?");
    $upStmt->execute([$eventId]);
    $uploads = $upStmt->fetchAll();
    
    if (empty($uploads)) {
        die("No captures found to bundle. Your guest gallery is empty.");
    }
    
    // Configure ZIP creation
    $zip = new ZipArchive();
    $tempZipName = tempnam(sys_get_temp_dir(), 'snap_zip_') . '.zip';
    
    if ($zip->open($tempZipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Could not create local ZIP compile archive.");
    }
    
    // Add photos
    $addedCount = 0;
    foreach ($uploads as $up) {
        $filePath = __DIR__ . '/' . $up['file_path'];
        if (file_exists($filePath)) {
            // Give each photo a clean name. Maintain original format, add index if name conflicts
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $cleanName = pathinfo($up['original_name'], PATHINFO_FILENAME);
            $cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $cleanName); // sanitize filename
            
            $archiveFilename = $cleanName . '_' . ($addedCount + 1) . '.' . $extension;
            
            $zip->addFile($filePath, $archiveFilename);
            $addedCount++;
        }
    }
    
    $zip->close();
    
    if ($addedCount === 0) {
        if (file_exists($tempZipName)) {
            @unlink($tempZipName);
        }
        die("Aggregated files are missing on storage directories.");
    }
    
    // Dispatch ZIP via HTTP headers
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $event['name']) . '_snaps.zip"');
    header('Content-Length: ' . filesize($tempZipName));
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Send file contents to stream
    readfile($tempZipName);
    
    // Delete temp file from system temp dir
    @unlink($tempZipName);
    exit;
    
} catch (PDOException $e) {
    die("Database error encountered: " . $e->getMessage());
}
?>
