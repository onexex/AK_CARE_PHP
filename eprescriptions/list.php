<?php
header('Content-Type: application/json');
require '../config.php';

$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    echo json_encode(["status" => "error", "message" => "user_id required"]);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM eprescriptions WHERE user_id = ? ORDER BY created_at DESC");
// user_id is a member_id ('AKM-787'), not an integer — bind as a string.
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

$prescriptions = [];
while ($row = $result->fetch_assoc()) {
    // Get items
    $itemsStmt = $conn->prepare("SELECT * FROM eprescription_items WHERE prescription_id = ?");
    $itemsStmt->bind_param("i", $row['id']);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = $item;
    }

    $prescriptions[] = [
        'id'          => $row['id'],
        'user_id'     => $row['user_id'],
        'doctor_name' => $row['doctor_name'],
        'diagnosis'   => $row['diagnosis'],
        'notes'       => $row['notes'],
        'status'      => $row['status'],
        'created_at'  => $row['created_at'],
        'items'       => $items,
        'items_count' => count($items),
    ];
}

echo json_encode(["status" => "success", "data" => $prescriptions]);