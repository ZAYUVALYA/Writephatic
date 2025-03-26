<?php
// Start the session for potential flash messages
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
    <title>Register - Writepathic</title>
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome (latest version) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-3Bf5rLdpK6E+woua6Ok+X2BxF/MRk9AwFcic3wou0vWqL5y6e/j5G+tmC+qTz9AYyAcxOUi+3DbGe1bEk7x1xw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- ScrollReveal -->
<script src="https://unpkg.com/scrollreveal"></script>

</head>
<body>
    <h2>Register</h2>
    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <form action="register.php" method="POST">
        <label>Username:<br>
            <input type="text" name="username" required>
        </label><br><br>
        <label>Email:<br>
            <input type="email" name="email" required>
        </label><br><br>
        <label>Password:<br>
            <input type="password" name="password" required>
        </label><br><br>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Log in here</a>.</p>

    <script type="module" src="main.js"></script>
</body>
</html>
