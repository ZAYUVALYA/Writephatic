<?php
session_start();

// Ensure only authenticated instructors can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: login.php');
    exit;
}

// Add headers to prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Validate required parameters
if (!isset($_GET['assignment_id']) || !isset($_GET['submission_id'])) {
    die("Invalid request parameters.");
}

$assignment_id = trim($_GET['assignment_id']);
$submission_id = trim($_GET['submission_id']);

// Define paths to JSON files
$submissionsFile = __DIR__ . '/data/submissions.json';
$usersFile = __DIR__ . '/data/users.json';
$assignmentsFile = __DIR__ . '/data/assignments.json';

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

// Load data
$submissions = loadJson($submissionsFile);
$users = loadJson($usersFile);
$assignments = loadJson($assignmentsFile);

// Find the submission
$selectedSubmission = null;
foreach ($submissions as $submission) {
    if ($submission['submission_id'] === $submission_id && $submission['assignment_id'] === $assignment_id) {
        $selectedSubmission = $submission;
        break;
    }
}

if (!$selectedSubmission) {
    die("Submission not found.");
}

// Find the student
$student = null;
foreach ($users as $user) {
    if ($user['user_id'] === $selectedSubmission['user_id']) {
        $student = $user;
        break;
    }
}

// Find the assignment
$assignment = null;
foreach ($assignments as $a) {
    if ($a['assignment_id'] === $assignment_id) {
        $assignment = $a;
        break;
    }
}

// Parse submission date
$submissionDate = new DateTime($selectedSubmission['submission_date']);

// Process grading and feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grade = isset($_POST['grade']) ? trim($_POST['grade']) : null;
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : null;
    
    if ($grade !== null || $feedback !== null) {
        foreach ($submissions as &$submission) {
            if ($submission['submission_id'] === $submission_id) {
                if ($grade !== null) {
                    $submission['grade'] = $grade;
                }
                if ($feedback !== null) {
                    $submission['feedback'] = $feedback;
                }
                $submission['status'] = 'graded';
                break;
            }
        }
        
        saveJson($submissionsFile, $submissions);
        header("Location: response.php?assignment_id=$assignment_id&submission_id=$submission_id");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Submission Details - Writepathic</title>
    
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
        
        .submission-header {
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 0.5rem;
        }
        
        .submission-content {
            line-height: 1.6;
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
        
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .cancel-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .grade-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .grading-section {
            margin-top: 2rem;
            display: none; /* Hidden by default */
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
                <i class="fa-solid fa-book me-2"></i>
                Assignment Submission Details
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container my-4">
        <div class="form-container">
            <h2 class="fw-bold mb-4">Submission Details</h2>
            
            <div class="submission-header">
                <h3 class="fw-bold"><?php echo htmlspecialchars($assignment['assignment_title']); ?></h3>
                <p><strong>Student:</strong> <?php echo htmlspecialchars($student['username']); ?></p>
                <p><strong>Submission Date:</strong> <?php echo $submissionDate->format('F j, Y, g:i a'); ?></p>
            </div>
            
            <div class="submission-content">
                <h4 class="fw-bold mb-3">Submission Content</h4>
                <div class="mb-3">
                    <label class="form-label">Text Response:</label>
                    <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($selectedSubmission['content']['text_response'])); ?></p>
                </div>
                
                <?php if (!empty($selectedSubmission['uploaded_files'])): ?>
                    <div class="mb-3">
                        <label class="form-label">Uploaded File:</label>
                        <a href="<?php echo htmlspecialchars($selectedSubmission['uploaded_files'][0]['file_path']); ?>" target="_blank" class="file-link">
                            <i class="fa-solid fa-file me-1"></i> <?php echo htmlspecialchars($selectedSubmission['uploaded_files'][0]['file_name']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="grading-section" id="gradingSection">
                <h4 class="fw-bold mb-3">Grade and Feedback</h4>
                <form action="response.php?assignment_id=<?php echo urlencode($assignment_id); ?>&submission_id=<?php echo urlencode($submission_id); ?>" method="POST">
                    <div class="mb-3">
                        <label for="grade" class="form-label">Grade:</label>
                        <input type="text" id="grade" name="grade" class="form-control" placeholder="Enter grade (e.g., A, B+, 90/100)">
                    </div>
                    
                    <div class="mb-3">
                        <label for="feedback" class="form-label">Feedback:</label>
                        <textarea id="feedback" name="feedback" class="form-control" rows="4" placeholder="Enter feedback for the student"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Grade and Feedback</button>
                </form>
            </div>
            
            <div class="actions">
                <button type="button" class="cancel-btn" onclick="window.location.href='dashboard.php'">Back</button>
                <button type="button" class="grade-btn" id="toggleGradeBtn">Grade Submission</button>
            </div>
        </div>
    </main>

    <footer class="py-3 my-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> Writepathic. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleGradeBtn = document.getElementById('toggleGradeBtn');
            const gradingSection = document.getElementById('gradingSection');
            
            toggleGradeBtn.addEventListener('click', function() {
                // Toggle the visibility of the grading section
                if (gradingSection.style.display === 'block') {
                    gradingSection.style.display = 'none';
                    toggleGradeBtn.textContent = 'Grade Submission';
                } else {
                    gradingSection.style.display = 'block';
                    toggleGradeBtn.textContent = 'Hide Grading';
                }
            });
        });
    </script>
</body>
</html>