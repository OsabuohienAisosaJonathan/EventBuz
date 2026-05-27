<?php
/**
 * EventSnap Cloud - Server Diagnostic Script
 * ============================================
 * INSTRUCTIONS:
 *   1. Upload this file to your live server root (eventsnap.xo.je/diagnostic.php)
 *   2. Open it in your browser
 *   3. Fix whatever shows RED below
 *   4. DELETE this file from the server immediately after — it exposes sensitive info!
 */

// Force error display for this diagnostic only
error_reporting(E_ALL);
ini_set('display_errors', '1');

$pass = '✅';
$fail = '❌';

function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $icon  = $ok ? $pass : $fail;
    $color = $ok ? '#16a34a' : '#dc2626';
    $bg    = $ok ? '#f0fdf4' : '#fef2f2';
    echo "<div style='padding:10px 16px;margin:6px 0;border-radius:8px;background:{$bg};border-left:4px solid {$color};font-family:monospace'>";
    echo "<span style='color:{$color};font-size:1.1em'>{$icon}</span> <strong>{$label}</strong>";
    if ($detail) echo "<br><span style='color:#555;font-size:.9em;margin-left:20px'>{$detail}</span>";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>EventSnap Diagnostics</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; background: #f8fafc; color: #1e293b; }
  h1   { font-size: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
  h2   { font-size: 1rem; color: #64748b; margin-top: 28px; text-transform: uppercase; letter-spacing: .05em; }
  .warn { background:#fffbeb; border:1px solid #f59e0b; padding:10px 16px; border-radius:8px; margin-top:20px; font-size:.9em; }
</style>
</head>
<body>
<h1>🔍 EventSnap Server Diagnostic</h1>
<p style="color:#64748b">Running on: <strong><?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'unknown'); ?></strong> &nbsp;|&nbsp; PHP <strong><?php echo phpversion(); ?></strong></p>

<h2>1 · PHP Version &amp; Core Extensions</h2>
<?php
check('PHP version ≥ 7.4', version_compare(PHP_VERSION, '7.4.0', '>='), 'Running: ' . PHP_VERSION);
check('PDO extension', extension_loaded('pdo'), '');
check('PDO MySQL driver', extension_loaded('pdo_mysql'), '');
check('GD / image processing', extension_loaded('gd'), 'Required for QR code generation');
check('mbstring', extension_loaded('mbstring'), '');
check('openssl', extension_loaded('openssl'), 'Required for CSRF random_bytes()');
check('json', extension_loaded('json'), '');
check('session', extension_loaded('session'), '');
?>

<h2>2 · Composer / Autoloader</h2>
<?php
$vendorPath = __DIR__ . '/vendor/autoload.php';
$vendorExists = file_exists($vendorPath);
check('vendor/autoload.php exists', $vendorExists, $vendorPath);

if ($vendorExists) {
    try {
        require_once $vendorPath;
        check('Autoloader loads without error', true);
        check('chillerlan\\QRCode\\QRCode class', class_exists('chillerlan\\QRCode\\QRCode'), 'Required for QR generation');
        check('PHPMailer\\PHPMailer\\PHPMailer class', class_exists('PHPMailer\\PHPMailer\\PHPMailer'), 'Required for email');
    } catch (Throwable $e) {
        check('Autoloader loads without error', false, $e->getMessage());
    }
} else {
    echo "<div style='padding:10px 16px;margin:6px 0;border-radius:8px;background:#fef2f2;border-left:4px solid #dc2626;font-family:monospace'>
        ❌ <strong>FIX:</strong> Upload the <code>vendor/</code> folder to the server root, or SSH in and run <code>composer install --no-dev</code>
    </div>";
}
?>

<h2>3 · Database Connection</h2>
<?php
// Try to load config to pick up credentials
$configPath = __DIR__ . '/config/database.php';
$configLoaded = false;
if (file_exists($configPath)) {
    // We suppress here because it may try to start a session etc.
    try {
        // Manually define IS_LOCAL override for diagnostic
        if (!defined('IS_LOCAL')) define('IS_LOCAL', false); // treat as prod for this test
        // Just parse out the prod constants — safest way is to load it
        // But config starts session; we need to be careful
        if (session_status() === PHP_SESSION_NONE) session_start();
        require_once $configPath;
        $configLoaded = true;
    } catch (Throwable $e) {
        check('config/database.php loads', false, $e->getMessage());
    }
}

if ($configLoaded) {
    check('config/database.php loads', true);
    // Try actual DB connect
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        check('Database connection', true, "Connected to `" . DB_NAME . "` as `" . DB_USER . "` on " . DB_HOST);

        // Check tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $required = ['users', 'events', 'media_uploads', 'subscriptions', 'crew_invitations'];
        foreach ($required as $t) {
            check("Table `{$t}` exists", in_array($t, $tables));
        }
    } catch (PDOException $e) {
        check('Database connection', false, $e->getMessage());
        echo "<div style='padding:10px 16px;margin:6px 0;border-radius:8px;background:#fef2f2;border-left:4px solid #dc2626;font-family:monospace'>
            ❌ <strong>FIX:</strong> Open <code>config/database.php</code> and update the <code>PROD_DB_*</code> constants with your cPanel MySQL credentials.<br>
            &nbsp;&nbsp;&nbsp;DB Name format: <em>cpanelusername_dbname</em> &nbsp;|&nbsp; DB User format: <em>cpanelusername_dbuser</em>
        </div>";
    }
}
?>

<h2>4 · File System &amp; Permissions</h2>
<?php
$paths = [
    'uploads/'           => 'Guest photo uploads',
    'assets/css/'        => 'CSS stylesheets',
    'config/'            => 'Config directory (should NOT be web-accessible ideally)',
];
foreach ($paths as $dir => $label) {
    $full = __DIR__ . '/' . $dir;
    $exists   = is_dir($full);
    $writable = $exists && is_writable($full);
    check("Directory: {$dir}", $exists, $label);
    if ($exists) check("  └─ Writable", $writable, $writable ? 'OK' : 'Run: chmod 755 ' . $dir);
}

// uploads/.htaccess protection check
$uploadHtaccess = __DIR__ . '/uploads/.htaccess';
check('uploads/.htaccess exists (security)', file_exists($uploadHtaccess), 'Prevents PHP execution in uploads folder');
?>

<h2>5 · Session</h2>
<?php
check('Session started successfully', session_status() === PHP_SESSION_ACTIVE);
$_SESSION['diag_test'] = 'ok';
check('Session write works', isset($_SESSION['diag_test']));
?>

<h2>6 · BASE_URL Detection</h2>
<?php
if (defined('BASE_URL')) {
    check('BASE_URL resolved', true, BASE_URL);
} else {
    check('BASE_URL resolved', false, 'BASE_URL constant not defined');
}
?>

<div class="warn">
  ⚠️ <strong>IMPORTANT:</strong> Delete <code>diagnostic.php</code> from your server immediately after reviewing these results.
  It exposes server configuration details.
</div>
</body>
</html>
