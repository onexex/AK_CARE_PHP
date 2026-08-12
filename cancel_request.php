<?php
header('Content-Type: application/json');
include 'config.php';

$id = $_POST['id'] ?? '';
// The member's contact number, the same value get_my_requests.php is called
// with. teleconsult_requests has no member_id column, so the phone number is
// the only link between a request and the member who filed it.
$user_id = $_POST['user_id'] ?? '';

if (empty($id)) {
    echo json_encode(["status" => "error", "message" => "ID is required"]);
    exit;
}

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

// Stored numbers come in both 09XXXXXXXXX and 9XXXXXXXXX form, so the owner
// check has to compare against every variant — the same match get_my_requests.php
// uses to decide the request is yours in the first place. Without this the
// UPDATE keyed on request_id alone, a sequential integer, so any member could
// cancel any other member's pending consultation.
$phones = ph_mobile_variants($user_id);

if ($phones === []) {
    echo json_encode(["status" => "error", "message" => "Invalid mobile number"]);
    exit;
}

try {
    $in = implode(',', array_fill(0, count($phones), '?'));
    $sql = "UPDATE teleconsult_requests
            SET status = 'Cancelled'
            WHERE request_id = ?
              AND status = 'Pending'
              AND phone_number IN ({$in})";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i' . str_repeat('s', count($phones)), $id, ...$phones);

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
