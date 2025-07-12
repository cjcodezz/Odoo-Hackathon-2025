<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$user = $auth->getCurrentUser();

if (!$user) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_question'])) {
    $title = trim($_POST['title']);
    $description = sanitizeHtml($_POST['description']);
    $tags = isset($_POST['tags']) ? $_POST['tags'] : [];
    
    // Validation
    if (empty($title)) {
        $errors['title'] = 'Title is required';
    } elseif (strlen($title) < 10) {
        $errors['title'] = 'Title must be at least 10 characters';
    }
    
    if (empty($description)) {
        $errors['description'] = 'Description is required';
    } elseif (strlen(strip_tags($description)) < 20) {
        $errors['description'] = 'Description must be at least 20 characters';
    }
    
    if (empty($tags)) {
        $errors['tags'] = 'At least one tag is required';
    } elseif (count($tags) > 5) {
        $errors['tags'] = 'You can add maximum 5 tags';
    }
    
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Insert question
            $query = "INSERT INTO questions (title, description, author_id) VALUES (:title, :description, :author_id)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':author_id', $user['id']);
            $stmt->execute();
            
            $question_id = $conn->lastInsertId();
            
            // Handle tags
            foreach ($tags as $tag_name) {
                $tag_name = trim($tag_name);
                if (empty($tag_name)) continue;
                
                // Check if tag exists
                $tag_query = "SELECT id FROM tags WHERE name = :name";
                $tag_stmt = $conn->prepare($tag_query);
                $tag_stmt->bindParam(':name', $tag_name);
                $tag_stmt->execute();
                $tag = $tag_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$tag) {
                    // Create new tag
                    $create_tag_query = "INSERT INTO tags (name) VALUES (:name)";
                    $create_tag_stmt = $conn->prepare($create_tag_query);
                    $create_tag_stmt->bindParam(':name', $tag_name);
                    $create_tag_stmt->execute();
                    $tag_id = $conn->lastInsertId();
                } else {
                    $tag_id = $tag['id'];
                }
                
                // Link tag to question
                $link_query = "INSERT INTO question_tags (question_id, tag_id) VALUES (:question_id, :tag_id)";
                $link_stmt = $conn->prepare($link_query);
                $link_stmt->bindParam(':question_id', $question_id);
                $link_stmt->bindParam(':tag_id', $tag_id);
                $link_stmt->execute();
            }
            
            $conn->commit();
            header("Location: question.php?id=$question_id");
            exit;
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors['submit'] = 'Failed to create question. Please try again.';
        }
    }
}

// Get existing tags for autocomplete
$tag_query = "SELECT name FROM tags ORDER BY name";
$tag_stmt = $conn->prepare($tag_query);
$tag_stmt->execute();
$existing_tags = $tag_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ask a Question - StackIt</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/c0ozd81phoow5dms3treza0hu9de4f65v8zzj9f4wd7d1q2a/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <i class="fas fa-comments"></i>
                    <span class="logo-text">StackIt</span>
                </a>
                
                <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars"></i>
                </button>
                
                <nav class="nav">
                    <div class="nav-items" id="mobileMenu">
                        <div class="notification-wrapper">
                            <button class="notification-btn" onclick="toggleNotifications()">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count" id="notificationCount">0</span>
                            </button>
                            <div class="notification-dropdown" id="notificationDropdown">
                                <!-- Notifications will be loaded here -->
                            </div>
                        </div>
                        
                        <div class="user-menu">
                            <button class="user-btn" onclick="toggleUserMenu()">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['first_name'] ?: $user['email'], 0, 1)); ?>
                                </div>
                                <span><?php echo htmlspecialchars($user['first_name'] ?: explode('@', $user['email'])[0]); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="user-dropdown" id="userDropdown">
                                <a href="profile.php">
                                    <i class="fas fa-user-circle"></i>
                                    Profile
                                </a>
                                <a href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Sign Out
                                </a>
                            </div>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="container">
            <!-- Back Link -->
            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to questions
                </a>
            </div>

            <div style="background: var(--surface-color); border-radius: var(--radius-lg); padding: 3rem; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
                <div style="text-align: center; margin-bottom: 3rem;">
                    <h1 style="color: var(--text-primary); font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem;">Ask a Question</h1>
                    <p style="color: var(--text-secondary); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">
                        Get help from our community by asking a detailed question. The more specific you are, the better answers you'll receive.
                    </p>
                </div>
                
                <form method="POST" id="questionForm">
                    <!-- Title -->
                    <div class="form-group">
                        <label for="title">
                            <i class="fas fa-heading" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                            Question Title
                        </label>
                        <input type="text" id="title" name="title" 
                               placeholder="Be specific and imagine you're asking a question to another person"
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                               class="<?php echo isset($errors['title']) ? 'error' : ''; ?>">
                        <?php if (isset($errors['title'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $errors['title']; ?>
                            </div>
                        <?php endif; ?>
                        <div class="help-text">
                            <i class="fas fa-lightbulb"></i>
                            Summarize your problem in a one-line title that helps others understand what you're asking
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label for="description">
                            <i class="fas fa-align-left" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                            Detailed Description
                        </label>
                        <textarea id="description" name="description" 
                                  placeholder="Provide all the information someone would need to answer your question. Include what you've tried, what you expected to happen, and what actually happened."
                                  class="<?php echo isset($errors['description']) ? 'error' : ''; ?>"
                                  style="min-height: 300px;"><?php echo isset($_POST['description']) ? $_POST['description'] : ''; ?></textarea>
                        <?php if (isset($errors['description'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $errors['description']; ?>
                            </div>
                        <?php endif; ?>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i>
                            Provide enough detail so others can understand and answer your question. Use the rich text editor to format your content.
                        </div>
                    </div>
                    <script>
    tinymce.init({
        selector: 'textarea#description',
        height: 400,
        plugins: 'image link media table code lists advlist fullscreen preview',
        toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | link image media | code fullscreen preview',
        menubar: false,
        branding: false,
        automatic_uploads: true,
        images_upload_url: 'upload_image.php',
        images_upload_credentials: true,
        images_upload_handler: function (blobInfo, success, failure) {
            let xhr, formData;

            xhr = new XMLHttpRequest();
            xhr.open('POST', 'upload_image.php');

            xhr.onload = function () {
                if (xhr.status !== 200) {
                    failure('HTTP Error: ' + xhr.status);
                    return;
                }

                const json = JSON.parse(xhr.responseText);

                if (!json || typeof json.location != 'string') {
                    failure('Invalid JSON: ' + xhr.responseText);
                    return;
                }

                success(json.location);
            };

            formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());

            xhr.send(formData);
        }
    });
</script>

                    <!-- Tags -->
                    <div class="form-group">
                        <label for="tags">
                            <i class="fas fa-tags" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                            Tags
                        </label>
                        <div class="tag-input-container <?php echo isset($errors['tags']) ? 'error' : ''; ?>">
                            <div class="selected-tags" id="selectedTags"></div>
                            <input type="text" id="tagInput" placeholder="Add tags (press Enter to add)">
                            <div class="tag-suggestions" id="tagSuggestions"></div>
                        </div>
                        <div class="help-text">
                            <i class="fas fa-tag"></i>
                            Add up to 5 tags to describe what your question is about (e.g., javascript, php, mysql, css)
                        </div>
                        <?php if (isset($errors['tags'])): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?php echo $errors['tags']; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($errors['submit'])): ?>
                        <div class="error-message" style="margin-bottom: 2rem; text-align: center; background: #fef2f2; padding: 1.5rem; border-radius: var(--radius-md); border: 1px solid #fecaca;">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $errors['submit']; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Submit -->
                    <div class="form-actions">
                        <a href="index.php" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="submit" name="submit_question" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Post Question
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>


    <script src="assets/js/main.js"></script>
    <script>
        // Tag input functionality
        window.existingTags = <?php echo json_encode($existing_tags); ?>;
        window.selectedTags = [];
        
        // Initialize with any existing tags from form submission if there was an error
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tags'])): ?>
            window.selectedTags = <?php echo json_encode($_POST['tags']); ?>;
        <?php endif; ?>

        // Focus on title input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('title').focus();
        });
    </script>
</body>
</html>