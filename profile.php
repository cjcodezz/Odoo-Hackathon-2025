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

// Get user statistics
$stats = getUserStats($conn, $user['id']);

// Get user's questions
$questions_query = "SELECT q.*, 
    (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id) as answer_count,
    (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id AND a.is_accepted = 1) as accepted_count
    FROM questions q 
    WHERE q.author_id = ? 
    ORDER BY q.created_at DESC 
    LIMIT 10";
$questions_stmt = $conn->prepare($questions_query);
$questions_stmt->execute([$user['id']]);
$user_questions = $questions_stmt->fetchAll();

// Get user's answers
$answers_query = "SELECT a.*, q.title as question_title, q.id as question_id
    FROM answers a 
    JOIN questions q ON a.question_id = q.id 
    WHERE a.author_id = ? 
    ORDER BY a.created_at DESC 
    LIMIT 10";
$answers_stmt = $conn->prepare($answers_query);
$answers_stmt->execute([$user['id']]);
$user_answers = $answers_stmt->fetchAll();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $bio = trim($_POST['bio']);
    $location = trim($_POST['location']);
    $website = trim($_POST['website']);
    $github = trim($_POST['github']);
    $linkedin = trim($_POST['linkedin']);
    
    try {
        $update_stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, bio = ?, location = ?, website = ?, github = ?, linkedin = ? WHERE id = ?");
        $update_stmt->execute([$first_name, $last_name, $bio, $location, $website, $github, $linkedin, $user['id']]);
        
        $success_message = "Profile updated successfully!";
        
        // Refresh user data
        $user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $user_stmt->execute([$user['id']]);
        $user = $user_stmt->fetch();
        
    } catch (Exception $e) {
        $error_message = "Failed to update profile. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - StackIt</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

            <div class="profile-container">
                <!-- Profile Sidebar -->
                <div class="profile-sidebar">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['first_name'] ?: $user['email'], 0, 1)); ?>
                    </div>
                    
                    <div class="profile-info">
                        <h1><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']) ?: explode('@', $user['email'])[0]); ?></h1>
                        <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                        
                        <?php if ($user['bio']): ?>
                            <p style="text-align: center; color: var(--text-secondary); margin-bottom: 1.5rem; font-style: italic; line-height: 1.6;">
                                "<?php echo htmlspecialchars($user['bio']); ?>"
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($user['location']): ?>
                            <div style="text-align: center; margin-bottom: 0.75rem; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                <i class="fas fa-map-marker-alt" style="color: var(--primary-color);"></i>
                                <?php echo htmlspecialchars($user['location']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: center; gap: 1rem; margin-bottom: 2rem;">
                            <?php if ($user['website']): ?>
                                <a href="<?php echo htmlspecialchars($user['website']); ?>" target="_blank" style="color: var(--text-secondary); font-size: 1.25rem; transition: all 0.3s ease;" title="Website">
                                    <i class="fas fa-globe"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($user['github']): ?>
                                <a href="<?php echo htmlspecialchars($user['github']); ?>" target="_blank" style="color: var(--text-secondary); font-size: 1.25rem; transition: all 0.3s ease;" title="GitHub">
                                    <i class="fab fa-github"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($user['linkedin']): ?>
                                <a href="<?php echo htmlspecialchars($user['linkedin']); ?>" target="_blank" style="color: var(--text-secondary); font-size: 1.25rem; transition: all 0.3s ease;" title="LinkedIn">
                                    <i class="fab fa-linkedin"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['reputation']; ?></span>
                            <span class="stat-label">Reputation</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['questions']; ?></span>
                            <span class="stat-label">Questions</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['answers']; ?></span>
                            <span class="stat-label">Answers</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $stats['accepted_answers']; ?></span>
                            <span class="stat-label">Accepted</span>
                        </div>
                    </div>
                </div>

                <!-- Profile Content -->
                <div class="profile-content">
                    <?php if (isset($success_message)): ?>
                        <div style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #bbf7d0; color: #166534; padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-check-circle" style="font-size: 1.25rem;"></i>
                            <span><?php echo $success_message; ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error_message)): ?>
                        <div style="background: linear-gradient(135deg, #fef2f2, #fee2e2); border: 1px solid #fecaca; color: #dc2626; padding: 1.5rem; border-radius: var(--radius-lg); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-exclamation-circle" style="font-size: 1.25rem;"></i>
                            <span><?php echo $error_message; ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Tabs -->
                    <div class="profile-tabs">
                        <button class="tab-btn active" onclick="showTab('overview')">
                            <i class="fas fa-chart-line"></i>
                            Overview
                        </button>
                        <button class="tab-btn" onclick="showTab('questions')">
                            <i class="fas fa-question-circle"></i>
                            Questions
                        </button>
                        <button class="tab-btn" onclick="showTab('answers')">
                            <i class="fas fa-reply"></i>
                            Answers
                        </button>
                        <button class="tab-btn" onclick="showTab('settings')">
                            <i class="fas fa-cog"></i>
                            Settings
                        </button>
                    </div>

                    <!-- Overview Tab -->
                    <div class="tab-content active" id="overviewTab">
                        <h3 style="margin-bottom: 2rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-chart-line" style="color: var(--primary-color);"></i>
                            Activity Overview
                        </h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 3rem;">
                            <div style="background: linear-gradient(135deg, #f8fafc, var(--surface-color)); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem;">
                                <h4 style="color: var(--primary-color); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.25rem;">
                                    <i class="fas fa-question-circle"></i>
                                    Recent Questions
                                </h4>
                                <?php if (!empty($user_questions)): ?>
                                    <?php foreach (array_slice($user_questions, 0, 3) as $question): ?>
                                        <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light);">
                                            <a href="question.php?id=<?php echo $question['id']; ?>" style="text-decoration: none; color: var(--text-primary); font-weight: 600; display: block; margin-bottom: 0.75rem; line-height: 1.4; transition: color 0.3s ease;">
                                                <?php echo htmlspecialchars(substr($question['title'], 0, 80)) . (strlen($question['title']) > 80 ? '...' : ''); ?>
                                            </a>
                                            <div style="font-size: 0.875rem; color: var(--text-muted); display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                                <span><i class="fas fa-reply"></i> <?php echo $question['answer_count']; ?> answers</span>
                                                <span><i class="fas fa-clock"></i> <?php echo timeAgo($question['created_at']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($user_questions) > 3): ?>
                                        <button onclick="showTab('questions')" style="color: var(--primary-color); background: none; border: none; cursor: pointer; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease;">
                                            View all questions →
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 2rem;">No questions asked yet.</p>
                                <?php endif; ?>
                            </div>
                            
                            <div style="background: linear-gradient(135deg, #f8fafc, var(--surface-color)); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem;">
                                <h4 style="color: var(--success-color); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.25rem;">
                                    <i class="fas fa-reply"></i>
                                    Recent Answers
                                </h4>
                                <?php if (!empty($user_answers)): ?>
                                    <?php foreach (array_slice($user_answers, 0, 3) as $answer): ?>
                                        <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light);">
                                            <a href="question.php?id=<?php echo $answer['question_id']; ?>" style="text-decoration: none; color: var(--text-primary); font-weight: 600; display: block; margin-bottom: 0.75rem; line-height: 1.4; transition: color 0.3s ease;">
                                                <?php echo htmlspecialchars(substr($answer['question_title'], 0, 80)) . (strlen($answer['question_title']) > 80 ? '...' : ''); ?>
                                            </a>
                                            <div style="font-size: 0.875rem; color: var(--text-muted); display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                                <?php if ($answer['is_accepted']): ?>
                                                    <span style="color: var(--success-color);"><i class="fas fa-check"></i> Accepted</span>
                                                <?php endif; ?>
                                                <span><i class="fas fa-clock"></i> <?php echo timeAgo($answer['created_at']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($user_answers) > 3): ?>
                                        <button onclick="showTab('answers')" style="color: var(--primary-color); background: none; border: none; cursor: pointer; font-size: 0.875rem; font-weight: 500; transition: all 0.3s ease;">
                                            View all answers →
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p style="color: var(--text-muted); font-style: italic; text-align: center; padding: 2rem;">No answers posted yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div style="text-align: center; background: linear-gradient(135deg, var(--border-light), var(--surface-color)); border-radius: var(--radius-lg); padding: 3rem;">
                            <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 1.1rem;">
                                <i class="fas fa-calendar-alt" style="margin-right: 0.5rem;"></i>
                                Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                            </p>
                            <a href="ask.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                <i class="fas fa-plus"></i>
                                Ask Your Next Question
                            </a>
                        </div>
                    </div>

                    <!-- Questions Tab -->
                    <div class="tab-content" id="questionsTab">
                        <h3 style="margin-bottom: 2rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-question-circle" style="color: var(--primary-color);"></i>
                            My Questions
                        </h3>
                        
                        <?php if (!empty($user_questions)): ?>
                            <?php foreach ($user_questions as $question): ?>
                                <div style="background: linear-gradient(135deg, #f8fafc, var(--surface-color)); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; margin-bottom: 1.5rem; transition: all 0.3s ease;">
                                    <h4 style="margin-bottom: 1rem;">
                                        <a href="question.php?id=<?php echo $question['id']; ?>" style="text-decoration: none; color: var(--text-primary); font-size: 1.25rem; font-weight: 600; line-height: 1.4; transition: color 0.3s ease;">
                                            <?php echo htmlspecialchars($question['title']); ?>
                                        </a>
                                    </h4>
                                    <div style="display: flex; gap: 2rem; font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1rem; flex-wrap: wrap;">
                                        <span>
                                            <i class="fas fa-reply"></i>
                                            <?php echo $question['answer_count']; ?> answers
                                        </span>
                                        <span>
                                            <i class="fas fa-eye"></i>
                                            <?php echo $question['view_count']; ?> views
                                        </span>
                                        <span>
                                            <i class="fas fa-clock"></i>
                                            <?php echo timeAgo($question['created_at']); ?>
                                        </span>
                                        <?php if ($question['accepted_count'] > 0): ?>
                                            <span style="color: var(--success-color);">
                                                <i class="fas fa-check"></i>
                                                Has accepted answer
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="color: var(--text-secondary); line-height: 1.6;">
                                        <?php 
                                        $description = strip_tags($question['description']);
                                        echo htmlspecialchars(substr($description, 0, 200)) . (strlen($description) > 200 ? '...' : '');
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 4rem 2rem; background: linear-gradient(135deg, #f8fafc, var(--surface-color)); border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                                <i class="fas fa-question-circle" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 1.5rem; opacity: 0.5;"></i>
                                <h4 style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 1.5rem;">No questions yet</h4>
                                <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 1.1rem;">Start by asking your first question!</p>
                                <a href="ask.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                    <i class="fas fa-plus"></i>
                                    Ask a Question
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Answers Tab -->
                    <div class="tab-content" id="answersTab">
                        <h3 style="margin-bottom: 2rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-reply" style="color: var(--primary-color);"></i>
                            My Answers
                        </h3>
                        
                        <?php if (!empty($user_answers)): ?>
                            <?php foreach ($user_answers as $answer): ?>
                                <div style="background: linear-gradient(135deg, #f8fafc, var(--surface-color)); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 2rem; margin-bottom: 1.5rem; <?php echo $answer['is_accepted'] ? 'border-left: 4px solid var(--success-color);' : ''; ?> transition: all 0.3s ease;">
                                    <h4 style="margin-bottom: 1rem;">
                                        <a href="question.php?id=<?php echo $answer['question_id']; ?>" style="text-decoration: none; color: var(--text-primary); font-size: 1.25rem; font-weight: 600; line-height: 1.4; transition: color 0.3s ease;">
                                            <?php echo htmlspecialchars($answer['question_title']); ?>
                                        </a>
                                        <?php if ($answer['is_accepted']): ?>
                                            <span style="background: linear-gradient(135deg, var(--success-color), #059669); color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-md); font-size: 0.75rem; margin-left: 1rem; font-weight: 600;">
                                                <i class="fas fa-check"></i> ACCEPTED
                                            </span>
                                        <?php endif; ?>
                                    </h4>
                                    <div style="color: var(--text-secondary); margin-bottom: 1rem; line-height: 1.6;">
                                        <?php 
                                        $content = strip_tags($answer['content']);
                                        echo htmlspecialchars(substr($content, 0, 250)) . (strlen($content) > 250 ? '...' : '');
                                        ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--text-muted);">
                                        <i class="fas fa-clock"></i>
                                        Answered <?php echo timeAgo($answer['created_at']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 4rem 2rem; background: linear-gradient(135deg, #f8fafc, var(--surface-color)); border-radius: var(--radius-lg); border: 1px solid var(--border-color);">
                                <i class="fas fa-reply" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 1.5rem; opacity: 0.5;"></i>
                                <h4 style="color: var(--text-secondary); margin-bottom: 1rem; font-size: 1.5rem;">No answers yet</h4>
                                <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 1.1rem;">Start helping others by answering questions!</p>
                                <a href="index.php" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                    <i class="fas fa-search"></i>
                                    Browse Questions
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Settings Tab -->
                    <div class="tab-content" id="settingsTab">
                        <h3 style="margin-bottom: 2rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.75rem;">
                            <i class="fas fa-cog" style="color: var(--primary-color);"></i>
                            Profile Settings
                        </h3>
                        
                        <form method="POST" style="max-width: 700px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                                <div class="form-group">
                                    <label for="first_name">
                                        <i class="fas fa-user" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                                        First Name
                                    </label>
                                    <input type="text" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">
                                        <i class="fas fa-user" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                                        Last Name
                                    </label>
                                    <input type="text" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="bio">
                                    <i class="fas fa-quote-left" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                                    Bio
                                </label>
                                <textarea id="bio" name="bio" placeholder="Tell us about yourself..." style="min-height: 120px;"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="location">
                                    <i class="fas fa-map-marker-alt" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                                    Location
                                </label>
                                <input type="text" id="location" name="location" 
                                       placeholder="City, Country"
                                       value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="website">
                                    <i class="fas fa-globe" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                                    Website
                                </label>
                                <input type="url" id="website" name="website" 
                                       placeholder="https://yourwebsite.com"
                                       value="<?php echo htmlspecialchars($user['website'] ?? ''); ?>">
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div class="form-group">
                                    <label for="github">
                                        <i class="fab fa-github" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                                        GitHub Profile
                                    </label>
                                    <input type="url" id="github" name="github" 
                                           placeholder="https://github.com/yourusername"
                                           value="<?php echo htmlspecialchars($user['github'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="linkedin">
                                        <i class="fab fa-linkedin" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                                        LinkedIn Profile
                                    </label>
                                    <input type="url" id="linkedin" name="linkedin" 
                                           placeholder="https://linkedin.com/in/yourusername"
                                           value="<?php echo htmlspecialchars($user['linkedin'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="update_profile" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 2rem;">
                                    <i class="fas fa-save"></i>
                                    Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>