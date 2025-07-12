<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$user = $auth->getCurrentUser();

$database = new Database();
$conn = $database->getConnection();

$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$question_id) {
    header('Location: index.php');
    exit;
}

// Increment view count
$view_update = $conn->prepare("UPDATE questions SET view_count = view_count + 1 WHERE id = ?");
$view_update->execute([$question_id]);

// Handle answer submission
if ($_POST && isset($_POST['submit_answer']) && $user) {
    $content = sanitizeHtml($_POST['content']);
    
    if (!empty($content)) {
        $query = "INSERT INTO answers (content, question_id, author_id) VALUES (:content, :question_id, :author_id)";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':content', $content);
        $stmt->bindParam(':question_id', $question_id);
        $stmt->bindParam(':author_id', $user['id']);
        
        if ($stmt->execute()) {
            $answer_id = $conn->lastInsertId();
            
            // Create notification for question author
            $question_query = "SELECT author_id, title FROM questions WHERE id = :id";
            $question_stmt = $conn->prepare($question_query);
            $question_stmt->bindParam(':id', $question_id);
            $question_stmt->execute();
            $question_data = $question_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($question_data && $question_data['author_id'] != $user['id']) {
                createNotification(
                    $conn,
                    $question_data['author_id'],
                    'answer',
                    'New Answer',
                    "Someone answered your question: " . $question_data['title'],
                    $answer_id
                );
            }
            
            header("Location: question.php?id=$question_id");
            exit;
        }
    }
}

// Get question details
$query = "SELECT q.*, u.email as author_email, u.first_name, u.last_name 
          FROM questions q 
          JOIN users u ON q.author_id = u.id 
          WHERE q.id = :id";
$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $question_id);
$stmt->execute();
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    header('Location: index.php');
    exit;
}

// Get question tags
$tag_query = "SELECT t.name FROM tags t 
              JOIN question_tags qt ON t.id = qt.tag_id 
              WHERE qt.question_id = :question_id";
$tag_stmt = $conn->prepare($tag_query);
$tag_stmt->bindParam(':question_id', $question_id);
$tag_stmt->execute();
$tags = $tag_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get answers
$answer_query = "SELECT a.*, u.email as author_email, u.first_name, u.last_name 
                 FROM answers a 
                 JOIN users u ON a.author_id = u.id 
                 WHERE a.question_id = :question_id 
                 ORDER BY a.is_accepted DESC, a.created_at ASC";
$answer_stmt = $conn->prepare($answer_query);
$answer_stmt->bindParam(':question_id', $question_id);
$answer_stmt->execute();
$answers = $answer_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get vote counts
$question_vote_count = getVoteCount($conn, $question_id, 'question');
$question_user_vote = getUserVote($conn, $user['id'] ?? null, $question_id, 'question');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($question['title']); ?> - StackIt</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/c0ozd81phoow5dms3treza0hu9de4f65v8zzj9f4wd7d1q2a/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: 'textarea[name="content"]',
        height: 400,
        plugins: 'preview importcss searchreplace autolink directionality code visualblocks visualchars fullscreen image link media table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help autosave',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | link image media table | alignleft aligncenter alignright alignjustify | numlist bullist outdent indent | removeformat | code preview',
        images_upload_url: 'upload_image.php',
        images_upload_credentials: true,
        automatic_uploads: true,
        images_upload_handler: function (blobInfo, success, failure) {
            let xhr, formData;

            xhr = new XMLHttpRequest();
            xhr.withCredentials = false;
            xhr.open('POST', 'upload_image.php');

            xhr.onload = function () {
                let json;

                if (xhr.status !== 200) {
                    failure('HTTP Error: ' + xhr.status);
                    return;
                }

                json = JSON.parse(xhr.responseText);

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
                        <?php if ($user): ?>
                            <a href="ask.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Ask Question
                            </a>
                            
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
                        <?php else: ?>
                            <a href="login.php" class="btn btn-outline">Sign In</a>
                            <a href="register.php" class="btn btn-primary">Sign Up</a>
                        <?php endif; ?>
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

            <!-- Question -->
            <div class="question-detail">
                <div class="question-voting">
                    <button class="vote-btn vote-up <?php echo $question_user_vote === 'up' ? 'active' : ''; ?>" 
                            onclick="vote(<?php echo $question_id; ?>, 'question', 'up')" 
                            <?php echo !$user ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-up"></i>
                    </button>
                    <span class="vote-count <?php echo $question_vote_count > 0 ? 'positive' : ($question_vote_count < 0 ? 'negative' : ''); ?>">
                        <?php echo $question_vote_count; ?>
                    </span>
                    <button class="vote-btn vote-down <?php echo $question_user_vote === 'down' ? 'active' : ''; ?>" 
                            onclick="vote(<?php echo $question_id; ?>, 'question', 'down')" 
                            <?php echo !$user ? 'disabled' : ''; ?>>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                
                <div class="question-content">
                    <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 1.5rem; line-height: 1.3;">
                        <?php echo htmlspecialchars($question['title']); ?>
                    </h1>
                    
                    <div class="question-body" style="font-size: 1.1rem; line-height: 1.8; margin-bottom: 2rem;">
                        <?php echo $question['description']; ?>
                    </div>
                    
                    <?php if (!empty($tags)): ?>
                        <div class="question-tags">
                            <?php foreach ($tags as $tag): ?>
                                <a href="index.php?tag=<?php echo urlencode($tag); ?>" class="tag">
                                    <?php echo htmlspecialchars($tag); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="question-meta" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                        <span>
                            <i class="fas fa-eye"></i>
                            <?php echo $question['view_count']; ?> views
                        </span>
                        <span>
                            <i class="fas fa-clock"></i>
                            asked <?php echo timeAgo($question['created_at']); ?>
                        </span>
                        <span>
                            <i class="fas fa-user"></i>
                            by <strong><?php echo htmlspecialchars($question['first_name'] ?: explode('@', $question['author_email'])[0]); ?></strong>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Answers -->
            <div style="margin-bottom: 3rem;">
                <h2 style="margin-bottom: 2rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.75rem; font-size: 1.75rem;">
                    <i class="fas fa-reply" style="color: var(--primary-color);"></i>
                    <?php echo count($answers); ?> <?php echo count($answers) === 1 ? 'Answer' : 'Answers'; ?>
                </h2>
                
                <?php if (empty($answers)): ?>
                    <div style="text-align: center; padding: 4rem 2rem; background: var(--surface-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
                        <i class="fas fa-reply" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 1.5rem; opacity: 0.5;"></i>
                        <h3 style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 1.5rem;">No answers yet</h3>
                        <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 1.1rem;">Be the first to answer this question and help the community!</p>
                        <?php if ($user): ?>
                            <a href="#answer-form" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                <i class="fas fa-pen"></i>
                                Write an Answer
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                <i class="fas fa-sign-in-alt"></i>
                                Sign in to Answer
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($answers as $answer): ?>
                        <?php
                        $answer_vote_count = getVoteCount($conn, $answer['id'], 'answer');
                        $answer_user_vote = getUserVote($conn, $user['id'] ?? null, $answer['id'], 'answer');
                        ?>
                        
                        <div class="answer <?php echo $answer['is_accepted'] ? 'accepted' : ''; ?>">
                            <div class="answer-voting">
                                <button class="vote-btn vote-up <?php echo $answer_user_vote === 'up' ? 'active' : ''; ?>" 
                                        onclick="vote(<?php echo $answer['id']; ?>, 'answer', 'up')" 
                                        <?php echo !$user ? 'disabled' : ''; ?>>
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                                <span class="vote-count <?php echo $answer_vote_count > 0 ? 'positive' : ($answer_vote_count < 0 ? 'negative' : ''); ?>">
                                    <?php echo $answer_vote_count; ?>
                                </span>
                                <button class="vote-btn vote-down <?php echo $answer_user_vote === 'down' ? 'active' : ''; ?>" 
                                        onclick="vote(<?php echo $answer['id']; ?>, 'answer', 'down')" 
                                        <?php echo !$user ? 'disabled' : ''; ?>>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                
                                <?php if ($user && $question['author_id'] == $user['id'] && !$answer['is_accepted']): ?>
                                    <button class="accept-btn" onclick="acceptAnswer(<?php echo $answer['id']; ?>)" title="Accept this answer">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($answer['is_accepted']): ?>
                                    <div class="accepted-indicator" title="Accepted answer">
                                        <i class="fas fa-check"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="answer-content">
                                <div class="answer-body" style="font-size: 1.05rem; line-height: 1.7;">
                                    <?php echo $answer['content']; ?>
                                </div>
                                
                                <div class="answer-meta" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-light);">
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        answered <?php echo timeAgo($answer['created_at']); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-user"></i>
                                        by <strong><?php echo htmlspecialchars($answer['first_name'] ?: explode('@', $answer['author_email'])[0]); ?></strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Answer Form -->
            <?php if ($user): ?>
                <div id="answer-form" style="background: var(--surface-color); border-radius: var(--radius-lg); padding: 3rem; box-shadow: var(--shadow-lg); border: 1px solid var(--border-color);">
                    <h3 style="margin-bottom: 2rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.75rem; font-size: 1.5rem;">
                        <i class="fas fa-pen" style="color: var(--primary-color);"></i>
                        Your Answer
                    </h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="content">
                                <i class="fas fa-align-left" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                                Answer Content
                            </label>
                            <textarea name="content" placeholder="Write your answer here..." style="min-height: 250px;"></textarea>
                            <div class="help-text">
                                <i class="fas fa-lightbulb"></i>
                                Provide a detailed answer to help the person asking the question. Use the rich text editor to format your content.
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="submit_answer" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                <i class="fas fa-paper-plane"></i>
                                Post Answer
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem 2rem; background: var(--surface-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
                    <i class="fas fa-sign-in-alt" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1.5rem; opacity: 0.5;"></i>
                    <h3 style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 1.5rem;">Want to answer this question?</h3>
                    <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 1.1rem;">Join our community to share your knowledge and help others.</p>
                    <a href="login.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                        <i class="fas fa-sign-in-alt"></i>
                        Sign in to post an answer
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>