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
$uploadsDir = __DIR__ . '/uploads/';
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
            $deadline = $post['deadline'] ?? "";
            $isEditing = true;
            break;
        }
    }
} else {
    $postTitle = "";
    $contentElements = [];
    $deadline = "";
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
    
    $newContent = [];
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
                    $element['assignment_title'] = trim($_POST['content_assignment_title'][$i] ?? '');
                    $element['assignment_description'] = trim($_POST['content_assignment_description'][$i] ?? '');
                    $element['deadline'] = trim($_POST['content_deadline'][$i] ?? '');
                    $element['text'] = trim($_POST['content_text'][$i] ?? '');
                    break;
                default:
                    $errors[] = "Unknown content type for element " . ($i + 1);
                    break;
            }
            $newContent[] = $element;
        }
    }
    
    if (count($newContent) > $maxElements) {
        $errors[] = "You can add a maximum of $maxElements content elements.";
    }
    
    if (empty($errors)) {
        $allPosts = loadJson($postsFile);
        $now = date('c');
        
        if ($isEditing) {
            $postFound = false;
            foreach ($allPosts as &$post) {
                if ($post['post_id'] === $edit_post_id && $post['class_id'] === $class_id) {
                    $post['title'] = $postTitle;
                    $post['content'] = $newContent;
                    $post['deadline'] = $_POST['global_deadline'] ?? "";
                    $post['updated_at'] = $now;
                    $postFound = true;
                    break;
                }
            }
            if (!$postFound) {
                $errors[] = "Post not found for editing.";
            }
        } else {
            $newPost = [
                'post_id' => 'p' . (count($allPosts) + 4001),
                'class_id' => $class_id,
                'instructor_id' => $_SESSION['user_id'],
                'title' => $postTitle,
                'content' => $newContent,
                'deadline' => $_POST['global_deadline'] ?? "",
                'created_at' => $now,
                'updated_at' => $now,
                'post_type' => 'mixed'
            ];
            $allPosts[] = $newPost;
        }
        saveJson($postsFile, $allPosts);
        header("Location: dashboard.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEditing ? "Edit Post" : "New Post"; ?> - Writepathic</title>
    
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
        
        .content-elements {
            margin: 1.5rem 0;
        }
        
        .content-element {
            background-color: var(--background-color);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            position: relative;
        }
        
        .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff5252;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 0.3rem 0.6rem;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .add-element-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 1rem;
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
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fa-solid fa-chalkboard-user me-2"></i>
                <?php echo $isEditing ? "Edit Post" : "Create New Post"; ?>
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
            <form action="post.php?class_id=<?php echo urlencode($class_id); ?><?php echo $isEditing ? '&edit_post_id=' . urlencode($edit_post_id) : ''; ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="post_title">Post Title:</label>
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
                                
                                <button type="button" class="remove-btn" onclick="removeElement(this)">Remove</button>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>
                
                <button type="button" onclick="addContentElement()">Add Content Element</button>
                
                <div class="form-group">
                    <label for="global_deadline">Global Deadline (if applicable):</label>
                    <input type="datetime-local" id="global_deadline" name="global_deadline" value="<?php echo htmlspecialchars($deadline); ?>">
                </div>
                
                <div class="actions">
                    <button type="button" class="cancel-btn" onclick="window.location.href='dashboard.php'">Cancel</button>
                    <button type="submit" class="submit-btn">Post</button>
                </div>
            </form>
        </div>
    </main>

    <script>
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
                <button type="button" class="remove-btn" onclick="removeElement(this)">Remove</button>
            `;
            container.appendChild(div);
            currentElements++;
        }
        
        function removeElement(el) {
            let parent = el.closest('.content-element');
            if (parent) {
                parent.remove();
                currentElements--;
            }
        }
        
        function toggleContentFields(selectElem, index) {
            let type = selectElem.value;
            let container = document.getElementById(`fields-${index}`);
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
                    html = `
                        <input type="text" name="content_assignment_title[]" placeholder="Assignment Title"><br>
                        <textarea name="content_assignment_description[]" placeholder="Assignment Description..."></textarea><br>
                        <input type="datetime-local" name="content_deadline[]"><br>
                        <textarea name="content_text[]" placeholder="Additional instructions (optional)"></textarea>
                    `;
                    break;
            }
            
            container.innerHTML = html;
        }
    </script>
</body>
</html>