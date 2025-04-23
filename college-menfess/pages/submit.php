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
    $receiver_name = sanitize($_POST['receiver_name']);
    $message_content = sanitize($_POST['message']);
    $song_title = sanitize($_POST['song_title']);
    $song_artist = sanitize($_POST['song_artist']);
    $spotify_url = sanitize($_POST['spotify_url']);
    $batch_visibility = sanitize($_POST['batch_visibility']);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    // Validate inputs
    if (empty($receiver_name)) {
        $errors[] = "Receiver name is required.";
    }
    if (empty($message_content)) {
        $errors[] = "Message content is required.";
    }
    if (empty($song_title) || empty($song_artist) || empty($spotify_url)) {
        $errors[] = "Complete song details are required.";
    }
    if (!in_array($batch_visibility, ['2022', '2023', '2024'])) {
        $errors[] = "Invalid batch visibility selected.";
    }

    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Insert song
            $stmt = $conn->prepare("INSERT INTO songs (title, artist, spotify_url) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $song_title, $song_artist, $spotify_url);
            $stmt->execute();
            $song_id = $stmt->insert_id;
            $stmt->close();

            // Insert message
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_name, message_content, song_id, batch_visibility, is_anonymous) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issisi", $user['id'], $receiver_name, $message_content, $song_id, $batch_visibility, $is_anonymous);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $success = true;
            redirect('/browse?status=success');
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Failed to submit message: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Submit Message - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Nunito:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body class="flex flex-col min-h-screen bg-pink-50">
    <header class="bg-white shadow-md">
        <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
            <a href="/" class="flex items-center space-x-2">
                <img src="/assets/images/favicon.svg" alt="Logo" class="w-8 h-8" />
                <span class="text-2xl font-bold text-pink-dark font-poppins"><?php echo SITE_NAME; ?></span>
            </a>
            <div class="space-x-4">
                <a href="/browse" class="text-gray-600 hover:text-pink"><i class="fas fa-list mr-2"></i>Browse</a>
                <a href="/profile" class="text-gray-600 hover:text-pink"><i class="fas fa-user mr-2"></i>Profile</a>
                <a href="/logout" class="text-gray-600 hover:text-pink"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
            </div>
        </nav>
    </header>
    <main class="flex-grow container mx-auto px-6 py-8 max-w-2xl">
        <h1 class="text-3xl font-bold text-gray-900 mb-8 font-poppins">Share Your Message</h1>
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" class="space-y-6">
            <div>
                <label for="receiver_name" class="block text-gray-700 mb-2">To</label>
                <input type="text" id="receiver_name" name="receiver_name" required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                    value="<?php echo isset($_POST['receiver_name']) ? htmlspecialchars($_POST['receiver_name']) : ''; ?>" />
            </div>
            <div>
                <label for="message" class="block text-gray-700 mb-2">Your Message</label>
                <textarea id="message" name="message" rows="4" required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="song_title" class="block text-gray-700 mb-2">Song Title</label>
                    <input type="text" id="song_title" name="song_title" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                        value="<?php echo isset($_POST['song_title']) ? htmlspecialchars($_POST['song_title']) : ''; ?>" />
                </div>
                <div>
                    <label for="song_artist" class="block text-gray-700 mb-2">Artist</label>
                    <input type="text" id="song_artist" name="song_artist" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                        value="<?php echo isset($_POST['song_artist']) ? htmlspecialchars($_POST['song_artist']) : ''; ?>" />
                </div>
            </div>
            <div>
                <label for="spotify_url" class="block text-gray-700 mb-2">Spotify URL</label>
                <input type="url" id="spotify_url" name="spotify_url" required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink"
                    value="<?php echo isset($_POST['spotify_url']) ? htmlspecialchars($_POST['spotify_url']) : ''; ?>" />
            </div>
            <div>
                <label for="batch_visibility" class="block text-gray-700 mb-2">Show to Batch</label>
                <select id="batch_visibility" name="batch_visibility" required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink focus:border-pink">
                    <option value="">Select batch year</option>
                    <option value="2024" <?php echo (isset($_POST['batch_visibility']) && $_POST['batch_visibility'] === '2024') ? 'selected' : ''; ?>>2024</option>
                    <option value="2023" <?php echo (isset($_POST['batch_visibility']) && $_POST['batch_visibility'] === '2023') ? 'selected' : ''; ?>>2023</option>
                    <option value="2022" <?php echo (isset($_POST['batch_visibility']) && $_POST['batch_visibility'] === '2022') ? 'selected' : ''; ?>>2022</option>
                </select>
            </div>
            <div class="flex items-center">
                <input type="checkbox" id="is_anonymous" name="is_anonymous" class="h-4 w-4 text-pink focus:ring-pink border-gray-300 rounded"
                    <?php echo (isset($_POST['is_anonymous'])) ? 'checked' : ''; ?> />
                <label for="is_anonymous" class="ml-2 block text-sm text-gray-700">Send anonymously</label>
            </div>
            <button type="submit" class="w-full bg-pink hover:bg-pink-dark text-white font-bold py-3 px-4 rounded-lg transition flex items-center justify-center">
                <i class="fas fa-paper-plane mr-2"></i> Send Message
            </button>
        </form>
    </main>
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
