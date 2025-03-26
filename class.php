<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
    <title><?php echo htmlspecialchars($currentClass['class_name']); ?> - Writepathic</title>
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-3Bf5rLdpK6E+woua6Ok+X2BxF/MRk9AwFcic3wou0vWqL5y6e/j5G+tmC+qTz9AYyAcxOUi+3DbGe1bEk7x1xw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- ScrollReveal CDN -->
    <script src="https://unpkg.com/scrollreveal"></script>
    <style>
      /* Style for assignment button */
      .assignment-btn {
          display: inline-block;
          padding: 8px 12px;
          background-color: var(--success-color);
          color: #fff;
          border-radius: 5px;
          text-decoration: none;
          transition: background var(--transition-speed);
          margin-top: 10px;
      }
      .assignment-btn:hover {
          background-color: #218838;
      }
      /* Style for file links */
      .file-link {
          display: inline-block;
          margin: 5px 0;
          padding: 5px 8px;
          background: #e9ecef;
          border-radius: 4px;
          text-decoration: none;
          color: #333;
      }
    </style>
</head>
<body>
    <header>
        <h1><?php echo htmlspecialchars($currentClass['class_name']); ?></h1>
        <p>Enrollment Code: <strong><?php echo htmlspecialchars($currentClass['enrollment_code']); ?></strong></p>
        <nav>
            <a href="#attendance"><i class="fa-solid fa-user-check"></i> Attendance</a> | 
            <a href="leave_class.php?class_id=<?php echo urlencode($currentClass['class_id']); ?>" onclick="return confirm('Are you sure you want to leave this class?');">
                <i class="fa-solid fa-right-from-bracket"></i> Leave Class
            </a>
        </nav>
    </header>

    <main>
        <!-- Section: Class Content Posts -->
        <section id="class-content">
            <h2>Class Announcements & Content</h2>
            <?php if (!empty($classPosts)): ?>
                <?php foreach ($classPosts as $post): ?>
                    <article class="post-card" data-sr>
                        <header>
                            <!-- Display the actual post title -->
                            <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                            <small>Posted on <?php echo date("F j, Y, g:i a", strtotime($post['created_at'])); ?></small>
                        </header>
                        <div class="post-content">
                            <?php
                            // Loop through each content element in the post.
                            if (isset($post['content']) && is_array($post['content'])) {
                                foreach ($post['content'] as $element) {
                                    switch ($element['type']) {
                                        case 'text':
                                            if (!empty($element['text'])) {
                                                echo "<p>" . nl2br(htmlspecialchars($element['text'])) . "</p>";
                                            }
                                            break;
                                        case 'link':
                                            if (!empty($element['link'])) {
                                                echo "<p><a href='" . htmlspecialchars($element['link']) . "' target='_blank'><i class='fa-solid fa-link'></i> " . htmlspecialchars($element['link']) . "</a></p>";
                                            }
                                            break;
                                        case 'image':
                                            if (isset($element['file']) && !empty($element['file']['file_path'])) {
                                                // Display image thumbnail (or icon) and file name.
                                                echo "<p><i class='fa-solid fa-image'></i> <a class='file-link' href='" . htmlspecialchars($element['file']['file_path']) . "' target='_blank'>" . htmlspecialchars($element['file']['file_name']) . "</a></p>";
                                            }
                                            break;
                                        case 'file':
                                            if (isset($element['file']) && !empty($element['file']['file_path'])) {
                                                echo "<p><i class='fa-solid fa-file'></i> <a class='file-link' href='" . htmlspecialchars($element['file']['file_path']) . "' target='_blank'>" . htmlspecialchars($element['file']['file_name']) . "</a></p>";
                                            }
                                            break;
                                        case 'video':
                                            if (isset($element['file']) && !empty($element['file']['file_path'])) {
                                                echo "<p><i class='fa-solid fa-video'></i> <a class='file-link' href='" . htmlspecialchars($element['file']['file_path']) . "' target='_blank'>" . htmlspecialchars($element['file']['file_name']) . "</a></p>";
                                            } else {
                                                echo "<p><i class='fa-solid fa-video'></i> Video element (no file attached)</p>";
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
                                                echo "<p><i class='fa-solid fa-book'></i> Assignment: " . htmlspecialchars($element['assignment_title']) . "</p>";
                                                echo "<a class='assignment-btn' href='assignment.php?assignment_id=" . urlencode($assignmentId) . "'><i class='fa-solid fa-file-arrow-down'></i> Go to Assignment</a>";
                                            }
                                            break;
                                        default:
                                            // For unknown types, do nothing.
                                            break;
                                    }
                                }
                            } else {
                                echo "<p>No content available.</p>";
                            }
                            ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No posts have been added yet.</p>
            <?php endif; ?>
        </section>

        <!-- Section: Attendance (placeholder) -->
        <section id="attendance">
            <h2>Attendance</h2>
            <p><a href="#attendance">Click here to mark or view your attendance (feature coming soon)</a></p>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Writepathic. All rights reserved.</p>
    </footer>

    <!-- Initialize ScrollReveal animations -->
    <script>
      ScrollReveal().reveal('[data-sr]', { 
        distance: '50px',
        duration: 800,
        easing: 'ease-out',
        origin: 'bottom',
        interval: 100 
      });
    </script>
</body>
</html>
