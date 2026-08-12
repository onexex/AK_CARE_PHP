<?php
header('Content-Type: application/json');
include 'config.php';

$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

// The app sends the member's contact number, which is stored in members in
// either 09XXXXXXXXX or 9XXXXXXXXX form, while rows here were written in
// whichever form the app happened to hold at the time. Matching on one spelling
// hid every request from the member who filed it.
$phones = ph_mobile_variants($user_id);

if ($phones === []) {
    echo json_encode(["status" => "error", "message" => "Invalid mobile number"]);
    exit;
}

try {
    $in = implode(',', array_fill(0, count($phones), '?'));
    $sql = "SELECT request_id, consultation_reason, preferred_date, phone_number, status, created_at
            FROM teleconsult_requests
            WHERE phone_number IN ({$in})
            ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($phones)), ...$phones);
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