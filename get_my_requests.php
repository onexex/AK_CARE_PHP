<?php
// Started explicitly. The ob_clean() calls below assume a buffer exists; with
// output_buffering off there is none, and the resulting PHP notice lands inside
// the JSON body and breaks parsing on the client.
ob_start();
header('Content-Type: application/json');
include 'config.php';

// The member_id the app holds in its session, the same value the request was
// filed and is cancelled under.
$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

// Rows written since the member_id column exists are matched on it exactly.
// Older rows carry NULL and can only be matched on the number they were filed
// with — which is stored in members as either 09XXXXXXXXX or 9XXXXXXXXX, and in
// this table in whichever form the app happened to hold at the time, so every
// spelling has to be tried or the member's own history disappears.
$phones = ph_member_variants($conn, $user_id);

try {
    $columns = "request_id, consultation_reason, preferred_date, phone_number, status, created_at";

    if ($phones === []) {
        $sql = "SELECT {$columns}
                FROM teleconsult_requests
                WHERE member_id = ?
                ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $user_id);
    } else {
        $in = implode(',', array_fill(0, count($phones), '?'));
        $sql = "SELECT {$columns}
                FROM teleconsult_requests
                WHERE member_id = ?
                   OR (member_id IS NULL AND phone_number IN ({$in}))
                ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s' . str_repeat('s', count($phones)), $user_id, ...$phones);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    ob_clean(); 
    echo json_encode([
        "status" => "success",
        "data" => $requests
    ]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}

if (isset($stmt)) $stmt->close();
$conn->close();
?>