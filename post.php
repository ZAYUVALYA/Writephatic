<?php
session_start();

// Ensure only instructors can access this page.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    header('Location: login.php');
    exit;
}

// Verify required GET parameters: class_id is always needed.
if (!isset($_GET['class_id'])) {
    die("Class not specified.");
}
$class_id = trim($_GET['class_id']);

// Determine mode: edit or new.
$isEditing = false;
$edit_post_id = isset($_GET['edit_post_id']) ? trim($_GET['edit_post_id']) : null;

// Define paths.
$postsFile = __DIR__ . '/data/posts.json';
$uploadsDir = __DIR__ . '/uploads/'; // You might further separate images, videos, etc.
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Helper functions.
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

// Initialize variables.
$postTitle = "";
$contentElements = []; // Array of content elements.
$deadline = ""; // Global deadline time (if set for an assignment post)

// If editing, load the post data.
if ($isEditing && $edit_post_id) {
    $allPosts = loadJson($postsFile);
    foreach ($allPosts as $post) {
        if ($post['post_id'] === $edit_post_id && $post['class_id'] === $class_id) {
            $postTitle = $post['title'];
            $contentElements = $post['content']; // Expected to be an array of content elements.
            // Optionally, if deadline is stored separately (for assignment posts) store it:
            $deadline = isset($post['deadline']) ? $post['deadline'] : "";
            $isEditing = true;
            break;
        }
    }
}

// Define maximum number of content elements allowed.
$maxElements = 10;

// Process form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $postTitle = trim($_POST['post_title'] ?? '');
    if (empty($postTitle)) {
        $errors[] = "Post title is required.";
    }
    
    // Initialize an array to hold content elements.
    $newContent = [];
    
    // Process each content element submitted. The form sends arrays:
    // content_type[], content_text[], content_link[], content_assignment_title[], content_assignment_description[], content_deadline[]
    if (isset($_POST['content_type']) && is_array($_POST['content_type'])) {
        for ($i = 0; $i < count($_POST['content_type']); $i++) {
            if ($i >= $maxElements) break; // Limit to maximum allowed.
            
            $type = trim($_POST['content_type'][$i]);
            if (empty($type)) continue; // Skip if no type selected.
            
            $element = ['type' => $type];
            
            // Process according to type.
            switch ($type) {
                case 'text':
                    $text = trim($_POST['content_text'][$i] ?? '');
                    $element['text'] = $text;
                    break;
                case 'link':
                    $link = trim($_POST['content_link'][$i] ?? '');
                    $element['link'] = $link;
                    break;
                case 'image':
                case 'video':
                case 'file':
                    // Process file upload for these types.
                    if (isset($_FILES['content_file']) && isset($_FILES['content_file']['error'][$i])) {
                        $fileError = $_FILES['content_file']['error'][$i];
                        if ($fileError === UPLOAD_ERR_OK) {
                            $fileSize = $_FILES['content_file']['size'][$i];
                            // (Optional) Add file size limit for each file type if needed.
                            $originalFileName = basename($_FILES['content_file']['name'][$i]);
                            $uniqueFileName = uniqid("post_", true) . "_" . $originalFileName;
                            $destination = $uploadsDir . $uniqueFileName;
                            if (move_uploaded_file($_FILES['content_file']['tmp_name'][$i], $destination)) {
                                $element['file'] = [
                                    'file_name' => $originalFileName,
                                    'file_path' => "uploads/" . $uniqueFileName,
                                    'file_size' => $fileSize
                                ];
                            } else {
                                $errors[] = "Failed to upload file for element " . ($i + 1);
                            }
                        } else {
                            $errors[] = "Error uploading file for element " . ($i + 1);
                        }
                    }
                    break;
                case 'assignment':
                    // For assignment, include text (if any), plus extra fields.
                    $assignmentText = trim($_POST['content_text'][$i] ?? '');
                    $assignmentTitle = trim($_POST['content_assignment_title'][$i] ?? '');
                    $assignmentDescription = trim($_POST['content_assignment_description'][$i] ?? '');
                    $assignmentDeadline = trim($_POST['content_deadline'][$i] ?? '');
                    $element['text'] = $assignmentText;
                    $element['assignment_title'] = $assignmentTitle;
                    $element['assignment_description'] = $assignmentDescription;
                    $element['deadline'] = $assignmentDeadline;
                    break;
                default:
                    $errors[] = "Unknown content type for element " . ($i + 1);
                    break;
            }
            $newContent[] = $element;
        }
    }
    
    // Limit to a maximum of 10 content elements.
    if (count($newContent) > $maxElements) {
        $errors[] = "You can add a maximum of $maxElements content elements.";
    }
    
    // If there are no errors, update or add the post.
    if (empty($errors)) {
        $allPosts = loadJson($postsFile);
        $now = date('c');
        
        if ($isEditing) {
            // Update existing post.
            foreach ($allPosts as &$post) {
                if ($post['post_id'] === $edit_post_id && $post['class_id'] === $class_id) {
                    $post['title'] = $postTitle;
                    $post['content'] = $newContent;
                    // For assignment posts, allow setting a deadline.
                    $post['deadline'] = $_POST['global_deadline'] ?? "";
                    $post['updated_at'] = $now;
                    break;
                }
            }
        } else {
            // Create new post.
            $newPost = [
                'post_id'       => 'p' . (count($allPosts) + 4001),
                'class_id'      => $class_id,
                'instructor_id' => $_SESSION['user_id'],
                'title'         => $postTitle,
                'content'       => $newContent,
                'deadline'      => $_POST['global_deadline'] ?? "",
                'created_at'    => $now,
                'updated_at'    => $now,
                'post_type'     => 'mixed' // Could be determined from the elements; for now, use 'mixed'
            ];
            $allPosts[] = $newPost;
        }
        saveJson($postsFile, $allPosts);
        // Redirect back to the dashboard (or class dashboard) once posting is done.
        header("Location: dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isEditing ? "Edit Post" : "New Post"; ?> - Writepathic</title>
    <link rel="stylesheet" href="style.css">
    <!-- Font Awesome (latest version) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-3Bf5rLdpK6E+woua6Ok+X2BxF/MRk9AwFcic3wou0vWqL5y6e/j5G+tmC+qTz9AYyAcxOUi+3DbGe1bEk7x1xw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- ScrollReveal -->
<script src="https://unpkg.com/scrollreveal"></script>

</head>
<body>
    <header>
        <h1><?php echo $isEditing ? "Edit Post" : "Create New Post"; ?></h1>
    </header>
    <?php
    if (!empty($errors)) {
        echo '<div class="error-messages">';
        foreach ($errors as $err) {
            echo '<p style="color:red;">' . htmlspecialchars($err) . '</p>';
        }
        echo '</div>';
    }
    ?>
    <form action="post.php?class_id=<?php echo urlencode($class_id); ?><?php echo $isEditing ? '&edit_post_id=' . urlencode($edit_post_id) : ''; ?>" method="POST" enctype="multipart/form-data">
        <div>
            <label for="post_title">Post Title:</label><br>
            <input type="text" id="post_title" name="post_title" value="<?php echo htmlspecialchars($postTitle); ?>" required>
        </div>
        <div id="content-elements-container">
            <!-- Existing content elements if editing -->
            <?php 
            if (!empty($contentElements)) {
                foreach ($contentElements as $index => $element) {
                    ?>
                    <div class="content-element" data-index="<?php echo $index; ?>">
                        <label>Content Type:</label>
                        <select name="content_type[]" onchange="toggleContentFields(this, <?php echo $index; ?>)">
                            <?php
                            $types = ['text', 'image', 'video', 'file', 'link', 'assignment'];
                            foreach ($types as $type) {
                                $selected = ($element['type'] === $type) ? "selected" : "";
                                echo "<option value=\"$type\" $selected>" . ucfirst($type) . "</option>";
                            }
                            ?>
                        </select>
                        <div class="fields-container" id="fields-<?php echo $index; ?>">
                            <?php
                            // Show fields based on the loaded type.
                            switch ($element['type']) {
                                case 'text':
                                    echo '<textarea name="content_text[]" placeholder="Enter text...">' . htmlspecialchars($element['text'] ?? '') . '</textarea>';
                                    break;
                                case 'link':
                                    echo '<input type="url" name="content_link[]" placeholder="Enter URL..." value="' . htmlspecialchars($element['link'] ?? '') . '">';
                                    break;
                                case 'image':
                                case 'video':
                                case 'file':
                                    echo '<input type="file" name="content_file[]" accept="*/*">';
                                    echo '<p>Existing file: ' . (isset($element['file']['file_name']) ? htmlspecialchars($element['file']['file_name']) : 'None') . '</p>';
                                    break;
                                case 'assignment':
                                    echo '<input type="text" name="content_assignment_title[]" placeholder="Assignment Title" value="' . htmlspecialchars($element['assignment_title'] ?? '') . '"><br>';
                                    echo '<textarea name="content_assignment_description[]" placeholder="Assignment Description...">' . htmlspecialchars($element['assignment_description'] ?? '') . '</textarea><br>';
                                    echo '<input type="datetime-local" name="content_deadline[]" value="' . htmlspecialchars($element['deadline'] ?? '') . '">';
                                    // Also provide an optional text area.
                                    echo '<textarea name="content_text[]" placeholder="Additional instructions (optional)">' . htmlspecialchars($element['text'] ?? '') . '</textarea>';
                                    break;
                            }
                            ?>
                        </div>
                        <span class="remove-btn" onclick="removeElement(this)">Remove</span>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <button type="button" onclick="addContentElement()">Add Content Element</button>
        <br><br>
        <!-- Global deadline for the post (if any assignment requires a deadline) -->
        <label for="global_deadline">Global Deadline (if applicable):</label>
        <input type="datetime-local" id="global_deadline" name="global_deadline" value="<?php echo htmlspecialchars($deadline); ?>">
        <br><br>
        <button type="button" onclick="window.location.href='dashboard.php'">Cancel</button>
        <button type="submit">Post</button>
    </form>

    <script type="module" src="main.js"></script>
    <script>
        // JavaScript functions to add or remove content elements dynamically.
        let maxElements = <?php echo $maxElements; ?>;
        let currentElements = document.querySelectorAll('.content-element').length;
        
        function addContentElement() {
            if (currentElements >= maxElements) {
                alert("Maximum of " + maxElements + " content elements allowed.");
                return;
            }
            let container = document.getElementById('content-elements-container');
            let index = currentElements;
            let div = document.createElement('div');
            div.className = 'content-element';
            div.setAttribute('data-index', index);
            div.innerHTML = `
                <label>Content Type:</label>
                <select name="content_type[]" onchange="toggleContentFields(this, ${index})">
                    <option value="">--Select Type--</option>
                    <option value="text">Text</option>
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                    <option value="file">File</option>
                    <option value="link">Link</option>
                    <option value="assignment">Assignment</option>
                </select>
                <div class="fields-container" id="fields-${index}"></div>
                <span class="remove-btn" onclick="removeElement(this)">Remove</span>
            `;
            container.appendChild(div);
            currentElements++;
        }
        
        function removeElement(el) {
            let parent = el.parentNode;
            parent.parentNode.removeChild(parent);
            currentElements--;
        }
        
        // Toggle display of additional fields based on selected type.
        function toggleContentFields(selectElem, index) {
            let type = selectElem.value;
            let container = document.getElementById('fields-' + index);
            let html = '';
            switch (type) {
                case 'text':
                    html = '<textarea name="content_text[]" placeholder="Enter text..."></textarea>';
                    break;
                case 'link':
                    html = '<input type="url" name="content_link[]" placeholder="Enter URL...">';
                    break;
                case 'image':
                case 'video':
                case 'file':
                    html = '<input type="file" name="content_file[]" accept="*/*">';
                    break;
                case 'assignment':
                    html = '<input type="text" name="content_assignment_title[]" placeholder="Assignment Title"><br>';
                    html += '<textarea name="content_assignment_description[]" placeholder="Assignment Description..."></textarea><br>';
                    html += '<input type="datetime-local" name="content_deadline[]"><br>';
                    html += '<textarea name="content_text[]" placeholder="Additional instructions (optional)"></textarea>';
                    break;
                default:
                    html = '';
            }
            container.innerHTML = html;
        }
    </script>
</body>
</html>
