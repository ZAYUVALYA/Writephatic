<?php
session_start();

// If user is already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') {
        header('Location: index.php');
    } elseif ($_SESSION['role'] === 'instructor') {
        header('Location: dashboard.php');
    }
    exit;
}

// Define the path to the JSON database file
$usersFile = __DIR__ . '/data/users.json';

// Helper function to load users from JSON file
function loadUsers($file) {
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

// Helper function to save users to JSON file
function saveUsers($file, $users) {
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = 'student'; // default role for registration

    // Basic validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } else {
        // Load existing users
        $users = loadUsers($usersFile);

        // Check for duplicate username or email
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                $error = 'Username already exists.';
                break;
            }
            if ($user['email'] === $email) {
                $error = 'Email already registered.';
                break;
            }
        }
        
        // If no error, create a new user record
        if (!isset($error)) {
            // Generate a unique user id (you can replace this with a more robust mechanism later)
            $user_id = 'u' . (count($users) + 1001);
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $now = date('c'); // ISO 8601 formatted timestamp

            $newUser = [
                'user_id'           => $user_id,
                'username'          => $username,
                'hashed_password'   => $hashed_password,
                'role'              => $role,
                'email'             => $email,
                'registration_date' => $now,
                'last_login'        => $now // or null if you prefer
            ];

            // Append and save the user record
            $users[] = $newUser;
            saveUsers($usersFile, $users);

            $_SESSION['success'] = 'Registration successful! Please log in.';
            header('Location: login.php');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Writepathic</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #76ABAE;
            --secondary-color: #31363F;
            --text-color: #EEEEEE;
            --background-color: #222831;
            --border-color: #4E5D6C;
        }
        
        body {
            background-color: var(--background-color);
            color: var(--text-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .register-container {
            background-color: var(--secondary-color);
            border-radius: 8px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--background-color);
            color: var(--text-color);
        }
        
        button {
            width: 100%;
            padding: 0.8rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        .primary-btn {
            background-color: var(--primary-color);
            color: var(--text-color);
        }
        
        .primary-btn:hover {
            background-color: #63999C;
        }
        
        .error {
            color: #dc3545;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1><i class="fa-solid fa-graduation-cap me-2"></i>Writepathic</h1>
            <p>Create your account</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form action="register.php" method="POST">
            <div class="form-group">
                <label for="username"><i class="fa-solid fa-user me-1"></i> Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fa-solid fa-envelope me-1"></i> Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock me-1"></i> Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="primary-btn">Register</button>
        </form>
        
        <div class="login-link">
            <p>Already have an account? <a href="login.php">Log in here</a></p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script type="module" src="main.js"></script>
</body>
</html>