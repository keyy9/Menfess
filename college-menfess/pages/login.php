<?php
require_once __DIR__ . '/../config/config.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no errors, attempt login
    if (empty($errors)) {
        try {
            // Check if user exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $result = $stmt->execute();
            $user = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['batch_year'] = $user['batch_year'];
                
                // Redirect to home page
                redirect('/');
            } else {
                $errors[] = "Invalid email or password";
            }
        } catch (Exception $e) {
            $errors[] = "Login failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
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
                    <a href="/register" class="bg-pink hover:bg-pink-dark text-white px-4 py-2 rounded-lg transition">Register</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Login Form -->
    <main class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-lg">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 font-poppins">
                    Welcome Back!
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Sign in to share your musical messages
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
                        <label for="email" class="block text-sm font-medium text-gray-700">
                            Email address
                        </label>
                        <input id="email" name="email" type="email" autocomplete="email" required 
                               class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-pink focus:border-pink focus:z-10 sm:text-sm"
                               placeholder="Enter your email"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required 
                               class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-pink focus:border-pink focus:z-10 sm:text-sm"
                               placeholder="Enter your password">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember_me" name="remember_me" type="checkbox" 
                               class="h-4 w-4 text-pink focus:ring-pink border-gray-300 rounded">
                        <label for="remember_me" class="ml-2 block text-sm text-gray-900">
                            Remember me
                        </label>
                    </div>

                    <div class="text-sm">
                        <a href="/forgot-password" class="font-medium text-pink hover:text-pink-dark">
                            Forgot your password?
                        </a>
                    </div>
                </div>

                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-pink hover:bg-pink-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <i class="fas fa-sign-in-alt"></i>
                    </span>
                    Sign in
                </button>
            </form>

            <div class="text-center mt-4">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <a href="/register" class="font-medium text-pink hover:text-pink-dark">
                        Register here
                    </a>
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
