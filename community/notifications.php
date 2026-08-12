<?php
header('Content-Type: application/json');
require '../config.php';

$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    echo json_encode(["status" => "error", "message" => "user_id required"]);
    exit;
}

$stmt = $conn->prepare("SELECT n.*, m.contact_number AS contact, m.firstname, m.lastname, p.content AS post_preview
    FROM community_notifications n
    LEFT JOIN members m ON n.from_user_id = m.member_id
    LEFT JOIN community_posts p ON n.post_id = p.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50");
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $fullName = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));

    $notifications[] = [
        'id'           => $row['id'],
        'type'         => $row['type'],
        'post_id'      => $row['post_id'],
        'comment_id'   => $row['comment_id'],
        'is_read'      => (bool) $row['is_read'],
        'created_at'   => $row['created_at'],
        'from_user'    => [
            'id'        => $row['from_user_id'],
            'contact'   => $row['contact'],
            'full_name' => $fullName ?: ($row['contact'] ?? 'Unknown'),
        ],
        'post_preview' => mb_strimwidth($row['post_preview'] ?? '', 0, 80, '...'),
    ];
}

// Mark all as read.
//
// Bound, not interpolated. This was "... WHERE user_id = $userId" with $userId
// taken straight from $_GET — an injection point on a write, where user_id=1 OR 1=1
// would clear every member's notifications. The SELECT above was already bound;
// only this statement was not.
$markRead = $conn->prepare("UPDATE community_notifications SET is_read = 1 WHERE user_id = ?");
$markRead->bind_param("s", $userId);
$markRead->execute();

echo json_encode(["status" => "success", "data" => $notifications]);