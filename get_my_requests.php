<?php
header('Content-Type: application/json');
include 'config.php';

$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

try {
    $sql = "SELECT request_id, consultation_reason, preferred_date, phone_number, status, created_at 
            FROM teleconsult_requests 
            WHERE phone_number = ? 
            ORDER BY created_at DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $user_id);
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