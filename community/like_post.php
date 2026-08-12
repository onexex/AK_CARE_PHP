<?php
header('Content-Type: application/json');
require '../config.php';

// The like belongs to the signed-in member: liking on someone else's behalf, or
// clearing their like, was previously just a matter of naming them.
$member = require_member($conn);
$userId = $member['member_id'];
$postId = $_POST['post_id'] ?? '';

if (empty($postId)) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

// Toggle like — insert if not exists, delete if exists
$check = $conn->prepare("SELECT id FROM community_post_likes WHERE post_id = ? AND user_id = ?");
$check->bind_param("is", $postId, $userId);
$check->execute();
$exists = $check->get_result()->num_rows > 0;

if ($exists) {
    $del = $conn->prepare("DELETE FROM community_post_likes WHERE post_id = ? AND user_id = ?");
    $del->bind_param("is", $postId, $userId);
    $del->execute();
    $action = 'unliked';
} else {
    $ins = $conn->prepare("INSERT INTO community_post_likes (post_id, user_id) VALUES (?, ?)");
    $ins->bind_param("is", $postId, $userId);
    $ins->execute();

    // Notify post owner (if not self-like)
    $postRow = $conn->query("SELECT user_id FROM community_posts WHERE id = $postId")->fetch_assoc();
    if ($postRow && $postRow['user_id'] != $userId) {
        $notify = $conn->prepare("INSERT INTO community_notifications (user_id, type, post_id, from_user_id) VALUES (?, 'like', ?, ?)");
        $notify->bind_param("sis", $postRow['user_id'], $postId, $userId);
        $notify->execute();
    }
    $action = 'liked';
}

$likeCount = $conn->query("SELECT COUNT(*) AS c FROM community_post_likes WHERE post_id = $postId")->fetch_assoc()['c'];

echo json_encode([
    "status"     => "success",
    "action"     => $action,
    "liked_by_me"=> $action === 'liked',
    "like_count" => (int) $likeCount
]);