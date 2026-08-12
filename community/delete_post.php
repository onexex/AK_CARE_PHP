<?php
header('Content-Type: application/json');
require '../config.php';

// The ownership check below was only ever as good as this value: supplying the
// post owner's member_id satisfied it. It now comes from the session.
$member = require_member($conn);
$userId = $member['member_id'];
$postId = $_POST['post_id'] ?? '';

if (empty($postId)) {
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