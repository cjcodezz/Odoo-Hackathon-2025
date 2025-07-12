<?php
require_once 'config/database.php';

function sanitizeHtml($content) {
    // Allow TinyMCE HTML tags for rich text formatting
    $allowed_tags = '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><code><pre><a><img><table><tr><td><th><thead><tbody><span><div>';
    return strip_tags($content, $allowed_tags);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

function getVoteCount($conn, $votable_id, $votable_type) {
    try {
        $stmt = $conn->prepare("SELECT 
            SUM(CASE WHEN vote_type = 'up' THEN 1 ELSE 0 END) -
            SUM(CASE WHEN vote_type = 'down' THEN 1 ELSE 0 END) as vote_count
            FROM votes WHERE votable_id = ? AND votable_type = ?");
        $stmt->execute([$votable_id, $votable_type]);
        $result = $stmt->fetch();
        return $result['vote_count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getUserVote($conn, $user_id, $votable_id, $votable_type) {
    if (!$user_id) return null;
    
    try {
        $stmt = $conn->prepare("SELECT vote_type FROM votes WHERE user_id = ? AND votable_id = ? AND votable_type = ?");
        $stmt->execute([$user_id, $votable_id, $votable_type]);
        $result = $stmt->fetch();
        return $result ? $result['vote_type'] : null;
    } catch (Exception $e) {
        return null;
    }
}

function createNotification($conn, $user_id, $type, $title, $message, $related_id = null) {
    try {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $type, $title, $message, $related_id]);
    } catch (Exception $e) {
        return false;
    }
}

function getUnreadNotificationCount($conn, $user_id) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getUserStats($conn, $user_id) {
    $stats = [];
    
    try {
        // Questions count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM questions WHERE author_id = ?");
        $stmt->execute([$user_id]);
        $stats['questions'] = $stmt->fetch()['count'];
        
        // Answers count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM answers WHERE author_id = ?");
        $stmt->execute([$user_id]);
        $stats['answers'] = $stmt->fetch()['count'];
        
        // Reputation (total votes on user's content)
        $stmt = $conn->prepare("SELECT 
            COALESCE(SUM(CASE WHEN v.vote_type = 'up' THEN 1 ELSE -1 END), 0) as reputation
            FROM votes v 
            WHERE (v.votable_type = 'question' AND v.votable_id IN (SELECT id FROM questions WHERE author_id = ?))
            OR (v.votable_type = 'answer' AND v.votable_id IN (SELECT id FROM answers WHERE author_id = ?))");
        $stmt->execute([$user_id, $user_id]);
        $stats['reputation'] = max(0, $stmt->fetch()['reputation']);
        
        // Accepted answers
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM answers WHERE author_id = ? AND is_accepted = 1");
        $stmt->execute([$user_id]);
        $stats['accepted_answers'] = $stmt->fetch()['count'];
        
    } catch (Exception $e) {
        $stats = [
            'questions' => 0,
            'answers' => 0,
            'reputation' => 0,
            'accepted_answers' => 0
        ];
    }
    
    return $stats;
}
?>