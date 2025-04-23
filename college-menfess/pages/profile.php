<?php
require_once '../config/config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('/login');
}

$errors = [];
$success = false;
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if username or email already exists (excluding current user)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE (username = :username OR email = :email) AND id != :user_id");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt->bindValue(':user_id', $user['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row['count'] > 0) {
        $errors[] = "Username or email already exists";
    }
    
    // If changing password
    if (!empty($current_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters long";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        try {
            $sql = "UPDATE users SET username = :username, email = :email";
            $params = [
                ':username' => $username,
                ':email' => $email,
                ':user_id' => $user['id']
            ];
            
            if (!empty($new_password)) {
                $sql .= ", password = :password";
                $params[':password'] = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = :user_id";
            
            $stmt = $conn->prepare($sql);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            if ($stmt->execute()) {
                $success = true;
                $user = getCurrentUser(); // Refresh user data
            } else {
                $errors[] = "Failed to update profile";
            }
        } catch (Exception $e) {
            $errors[] = "Failed to update profile";
        }
    }
}

// Get user's messages
$stmt = $conn->prepare("
    SELECT m.*, s.title, s.artist, s.spotify_url,
           (SELECT COUNT(*) FROM likes WHERE message_id = m.id) as likes_count
    FROM messages m
    LEFT JOIN songs s ON m.song_id = s.id
    WHERE m.sender_id = :user_id
    ORDER BY m.created_at DESC
");
$stmt->bindValue(':user_id', $user['id'], SQLITE3_INTEGER);
$result = $stmt->execute();

$messages = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $messages[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Nunito:wght@400;600&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        pink: {
                            light: '#FFB6C1',
                            DEFAULT: '#FF69B4',
                            dark: '#FF1493',
                        }
                    },
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif'],
                        nunito: ['Nunito', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="flex flex-col min-h-screen bg-pink-50">
    <!-- Header -->
    <header class="bg-white shadow-md">
        <nav class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <a href="/" class="flex items-center space-x-2">
                    <img src="/assets/images/favicon.svg" alt="Logo" class="w-8 h-8">
                    <span class="text-2xl font-bold text-pink-dark font-poppins"><?php echo SITE_NAME; ?></span>
                </a>
                <div class="space-x-4">
                    <a href="/submit" class="bg-pink hover:bg-pink-dark text-white px-4 py-2 rounded-lg transition">
                        <i class="fas fa-plus mr-2"></i>New Message
                    </a>
                    <a href="/browse" class="text-gray-600 hover:text-pink">
                        <i class="fas fa-list mr-2"></i>Browse
                    </a>
                    <a href="/logout" class="text-gray-600 hover:text-pink">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Profile Section -->
            <div class="md:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-bold mb-6">Profile Settings</h2>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            <ul class="list-disc list-inside">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            Profile updated successfully!
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="username" class="block text-gray-700 mb-2">Username</label>
                            <input type="text" id="username" name="username" 
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                                   value="<?php echo htmlspecialchars($user['username']); ?>"
                                   required>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-gray-700 mb-2">Email</label>
                            <input type="email" id="email" name="email" 
                                   class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                                   value="<?php echo htmlspecialchars($user['email']); ?>"
                                   required>
                        </div>
                        
                        <div>
                            <label for="batch_year" class="block text-gray-700 mb-2">Batch Year</label>
                            <input type="text" id="batch_year" 
                                   class="w-full px-4 py-2 border rounded-lg bg-gray-100"
                                   value="<?php echo htmlspecialchars($user['batch_year']); ?>"
                                   disabled>
                        </div>
                        
                        <div class="border-t pt-4 mt-4">
                            <h3 class="text-lg font-semibold mb-4">Change Password</h3>
                            
                            <div>
                                <label for="current_password" class="block text-gray-700 mb-2">Current Password</label>
                                <input type="password" id="current_password" name="current_password" 
                                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink">
                            </div>
                            
                            <div>
                                <label for="new_password" class="block text-gray-700 mb-2">New Password</label>
                                <input type="password" id="new_password" name="new_password" 
                                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink">
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink">
                            </div>
                        </div>
                        
                        <button type="submit" 
                                class="w-full bg-pink hover:bg-pink-dark text-white font-bold py-3 px-4 rounded-lg transition">
                            Update Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Messages Section -->
            <div class="md:col-span-2">
                <h2 class="text-2xl font-bold mb-6">Your Messages</h2>
                
                <?php if (empty($messages)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 text-center">
                        <p class="text-gray-600">You haven't sent any messages yet.</p>
                        <a href="/submit" class="inline-block mt-4 text-pink hover:text-pink-dark">
                            Send your first message
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($messages as $message): ?>
                            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                <div class="p-6">
                                    <div class="flex justify-between items-start mb-4">
                                        <div>
                                            <div class="text-sm text-gray-500">To: <?php echo htmlspecialchars($message['receiver_name']); ?></div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo date('F j, Y', strtotime($message['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="text-sm">
                                            <span class="bg-pink-light text-pink-dark px-3 py-1 rounded-full">
                                                Batch <?php echo htmlspecialchars($message['batch_visibility']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <p class="text-gray-800 mb-4"><?php echo nl2br(htmlspecialchars($message['message_content'])); ?></p>
                                    
                                    <div class="border-t pt-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="font-semibold"><?php echo htmlspecialchars($message['title']); ?></h4>
                                                <p class="text-gray-600"><?php echo htmlspecialchars($message['artist']); ?></p>
                                            </div>
                                            <?php if (!empty($message['spotify_url'])): ?>
                                                <a href="<?php echo htmlspecialchars($message['spotify_url']); ?>" 
                                                   target="_blank"
                                                   class="text-green-500 hover:text-green-600">
                                                    <i class="fab fa-spotify text-2xl"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 flex items-center text-sm text-gray-500">
                                        <i class="fas fa-heart text-pink mr-1"></i>
                                        <?php echo $message['likes_count']; ?> likes
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8">
        <div class="container mx-auto px-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p>&copy; 2024 <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="flex space-x-4">
                    <a href="/about" class="hover:text-pink-light">About</a>
                    <a href="/privacy" class="hover:text-pink-light">Privacy Policy</a>
                    <a href="/terms" class="hover:text-pink-light">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
