<?php
require_once __DIR__ . '/../config/config.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    redirect('/login');
}

$errors = [];
$success = false;
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_name = filter_var($_POST['receiver_name'], FILTER_SANITIZE_STRING);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
    $song_title = filter_var($_POST['song_title'], FILTER_SANITIZE_STRING);
    $song_artist = filter_var($_POST['song_artist'], FILTER_SANITIZE_STRING);
    $spotify_url = filter_var($_POST['spotify_url'], FILTER_SANITIZE_URL);
    $batch_visibility = filter_var($_POST['batch_visibility'], FILTER_SANITIZE_STRING);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    
    // Validate input
    if (empty($receiver_name)) {
        $errors[] = "Receiver name is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    if (empty($song_title) || empty($song_artist) || empty($spotify_url)) {
        $errors[] = "Song details are required";
    }
    
    if (!filter_var($spotify_url, FILTER_VALIDATE_URL)) {
        $errors[] = "Invalid Spotify URL";
    }
    
    if (!in_array($batch_visibility, ['2022', '2023', '2024'])) {
        $errors[] = "Invalid batch visibility";
    }
    
    if (empty($errors)) {
        try {
            // Begin transaction
            $conn->exec('BEGIN');
            
            // Add song
            $stmt = $conn->prepare("INSERT INTO songs (title, artist, spotify_url) VALUES (:title, :artist, :spotify_url)");
            $stmt->bindValue(':title', $song_title, SQLITE3_TEXT);
            $stmt->bindValue(':artist', $song_artist, SQLITE3_TEXT);
            $stmt->bindValue(':spotify_url', $spotify_url, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $song_id = $conn->lastInsertRowID();
                
                // Add message
                $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_name, message_content, song_id, batch_visibility, is_anonymous) VALUES (:sender_id, :receiver_name, :message, :song_id, :batch_visibility, :is_anonymous)");
                $stmt->bindValue(':sender_id', $_SESSION['user_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':receiver_name', $receiver_name, SQLITE3_TEXT);
                $stmt->bindValue(':message', $message, SQLITE3_TEXT);
                $stmt->bindValue(':song_id', $song_id, SQLITE3_INTEGER);
                $stmt->bindValue(':batch_visibility', $batch_visibility, SQLITE3_TEXT);
                $stmt->bindValue(':is_anonymous', $is_anonymous, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    $conn->exec('COMMIT');
                    $success = true;
                    redirect('/browse?status=success');
                } else {
                    throw new Exception("Failed to create message");
                }
            } else {
                throw new Exception("Failed to add song");
            }
        } catch (Exception $e) {
            $conn->exec('ROLLBACK');
            $errors[] = "Failed to submit message. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Message - <?php echo SITE_NAME; ?></title>
    
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
                    <a href="/browse" class="text-gray-600 hover:text-pink">
                        <i class="fas fa-list mr-2"></i>Browse
                    </a>
                    <a href="/profile" class="text-gray-600 hover:text-pink">
                        <i class="fas fa-user mr-2"></i>Profile
                    </a>
                    <a href="/logout" class="text-gray-600 hover:text-pink">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Submit Form -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-8 font-poppins">Share Your Message</h1>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div>
                    <label for="receiver_name" class="block text-sm font-medium text-gray-700 mb-2">
                        To
                    </label>
                    <input type="text" id="receiver_name" name="receiver_name" 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                           placeholder="Who is this message for?"
                           value="<?php echo isset($_POST['receiver_name']) ? htmlspecialchars($_POST['receiver_name']) : ''; ?>"
                           required>
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                        Your Message
                    </label>
                    <textarea id="message" name="message" rows="4"
                              class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                              placeholder="Write your message here..."
                              required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="song_title" class="block text-sm font-medium text-gray-700 mb-2">
                            Song Title
                        </label>
                        <input type="text" id="song_title" name="song_title" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                               placeholder="Enter song title"
                               value="<?php echo isset($_POST['song_title']) ? htmlspecialchars($_POST['song_title']) : ''; ?>"
                               required>
                    </div>

                    <div>
                        <label for="song_artist" class="block text-sm font-medium text-gray-700 mb-2">
                            Artist
                        </label>
                        <input type="text" id="song_artist" name="song_artist" 
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                               placeholder="Enter artist name"
                               value="<?php echo isset($_POST['song_artist']) ? htmlspecialchars($_POST['song_artist']) : ''; ?>"
                               required>
                    </div>
                </div>

                <div>
                    <label for="spotify_url" class="block text-sm font-medium text-gray-700 mb-2">
                        Spotify URL
                    </label>
                    <input type="url" id="spotify_url" name="spotify_url" 
                           class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                           placeholder="Paste Spotify song URL here"
                           value="<?php echo isset($_POST['spotify_url']) ? htmlspecialchars($_POST['spotify_url']) : ''; ?>"
                           required>
                </div>

                <div>
                    <label for="batch_visibility" class="block text-sm font-medium text-gray-700 mb-2">
                        Show to Batch
                    </label>
                    <select id="batch_visibility" name="batch_visibility" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                            required>
                        <option value="">Select batch year</option>
                        <option value="2024" <?php echo (isset($_POST['batch_visibility']) && $_POST['batch_visibility'] === '2024') ? 'selected' : ''; ?>>2024</option>
                        <option value="2023" <?php echo (isset($_POST['batch_visibility']) && $_POST['batch_visibility'] === '2023') ? 'selected' : ''; ?>>2023</option>
                        <option value="2022" <?php echo (isset($_POST['batch_visibility']) && $_POST['batch_visibility'] === '2022') ? 'selected' : ''; ?>>2022</option>
                    </select>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="is_anonymous" name="is_anonymous" 
                           class="h-4 w-4 text-pink focus:ring-pink border-gray-300 rounded"
                           <?php echo (isset($_POST['is_anonymous'])) ? 'checked' : ''; ?>>
                    <label for="is_anonymous" class="ml-2 block text-sm text-gray-700">
                        Send anonymously
                    </label>
                </div>

                <button type="submit" 
                        class="w-full bg-pink hover:bg-pink-dark text-white font-bold py-3 px-4 rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-paper-plane mr-2"></i>
                    Send Message
                </button>
            </form>
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
