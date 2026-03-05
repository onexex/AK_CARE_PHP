<?php
header('Content-Type: application/json');
include 'config.php';
//test
$id = $_POST['id'] ?? '';

if (empty($id)) {
    echo json_encode(["status" => "error", "message" => "ID is required"]);
    exit;
}

try {
    // I-update ang status sa 'Cancelled'
    $stmt = $conn->prepare("UPDATE teleconsult_requests SET status = 'Cancelled' WHERE id = ? AND status = 'Pending'");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Request cancelled."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Cannot cancel. Request might be processed already."]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>