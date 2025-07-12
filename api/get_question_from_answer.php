<?php
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['answer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Answer ID is required']);
    exit;
}

$answer_id = (int)$_GET['answer_id'];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("SELECT question_id FROM answers WHERE id = ?");
    $stmt->execute([$answer_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        echo json_encode(['success' => true, 'question_id' => $result['question_id']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Answer not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>