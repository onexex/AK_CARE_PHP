<?php
header('Content-Type: application/json');
require '../config.php';

$postId = $_POST['post_id'] ?? '';
$userId = $_POST['user_id'] ?? '';
$reason = $_POST['reason'] ?? '';

if (empty($postId) || empty($userId) || empty($reason)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// Check if already reported by this user
$check = $conn->prepare("SELECT id FROM community_reports WHERE post_id = ? AND user_id = ?");
$check->bind_param("is", $postId, $userId);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "You have already reported this post"]);
    exit;
}

// Insert report
$stmt = $conn->prepare("INSERT INTO community_reports (post_id, user_id, reason) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $postId, $userId, $reason);

if ($stmt->execute()) {
    // Count total reports for this post
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM community_reports WHERE post_id = $postId");
    $count = $countResult->fetch_assoc()['total'];

    // Auto-hide post if 5+ reports
    if ($count >= 5) {
        $conn->query("UPDATE community_posts SET status = 'reported' WHERE id = $postId");
        $msg = "Report submitted. This post has been flagged for review.";
    } else {
        $msg = "Report submitted. Thank you for helping keep our community safe.";
    }

    echo json_encode(["status" => "success", "message" => $msg]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to submit report"]);
}