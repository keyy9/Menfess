<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_FILE', __DIR__ . '/../database.sqlite');

try {
    // Delete existing database if it exists
    if (file_exists(DB_FILE)) {
        unlink(DB_FILE);
    }

    // Create SQLite database connection
    $conn = new SQLite3(DB_FILE);
    
    // Enable foreign key support
    $conn->exec('PRAGMA foreign_keys = ON');

    // Create tables with correct syntax
    $tables = [
        "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            batch_year TEXT CHECK(batch_year IN ('2022', '2023', '2024')) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            profile_picture TEXT
        )",
        
        "CREATE TABLE songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            artist TEXT NOT NULL,
            spotify_url TEXT NOT NULL,
            album_art_url TEXT NOT NULL,
            preview_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sender_id INTEGER,
            receiver_name TEXT NOT NULL,
            message_content TEXT NOT NULL,
            song_id INTEGER,
            batch_visibility TEXT CHECK(batch_visibility IN ('2022', '2023', '2024')) NOT NULL,
            is_anonymous INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (song_id) REFERENCES songs(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE likes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(message_id, user_id)
        )",
        
        "CREATE TABLE comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            comment_content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            message_id INTEGER NOT NULL,
            reporter_id INTEGER NOT NULL,
            reason TEXT NOT NULL,
            status TEXT CHECK(status IN ('pending', 'reviewed', 'resolved')) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE user_preferences (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE NOT NULL,
            theme_preference TEXT DEFAULT 'pink',
            notification_settings TEXT,
            privacy_settings TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];

    foreach ($tables as $sql) {
        if (!$conn->exec($sql)) {
            throw new Exception("Error creating table: " . $conn->lastErrorMsg());
        }
    }

    return $conn;
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
