<?php
header('Content-Type: application/json');
require 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $userId = $_GET['user_id'] ?? '';
    if (empty($userId)) { echo json_encode(["status"=>"error","message"=>"user_id required"]); exit; }
    $stmt = $conn->prepare("SELECT * FROM medical_cert_requests WHERE user_id = ? ORDER BY created_at DESC");
    // user_id is a member_id ('AKM-787'), not an integer — bind as a string.
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(["status" => "success", "data" => $data]);
    exit;
}

if ($method === 'POST') {
    $userId  = $_POST['user_id'] ?? '';
    $reason  = $_POST['reason'] ?? '';
    $date    = $_POST['preferred_date'] ?? '';
    if (empty($userId) || empty($reason)) { echo json_encode(["status"=>"error","message"=>"Missing fields"]); exit; }

    $stmt = $conn->prepare("INSERT INTO medical_cert_requests (user_id, reason, preferred_date) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $userId, $reason, $date);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Medical certificate request submitted"]);
    exit;
}