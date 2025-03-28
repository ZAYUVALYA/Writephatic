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

// Validate that assignment_id is provided
if (!isset($_GET['assignment_id'])) {
    die("No assignment specified.");
}

$assignment_id = trim($_GET['assignment_id']);

// Define paths to JSON data files and uploads directory
$postsFile = __DIR__ . '/data/posts.json';
$submissionsFile = __DIR__ . '/data/submissions.json';
$uploadsDir = __DIR__ . '/uploads/assignments/';

// Helper function: load JSON data from file
function loadJson($file) {
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

// Search through posts.json for an assignment element with the matching assignment_id.
$posts = loadJson($postsFile);
$foundAssignment = null;

foreach ($posts as $post) {
    if (isset($post['content']) && is_array($post['content'])) {
        foreach ($post['content'] as $element) {
            if ($element['type'] === 'assignment') {
                // Use assignment_id if available, otherwise fall back to post_id
                $elementAssignmentId = $element['assignment_id'] ?? $post['post_id'];
                
                if ($elementAssignmentId === $assignment_id) {
                    // Found the assignment element.
                    $foundAssignment = [
                        "assignment_id" => $elementAssignmentId,
                        "assignment_title" => $element['assignment_title'] ?? 'Untitled Assignment',
                        "assignment_description" => $element['assignment_description'] ?? '',
                        "deadline" => $element['deadline'] ?? '',
                        "post_id" => $post['post_id'],
                        "class_id" => $post['class_id'],
                        "instructions" => $element['text'] ?? '' // any additional instructions
                    ];
                    break 2;
                }
            }
        }
    }
}

if (!$foundAssignment) {
    die("Assignment not found.");
}

// Define the maximum file size (25 MB)
$maxFileSize = 25 * 1024 * 1024; // 25 MB in bytes

// Process form submission for assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $textResponse = trim($_POST['text_response'] ?? '');
    $errors = [];

    // Process file upload if provided
    $uploadedFileData = null;
    if (isset($_FILES['file_response']) && $_FILES['file_response']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileError = $_FILES['file_response']['error'];
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading file.";
        } else {
            $fileSize = $_FILES['file_response']['size'];
            if ($fileSize > $maxFileSize) {
                $errors[] = "File size exceeds the maximum allowed limit of 25 MB.";
            } else {
                $originalFileName = basename($_FILES['file_response']['name']);
                $uniqueFileName = uniqid("assignment_", true) . "_" . $originalFileName;
                $destination = $uploadsDir . $uniqueFileName;
                
                // Ensure uploads directory exists
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                
                if (move_uploaded_file($_FILES['file_response']['tmp_name'], $destination)) {
                    $uploadedFileData = [
                        "file_name" => $originalFileName,
                        "file_path" => "uploads/assignments/" . $uniqueFileName,
                        "file_size" => $fileSize
                    ];
                } else {
                    $errors[] = "Failed to move uploaded file.";
                }
            }
        }
    }

    // If no errors, save submission data
    if (empty($errors)) {
        $submissions = loadJson($submissionsFile);
        // Generate a unique submission ID
        $submission_id = 's' . (count($submissions) + 6001);
        $now = date('c');

        $newSubmission = [
            "submission_id"   => $submission_id,
            "assignment_id"   => $assignment_id,
            "user_id"         => $_SESSION['user_id'],
            "submission_date" => $now,
            "content"         => [
                "text_response" => $textResponse,
                "rich_text"     => "" // For integration with rich text editor if needed
            ],
            "uploaded_files"  => $uploadedFileData ? [$uploadedFileData] : [],
            "status"          => "submitted",
            "grade"           => null,
            "feedback"        => null
        ];

        $submissions[] = $newSubmission;
        if (file_put_contents($submissionsFile, json_encode($submissions, JSON_PRETTY_PRINT))) {
            header("Location: class.php?class_id=" . urlencode($foundAssignment['class_id']));
            exit;
        } else {
            $errors[] = "Failed to save submission.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($foundAssignment['assignment_title']); ?> - Assignment Submission</title>
    
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        input[type="text"],
        textarea,
        select,
        input[type="datetime-local"],
        input[type="file"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--background-color);
            color: var(--text-color);
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
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
        
        .submit-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .error {
            color: #dc3545;
            margin-bottom: 1rem;
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
                Assignment Submission
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="class.php?class_id=<?php echo urlencode($foundAssignment['class_id']); ?>">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back to Class
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container my-4">
        <div class="form-container">
            <h1><?php echo htmlspecialchars($foundAssignment['assignment_title']); ?></h1>
            
            <!-- Assignment Details -->
            <section id="assignment-details" class="mb-4">
                <h2 class="fw-bold mb-3">Assignment Details</h2>
                <div class="mb-3">
                    <label class="form-label">Description:</label>
                    <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($foundAssignment['assignment_description'])); ?></p>
                </div>
                <?php if (!empty($foundAssignment['instructions'])): ?>
                    <div class="mb-3">
                        <label class="form-label">Instructions:</label>
                        <p class="form-control-plaintext"><?php echo nl2br(htmlspecialchars($foundAssignment['instructions'])); ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($foundAssignment['deadline'])): ?>
                    <div class="mb-3">
                        <label class="form-label">Deadline:</label>
                        <p class="form-control-plaintext"><?php echo date("F j, Y, g:i a", strtotime($foundAssignment['deadline'])); ?></p>
                    </div>
                <?php endif; ?>
            </section>
            
            <!-- Submission Form -->
            <section id="submission-form">
                <h2 class="fw-bold mb-4">Submit Your Assignment</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger error" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p class="mb-1"><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form action="assignment.php?assignment_id=<?php echo urlencode($assignment_id); ?>" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="text_response" class="form-label">Your Response:</label>
                        <textarea class="form-control" id="text_response" name="text_response" rows="8" placeholder="Type your response here..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="file_response" class="form-label">Upload File (max 25MB):</label>
                        <input class="form-control" type="file" name="file_response" id="file_response" accept="*/*">
                    </div>
                    
                    <div class="actions">
                        <button type="button" class="cancel-btn" onclick="window.location.href='class.php?class_id=<?php echo urlencode($foundAssignment['class_id']); ?>'">Cancel</button>
                        <button type="submit" class="submit-btn">Submit</button>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <footer class="py-3 my-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> Writepathic. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>