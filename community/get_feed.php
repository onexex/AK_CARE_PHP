<?php
header('Content-Type: application/json');
require '../config.php';

$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;
$userId = $_GET['user_id'] ?? '0';

$sql = "SELECT p.*,
               (SELECT COUNT(*) FROM community_post_likes WHERE post_id = p.id) AS like_count,
               (SELECT COUNT(*) FROM community_post_likes WHERE post_id = p.id AND user_id = ?) AS liked_by_me,
               (SELECT COUNT(*) FROM community_comments WHERE post_id = p.id) AS comment_count,
               m.contact_number AS contact, m.firstname, m.lastname
        FROM community_posts p
        LEFT JOIN members m ON p.user_id = m.member_id
        WHERE p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT $limit OFFSET $offset";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($row = $result->fetch_assoc()) {
    $fullName = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));

    $posts[] = [
        'id'            => $row['id'],
        'user_id'       => $row['user_id'],
        'content'       => $row['content'],
        'status'        => $row['status'],
        'created_at'    => $row['created_at'],
        'updated_at'    => $row['updated_at'],
        'like_count'    => (int) $row['like_count'],
        'liked_by_me'   => (int) $row['liked_by_me'] > 0,
        'comment_count' => (int) $row['comment_count'],
        'user' => [
            'id'        => $row['user_id'],
            'contact'   => $row['contact'],
            'full_name' => $fullName ?: ($row['contact'] ?? 'Unknown'),
        ],
    ];
}

// Fetch images for all posts in this page
if (count($posts) > 0) {
    $postIds = implode(',', array_column($posts, 'id'));
    $imgSql = "SELECT post_id, image_path FROM community_post_images WHERE post_id IN ($postIds) ORDER BY sort_order";
    $imgResult = $conn->query($imgSql);
    $imagesByPost = [];
    while ($img = $imgResult->fetch_assoc()) {
        $imagesByPost[$img['post_id']][] = $img['image_path'];
    }
    foreach ($posts as &$post) {
        $post['images'] = $imagesByPost[$post['id']] ?? [];
    }
}

echo json_encode([
    'status' => 'success',
    'data'   => $posts,
    'page'   => $page,
    'has_more' => count($posts) >= $limit,
]);