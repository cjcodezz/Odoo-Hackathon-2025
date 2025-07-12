<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$user = $auth->getCurrentUser();

// Check if user is admin
if (!$user || !$user['is_admin']) {
    header('Location: index.php');
    exit;
}

$database = new Database();
$conn = $database->getConnection();

// Get admin statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'new_users_today' => $conn->query("SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()")->fetchColumn(),
    'total_questions' => $conn->query("SELECT COUNT(*) FROM questions")->fetchColumn(),
    'total_answers' => $conn->query("SELECT COUNT(*) FROM answers")->fetchColumn(),
    'pending_reports' => $conn->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn()
];

// Get recent activities
$activities = $conn->query("SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();

// Get reported content
$reported_content = $conn->query("
    SELECT r.*, u.email as reporter_email, 
    CASE 
        WHEN r.content_type = 'question' THEN q.title
        WHEN r.content_type = 'answer' THEN a.content
        WHEN r.content_type = 'comment' THEN c.content
    END as content_preview
    FROM reports r
    JOIN users u ON r.reporter_id = u.id
    LEFT JOIN questions q ON r.content_type = 'question' AND r.content_id = q.id
    LEFT JOIN answers a ON r.content_type = 'answer' AND r.content_id = a.id
    LEFT JOIN comments c ON r.content_type = 'comment' AND r.content_id = c.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
    LIMIT 10
")->fetchAll();

// Get latest users
$latest_users = $conn->query("
    SELECT id, email, first_name, last_name, created_at, is_admin 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        if (isset($_POST['resolve_report'])) {
            $stmt = $conn->prepare("
                UPDATE reports SET status = 'resolved', resolved_at = NOW(), 
                resolved_by = ?, action_taken = ? 
                WHERE id = ?
            ");
            $stmt->execute([$user['id'], $_POST['action_taken'], $_POST['report_id']]);
            
            // Log the action
            $conn->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)")
                ->execute([$user['id'], "Resolved report #{$_POST['report_id']}"]);
            
            $success_message = "Report resolved successfully!";
        }
        elseif (isset($_POST['update_user_role'])) {
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?")
                ->execute([$is_admin, $_POST['user_id']]);
            
            // Log the action
            $action = $is_admin ? "Promoted user #{$_POST['user_id']} to admin" : "Demoted user #{$_POST['user_id']} from admin";
            $conn->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)")
                ->execute([$user['id'], $action]);
            
            $success_message = "User role updated successfully!";
        }
        elseif (isset($_POST['delete_content'])) {
            $content_type = $_POST['content_type'];
            $content_id = $_POST['content_id'];
            
            $table = $content_type === 'question' ? 'questions' : ($content_type === 'answer' ? 'answers' : 'comments');
            $conn->prepare("DELETE FROM $table WHERE id = ?")->execute([$content_id]);
            
            // Log the action
            $conn->prepare("INSERT INTO admin_logs (admin_id, action) VALUES (?, ?)")
                ->execute([$user['id'], "Deleted $content_type #$content_id"]);
            
            $success_message = "Content deleted successfully!";
        }
        
        $conn->commit();
        // Refresh data after changes
        header("Location: admin.php");
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Action failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StackIt</title>
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-light: #e0e7ff;
            --danger-color: #ef4444;
            --danger-light: #fee2e2;
            --success-color: #10b981;
            --success-light: #d1fae5;
            --surface-color: #f9fafb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border-color: #e5e7eb;
            --border-light: #f3f4f6;
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow-xs: 0 1px 3px 0 rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --radius-md: 0.375rem;
            --radius-lg: 0.5rem;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
        body { background-color: #f3f4f6; color: var(--text-primary); line-height: 1.5; }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; }
        
        /* Header */
        .header { background: white; box-shadow: var(--shadow-sm); padding: 1rem 0; position: sticky; top: 0; z-index: 50; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; align-items: center; gap: 0.5rem; font-weight: 600; font-size: 1.25rem; color: var(--primary-color); }
        .mobile-menu-btn { display: none; background: none; border: none; font-size: 1.25rem; }
        .nav { display: flex; align-items: center; gap: 1rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-weight: 500; transition: all 0.3s ease; }
        .btn-primary { background: var(--primary-color); color: white; border: 1px solid var(--primary-color); }
        .btn-primary:hover { background: #4338ca; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-secondary); }
        .btn-outline:hover { background: var(--surface-color); }
        
        /* Admin Layout */
        .admin-container { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; margin-top: 2rem; }
        .admin-sidebar { background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm); height: fit-content; position: sticky; top: 5rem; }
        .admin-content { background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-sm); }
        
        /* Admin Info */
        .admin-info { text-align: center; margin-bottom: 2rem; }
        .admin-avatar { width: 80px; height: 80px; background: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; margin: 0 auto 1rem; position: relative; }
        .admin-badge { position: absolute; bottom: 0; right: 0; background: var(--success-color); color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; border: 2px solid white; }
        .admin-role { background: var(--primary-light); color: var(--primary-color); padding: 0.25rem 0.75rem; border-radius: var(--radius-md); font-size: 0.75rem; font-weight: 600; display: inline-block; margin-top: 0.5rem; }
        
        /* Navigation */
        .admin-nav { display: flex; flex-direction: column; gap: 0.5rem; }
        .admin-nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: var(--radius-md); color: var(--text-secondary); transition: all 0.3s ease; }
        .admin-nav-link:hover { background: var(--primary-light); color: var(--primary-color); }
        .admin-nav-link.active { background: var(--primary-light); color: var(--primary-color); font-weight: 500; }
        .badge { background: var(--danger-color); color: white; font-size: 0.7rem; padding: 0.2rem 0.5rem; border-radius: 10px; margin-left: auto; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
        .stat-card { background: white; border-radius: var(--radius-lg); padding: 1.5rem; box-shadow: var(--shadow-sm); display: flex; flex-direction: column; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        .stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; margin-bottom: 1rem; }
        .stat-icon.users { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
        .stat-icon.questions { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .stat-icon.answers { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .stat-icon.reports { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .stat-number { font-size: 1.75rem; font-weight: 700; }
        .stat-label { font-size: 0.875rem; color: var(--text-muted); }
        .stat-change { margin-top: auto; font-size: 0.75rem; color: var(--success-color); display: flex; align-items: center; gap: 0.25rem; }
        .stat-action { margin-top: 0.5rem; font-size: 0.875rem; color: var(--primary-color); font-weight: 500; }
        
        /* Sections */
        .admin-section { margin-bottom: 3rem; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .section-header h3 { font-size: 1.25rem; display: flex; align-items: center; gap: 0.75rem; }
        
        /* Activities */
        .activities-list { display: flex; flex-direction: column; gap: 1rem; }
        .activity-item { display: flex; gap: 1rem; padding: 1rem; background: var(--surface-color); border-radius: var(--radius-md); }
        .activity-icon { width: 40px; height: 40px; background: var(--primary-light); color: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .activity-meta { font-size: 0.875rem; color: var(--text-muted); display: flex; gap: 1rem; }
        
        /* Reports */
        .reports-list { display: flex; flex-direction: column; gap: 1.5rem; }
        .report-item { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-xs); }
        .report-header { padding: 1rem 1.5rem; background: var(--danger-light); border-left: 4px solid var(--danger-color); }
        .report-meta { display: flex; gap: 1rem; flex-wrap: wrap; font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.5rem; }
        .report-type { background: var(--danger-color); color: white; padding: 0.25rem 0.75rem; border-radius: var(--radius-md); font-size: 0.75rem; font-weight: 600; }
        .report-content { padding: 1.5rem; }
        .content-preview { padding: 1rem; background: var(--surface-color); border-radius: var(--radius-md); margin-bottom: 1rem; font-size: 0.875rem; }
        .report-actions { display: flex; gap: 1rem; align-items: center; }
        .report-actions select { flex: 1; padding: 0.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); }
        
        /* Users Table */
        .users-table { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem; background: var(--surface-color); color: var(--text-secondary); font-weight: 500; font-size: 0.875rem; }
        td { padding: 1rem; border-bottom: 1px solid var(--border-light); vertical-align: middle; }
        .user-cell { display: flex; align-items: center; gap: 0.75rem; }
        .user-avatar { width: 32px; height: 32px; background: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.875rem; }
        .badge.admin { background: var(--primary-light); color: var(--primary-color); }
        .badge.user { background: var(--success-light); color: var(--success-color); }
        .role-form { display: flex; align-items: center; gap: 0.75rem; }
        
        /* Toggle Switch */
        .switch { position: relative; display: inline-block; width: 50px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #ccc; transition: .4s; border-radius: 24px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background: var(--primary-color); }
        input:checked + .slider:before { transform: translateX(26px); }
        
        /* Alerts */
        .alert { padding: 1rem 1.5rem; border-radius: var(--radius-md); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; }
        .alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        
        /* Empty States */
        .empty-state { padding: 2rem; text-align: center; background: var(--surface-color); border-radius: var(--radius-md); color: var(--text-muted); }
        .empty-state i { font-size: 2rem; opacity: 0.5; display: block; margin-bottom: 0.5rem; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-container { grid-template-columns: 1fr; }
            .admin-sidebar { position: static; }
            .mobile-menu-btn { display: block; }
            .nav-items { display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; padding: 1rem; box-shadow: var(--shadow-md); flex-direction: column; }
            .nav-items.show { display: flex; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <i class="fas fa-comments"></i>
                    <span>StackIt</span>
                </a>
                
                <button class="mobile-menu-btn" onclick="document.getElementById('mobileMenu').classList.toggle('show')">
                    <i class="fas fa-bars"></i>
                </button>
                
                <nav class="nav">
                    <div class="nav-items" id="mobileMenu">
                        <a href="admin.php" class="btn btn-primary">
                            <i class="fas fa-tachometer-alt"></i>
                            Admin Dashboard
                        </a>
                        
                        <div class="notification-wrapper">
                            <button class="notification-btn" onclick="document.getElementById('notificationDropdown').classList.toggle('show')">
                                <i class="fas fa-bell"></i>
                                <span class="notification-count"><?= $stats['pending_reports'] ?></span>
                            </button>
                            <div class="notification-dropdown" id="notificationDropdown">
                                <?php if ($stats['pending_reports'] > 0): ?>
                                    <div class="alert error">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <?= $stats['pending_reports'] ?> pending reports
                                    </div>
                                <?php else: ?>
                                    <div class="alert success">
                                        <i class="fas fa-check-circle"></i>
                                        No pending reports
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="user-menu">
                            <button class="user-btn" onclick="document.getElementById('userDropdown').classList.toggle('show')">
                                <div class="user-avatar admin">
                                    <?= strtoupper(substr($user['first_name'] ?: $user['email'], 0, 1)) ?>
                                </div>
                                <span><?= htmlspecialchars($user['first_name'] ?: explode('@', $user['email'])[0]) ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="user-dropdown" id="userDropdown">
                                <a href="profile.php">
                                    <i class="fas fa-user-circle"></i>
                                    My Profile
                                </a>
                                <a href="admin.php">
                                    <i class="fas fa-tachometer-alt"></i>
                                    Admin Dashboard
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
            <div class="admin-container">
                <!-- Admin Sidebar -->
                <div class="admin-sidebar">
                    <div class="admin-info">
                        <div class="admin-avatar">
                            <?= strtoupper(substr($user['first_name'] ?: $user['email'], 0, 1)) ?>
                            <div class="admin-badge">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                        </div>
                        <h1><?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']) ?: explode('@', $user['email'])[0]) ?></h1>
                        <div class="admin-email"><?= htmlspecialchars($user['email']) ?></div>
                        <div class="admin-role">Administrator</div>
                    </div>
                    
                    <nav class="admin-nav">
                        <a href="admin.php" class="admin-nav-link active">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                        <a href="admin_users.php" class="admin-nav-link">
                            <i class="fas fa-users"></i>
                            User Management
                        </a>
                        <a href="admin_reports.php" class="admin-nav-link">
                            <i class="fas fa-flag"></i>
                            Content Reports
                            <?php if ($stats['pending_reports'] > 0): ?>
                                <span class="badge"><?= $stats['pending_reports'] ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="admin_content.php" class="admin-nav-link">
                            <i class="fas fa-file-alt"></i>
                            Content Management
                        </a>
                        <a href="admin_logs.php" class="admin-nav-link">
                            <i class="fas fa-clipboard-list"></i>
                            Activity Logs
                        </a>
                    </nav>
                </div>

                <!-- Admin Content -->
                <div class="admin-content">
                    <h2 class="admin-title">
                        <i class="fas fa-tachometer-alt"></i>
                        Admin Dashboard
                    </h2>
                    
                    <?php if (isset($success_message)): ?>
                        <div class="alert success">
                            <i class="fas fa-check-circle"></i>
                            <?= $success_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= $error_message ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Stats Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon users">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-number"><?= $stats['total_users'] ?></div>
                                <div class="stat-label">Total Users</div>
                            </div>
                            <div class="stat-change">
                                <i class="fas fa-arrow-up"></i>
                                <?= $stats['new_users_today'] ?> today
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon questions">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-number"><?= $stats['total_questions'] ?></div>
                                <div class="stat-label">Total Questions</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon answers">
                                <i class="fas fa-reply"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-number"><?= $stats['total_answers'] ?></div>
                                <div class="stat-label">Total Answers</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon reports">
                                <i class="fas fa-flag"></i>
                            </div>
                            <div class="stat-info">
                                <div class="stat-number"><?= $stats['pending_reports'] ?></div>
                                <div class="stat-label">Pending Reports</div>
                            </div>
                            <?php if ($stats['pending_reports'] > 0): ?>
                                <a href="admin_reports.php" class="stat-action">
                                    Review Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Activities Section -->
                    <div class="admin-section">
                        <div class="section-header">
                            <h3>
                                <i class="fas fa-clipboard-list"></i>
                                Recent Activities
                            </h3>
                            <a href="admin_logs.php" class="btn btn-outline">
                                View All
                            </a>
                        </div>
                        
                        <div class="activities-list">
                            <?php if (!empty($activities)): ?>
                                <?php foreach ($activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-action">
                                                <?= htmlspecialchars($activity['action']) ?>
                                            </div>
                                            <div class="activity-meta">
                                                <span class="activity-time">
                                                    <i class="fas fa-clock"></i>
                                                    <?= timeAgo($activity['created_at']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    No recent activities found
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Reported Content Section -->
                    <div class="admin-section">
                        <div class="section-header">
                            <h3>
                                <i class="fas fa-flag"></i>
                                Recent Reports
                            </h3>
                            <a href="admin_reports.php" class="btn btn-outline">
                                View All
                            </a>
                        </div>
                        
                        <?php if (!empty($reported_content)): ?>
                            <div class="reports-list">
                                <?php foreach ($reported_content as $report): ?>
                                    <div class="report-item">
                                        <div class="report-header">
                                            <div class="report-meta">
                                                <span class="report-type">
                                                    <?= ucfirst($report['content_type']) ?>
                                                </span>
                                                <span class="report-reporter">
                                                    Reported by <?= htmlspecialchars($report['reporter_email']) ?>
                                                </span>
                                                <span class="report-time">
                                                    <i class="fas fa-clock"></i>
                                                    <?= timeAgo($report['created_at']) ?>
                                                </span>
                                            </div>
                                            <div class="report-reason">
                                                <strong>Reason:</strong> <?= htmlspecialchars($report['reason']) ?>
                                            </div>
                                        </div>
                                        
                                        <div class="report-content">
                                            <div class="content-preview">
                                                <?= htmlspecialchars(substr(strip_tags($report['content_preview']), 0, 150)) ?>
                                                <?= strlen(strip_tags($report['content_preview'])) > 150 ? '...' : '' ?>
                                            </div>
                                            
                                            <form method="POST" class="report-actions">
                                                <input type="hidden" name="resolve_report">
                                                <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                                
                                                <select name="action_taken" required>
                                                    <option value="">Select action...</option>
                                                    <option value="no_action">No action needed</option>
                                                    <option value="content_removed">Content removed</option>
                                                    <option value="user_warned">User warned</option>
                                                    <option value="user_suspended">User suspended</option>
                                                </select>
                                                
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-check"></i>
                                                    Resolve
                                                </button>
                                                
                                                <a href="admin_content_review.php?type=<?= $report['content_type'] ?>&id=<?= $report['content_id'] ?>" 
                                                   class="btn btn-outline">
                                                    <i class="fas fa-eye"></i>
                                                    Review
                                                </a>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                No pending reports
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Latest Users Section -->
                    <div class="admin-section">
                        <div class="section-header">
                            <h3>
                                <i class="fas fa-users"></i>
                                Latest Users
                            </h3>
                            <a href="admin_users.php" class="btn btn-outline">
                                View All
                            </a>
                        </div>
                        
                        <div class="users-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Joined</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($latest_users)): ?>
                                        <?php foreach ($latest_users as $latest_user): ?>
                                            <tr>
                                                <td>
                                                    <div class="user-cell">
                                                        <div class="user-avatar">
                                                            <?= strtoupper(substr($latest_user['first_name'] ?: $latest_user['email'], 0, 1)) ?>
                                                        </div>
                                                        <span>
                                                            <?= htmlspecialchars(trim($latest_user['first_name'] . ' ' . $latest_user['last_name']) ?: explode('@', $latest_user['email'])[0]) ?>
                                                        </span>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($latest_user['email']) ?></td>
                                                <td><?= date('M j, Y', strtotime($latest_user['created_at'])) ?></td>
                                                <td>
                                                    <?php if ($latest_user['is_admin']): ?>
                                                        <span class="badge admin">Admin</span>
                                                    <?php else: ?>
                                                        <span class="badge user">User</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" class="role-form">
                                                        <input type="hidden" name="update_user_role">
                                                        <input type="hidden" name="user_id" value="<?= $latest_user['id'] ?>">
                                                        
                                                        <label class="switch">
                                                            <input type="checkbox" name="is_admin" <?= $latest_user['is_admin'] ? 'checked' : '' ?> onchange="if(confirm('Are you sure?')) this.form.submit()">
                                                            <span class="slider round"></span>
                                                        </label>
                                                        
                                                        <a href="admin_user_edit.php?id=<?= $latest_user['id'] ?>" class="btn-icon">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="empty-state">
                                                <i class="fas fa-info-circle"></i>
                                                No users found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-wrapper')) {
                document.getElementById('notificationDropdown').classList.remove('show');
            }
            
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown').classList.remove('show');
            }
        });
        
        // Handle report resolution confirmation
        document.querySelectorAll('.report-actions').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to resolve this report?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>