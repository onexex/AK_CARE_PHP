<?php
header('Content-Type: application/json');
require '../config.php';

$userId = $_GET['user_id'] ?? '';
if (empty($userId)) { echo json_encode(["status"=>"error","message"=>"user_id required"]); exit; }

$postStmt = $conn->prepare("SELECT content, created_at FROM community_posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$postStmt->bind_param("i", $userId);
$postStmt->execute();
$latestPost = $postStmt->get_result()->fetch_assoc();

$histStmt = $conn->prepare("SELECT p_patient, created_at FROM tblcrmlogsdata WHERE p_contact = ? ORDER BY created_at DESC LIMIT 1");
$histStmt->bind_param("s", $userId);
$histStmt->execute();
$latestHistory = $histStmt->get_result()->fetch_assoc();

$reqStmt = $conn->prepare("SELECT COUNT(*) AS c FROM teleconsult_requests WHERE phone_number = ? AND status = 'Pending'");
$reqStmt->bind_param("s", $userId);
$reqStmt->execute();
$pendingCount = $reqStmt->get_result()->fetch_assoc()['c'];

$notifStmt = $conn->prepare("SELECT COUNT(*) AS c FROM community_notifications WHERE user_id = ? AND is_read = 0");
$notifStmt->bind_param("i", $userId);
$notifStmt->execute();
$unreadNotifs = $notifStmt->get_result()->fetch_assoc()['c'];

echo json_encode([
    "status" => "success",
    "data" => [
        "latest_post" => $latestPost ? ["content" => mb_strimwidth($latestPost['content'] ?? '', 0, 100, '...'), "created_at" => $latestPost['created_at']] : null,
        "latest_history" => $latestHistory ? ["patient" => $latestHistory['p_patient'], "created_at" => $latestHistory['created_at']] : null,
        "pending_requests" => (int) $pendingCount,
        "unread_notifications" => (int) $unreadNotifs,
    ]
]);