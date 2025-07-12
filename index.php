<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$user = $auth->getCurrentUser();

$database = new Database();
$conn = $database->getConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$tag_filter = isset($_GET['tag']) ? trim($_GET['tag']) : '';

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(q.title LIKE ? OR q.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($tag_filter) {
    $where_conditions[] = "q.id IN (SELECT qt.question_id FROM question_tags qt JOIN tags t ON qt.tag_id = t.id WHERE t.name = ?)";
    $params[] = $tag_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get questions
$query = "SELECT q.*, u.email as author_email, u.first_name, u.last_name,
          (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id) as answer_count,
          (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id AND a.is_accepted = 1) as accepted_count
          FROM questions q 
          JOIN users u ON q.author_id = u.id 
          $where_clause
          ORDER BY q.created_at DESC 
          LIMIT $per_page OFFSET $offset";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$questions = $stmt->fetchAll();

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM questions q $where_clause";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_questions = $count_stmt->fetch()['total'];
$total_pages = ceil($total_questions / $per_page);

// Get popular tags
$tag_query = "SELECT t.name, COUNT(qt.question_id) as question_count 
              FROM tags t 
              JOIN question_tags qt ON t.id = qt.tag_id 
              GROUP BY t.id, t.name 
              ORDER BY question_count DESC 
              LIMIT 10";
$tag_stmt = $conn->prepare($tag_query);
$tag_stmt->execute();
$popular_tags = $tag_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StackIt - Professional Q&A Platform</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tiny.cloud/1/c0ozd81phoow5dms3treza0hu9de4f65v8zzj9f4wd7d1q2a/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>
<body>
    <!-- Enhanced Header -->
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
            <!-- Enhanced Search Section -->
            <div class="search-container">
                <div style="position: relative;">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" id="searchInput" 
                           placeholder="Search questions, answers, and topics..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>

            <!-- Popular Tags -->
            <?php if (!empty($popular_tags) && empty($search) && empty($tag_filter)): ?>
                <div style="background: var(--surface-color); border-radius: var(--radius-lg); padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
                    <h3 style="margin-bottom: 1.5rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-tags" style="color: var(--primary-color);"></i>
                        Popular Tags
                    </h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                        <?php foreach ($popular_tags as $tag): ?>
                            <a href="?tag=<?php echo urlencode($tag['name']); ?>" class="tag">
                                <?php echo htmlspecialchars($tag['name']); ?>
                                <span style="opacity: 0.7; margin-left: 0.25rem;">(<?php echo $tag['question_count']; ?>)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Current Filters -->
            <?php if ($search || $tag_filter): ?>
                <div style="background: linear-gradient(135deg, #eff6ff, var(--surface-color)); border: 1px solid #bfdbfe; border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 2rem;">
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <span style="font-weight: 600; color: #1e40af; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-filter"></i>
                            Active filters:
                        </span>
                        <?php if ($search): ?>
                            <span style="background: var(--surface-color); padding: 0.5rem 1rem; border-radius: var(--radius-md); border: 1px solid #bfdbfe; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-search"></i>
                                Search: "<?php echo htmlspecialchars($search); ?>"
                            </span>
                        <?php endif; ?>
                        <?php if ($tag_filter): ?>
                            <span style="background: var(--surface-color); padding: 0.5rem 1rem; border-radius: var(--radius-md); border: 1px solid #bfdbfe; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-tag"></i>
                                Tag: <?php echo htmlspecialchars($tag_filter); ?>
                            </span>
                        <?php endif; ?>
                        <a href="index.php" style="color: var(--error-color); text-decoration: none; font-size: 0.875rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); transition: all 0.3s ease;">
                            <i class="fas fa-times"></i> Clear filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Questions List -->
            <div style="margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 style="color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fas fa-question-circle" style="color: var(--primary-color);"></i>
                        <?php if ($search): ?>
                            Search results for "<?php echo htmlspecialchars($search); ?>"
                        <?php elseif ($tag_filter): ?>
                            Questions tagged with "<?php echo htmlspecialchars($tag_filter); ?>"
                        <?php else: ?>
                            Latest Questions
                        <?php endif; ?>
                        <span style="color: var(--text-muted); font-weight: normal; font-size: 1rem;">(<?php echo $total_questions; ?>)</span>
                    </h2>
                    <?php if ($user): ?>
                        <a href="ask.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Ask Question
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($questions)): ?>
                    <div style="text-align: center; padding: 4rem 2rem; background: var(--surface-color); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); border: 1px solid var(--border-color);">
                        <i class="fas fa-question-circle" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 1.5rem; opacity: 0.5;"></i>
                        <h3 style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 1.5rem;">No questions found</h3>
                        <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 1.1rem; max-width: 400px; margin-left: auto; margin-right: auto;">
                            <?php if ($search || $tag_filter): ?>
                                Try adjusting your search criteria or browse all questions.
                            <?php else: ?>
                                Be the first to ask a question and start the conversation!
                            <?php endif; ?>
                        </p>
                        <?php if ($user): ?>
                            <a href="ask.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                <i class="fas fa-plus"></i>
                                Ask the First Question
                            </a>
                        <?php else: ?>
                            <a href="register.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                <i class="fas fa-user-plus"></i>
                                Sign up to Ask Questions
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($questions as $question): ?>
                        <?php
                        $vote_count = getVoteCount($conn, $question['id'], 'question');
                        $user_vote = getUserVote($conn, $user['id'] ?? null, $question['id'], 'question');
                        
                        // Get tags for this question
                        $tag_query = "SELECT t.name FROM tags t JOIN question_tags qt ON t.id = qt.tag_id WHERE qt.question_id = ?";
                        $tag_stmt = $conn->prepare($tag_query);
                        $tag_stmt->execute([$question['id']]);
                        $question_tags = $tag_stmt->fetchAll(PDO::FETCH_COLUMN);
                        ?>
                        
                        <div class="question-item">
                            <div style="display: flex; gap: 1.5rem;">
                                <div class="voting">
                                    <button class="vote-btn vote-up <?php echo $user_vote === 'up' ? 'active' : ''; ?>" 
                                            onclick="vote(<?php echo $question['id']; ?>, 'question', 'up')" 
                                            <?php echo !$user ? 'disabled' : ''; ?>>
                                        <i class="fas fa-chevron-up"></i>
                                    </button>
                                    <span class="vote-count <?php echo $vote_count > 0 ? 'positive' : ($vote_count < 0 ? 'negative' : ''); ?>">
                                        <?php echo $vote_count; ?>
                                    </span>
                                    <button class="vote-btn vote-down <?php echo $user_vote === 'down' ? 'active' : ''; ?>" 
                                            onclick="vote(<?php echo $question['id']; ?>, 'question', 'down')" 
                                            <?php echo !$user ? 'disabled' : ''; ?>>
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </div>
                                
                                <div style="flex: 1;">
                                    <h3 class="question-title">
                                        <a href="question.php?id=<?php echo $question['id']; ?>">
                                            <?php echo htmlspecialchars($question['title']); ?>
                                        </a>
                                    </h3>
                                    
                                    <div class="question-body" style="margin-bottom: 1.5rem;">
                                        <?php 
                                        $description = strip_tags($question['description']);
                                        echo htmlspecialchars(substr($description, 0, 250)) . (strlen($description) > 250 ? '...' : '');
                                        ?>
                                    </div>
                                    
                                    <?php if (!empty($question_tags)): ?>
                                        <div class="question-tags">
                                            <?php foreach ($question_tags as $tag): ?>
                                                <a href="?tag=<?php echo urlencode($tag); ?>" class="tag">
                                                    <?php echo htmlspecialchars($tag); ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="question-meta">
                                        <span>
                                            <i class="fas fa-reply"></i>
                                            <?php echo $question['answer_count']; ?> 
                                            <?php echo $question['answer_count'] == 1 ? 'answer' : 'answers'; ?>
                                            <?php if ($question['accepted_count'] > 0): ?>
                                                <i class="fas fa-check" style="color: var(--success-color); margin-left: 0.5rem;"></i>
                                                <span style="color: var(--success-color);">accepted</span>
                                            <?php endif; ?>
                                        </span>
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
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Enhanced Pagination -->
            <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 3rem; flex-wrap: wrap;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $tag_filter ? '&tag=' . urlencode($tag_filter) : ''; ?>" 
                           class="btn btn-outline">
                            <i class="fas fa-chevron-left"></i>
                            Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $tag_filter ? '&tag=' . urlencode($tag_filter) : ''; ?>" 
                           class="btn btn-outline">1</a>
                        <?php if ($start_page > 2): ?>
                            <span style="padding: 0.75rem; color: var(--text-muted);">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $tag_filter ? '&tag=' . urlencode($tag_filter) : ''; ?>" 
                           class="btn <?php echo $i == $page ? 'btn-primary' : 'btn-outline'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span style="padding: 0.75rem; color: var(--text-muted);">...</span>
                        <?php endif; ?>
                        <a href="?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $tag_filter ? '&tag=' . urlencode($tag_filter) : ''; ?>" 
                           class="btn btn-outline"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $tag_filter ? '&tag=' . urlencode($tag_filter) : ''; ?>" 
                           class="btn btn-outline">
                            Next
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>