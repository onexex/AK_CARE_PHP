<?php
header('Content-Type: application/json');
require 'config.php';

$migrations = [
    "ALTER TABLE community_posts           MODIFY user_id VARCHAR(50) NOT NULL",
    "ALTER TABLE community_post_likes      MODIFY user_id VARCHAR(50) NOT NULL",
    "ALTER TABLE community_comments        MODIFY user_id VARCHAR(50) NOT NULL",
    "ALTER TABLE community_comment_replies MODIFY user_id VARCHAR(50) NOT NULL",
    "ALTER TABLE community_notifications   MODIFY user_id VARCHAR(50) NOT NULL",
    "ALTER TABLE community_notifications   MODIFY from_user_id VARCHAR(50) NOT NULL",
];

$results = [];
foreach ($migrations as $sql) {
    try {
        $conn->query($sql);
        $results[] = ["sql" => $sql, "status" => "ok"];
    } catch (Exception $e) {
        $results[] = ["sql" => $sql, "status" => "error", "error" => $e->getMessage()];
    }
}

echo json_encode(["status" => "success", "results" => $results]);