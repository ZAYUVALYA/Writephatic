<?php
session_start();

// Handle logout request
if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Ensure only authenticated users can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: login.php');
    exit;
}

// Add headers to prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Define paths to JSON files
$dataDir = __DIR__ . '/data/';
$classesFile = $dataDir . 'classes.json';
$postsFile = $dataDir . 'posts.json';
$assignmentsFile = $dataDir . 'assignments.json';
$enrollmentsFile = $dataDir . 'enrollments.json';
$submissionsFile = $dataDir . 'submissions.json';
$usersFile = $dataDir . 'users.json';

// Helper functions
function loadJson($file) {
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Process new class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_class_name'])) {
    $newClassName = trim($_POST['new_class_name']);
    if (!empty($newClassName)) {
        $classes = loadJson($classesFile);
        $class_id = 'c' . (count($classes) + 101);
        $enrollment_code = strtoupper(substr($newClassName, 0, 3)) . rand(1000, 9999);
        $now = date('c');
        $newClass = [
            'class_id' => $class_id,
            'instructor_id' => $_SESSION['user_id'],
            'class_name' => $newClassName,
            'enrollment_code' => $enrollment_code,
            'created_at' => $now,
            'updated_at' => $now
        ];
        $classes[] = $newClass;
        saveJson($classesFile, $classes);
        header("Location: dashboard.php");
        exit;
    }
}

// Handle post deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post_id'])) {
    $post_id = trim($_POST['delete_post_id']);
    if (!empty($post_id)) {
        $posts = loadJson($postsFile);
        $postFound = false;
        foreach ($posts as $index => $post) {
            if ($post['post_id'] === $post_id && $post['instructor_id'] === $_SESSION['user_id']) {
                unset($posts[$index]);
                $postFound = true;
                break;
            }
        }
        if ($postFound) {
            saveJson($postsFile, array_values($posts));
            header("Location: dashboard.php");
            exit;
        } else {
            $errorMessage = "Post not found or you don't have permission to delete it.";
        }
    } else {
        $errorMessage = "Invalid post ID.";
    }
}

// Load data
$classes = loadJson($classesFile);
$posts = loadJson($postsFile);
$assignments = loadJson($assignmentsFile);
$enrollments = loadJson($enrollmentsFile);
$submissions = loadJson($submissionsFile);
$users = loadJson($usersFile);

// Filter classes created by the instructor
$instructorClasses = array_filter($classes, function($class) {
    return $class['instructor_id'] === $_SESSION['user_id'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - Writepathic</title>
    
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
            cursor: pointer;
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
        
        .class-dashboard {
            background-color: var(--secondary-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .post-card {
            background-color: rgba(49, 54, 63, 0.8);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 0.5rem;
        }
        
        .post-title {
            font-size: 1.25rem;
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
        
        .action-btn {
            padding: 0.3rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }
        
        .edit-btn {
            background-color: var(--primary-color);
            color: var(--text-color);
            border: none;
        }
        
        .delete-btn {
            background-color: #D63031;
            color: var(--text-color);
            border: none;
        }
        
        .assignment-btn {
            background-color: #00B894;
            color: var(--text-color);
            border: none;
        }
        
        .student-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .student-item {
            padding: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .student-item:last-child {
            border-bottom: none;
        }
        
        .search-box {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--secondary-color);
            color: var(--text-color);
        }
        
        .button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .primary-btn {
            background-color: var(--primary-color);
            color: var(--text-color);
        }
        
        .primary-btn:hover {
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
                <i class="fa-solid fa-chalkboard-user me-2"></i>
                Instructor Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <form action="logout.php" method="POST" class="d-inline">
                            <button type="submit" name="logout" class="nav-link btn" style="background: none; border: none; cursor: pointer;">
                                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container my-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-6 fw-bold">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
            </div>
        </div>

        <!-- Your Classes Section -->
        <section id="your-classes" class="mb-5">
            <h2 class="fw-bold mb-4">Your Classes</h2>
            
            <!-- Add New Class Form -->
            <div class="form-container mb-4">
                <form action="dashboard.php" method="POST">
                    <div class="mb-3">
                        <label for="new_class_name" class="form-label">Add New Class:</label>
                        <input type="text" name="new_class_name" id="new_class_name" class="form-control" placeholder="Enter class name" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Class</button>
                </form>
            </div>

            <?php if (!empty($instructorClasses)): ?>
                <div class="row">
                    <?php foreach ($instructorClasses as $class): ?>
                        <div class="col-md-4">
                            <div class="class-card" onclick="showClassDashboard('<?php echo $class['class_id']; ?>')">
                                <h3 class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                                <p class="enrollment-code">Enrollment Code: <strong><?php echo htmlspecialchars($class['enrollment_code']); ?></strong></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light" role="alert">
                    You haven't created any classes yet.
                </div>
            <?php endif; ?>
        </section>

        <!-- Class Dashboards -->
        <?php foreach ($instructorClasses as $class): 
            $classPosts = array_filter($posts, function($post) use ($class) {
                return $post['class_id'] === $class['class_id'];
            });
            $classEnrollments = array_filter($enrollments, function($enrollment) use ($class) {
                return $enrollment['class_id'] === $class['class_id'] && $enrollment['status'] === 'active';
            });
        ?>
            <div id="class-dashboard-<?php echo $class['class_id']; ?>" class="class-dashboard" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="fw-bold"><?php echo htmlspecialchars($class['class_name']); ?> Dashboard</h2>
                    <button class="btn btn-primary" onclick="window.location.href='post.php?class_id=<?php echo urlencode($class['class_id']); ?>'">
                        <i class="fa-solid fa-plus me-1"></i> Add Post
                    </button>
                </div>

                <!-- Class Posts -->
                <section class="mb-5">
                    <h3 class="fw-bold mb-4">Class Posts</h3>
                    <?php if (!empty($classPosts)): ?>
                        <?php foreach ($classPosts as $post): ?>
                            <div class="post-card">
                                <div class="post-header">
                                    <div>
                                        <h4 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h4>
                                        <small class="post-date"><?php echo date("F j, Y, g:i a", strtotime($post['created_at'])); ?></small>
                                    </div>
                                    <div class="post-actions">
                                        <button class="action-btn edit-btn" onclick="editPost('<?php echo $post['post_id']; ?>', '<?php echo $class['class_id']; ?>')">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                        </button>
                                        <button class="action-btn delete-btn" onclick="deletePost('<?php echo $post['post_id']; ?>')">
                                            <i class="fa-solid fa-trash me-1"></i> Delete
                                        </button>
                                    </div>
                                </div>
                                <div class="post-content">
                                    <?php
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
                                                        $elementHtml .= "<p><strong>Deadline:</strong> " . date("F j, Y, g:i a", strtotime($element['deadline'])) . "</p>";
                                                        $elementHtml .= "<p>" . nl2br(htmlspecialchars($element['text'])) . "</p>";
                                                        
                                                        // Display submission status
                                                        $submissionCount = 0;
                                                        foreach ($submissions as $submission) {
                                                            if ($submission['assignment_id'] === $assignmentId) {
                                                                $submissionCount++;
                                                            }
                                                        }
                                                        $elementHtml .= "<p><strong>Submissions:</strong> " . $submissionCount . "</p>";
                                                        $elementHtml .= "<button class='action-btn assignment-btn' onclick='viewSubmissions(\"" . $assignmentId . "\")'><i class='fa-solid fa-eye me-1'></i> View Submissions</button>";
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
                        <p class="text-content">No posts in this class.</p>
                    <?php endif; ?>
                </section>

                <!-- Enrolled Students -->
                <section>
                    <h3 class="fw-bold mb-4">Enrolled Students (<?php echo count($classEnrollments); ?>)</h3>
                    <div class="mb-3">
                        <input type="text" class="search-box" placeholder="Search students" onkeyup="searchStudents(this, '<?php echo $class['class_id']; ?>')">
                    </div>
                    <div class="student-list" id="student-list-<?php echo $class['class_id']; ?>">
                        <?php
                        foreach ($classEnrollments as $enrollment) {
                            foreach ($users as $user) {
                                if ($user['user_id'] === $enrollment['user_id']) {
                                    echo "<div class='student-item'>" . htmlspecialchars($user['username']) . "</div>";
                                    break;
                                }
                            }
                        }
                        ?>
                    </div>
                </section>
            </div>
        <?php endforeach; ?>
    </main>

    <footer class="py-3 my-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> Writepathic. All rights reserved.</p>
        </div>
    </footer>

    <!-- Modal for viewing submissions -->
    <div id="submissionModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeSubmissionModal()">&times;</span>
            <h3>Assignment Submissions</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Submitted File</th>
                        <th>Submission Date</th>
                        <th>Grade</th>
                        <th>Feedback</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="submissionTableBody">
                    <!-- Filled dynamically via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Hidden form for post deletion -->
    <form id="delete-post-form" method="POST" style="display: none;">
        <input type="hidden" name="delete_post_id" id="delete_post_id">
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    
    <script>
        function showClassDashboard(classId) {
            // Hide all dashboards
            const allDashboards = document.querySelectorAll('.class-dashboard');
            allDashboards.forEach(dashboard => {
                dashboard.style.display = 'none';
            });
            
            // Show the selected dashboard
            const dashboard = document.getElementById('class-dashboard-' + classId);
            dashboard.style.display = 'block';
            
            // Scroll to the dashboard
            dashboard.scrollIntoView({ behavior: 'smooth' });
        }

        function searchStudents(input, classId) {
            const filter = input.value.toLowerCase();
            const studentList = document.getElementById('student-list-' + classId);
            const students = studentList.querySelectorAll('.student-item');
            
            students.forEach(student => {
                const txtValue = student.textContent || student.innerText;
                student.style.display = txtValue.toLowerCase().indexOf(filter) > -1 ? 'block' : 'none';
            });
        }

        function editPost(postId, classId) {
            window.location.href = 'post.php?class_id=<?php echo urlencode($class['class_id']); ?>&edit_post_id=' + postId;
        }

        function deletePost(postId) {
            if (confirm('Are you sure you want to delete this post?')) {
                document.getElementById('delete_post_id').value = postId;
                document.getElementById('delete-post-form').submit();
            }
        }

function viewSubmissions(assignmentId) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'core.php?action=fetchSubmissions', true);
    xhr.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
    xhr.onreadystatechange = function () {
        if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.success) {
                const tbody = document.getElementById('submissionTableBody');
                tbody.innerHTML = '';
                
                response.submissions.forEach(submission => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${submission.student_name}</td>
                        <td><a href="${submission.file_url}" target="_blank">${submission.file_name}</a></td>
                        <td>${new Date(submission.submission_date).toLocaleString()}</td>
                        <td>${submission.grade !== null ? submission.grade : '-'}</td>
                        <td>${submission.feedback !== null ? submission.feedback : '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="window.location.href='response.php?assignment_id=${assignmentId}&submission_id=${submission.submission_id}'">View</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                
                document.getElementById('submissionModal').style.display = 'block';
            } else {
                displayNotification("Failed to load submissions: " + response.message, "error");
            }
        } else {
            displayNotification("Failed to load submissions: Server error", "error");
        }
    };
    xhr.send(JSON.stringify({ assignment_id: assignmentId }));
}

        function closeSubmissionModal() {
            document.getElementById('submissionModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('submissionModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>