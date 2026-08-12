<?php
header('Content-Type: application/json');
require '../config.php';

// The author is whoever is signed in. Posting under someone else's name was a
// matter of changing one field in the request body.
$member  = require_member($conn);
$userId  = $member['member_id'];
$content = $_POST['content'] ?? '';
$images  = $_POST['images'] ?? '[]'; // JSON array of base64 or paths

if (empty($content)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO community_posts (user_id, content) VALUES (?, ?)");
$stmt->bind_param("ss", $userId, $content);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Failed to create post"]);
    exit;
}

$postId = $conn->insert_id;

// Save images if any
$imageList = json_decode($images, true);
if (is_array($imageList) && count($imageList) > 0) {
    $imgStmt = $conn->prepare("INSERT INTO community_post_images (post_id, image_path, sort_order) VALUES (?, ?, ?)");
    foreach ($imageList as $i => $path) {
        $imgStmt->bind_param("isi", $postId, $path, $i);
        $imgStmt->execute();
    }
}

echo json_encode([
    "status"  => "success",
    "message" => "Post created successfully",
    "post_id" => $postId
]);