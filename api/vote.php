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

if (!isset($input['votable_id']) || !isset($input['votable_type']) || !isset($input['vote_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$votable_id = (int)$input['votable_id'];
$votable_type = $input['votable_type'];
$vote_type = $input['vote_type'];

// Validate parameters
if (!in_array($votable_type, ['question', 'answer'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid votable type']);
    exit;
}

if (!in_array($vote_type, ['up', 'down'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid vote type']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Verify the content exists and get author
    if ($votable_type === 'question') {
        $check_stmt = $conn->prepare("SELECT id, author_id FROM questions WHERE id = ?");
    } else {
        $check_stmt = $conn->prepare("SELECT id, author_id FROM answers WHERE id = ?");
    }
    
    $check_stmt->execute([$votable_id]);
    $content = $check_stmt->fetch();
    
    if (!$content) {
        echo json_encode(['success' => false, 'message' => 'Content not found']);
        exit;
    }
    
    // Prevent users from voting on their own content
    if ($content['author_id'] == $user['id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot vote on your own content']);
        exit;
    }
    
    $conn->beginTransaction();
    
    // Check if user has already voted
    $existing_vote_stmt = $conn->prepare("SELECT vote_type FROM votes WHERE user_id = ? AND votable_id = ? AND votable_type = ?");
    $existing_vote_stmt->execute([$user['id'], $votable_id, $votable_type]);
    $existing_vote = $existing_vote_stmt->fetch();
    
    if ($existing_vote) {
        if ($existing_vote['vote_type'] === $vote_type) {
            // Remove the vote (toggle off)
            $delete_stmt = $conn->prepare("DELETE FROM votes WHERE user_id = ? AND votable_id = ? AND votable_type = ?");
            $delete_stmt->execute([$user['id'], $votable_id, $votable_type]);
            $user_vote = null;
        } else {
            // Update the vote
            $update_stmt = $conn->prepare("UPDATE votes SET vote_type = ? WHERE user_id = ? AND votable_id = ? AND votable_type = ?");
            $update_stmt->execute([$vote_type, $user['id'], $votable_id, $votable_type]);
            $user_vote = $vote_type;
        }
    } else {
        // Insert new vote
        $insert_stmt = $conn->prepare("INSERT INTO votes (user_id, votable_id, votable_type, vote_type) VALUES (?, ?, ?, ?)");
        $insert_stmt->execute([$user['id'], $votable_id, $votable_type, $vote_type]);
        $user_vote = $vote_type;
    }
    
    // Get updated vote count
    $vote_count = getVoteCount($conn, $votable_id, $votable_type);
    
    // Update user reputation for the content author
    $reputation_stmt = $conn->prepare("UPDATE users SET reputation = (
        SELECT COALESCE(SUM(CASE WHEN v.vote_type = 'up' THEN 1 ELSE -1 END), 0)
        FROM votes v 
        WHERE (v.votable_type = 'question' AND v.votable_id IN (SELECT id FROM questions WHERE author_id = ?))
        OR (v.votable_type = 'answer' AND v.votable_id IN (SELECT id FROM answers WHERE author_id = ?))
    ) WHERE id = ?");
    $reputation_stmt->execute([$content['author_id'], $content['author_id'], $content['author_id']]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'vote_count' => $vote_count,
        'user_vote' => $user_vote,
        'message' => 'Vote recorded successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>