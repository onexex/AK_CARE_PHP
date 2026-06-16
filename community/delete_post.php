<?php
header('Content-Type: application/json');
require '../config.php';

$postId = $_POST['post_id'] ?? '';
$userId = $_POST['user_id'] ?? '';

if (empty($postId) || empty($userId)) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

$stmt = $conn->prepare("UPDATE community_posts SET status = 'deleted' WHERE id = ? AND user_id = ?");
$stmt->bind_param("is", $postId, $userId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Post deleted"]);
} else {
    echo json_encode(["status" => "error", "message" => "Post not found or not authorized"]);
}