<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$auth = new Auth();
$user = $auth->getCurrentUser();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle marking notifications as read
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'mark_read') {
        try {
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    // Handle getting notifications
    try {
        // Get notifications
        $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$user['id']]);
        $notifications = $stmt->fetchAll();
        
        // Get unread count
        $unread_count = getUnreadNotificationCount($conn, $user['id']);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load notifications: ' . $e->getMessage()]);
    }
}
?>