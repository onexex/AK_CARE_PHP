<?php
header('Content-Type: application/json');
require 'config.php';

// Both branches act on the signed-in member's own certificate requests.
$member = require_member($conn);
$userId = $member['member_id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT * FROM medical_cert_requests WHERE user_id = ? ORDER BY created_at DESC");
    // user_id is a member_id ('AKM-787'), not an integer — bind as a string.
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(["status" => "success", "data" => $data]);
    exit;
}

if ($method === 'POST') {
    $reason  = $_POST['reason'] ?? '';
    $date    = $_POST['preferred_date'] ?? '';
    if (empty($reason)) { echo json_encode(["status"=>"error","message"=>"Missing fields"]); exit; }

    $stmt = $conn->prepare("INSERT INTO medical_cert_requests (user_id, reason, preferred_date) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $userId, $reason, $date);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Medical certificate request submitted"]);
    exit;
}