<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Define paths to JSON files
$classesFile     = __DIR__ . '/data/classes.json';
$enrollmentsFile = __DIR__ . '/data/enrollments.json';

// Helper function: load JSON data from file
function loadJson($file) {
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

// Helper function: save JSON data to file
function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Process enrollment code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enrollment_code'])) {
    $inputCode = trim($_POST['enrollment_code']);
    $classes   = loadJson($classesFile);
    $enrollments = loadJson($enrollmentsFile);
    $foundClass = null;

    // Look for a class with a matching enrollment code
    foreach ($classes as $class) {
        if ($class['enrollment_code'] === $inputCode) {
            $foundClass = $class;
            break;
        }
    }

    if ($foundClass) {
        // Check if user is already enrolled in this class
        $alreadyEnrolled = false;
        foreach ($enrollments as $enrollment) {
            if ($enrollment['class_id'] === $foundClass['class_id'] && $enrollment['user_id'] === $_SESSION['user_id']) {
                $alreadyEnrolled = true;
                break;
            }
        }
        
        if (!$alreadyEnrolled) {
            // Create a new enrollment record
            $newEnrollment = [
                'enrollment_id'   => 'e' . (count($enrollments) + 5001), // Generate a unique ID
                'class_id'        => $foundClass['class_id'],
                'user_id'         => $_SESSION['user_id'],
                'enrollment_date' => date('c'),
                'status'          => 'active'
            ];
            $enrollments[] = $newEnrollment;
            saveJson($enrollmentsFile, $enrollments);
            $successMessage = "Successfully enrolled in {$foundClass['class_name']}!";
        } else {
            $errorMessage = "You are already enrolled in {$foundClass['class_name']}.";
        }
    } else {
        $errorMessage = "Invalid enrollment code. Please try again.";
    }
}

// Load the updated enrollments and classes for display
$enrollments = loadJson($enrollmentsFile);
$classes     = loadJson($classesFile);

// Create an array of classes the student is enrolled in
$userClasses = [];
foreach ($enrollments as $enrollment) {
    if ($enrollment['user_id'] === $_SESSION['user_id'] && $enrollment['status'] === 'active') {
        // Find class details by matching class_id
        foreach ($classes as $class) {
            if ($class['class_id'] === $enrollment['class_id']) {
                $userClasses[] = $class;
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Writepathic</title>
    <link rel="stylesheet" href="style.css">
    <!-- You can include external CDNs for animations, icons, etc. here -->
     <!-- Font Awesome (latest version) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-3Bf5rLdpK6E+woua6Ok+X2BxF/MRk9AwFcic3wou0vWqL5y6e/j5G+tmC+qTz9AYyAcxOUi+3DbGe1bEk7x1xw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- ScrollReveal -->
<script src="https://unpkg.com/scrollreveal"></script>

</head>
<body>
    <header>
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        <nav>
            <a href="logout.php">Logout</a>
            <!-- Additional navigation links can be added here -->
        </nav>
    </header>

    <section id="enrollment">
        <h2>Enroll in a Class</h2>
        <?php if (isset($successMessage)): ?>
            <p class="success"><?php echo htmlspecialchars($successMessage); ?></p>
        <?php elseif (isset($errorMessage)): ?>
            <p class="error"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>
        <form action="index.php" method="POST">
            <label for="enrollment_code">Enter Enrollment Code:</label><br>
            <input type="text" name="enrollment_code" id="enrollment_code" required>
            <button type="submit">Enroll</button>
        </form>
    </section>

    <section id="class-list">
        <h2>Your Classes</h2>
        <?php if (!empty($userClasses)): ?>
            <ul>
                <?php foreach ($userClasses as $class): ?>
                    <li>
                        <a href="class.php?class_id=<?php echo urlencode($class['class_id']); ?>">
                            <?php echo htmlspecialchars($class['class_name']); ?>
                        </a>
                        <span class="enrollment-code">(<?php echo htmlspecialchars($class['enrollment_code']); ?>)</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>You are not enrolled in any classes yet.</p>
        <?php endif; ?>
    </section>

    <!-- Additional settings or elements can be added here -->
    <section id="additional">
        <h2>Additional Settings</h2>
        <p>
            <a href="assignments.php">View Assignments</a> | 
            <a href="discussion.php">Discussion Forum</a>
        </p>
    </section>

    <script type="module" src="main.js"></script>
</body>
</html>
