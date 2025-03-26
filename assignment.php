<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
            if ($element['type'] === 'assignment' && isset($element['assignment_id']) && $element['assignment_id'] === $assignment_id) {
                // Found the assignment element.
                $foundAssignment = [
                    "assignment_id" => $assignment_id,
                    "assignment_title" => $element['assignment_title'],
                    "assignment_description" => $element['assignment_description'],
                    "deadline" => $element['deadline'],
                    // Optionally, store additional details:
                    "post_id" => $post['post_id'],
                    "class_id" => $post['class_id'],
                    "instructions" => $element['text'] // any additional instructions
                ];
                break 2;
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
    <title><?php echo htmlspecialchars($foundAssignment['assignment_title']); ?> - Assignment Submission</title>
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-3Bf5rLdpK6E+woua6Ok+X2BxF/MRk9AwFcic3wou0vWqL5y6e/j5G+tmC+qTz9AYyAcxOUi+3DbGe1bEk7x1xw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <header>
        <h1><?php echo htmlspecialchars($foundAssignment['assignment_title']); ?></h1>
    </header>
    <main>
        <!-- Assignment Details / Content -->
        <section id="assignment-details">
            <h2>Assignment Details</h2>
            <p><?php echo nl2br(htmlspecialchars($foundAssignment['assignment_description'])); ?></p>
            <?php if (!empty($foundAssignment['instructions'])): ?>
                <p><strong>Instructions:</strong> <?php echo nl2br(htmlspecialchars($foundAssignment['instructions'])); ?></p>
            <?php endif; ?>
            <?php if (!empty($foundAssignment['deadline'])): ?>
                <p><strong>Deadline:</strong> <?php echo date("F j, Y, g:i a", strtotime($foundAssignment['deadline'])); ?></p>
            <?php endif; ?>
        </section>
        
        <!-- Submission Form -->
        <section id="submission-form">
            <h2>Submit Your Assignment</h2>
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <?php foreach ($errors as $error): ?>
                        <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form action="assignment.php?assignment_id=<?php echo urlencode($assignment_id); ?>" method="POST" enctype="multipart/form-data">
                <div>
                    <label for="text_response">Your Response:</label><br>
                    <textarea name="text_response" id="text_response" rows="8" cols="50" placeholder="Type your response here..."></textarea>
                </div>
                <div>
                    <label for="file_response">Upload File (max 25MB):</label><br>
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $maxFileSize; ?>">
                    <input type="file" name="file_response" id="file_response" accept="*/*">
                </div>
                <div class="form-buttons">
                    <button type="button" onclick="window.location.href='class.php?class_id=<?php echo urlencode($foundAssignment['class_id']); ?>';">Cancel</button>
                    <button type="submit">Submit</button>
                </div>
            </form>
        </section>
    </main>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Writepathic. All rights reserved.</p>
    </footer>
</body>
</html>
