<?php
session_start();

// Site configuration
define('SITE_NAME', 'College Menfess');
define('SITE_URL', 'http://localhost:8000');

// Spotify API configuration
define('SPOTIFY_CLIENT_ID', 'your_spotify_client_id');
define('SPOTIFY_CLIENT_SECRET', 'your_spotify_client_secret');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/database.php';

// Helper functions
function redirect($path) {
    header("Location: " . SITE_URL . $path);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    global $conn;
    if (!isLoggedIn()) return null;
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Security functions
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

// Set CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateToken();
}
?>
