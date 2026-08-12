<?php
header('Content-Type: application/json');
require '../config.php';

// The author of the reply is whoever is signed in, not whoever the request says.
$member    = require_member($conn);
$userId    = $member['member_id'];
$commentId = $_POST['comment_id'] ?? '';
$reply     = $_POST['reply'] ?? '';

if (empty($commentId) || empty($reply)) {
    echo json_encode(["status" => "error", "message" => "Missing fields"]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO community_comment_replies (comment_id, user_id, reply) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $commentId, $userId, $reply);
$stmt->execute();

// Notify comment owner
$commentRow = $conn->query("SELECT user_id, post_id FROM community_comments WHERE id = $commentId")->fetch_assoc();
if ($commentRow && $commentRow['user_id'] != $userId) {
    $notify = $conn->prepare("INSERT INTO community_notifications (user_id, type, post_id, comment_id, from_user_id) VALUES (?, 'reply', ?, ?, ?)");
    $notify->bind_param("siis", $commentRow['user_id'], $commentRow['post_id'], $commentId, $userId);
    $notify->execute();
}

echo json_encode(["status" => "success", "message" => "Reply added", "reply_id" => $conn->insert_id]);