<?php
header('Content-Type: application/json');
include 'config.php';

// Whoever is signed in — the same member save_teleconsult.php files a request
// under. Naming a member in the body used to be enough to cancel for them.
$member  = require_member($conn);
$user_id = $member['member_id'];
$id      = $_POST['id'] ?? '';

if (empty($id)) {
    echo json_encode(["status" => "error", "message" => "ID is required"]);
    exit;
}

// Rows written since the member_id column exists are matched on it exactly.
// Older rows carry NULL and can only be matched on the number they were filed
// with, in any of its spellings — approximate, since numbers are shared, but it
// is that or lock those members out of cancelling their own requests.
$phones = ph_member_variants($conn, $user_id);

try {
    if ($phones === []) {
        $sql = "UPDATE teleconsult_requests
                SET status = 'Cancelled'
                WHERE request_id = ?
                  AND status = 'Pending'
                  AND member_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $id, $user_id);
    } else {
        $in = implode(',', array_fill(0, count($phones), '?'));
        $sql = "UPDATE teleconsult_requests
                SET status = 'Cancelled'
                WHERE request_id = ?
                  AND status = 'Pending'
                  AND ( member_id = ?
                        OR (member_id IS NULL AND phone_number IN ({$in})) )";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is' . str_repeat('s', count($phones)), $id, $user_id, ...$phones);
    }

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Request cancelled."]);
    } else {
        // Deliberately one message for all three misses — already processed,
        // no such request, or someone else's. Distinguishing them would let a
        // caller probe which request_ids exist and who they belong to.
        echo json_encode(["status" => "error", "message" => "Cannot cancel. Request might be processed already."]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
