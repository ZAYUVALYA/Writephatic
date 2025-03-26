<?php
session_start();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please provide both username and password.';
    } else {
        // Load users
        $users = loadUsers($usersFile);
        $foundUser = null;
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                $foundUser = $user;
                break;
            }
        }

        if ($foundUser && password_verify($password, $foundUser['hashed_password'])) {
            // Update last login
            $foundUser['last_login'] = date('c');

            // (Optional) Update the user record in the JSON file
            foreach ($users as &$user) {
                if ($user['user_id'] === $foundUser['user_id']) {
                    $user = $foundUser;
                    break;
                }
            }
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

            // Set session variables (consider regenerating session ID)
            $_SESSION['user_id']   = $foundUser['user_id'];
            $_SESSION['username']  = $foundUser['username'];
            $_SESSION['role']      = $foundUser['role'];
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Writepathic</title>
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome (latest version) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-3Bf5rLdpK6E+woua6Ok+X2BxF/MRk9AwFcic3wou0vWqL5y6e/j5G+tmC+qTz9AYyAcxOUi+3DbGe1bEk7x1xw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- ScrollReveal -->
<script src="https://unpkg.com/scrollreveal"></script>

</head>
<body>
    <h2>Login</h2>
    <?php if (isset($_SESSION['success'])): ?>
        <p style="color: green;"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form action="login.php" method="POST">
        <label>Username:<br>
            <input type="text" name="username" required>
        </label><br><br>
        <label>Password:<br>
            <input type="password" name="password" required>
        </label><br><br>
        <button type="submit">Log In</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register here</a>.</p>

    <script type="module" src="main.js"></script>
</body>
</html>
