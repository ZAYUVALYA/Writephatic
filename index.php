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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Writepathic</title>
    
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
        }
        
        body {
            background-color: var(--background-color);
            color: var(--text-color);
        }
        
        .navbar {
            background-color: var(--secondary-color);
            padding: 0.5rem 1rem;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .nav-link {
            color: var(--primary-color) !important;
        }
        
        .nav-link:hover {
            color: var(--text-color) !important;
        }
        
        .form-container {
            background-color: var(--secondary-color);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .class-card {
            background-color: var(--secondary-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .class-card:hover {
            transform: translateY(-5px);
        }
        
        .class-name {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .enrollment-code {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .success, .error {
            font-size: 0.9rem;
            padding: 0.5rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .success {
            background-color: rgba(38, 166, 91, 0.1);
            color: #28a745;
        }
        
        .error {
            background-color: rgba(212, 63, 53, 0.1);
            color: #dc3545;
        }
        
        footer {
            background-color: var(--secondary-color);
            padding: 1rem;
            margin-top: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fa-solid fa-graduation-cap me-2"></i>
                Writepathic
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container my-4">
        <!-- Welcome Section -->
        <section id="welcome" class="mb-5">
            <h1 class="display-6 fw-bold">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        </section>

        <!-- Enrollment Section -->
        <section id="enrollment" class="mb-5">
            <h2 class="fw-bold mb-4">Enroll in a Class</h2>
            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success success" role="alert">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php elseif (isset($errorMessage)): ?>
                <div class="alert alert-danger error" role="alert">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>
            <form action="index.php" method="POST" class="form-container">
                <div class="mb-3">
                    <label for="enrollment_code" class="form-label">Enter Enrollment Code:</label>
                    <input type="text" name="enrollment_code" id="enrollment_code" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Enroll</button>
            </form>
        </section>

        <!-- Class List Section -->
        <section id="class-list" class="mb-5">
            <h2 class="fw-bold mb-4">Your Classes</h2>
            <?php if (!empty($userClasses)): ?>
                <div class="row">
                    <?php foreach ($userClasses as $class): ?>
                        <div class="col-md-4">
                            <div class="class-card">
                                <h3 class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                                <p class="enrollment-code">Enrollment Code: <strong><?php echo htmlspecialchars($class['enrollment_code']); ?></strong></p>
                                <a href="class.php?class_id=<?php echo urlencode($class['class_id']); ?>" class="btn btn-primary">View Class</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light" role="alert">
                    You are not enrolled in any classes yet.
                </div>
            <?php endif; ?>
        </section>

        <!-- Additional Settings Section -->
        <section id="additional" class="mb-5">
            <h2 class="fw-bold mb-4">Additional Settings</h2>
            <p>
                <a href="assignments.php" class="link-light">View Assignments</a> | 
                <a href="discussion.php" class="link-light">Discussion Forum</a>
            </p>
        </section>
    </main>

    <footer class="py-3 my-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> Writepathic. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- ScrollReveal -->
    <script src="https://unpkg.com/scrollreveal"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            ScrollReveal().reveal('.class-card', { 
                distance: '30px',
                duration: 600,
                easing: 'ease-out',
                origin: 'bottom',
                interval: 100 
            });
        });
    </script>
</body>
</html>