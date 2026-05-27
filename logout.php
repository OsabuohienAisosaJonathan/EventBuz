<?php
/**
 * EventSnap Cloud - Logout Handler
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Auth.php';

Auth::logout();
header("Location: index.php");
exit;
?>
