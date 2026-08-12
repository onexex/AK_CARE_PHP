<?php
header('Content-Type: application/json');
require '../config.php';

function formatUser($row) {
    $fullName = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
    return [
        'id'        => $row['user_id'],
        'contact'   => $row['contact_number'] ?? $row['contact'] ?? '',
        'full_name' => $fullName ?: ($row['contact_number'] ?? $row['contact'] ?? 'Unknown'),
    ];
}

// Community content is for members. Every branch below needs a session, so it
// is established once here rather than three times.
$member = require_member($conn);
$userId = $member['member_id'];

$method = $_SERVER['REQUEST_METHOD'];

// ── GET comments for a post ──
if ($method === 'GET') {
    $postId = $_GET['post_id'] ?? '';
    if (empty($postId)) { echo json_encode(["status"=>"error","message"=>"post_id required"]); exit; }

    $stmt = $conn->prepare("SELECT c.*, m.contact_number AS contact, m.firstname, m.lastname FROM community_comments c LEFT JOIN members m ON c.user_id = m.member_id WHERE c.post_id = ? ORDER BY c.created_at ASC");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $comments = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rStmt = $conn->prepare("SELECT r.*, m2.contact_number AS contact, m2.firstname, m2.lastname FROM community_comment_replies r LEFT JOIN members m2 ON r.user_id = m2.member_id WHERE r.comment_id = ? ORDER BY r.created_at ASC");
        $rStmt->bind_param("i", $row['id']);
        $rStmt->execute();
        $repliesRaw = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $replies = [];
        foreach ($repliesRaw as $rr) {
            $replies[] = [
                'id'        => $rr['id'],
                'comment_id'=> $rr['comment_id'],
                'user_id'   => $rr['user_id'],
                'reply'     => $rr['reply'],
                'created_at'=> $rr['created_at'],
                'user'      => formatUser($rr),
            ];
        }

        $comments[] = [
            'id'         => $row['id'],
            'post_id'    => $row['post_id'],
            'user_id'    => $row['user_id'],
            'comment'    => $row['comment'],
            'created_at' => $row['created_at'],
            'user'       => formatUser($row),
            'replies'    => $replies,
        ];
    }
    echo json_encode(["status"=>"success","data"=>$comments]);
    exit;
}

// ── POST: Create comment ──
if ($method === 'POST') {
    $postId  = $_POST['post_id'] ?? '';
    $comment = $_POST['comment'] ?? '';

    if (empty($postId) || empty($comment)) {
        echo json_encode(["status"=>"error","message"=>"Missing fields"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO community_comments (post_id, user_id, comment) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $postId, $userId, $comment);
    $stmt->execute();

    $postRow = $conn->query("SELECT user_id FROM community_posts WHERE id = $postId")->fetch_assoc();
    if ($postRow && $postRow['user_id'] != $userId) {
        $notify = $conn->prepare("INSERT INTO community_notifications (user_id, type, post_id, comment_id, from_user_id) VALUES (?, 'comment', ?, ?, ?)");
        $cid = $conn->insert_id;
        // from_user_id is a member_id ('AKM-787'), bound as "i" here until now —
        // so every comment notification recorded that it came from member 0.
        $notify->bind_param("siis", $postRow['user_id'], $postId, $cid, $userId);
        $notify->execute();
    }

    echo json_encode(["status"=>"success","message"=>"Comment added","comment_id"=>$conn->insert_id]);
    exit;
}

// ── DELETE comment ──
$id = $_POST['id'] ?? '';
if (empty($id)) { echo json_encode(["status"=>"error","message"=>"Missing fields"]); exit; }

$stmt = $conn->prepare("DELETE FROM community_comments WHERE id = ? AND user_id = ?");
$stmt->bind_param("is", $id, $userId);
$stmt->execute();

echo json_encode(["status"=>"success","message"=>"Comment deleted"]);