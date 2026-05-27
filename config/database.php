<?php
/**
 * EventSnap Cloud - Database Configuration & Initialization
 *
 * PRODUCTION SETUP (shared hosting / cPanel):
 *   1. Set IS_LOCAL to false
 *   2. Fill in PROD_DB_* constants below with your cPanel MySQL credentials
 *   3. Upload the vendor/ folder OR run `composer install` via SSH on the server
 */

// ─── ENVIRONMENT SWITCH ──────────────────────────────────────────────────────
// Set to false when deploying to your live server (eventsnap.xo.je)
define('IS_LOCAL', ($_SERVER['HTTP_HOST'] ?? '') === 'localhost' || str_contains($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1'));

// ─── LOCAL CREDENTIALS (XAMPP) ───────────────────────────────────────────────
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_PORT', '3306');
define('LOCAL_DB_NAME', 'eventsnap_cloud');
define('LOCAL_DB_USER', 'root');
define('LOCAL_DB_PASS', '');

// ─── PRODUCTION CREDENTIALS (cPanel Shared Hosting) ─────────────────────────
// ⚠️  Replace these with your actual cPanel MySQL values before uploading
define('PROD_DB_HOST', 'localhost');          // Usually 'localhost' on shared hosts
define('PROD_DB_PORT', '3306');
define('PROD_DB_NAME', 'eventsnap_cloud');    // cPanel format: cpanelusername_dbname
define('PROD_DB_USER', 'root');               // cPanel format: cpanelusername_dbuser
define('PROD_DB_PASS', '');                   // Your MySQL user password

// ─── ACTIVE CREDENTIALS (selected by environment) ────────────────────────────
define('DB_HOST', IS_LOCAL ? LOCAL_DB_HOST : PROD_DB_HOST);
define('DB_PORT', IS_LOCAL ? LOCAL_DB_PORT : PROD_DB_PORT);
define('DB_NAME', IS_LOCAL ? LOCAL_DB_NAME : PROD_DB_NAME);
define('DB_USER', IS_LOCAL ? LOCAL_DB_USER : PROD_DB_USER);
define('DB_PASS', IS_LOCAL ? LOCAL_DB_PASS : PROD_DB_PASS);

// ─── ERROR REPORTING ─────────────────────────────────────────────────────────
// Show errors locally; suppress on production (prevents output-before-header 500s)
if (IS_LOCAL) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// ─── COMPOSER AUTOLOADER ─────────────────────────────────────────────────────
$autoloaderPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    // Graceful fallback: log the error but don't crash with an ugly fatal
    $msg = '[EventSnap] FATAL: vendor/autoload.php not found. Run `composer install` or upload the vendor/ directory.';
    error_log($msg);
    if (IS_LOCAL) {
        die('<pre style="color:red;padding:20px;">' . $msg . '</pre>');
    } else {
        http_response_code(503);
        die('Service temporarily unavailable. Please try again later.');
    }
}
require_once $autoloaderPath;

// Base URL for routing and QR Code redirection calculated dynamically
if (!defined('BASE_URL')) {
    if (isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        
        // Bulletproof dynamic project root resolver relative to XAMPP document root
        $docRoot = strtolower(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']));
        $projectRoot = strtolower(str_replace('\\', '/', dirname(__DIR__)));
        
        $relativeFolder = '';
        if (strpos($projectRoot, $docRoot) === 0) {
            $origProjectRoot = str_replace('\\', '/', dirname(__DIR__));
            $relativeFolder = substr($origProjectRoot, strlen($docRoot));
        }
        
        $relativeFolder = '/' . ltrim($relativeFolder, '/');
        if ($relativeFolder !== '/') {
            $relativeFolder = rtrim($relativeFolder, '/') . '/';
        }
        
        define('BASE_URL', $protocol . $host . $relativeFolder);
    } else {
        define('BASE_URL', 'http://localhost/CAMBuzz/');
    }
}

// Secure session start if not already active
if (session_status() === PHP_SESSION_NONE) {
    // Session security settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    
    // In HTTPS environment, set secure cookie
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

/**
 * Returns a PDO Database Connection
 * @return PDO
 */
function getDBConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Utility to verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken(?string $token): bool {
    if (!$token) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>
