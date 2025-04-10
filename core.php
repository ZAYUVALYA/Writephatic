<?php
session_start();
header("Content-Type: application/json");

// ---------------------------
// Helper Functions
// ---------------------------

/**
 * Load JSON data from a file.
 * @param string $file - The file path.
 * @return array - Decoded JSON data or empty array.
 */
function loadJson($file) {
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

/**
 * Save JSON data to a file.
 * @param string $file - The file path.
 * @param mixed $data - Data to encode as JSON.
 * @return bool - True on success, false on failure.
 */
function saveJson($file, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT);
    return file_put_contents($file, $json) !== false;
}

/**
 * Generate a unique ID based on a prefix and a numeric value.
 * @param string $prefix - The prefix for the ID.
 * @param array $dataArray - The array in which to count items.
 * @param int $base - The starting number.
 * @return string - The generated unique ID.
 */
function generateId($prefix, $dataArray, $base = 1000) {
    return $prefix . (count($dataArray) + $base);
}

/**
 * Send a JSON response and exit.
 * @param mixed $data - Data to send as JSON.
 */
function sendResponse($data) {
    echo json_encode($data);
    exit;
}

/**
 * Check if the user is logged in.
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        sendResponse(["success" => false, "message" => "User not authenticated."]);
    }
}

/**
 * Check if the user has the required role.
 * @param string $role - Expected role: "student" or "instructor".
 */
function requireRole($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        sendResponse(["success" => false, "message" => "Insufficient privileges."]);
    }
}

// Define paths to JSON files
$dataDir = __DIR__ . '/data/';
$usersFile       = $dataDir . 'users.json';
$classesFile     = $dataDir . 'classes.json';
$enrollmentsFile = $dataDir . 'enrollments.json';
$assignmentsFile = $dataDir . 'assignments.json';
$postsFile       = $dataDir . 'posts.json';
$submissionsFile = $dataDir . 'submissions.json';

// Define uploads directory for files (for assignments, posts, etc.)
$uploadsDir = __DIR__ . '/uploads/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// ---------------------------
// Main Switch for Actions
// ---------------------------

$action = $_GET['action'] ?? '';

switch ($action) {

    // ---------------------------
    // Student Actions
    // ---------------------------
    
    // Get the list of classes the student is enrolled in.
    case "getEnrolledClasses":
        requireLogin();
        requireRole("student");

        $enrollments = loadJson($enrollmentsFile);
        $classes = loadJson($classesFile);
        $userClasses = [];
        foreach ($enrollments as $enrollment) {
            if ($enrollment['user_id'] === $_SESSION['user_id'] && $enrollment['status'] === 'active') {
                // Find class details.
                foreach ($classes as $cls) {
                    if ($cls['class_id'] === $enrollment['class_id']) {
                        $userClasses[] = $cls;
                        break;
                    }
                }
            }
        }
        sendResponse(["success" => true, "classes" => $userClasses]);
        break;
    
    // Enroll a student into a class using an enrollment code.
    case "enrollInClass":
        requireLogin();
        requireRole("student");
        $postData = json_decode(file_get_contents("php://input"), true);
        $enrollmentCode = trim($postData['enrollmentCode'] ?? '');
        if (empty($enrollmentCode)) {
            sendResponse(["success" => false, "message" => "Enrollment code is required."]);
        }
        $classes = loadJson($classesFile);
        $enrollments = loadJson($enrollmentsFile);
        $foundClass = null;
        foreach ($classes as $cls) {
            if ($cls['enrollment_code'] === $enrollmentCode) {
                $foundClass = $cls;
                break;
            }
        }
        if (!$foundClass) {
            sendResponse(["success" => false, "message" => "Invalid enrollment code."]);
        }
        // Check if already enrolled.
        foreach ($enrollments as $enrollment) {
            if ($enrollment['class_id'] === $foundClass['class_id'] && $enrollment['user_id'] === $_SESSION['user_id']) {
                sendResponse(["success" => false, "message" => "Already enrolled in this class."]);
            }
        }
        // Create new enrollment.
        $newEnrollment = [
            "enrollment_id"   => generateId("e", $enrollments, 5000),
            "class_id"        => $foundClass['class_id'],
            "user_id"         => $_SESSION['user_id'],
            "enrollment_date" => date('c'),
            "status"          => "active"
        ];
        $enrollments[] = $newEnrollment;
        if (saveJson($enrollmentsFile, $enrollments)) {
            sendResponse(["success" => true, "message" => "Successfully enrolled in " . $foundClass['class_name'] . "."]);
        } else {
            sendResponse(["success" => false, "message" => "Failed to update enrollment data."]);
        }
        break;
        
    // Get content for a given class (posts and assignments).
    case "getClassContent":
        requireLogin();
        requireRole("student");
        $postData = json_decode(file_get_contents("php://input"), true);
        $class_id = trim($postData['class_id'] ?? '');
        if (empty($class_id)) {
            sendResponse(["success" => false, "message" => "Class ID is required."]);
        }
        // Load posts and assignments for this class.
        $posts = loadJson($postsFile);
        $assignments = loadJson($assignmentsFile);
        $classPosts = array_values(array_filter($posts, function($p) use ($class_id) {
            return $p['class_id'] === $class_id;
        }));
        $classAssignments = array_values(array_filter($assignments, function($a) use ($class_id) {
            return $a['class_id'] === $class_id;
        }));
        // For simplicity, return the data as JSON (the front-end can render HTML as needed).
        sendResponse([
            "success" => true,
            "posts" => $classPosts,
            "assignments" => $classAssignments
        ]);
        break;

    // Get assignment details.
    case "getAssignmentDetails":
        requireLogin();
        requireRole("student");
        $postData = json_decode(file_get_contents("php://input"), true);
        $assignment_id = trim($postData['assignment_id'] ?? '');
        if (empty($assignment_id)) {
            sendResponse(["success" => false, "message" => "Assignment ID is required."]);
        }
        $posts = loadJson($postsFile);
        $foundAssignment = null;
        foreach ($posts as $post) {
            if (isset($post['content']) && is_array($post['content'])) {
                foreach ($post['content'] as $element) {
                    if ($element['type'] === 'assignment' && isset($element['assignment_id']) && $element['assignment_id'] === $assignment_id) {
                        $foundAssignment = [
                            "assignment_id" => $assignment_id,
                            "assignment_title" => $element['assignment_title'],
                            "assignment_description" => $element['assignment_description'],
                            "deadline" => $element['deadline'],
                            "post_id" => $post['post_id'],
                            "class_id" => $post['class_id'],
                            "instructions" => $element['text']
                        ];
                        break 2;
                    }
                }
            }
        }
        if ($foundAssignment) {
            sendResponse(["success" => true, "assignment" => $foundAssignment]);
        } else {
            sendResponse(["success" => false, "message" => "Assignment not found."]);
        }
        break;

    // Submit an assignment.
    case "submitAssignment":
        requireLogin();
        requireRole("student");
        // Use multipart/form-data handling.
        $assignment_id = $_POST['assignment_id'] ?? '';
        $text_response = trim($_POST['text_response'] ?? '');
        if (empty($assignment_id)) {
            sendResponse(["success" => false, "message" => "Assignment ID is required."]);
        }
        $submissions = loadJson($submissionsFile);
        $uploadedFile = null;
        if (isset($_FILES['file_response']) && $_FILES['file_response']['error'] === UPLOAD_ERR_OK) {
            $originalName = basename($_FILES['file_response']['name']);
            $uniqueName = uniqid("assignment_", true) . "_" . $originalName;
            $destination = $uploadsDir . "assignments/" . $uniqueName;
            if (!is_dir($uploadsDir . "assignments/")) {
                mkdir($uploadsDir . "assignments/", 0755, true);
            }
            if (move_uploaded_file($_FILES['file_response']['tmp_name'], $destination)) {
                $uploadedFile = [
                    "file_name" => $originalName,
                    "file_path" => "uploads/assignments/" . $uniqueName,
                    "file_size" => $_FILES['file_response']['size']
                ];
            } else {
                sendResponse(["success" => false, "message" => "Failed to upload file."]);
            }
        }
        $newSubmission = [
            "submission_id"   => generateId("s", $submissions, 6000),
            "assignment_id"   => $assignment_id,
            "user_id"         => $_SESSION['user_id'],
            "submission_date" => date('c'),
            "content"         => [
                "text_response" => $text_response,
                "rich_text"     => ""
            ],
            "uploaded_files"  => $uploadedFile ? [$uploadedFile] : [],
            "status"          => "submitted",
            "grade"           => null,
            "feedback"        => null
        ];
        $submissions[] = $newSubmission;
        if (saveJson($submissionsFile, $submissions)) {
            sendResponse(["success" => true, "message" => "Assignment submitted successfully."]);
        } else {
            sendResponse(["success" => false, "message" => "Failed to save submission."]);
        }
        break;

    // Fetch submissions for a given assignment.
case "fetchSubmissions":
    requireLogin();
    requireRole("instructor");
    $postData = json_decode(file_get_contents("php://input"), true);
    $assignment_id = trim($postData['assignment_id'] ?? '');
    if (empty($assignment_id)) {
        sendResponse(["success" => false, "message" => "Assignment ID is required."]);
    }
    $submissions = loadJson($submissionsFile);
    $users = loadJson($usersFile);
    $result = [];
    foreach ($submissions as $submission) {
        if ($submission['assignment_id'] === $assignment_id) {
            // Lookup the student's username.
            $studentName = "Unknown";
            foreach ($users as $user) {
                if ($user['user_id'] === $submission['user_id']) {
                    $studentName = $user['username'];
                    break;
                }
            }
            // Assume each submission has one file; adjust as needed.
            $fileInfo = (isset($submission['uploaded_files'][0])) ? $submission['uploaded_files'][0] : null;
            $result[] = [
                "submission_id" => $submission['submission_id'], // Ensure this is included
                "student_name" => $studentName,
                "file_name"    => $fileInfo ? $fileInfo['file_name'] : "N/A",
                "file_url"     => $fileInfo ? $fileInfo['file_path'] : "#",
                "submission_date" => $submission['submission_date']
            ];
        }
    }
    sendResponse(["success" => true, "submissions" => $result]);
    break;

    // Grade a submission.
    case "gradeSubmission":
        requireLogin();
        requireRole("instructor");
        $submission_id = $_POST['submission_id'] ?? '';
        $grade = $_POST['grade'] ?? '';
        $feedback = $_POST['feedback'] ?? '';
        if (empty($submission_id) || empty($grade)) {
            sendResponse(["success" => false, "message" => "Submission ID and grade are required."]);
        }
        $submissions = loadJson($submissionsFile);
        foreach ($submissions as &$submission) {
            if ($submission['submission_id'] === $submission_id) {
                $submission['grade'] = $grade;
                $submission['feedback'] = $feedback;
                break;
            }
        }
        if (saveJson($submissionsFile, $submissions)) {
            sendResponse(["success" => true, "message" => "Grade saved successfully."]);
        } else {
            sendResponse(["success" => false, "message" => "Failed to save grade."]);
        }
        break;

    // ---------------------------
    // Instructor Actions
    // ---------------------------
    
    // Get classes created by the instructor.
    case "getInstructorClasses":
        requireLogin();
        requireRole("instructor");
        $classes = loadJson($classesFile);
        $instructorClasses = array_values(array_filter($classes, function($cls) {
            return $cls['instructor_id'] === $_SESSION['user_id'];
        }));
        sendResponse(["success" => true, "classes" => $instructorClasses]);
        break;
    
    // Fetch submissions for a given assignment.
    case "fetchSubmissions":
        requireLogin();
        requireRole("instructor");
        $postData = json_decode(file_get_contents("php://input"), true);
        $assignment_id = trim($postData['assignment_id'] ?? '');
        if (empty($assignment_id)) {
            sendResponse(["success" => false, "message" => "Assignment ID is required."]);
        }
        $submissions = loadJson($submissionsFile);
        $users = loadJson($usersFile);
        $result = [];
        foreach ($submissions as $submission) {
            if ($submission['assignment_id'] === $assignment_id) {
                $studentName = "Unknown";
                foreach ($users as $user) {
                    if ($user['user_id'] === $submission['user_id']) {
                        $studentName = $user['username'];
                        break;
                    }
                }
                $fileInfo = (isset($submission['uploaded_files'][0])) ? $submission['uploaded_files'][0] : null;
                $result[] = [
                    "student_name" => $studentName,
                    "file_name"    => $fileInfo ? $fileInfo['file_name'] : "N/A",
                    "file_url"     => $fileInfo ? $fileInfo['file_path'] : "#",
                    "submission_date" => $submission['submission_date'],
                    "grade"        => $submission['grade'],
                    "feedback"     => $submission['feedback']
                ];
            }
        }
        sendResponse(["success" => true, "submissions" => $result]);
        break;
        
    // Save or update a post (for new post or editing an existing one).
    case "savePost":
        requireLogin();
        requireRole("instructor");
        // Post data comes via FormData (multipart/form-data)
        // For text data, use $_POST; for files, use $_FILES.
        $class_id = $_POST['class_id'] ?? '';
        $post_title = trim($_POST['post_title'] ?? '');
        if (empty($class_id) || empty($post_title)) {
            sendResponse(["success" => false, "message" => "Missing required post data."]);
        }
        $posts = loadJson($postsFile);
        $isEditing = false;
        $edit_post_id = $_GET['edit_post_id'] ?? '';
        if (!empty($edit_post_id)) {
            $isEditing = true;
        }
        $contentElements = [];
        // We expect arrays for content_type and related fields.
        $contentTypes = $_POST['content_type'] ?? [];
        $contentTexts = $_POST['content_text'] ?? [];
        $contentLinks = $_POST['content_link'] ?? [];
        $assignmentTitles = $_POST['content_assignment_title'] ?? [];
        $assignmentDescriptions = $_POST['content_assignment_description'] ?? [];
        $contentDeadlines = $_POST['content_deadline'] ?? [];
        
        // Process each content element.
        for ($i = 0; $i < count($contentTypes); $i++) {
            $type = trim($contentTypes[$i]);
            if (empty($type)) continue;
            $element = ["type" => $type];
            switch ($type) {
                case "text":
                    $element["text"] = trim($contentTexts[$i] ?? '');
                    break;
                case "link":
                    $element["link"] = trim($contentLinks[$i] ?? '');
                    break;
                case "image":
                case "video":
                case "file":
                    // Check if file was uploaded for this element.
                    if (isset($_FILES['content_file']) && isset($_FILES['content_file']['error'][$i]) && $_FILES['content_file']['error'][$i] === UPLOAD_ERR_OK) {
                        $originalName = basename($_FILES['content_file']['name'][$i]);
                        $uniqueName = uniqid("post_", true) . "_" . $originalName;
                        $destination = $uploadsDir . $uniqueName;
                        if (move_uploaded_file($_FILES['content_file']['tmp_name'][$i], $destination)) {
                            $element["file"] = [
                                "file_name" => $originalName,
                                "file_path" => "uploads/" . $uniqueName,
                                "file_size" => $_FILES['content_file']['size'][$i]
                            ];
                        }
                    }
                    break;
                case "assignment":
                    $element["assignment_title"] = trim($assignmentTitles[$i] ?? '');
                    $element["assignment_description"] = trim($assignmentDescriptions[$i] ?? '');
                    $element["deadline"] = trim($contentDeadlines[$i] ?? '');
                    $element["text"] = trim($contentTexts[$i] ?? ''); // Additional instructions.
                    break;
                default:
                    // Unknown type; skip.
                    continue 2;
            }
            $contentElements[] = $element;
        }
        
        $now = date('c');
        if ($isEditing) {
            // Update existing post.
            $updated = false;
            foreach ($posts as &$post) {
                if ($post['post_id'] === $edit_post_id && $post['class_id'] === $class_id) {
                    $post['title'] = $post_title;
                    $post['content'] = $contentElements;
                    $post['deadline'] = $_POST['global_deadline'] ?? "";
                    $post['updated_at'] = $now;
                    $updated = true;
                    break;
                }
            }
            if (!$updated) {
                sendResponse(["success" => false, "message" => "Post not found for editing."]);
            }
        } else {
            // Create new post.
            $newPost = [
                "post_id"       => generateId("p", $posts, 4000),
                "class_id"      => $class_id,
                "instructor_id" => $_SESSION['user_id'],
                "title"         => $post_title,
                "content"       => $contentElements,
                "deadline"      => $_POST['global_deadline'] ?? "",
                "created_at"    => $now,
                "updated_at"    => $now,
                "post_type"     => "mixed" // Can be further refined if needed.
            ];
            $posts[] = $newPost;
        }
        if (saveJson($postsFile, $posts)) {
            sendResponse(["success" => true, "message" => "Post saved successfully."]);
        } else {
            sendResponse(["success" => false, "message" => "Failed to save post."]);
        }
        break;
        
    // ---------------------------
    // Default / Unknown Action
    // ---------------------------
    default:
        sendResponse(["success" => false, "message" => "Unknown action."]);
        break;
}