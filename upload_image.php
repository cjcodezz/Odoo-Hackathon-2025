<?php
$upload_dir = 'uploads/images/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded.']);
    exit;
}

$file = $_FILES['file'];
$filename = uniqid() . '-' . basename($file['name']);
$target = $upload_dir . $filename;

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowed)) {
    http_response_code(415);
    echo json_encode(['error' => 'Unsupported file type.']);
    exit;
}

if (move_uploaded_file($file['tmp_name'], $target)) {
    echo json_encode(['location' => $target]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file.']);
}
?>
