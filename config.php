<?php
// File: C:\inetpub\wwwroot\portal\config.php
// Database Configuration
define('DB_SERVER', '10.0.0.4\\PGR2022');
define('DB_USERNAME', 'PRG_AdminUser');
define('DB_PASSWORD', 'PRG!ESXiAdminProd#2025$');
define('DB_MASTERLIST', 'MasterList');
define('DB_OPAD_HRMIS', 'OPAD-HRMIS');
define('DB_PGR_DMTS', 'PGR-DMTS');

// Application Settings
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/portal/');
define('SITE_NAME', 'Romblon Provincial Government - Document Tracking System');
define('LOGO_PATH', 'images/logo.png');

// Timezone
date_default_timezone_set('Asia/Manila');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>