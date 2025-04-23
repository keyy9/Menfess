<?php
require_once __DIR__ . '/../config/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug log function
function debug_log($message) {
    error_log("[DEBUG] " . print_r($message, true));
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log("POST data received: " . print_r($_POST, true));
    
    $username = htmlspecialchars(strip_tags($_POST['username']));
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $batch_year = htmlspecialchars(strip_tags($_POST['batch_year']));
    
    debug_log("Sanitized inputs - Username: $username, Email: $email, Batch: $batch_year");
    
    // Validate input
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (!in_array($batch_year, ['2022', '2023', '2024'])) {
        $errors[] = "Invalid batch year";
    }
    
    debug_log("Validation errors: " . print_r($errors, true));
    
    if (empty($errors)) {
        try {
            debug_log("Attempting database operations");
            
            // Check if username or email already exists
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = :username OR email = :email");
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);
            
            debug_log("Existing user check result: " . print_r($row, true));
            
            if ($row['count'] > 0) {
                $errors[] = "Username or email already exists";
            } else {
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, batch_year) VALUES (:username, :email, :password, :batch_year)");
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
                $stmt->bindValue(':batch_year', $batch_year, SQLITE3_TEXT);
                
                debug_log("Attempting to execute insert statement");
                
                if ($stmt->execute()) {
                    debug_log("User created successfully");
                    $success = true;
                    // Auto login after registration
                    $_SESSION['user_id'] = $conn->lastInsertRowID();
                    $_SESSION['username'] = $username;
                    $_SESSION['batch_year'] = $batch_year;
                    redirect('/');
                } else {
                    debug_log("Failed to create user: " . $conn->lastErrorMsg());
                    $errors[] = "Registration failed. Please try again.";
                }
            }
        } catch (Exception $e) {
            debug_log("Exception occurred: " . $e->getMessage());
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    
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
                <div>
                    <a href="/login" class="text-gray-600 hover:text-pink">Already have an account? Login</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Registration Form -->
    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 font-poppins">
                    Create Your Account
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Join your college mates and start sharing musical messages
                </p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="mt-8 space-y-6" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="rounded-md shadow-sm space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">
                            Username
                        </label>
                        <input id="username" name="username" type="text" required 
                               class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-pink focus:border-pink focus:z-10 sm:text-sm"
                               placeholder="Choose a username"
                               autocomplete="username"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Email address
                        </label>
                        <input id="email" name="email" type="email" required 
                               class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-pink focus:border-pink focus:z-10 sm:text-sm"
                               placeholder="Enter your email"
                               autocomplete="email"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div>
                        <label for="batch_year" class="block text-sm font-medium text-gray-700">
                            Batch Year
                        </label>
                        <select id="batch_year" name="batch_year" required
                                class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-pink focus:border-pink focus:z-10 sm:text-sm">
                            <option value="">Select your batch</option>
                            <option value="2024" <?php echo (isset($_POST['batch_year']) && $_POST['batch_year'] === '2024') ? 'selected' : ''; ?>>2024</option>
                            <option value="2023" <?php echo (isset($_POST['batch_year']) && $_POST['batch_year'] === '2023') ? 'selected' : ''; ?>>2023</option>
                            <option value="2022" <?php echo (isset($_POST['batch_year']) && $_POST['batch_year'] === '2022') ? 'selected' : ''; ?>>2022</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <input id="password" name="password" type="password" required 
                               class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-pink focus:border-pink focus:z-10 sm:text-sm"
                               placeholder="Create a password"
                               autocomplete="new-password"
                               minlength="6">
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                            Confirm Password
                        </label>
                        <input id="confirm_password" name="confirm_password" type="password" required 
                               class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-pink focus:border-pink focus:z-10 sm:text-sm"
                               placeholder="Confirm your password"
                               autocomplete="new-password"
                               minlength="6">
                    </div>
                </div>

                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-pink hover:bg-pink-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-user-plus"></i>
                    </span>
                    Create Account
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="text-sm text-gray-600">
                    By registering, you agree to our 
                    <a href="/terms" class="font-medium text-pink hover:text-pink-dark">Terms of Service</a>
                    and
                    <a href="/privacy" class="font-medium text-pink hover:text-pink-dark">Privacy Policy</a>
                </p>
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
