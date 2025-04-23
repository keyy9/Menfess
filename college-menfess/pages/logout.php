<?php
require_once __DIR__ . '/../config/config.php';

// Clear all session data
session_destroy();

// Redirect to home page
redirect('/');
?>
