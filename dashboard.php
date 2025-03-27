<?php
session_start();

// Ensure only instructors can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: login.php');
    exit;
}

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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/scrollreveal"></script>
    <style>
        /* dashboard.css - Modern Responsive Styling */

/* Base Styles & Variables */
:root {
    --primary-color: #4A90E2;
    --secondary-color: #6C5CE7;
    --success-color: #00B894;
    --danger-color: #D63031;
    --text-dark: #2D3436;
    --text-light: #F8F9FA;
    --transition-speed: 0.3s;
    --border-radius: 12px;
    --box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', system-ui, sans-serif;
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    color: var(--text-dark);
    line-height: 1.6;
}

/* Dashboard Layout */
.dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    display: grid;
    grid-gap: 2rem;
    min-height: 100vh;
}

header {
    padding: 2rem 0;
    animation: fadeInDown 0.8s;
    display: flex;
}

header h1 {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
    letter-spacing: -0.5px;
}

/* Class Cards Grid */
.class-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.class-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    cursor: pointer;
    transition: all var(--transition-speed) ease;
    transform-style: preserve-3d;
    border: 1px solid rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
}

.class-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: var(--box-shadow);
}

.class-card h3 {
    color: var(--secondary-color);
    margin-bottom: 0.5rem;
}

/* Class Dashboard Sections */
.class-dashboard {
    background: rgba(255, 255, 255, 0.95);
    border-radius: var(--border-radius);
    padding: 2rem;
    margin: 2rem 0;
    box-shadow: var(--box-shadow);
    backdrop-filter: blur(10px);
    animation: slideUp 0.6s ease;
}

/* Posts Grid */
.post-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin: 1rem 0;
    transition: transform var(--transition-speed);
    position: relative;
    overflow: hidden;
}

.post-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary-color);
}

.post-card:hover {
    transform: translateX(10px);
}

/* Student List & Search */
.student-list {
    background: rgba(255,255,255,0.9);
    border-radius: var(--border-radius);
    max-height: 400px;
    padding: 1rem;
}

.search-box {
    width: 100%;
    padding: 0.8rem;
    border: 2px solid var(--primary-color);
    border-radius: var(--border-radius);
    margin-bottom: 1rem;
    transition: all var(--transition-speed);
}

.search-box:focus {
    border-color: var(--secondary-color);
    box-shadow: 0 0 8px rgba(108,92,231,0.2);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.button {
    padding: 0.8rem 1.5rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all var(--transition-speed);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.button i {
    font-size: 1.1em;
}

.edit-btn {
    background: var(--primary-color);
    color: white;
}

.delete-btn {
    background: var(--danger-color);
    color: white;
}

.assignment-btn {
    background: var(--success-color);
    color: white;
}

/* Modal Styles */
#submissionModal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    padding: 2rem;
    border-radius: var(--border-radius);
    max-width: 90%;
    max-height: 90vh;
    overflow: auto;
    position: relative;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-container {
        padding: 1rem;
        grid-gap: 1rem;
    }

    .class-list {
        grid-template-columns: 1fr;
    }

    .class-dashboard {
        padding: 1rem;
    }

    .post-card {
        padding: 1rem;
    }

    .action-buttons {
        flex-direction: column;
    }

    .button {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    header h1 {
        font-size: 2rem;
    }

    .search-box {
        padding: 0.6rem;
    }
}

/* Animations */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ScrollReveal Adjustments */
[data-sr] {
    visibility: hidden;
}

/* Utility Classes */
.flex-center {
    display: flex;
    align-items: center;
    justify-content: center;
}

.shadow-lg {
    box-shadow: var(--box-shadow);
}

.mb-2 {
    margin-bottom: 2rem;
}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Instructor Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            <nav>
                <a href="logout.php">Logout</a>
            </nav>
        </header>

        <main>
            <section id="dashboard-info">
                <h2>Your Classes</h2>
                <form action="dashboard.php" method="POST" style="margin-bottom: 20px;">
                    <label for="new_class_name">Add New Class:</label>
                    <input type="text" name="new_class_name" id="new_class_name" required placeholder="Enter class name">
                    <button type="submit">Add Class</button>
                </form>

                <div id="class-list" class="class-list">
                    <?php if (!empty($instructorClasses)): ?>
                        <?php foreach ($instructorClasses as $class): ?>
                            <div class="class-card" onclick="toggleClassDashboard('<?php echo $class['class_id']; ?>')">
                                <h3><?php echo htmlspecialchars($class['class_name']); ?></h3>
                                <p>Enrollment Code: <strong><?php echo htmlspecialchars($class['enrollment_code']); ?></strong></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>You haven't created any classes yet.</p>
                    <?php endif; ?>
                </div>
            </section>

            <?php foreach ($instructorClasses as $class): 
                $classPosts = array_filter($posts, function($post) use ($class) {
                    return $post['class_id'] === $class['class_id'];
                });
                $classEnrollments = array_filter($enrollments, function($enrollment) use ($class) {
                    return $enrollment['class_id'] === $class['class_id'] && $enrollment['status'] === 'active';
                });
            ?>
            <div id="dashboard-<?php echo $class['class_id']; ?>" class="class-dashboard" style="display: none;">
                <div class="class-header">
                    <h2><?php echo htmlspecialchars($class['class_name']); ?> Dashboard</h2>
                    <p>Enrollment Code: <strong><?php echo htmlspecialchars($class['enrollment_code']); ?></strong></p>
                    <div class="action-buttons">
                        <button onclick="window.location.href='post.php?class_id=<?php echo urlencode($class['class_id']); ?>'">Add Post</button>
                    </div>
                </div>

                <div class="class-content">
                    <h3>Class Posts</h3>
                    <?php if (!empty($classPosts)): ?>
                        <?php foreach ($classPosts as $post): ?>
                            <div class="post-card" data-post-id="<?php echo $post['post_id']; ?>">
                                <div class="post-header">
                                    <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                                    <small>Posted on <?php echo date("F j, Y, g:i a", strtotime($post['created_at'])); ?></small>
                                    <div class="post-actions">
                                        <button class="edit-btn" onclick="editPost('<?php echo $post['post_id']; ?>', '<?php echo $class['class_id']; ?>')">Edit</button>
                                        <button class="delete-btn" onclick="deletePost('<?php echo $post['post_id']; ?>')">Delete</button>
                                    </div>
                                </div>
                                <div class="post-content">
                                    <?php
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
                                                        echo "<p><i class='fa-solid fa-book'></i> Assignment: " . htmlspecialchars($element['assignment_title']) . "</p>";
                                                        echo "<p><strong>Deadline:</strong> " . date("F j, Y, g:i a", strtotime($element['deadline'])) . "</p>";
                                                        echo "<p>" . nl2br(htmlspecialchars($element['text'])) . "</p>";
                                                        
                                                        // Display submission status
                                                        $assignmentId = $element['assignment_id'] ?? '';
                                                        if (!empty($assignmentId)) {
                                                            $submissionCount = 0;
                                                            foreach ($submissions as $submission) {
                                                                if ($submission['assignment_id'] === $assignmentId) {
                                                                    $submissionCount++;
                                                                }
                                                            }
                                                            echo "<p><strong>Submissions:</strong> " . $submissionCount . "</p>";
                                                            echo "<button class='assignment-btn' onclick='viewSubmissions(\"" . $assignmentId . "\")'>View Submissions</button>";
                                                        }
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
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No posts in this class.</p>
                    <?php endif; ?>
                </div>

                <aside class="sidebar">
                    <h3>Enrolled Students (<?php echo count($classEnrollments); ?>)</h3>
                    <input type="text" class="search-box" placeholder="Search student" onkeyup="searchStudents(this, '<?php echo $class['class_id']; ?>')">
                    <ul class="student-list" id="student-list-<?php echo $class['class_id']; ?>">
                        <?php
                        foreach ($classEnrollments as $enrollment) {
                            foreach ($users as $user) {
                                if ($user['user_id'] === $enrollment['user_id']) {
                                    echo "<li>" . htmlspecialchars($user['username']) . "</li>";
                                    break;
                                }
                            }
                        }
                        ?>
                    </ul>
                </aside>
            </div>
            <?php endforeach; ?>
        </main>

        <footer>
            <p>&copy; <?php echo date("Y"); ?> Writepathic. All rights reserved.</p>
        </footer>
    </div>

    <!-- Modal for viewing submissions -->
    <div id="submissionModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeSubmissionModal()">&times;</span>
            <h3>Assignment Submissions</h3>
            <table class="submission-table" border="1" width="100%">
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

    <script>
        function toggleClassDashboard(classId) {
            const dashboard = document.getElementById('dashboard-' + classId);
            const displayStyle = dashboard.style.display;
            dashboard.style.display = displayStyle === 'none' || displayStyle === '' ? 'block' : 'none';
        }

        function searchStudents(input, classId) {
            const filter = input.value.toLowerCase();
            const ul = document.getElementById('student-list-' + classId);
            const li = ul.getElementsByTagName('li');
            for (let i = 0; i < li.length; i++) {
                const txtValue = li[i].textContent || li[i].innerText;
                li[i].style.display = txtValue.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
            }
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
                                    <button onclick="gradeSubmission('${submission.submission_id}', ${submission.grade !== null ? submission.grade : ''})">Grade</button>
                                </td>
                            `;
                            tbody.appendChild(row);
                        });
                        
                        document.getElementById('submissionModal').style.display = 'block';
                    }
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