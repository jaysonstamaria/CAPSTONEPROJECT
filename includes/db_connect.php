<?php
// File: includes/db_connect.php

// --- Development Mode Switch ---
// Set to TRUE to display OTP on screen. Set to FALSE to send real emails.
define('DEV_MODE_DISPLAY_OTP', TRUE);



// --- Database Configuration ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'carsrus_db'); // <<< Correct DB name

// --- Site URL (for email links) ---
if(!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    $scriptDir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
    define('SITE_URL', $protocol . $domainName . $scriptDir);
}

$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_errno) {
    error_log("Database connection failed: " . $mysqli->connect_error);
    die("Database connection failed. Please try again later.");
}
$mysqli->set_charset('utf8mb4');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>