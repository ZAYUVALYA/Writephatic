<?php
session_start();

// Handle logout request
if (isset($_POST['logout'])) {
    // Clear all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: login.php');
    exit;
}

// Ensure only authenticated users can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

// Add headers to prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Rest of the existing code...

// Validate that class_id is provided
if (!isset($_GET['class_id'])) {
    die("No class selected.");
}

$class_id = trim($_GET['class_id']);

// Define paths to JSON data files
$classesFile     = __DIR__ . '/data/classes.json';
$postsFile       = __DIR__ . '/data/posts.json';
$assignmentsFile = __DIR__ . '/data/assignments.json';
$enrollmentsFile = __DIR__ . '/data/enrollments.json';

// Helper function: load JSON data from file
function loadJson($file) {
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

// Load class details
$classes = loadJson($classesFile);
$currentClass = null;
foreach ($classes as $class) {
    if ($class['class_id'] === $class_id) {
        $currentClass = $class;
        break;
    }
}

if (!$currentClass) {
    die("Class not found.");
}

// Check if the student is enrolled in this class
$enrollments = loadJson($enrollmentsFile);
$enrolled = false;
foreach ($enrollments as $enrollment) {
    if ($enrollment['class_id'] === $class_id && 
        $enrollment['user_id'] === $_SESSION['user_id'] && 
        $enrollment['status'] === 'active') {
        $enrolled = true;
        break;
    }
}

if (!$enrolled) {
    die("You are not enrolled in this class.");
}

// Load posts for this class
$posts = loadJson($postsFile);
$classPosts = array_values(array_filter($posts, function($post) use ($class_id) {
    return $post['class_id'] === $class_id;
}));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($currentClass['class_name']); ?> - Writepathic</title>
    
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
        
        .post-card {
            background-color: var(--secondary-color);
            border-radius: 8px;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .post-card:hover {
            transform: translateY(-5px);
        }
        
        .post-header {
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 0.5rem;
        }
        
        .post-title {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .post-date {
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .post-content {
            line-height: 1.6;
        }
        
        .text-content {
            margin-bottom: 1rem;
        }
        
        .file-link {
            display: inline-block;
            margin: 0.5rem 0;
            padding: 0.3rem 0.5rem;
            background-color: rgba(118, 171, 174, 0.1);
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .media-content {
            max-width: 100%;
            border-radius: 4px;
            margin: 0.5rem 0;
        }
        
        .video-content {
            width: 100%;
            height: auto;
        }
        
        .assignment-btn {
            display: inline-block;
            padding: 0.3rem 0.5rem;
            background-color: var(--primary-color);
            color: var(--text-color);
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .assignment-btn:hover {
            background-color: #63999C;
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
                <?php echo htmlspecialchars($currentClass['class_name']); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#attendance">
                            <i class="fa-solid fa-user-check me-1"></i> Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="leave_class.php?class_id=<?php echo urlencode($currentClass['class_id']); ?>" onclick="return confirm('Are you sure you want to leave this class?');">
                            <i class="fa-solid fa-right-from-bracket me-1"></i> Leave Class
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container my-4">
        <!-- Class Information -->
        <div class="row mb-4">
            <div class="col">
                <h2 class="display-6 fw-bold"><?php echo htmlspecialchars($currentClass['class_name']); ?></h2>
                <p class="text-muted">Enrollment Code: <strong><?php echo htmlspecialchars($currentClass['enrollment_code']); ?></strong></p>
            </div>
        </div>

        <!-- Class Announcements & Content -->
        <section id="class-content" class="mb-5">
            <h3 class="fw-bold mb-4">Class Announcements & Content</h3>
            <?php if (!empty($classPosts)): ?>
                <?php foreach ($classPosts as $post): ?>
                    <div class="post-card">
                        <div class="post-header">
                            <h4 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h4>
                            <small class="post-date"><?php echo date("F j, Y, g:i a", strtotime($post['created_at'])); ?></small>
                        </div>
                        <div class="post-content">
                            <?php
                            // Loop through each content element in the post.
                            if (isset($post['content']) && is_array($post['content'])) {
                                foreach ($post['content'] as $element) {
                                    $elementHtml = '';
                                    
                                    switch ($element['type']) {
                                        case 'text':
                                            if (!empty($element['text'])) {
                                                $elementHtml = "<p class='text-content'>" . nl2br(htmlspecialchars($element['text'])) . "</p>";
                                            }
                                            break;
                                        case 'link':
                                            if (!empty($element['link'])) {
                                                $elementHtml = "<a href='" . htmlspecialchars($element['link']) . "' target='_blank' class='file-link'><i class='fa-solid fa-link me-1'></i> " . htmlspecialchars($element['link']) . "</a>";
                                            }
                                            break;
                                        case 'image':
                                            if (isset($element['file']) && !empty($element['file']['file_path'])) {
                                                $elementHtml = "<img src='" . htmlspecialchars($element['file']['file_path']) . "' alt='" . htmlspecialchars($element['file']['file_name']) . "' class='img-fluid media-content'>";
                                            }
                                            break;
                                        case 'video':
                                            if (isset($element['file']) && !empty($element['file']['file_path'])) {
                                                $elementHtml = "<video class='video-content' controls>
                                                                    <source src='" . htmlspecialchars($element['file']['file_path']) . "' type='video/mp4'>
                                                                    Your browser does not support the video tag.
                                                                </video>";
                                            } else {
                                                $elementHtml = "<p><i class='fa-solid fa-video'></i> Video element (no file attached)</p>";
                                            }
                                            break;
                                        case 'file':
                                            if (isset($element['file']) && !empty($element['file']['file_path'])) {
                                                $elementHtml = "<a href='" . htmlspecialchars($element['file']['file_path']) . "' target='_blank' class='file-link'><i class='fa-solid fa-file me-1'></i> " . htmlspecialchars($element['file']['file_name']) . "</a>";
                                            }
                                            break;
                                        case 'assignment':
                                            // Check if the assignment element contains any non-empty info.
                                            $hasAssignmentData = (!empty($element['assignment_title']) || !empty($element['assignment_description']) || !empty($element['deadline']));
                                            if ($hasAssignmentData) {
                                                // Use the assignment_id if available; otherwise, fall back to the post id.
                                                $assignmentId = isset($element['assignment_id']) && !empty($element['assignment_id']) 
                                                    ? $element['assignment_id'] 
                                                    : $post['post_id'];
                                                $elementHtml = "<p><i class='fa-solid fa-book me-1'></i> Assignment: " . htmlspecialchars($element['assignment_title']) . "</p>";
                                                $elementHtml .= "<a href='assignment.php?assignment_id=" . urlencode($assignmentId) . "' class='assignment-btn'><i class='fa-solid fa-file-arrow-down me-1'></i> Go to Assignment</a>";
                                            }
                                            break;
                                        default:
                                            // For unknown types, do nothing.
                                            break;
                                    }
                                    
                                    if (!empty($elementHtml)) {
                                        echo "<div class='post-element'>" . $elementHtml . "</div>";
                                    }
                                }
                            } else {
                                echo "<p class='text-content'>No content available.</p>";
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-light" role="alert">
                    No posts have been added yet.
                </div>
            <?php endif; ?>
        </section>

        <!-- Attendance Section -->
        <section id="attendance" class="mb-5">
            <h3 class="fw-bold mb-4">Attendance</h3>
            <p><a href="#attendance" class="link-light">Click here to mark or view your attendance (feature coming soon)</a></p>
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
            ScrollReveal().reveal('.post-card', { 
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