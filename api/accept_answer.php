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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['answer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Answer ID is required']);
    exit;
}

$answer_id = (int)$input['answer_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get answer and question details
    $stmt = $conn->prepare("SELECT a.*, q.author_id as question_author_id, q.title as question_title 
                           FROM answers a 
                           JOIN questions q ON a.question_id = q.id 
                           WHERE a.id = ?");
    $stmt->execute([$answer_id]);
    $answer = $stmt->fetch();
    
    if (!$answer) {
        echo json_encode(['success' => false, 'message' => 'Answer not found']);
        exit;
    }
    
    // Check if user is the question author
    if ($answer['question_author_id'] != $user['id']) {
        echo json_encode(['success' => false, 'message' => 'Only the question author can accept answers']);
        exit;
    }
    
    $conn->beginTransaction();
    
    // Unmark any previously accepted answers for this question
    $unmark_stmt = $conn->prepare("UPDATE answers SET is_accepted = 0 WHERE question_id = ?");
    $unmark_stmt->execute([$answer['question_id']]);
    
    // Mark this answer as accepted
    $accept_stmt = $conn->prepare("UPDATE answers SET is_accepted = 1 WHERE id = ?");
    $accept_stmt->execute([$answer_id]);
    
    // Create notification for answer author
    if ($answer['author_id'] != $user['id']) {
        createNotification(
            $conn,
            $answer['author_id'],
            'accept',
            'Answer Accepted',
            "Your answer to '" . $answer['question_title'] . "' has been accepted!",
            $answer_id
        );
    }
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Answer accepted successfully']);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>