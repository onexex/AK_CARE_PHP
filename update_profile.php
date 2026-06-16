<?php
header('Content-Type: application/json');
require 'config.php';

$userId    = $_POST['user_id'] ?? '';
$contact   = $_POST['contact'] ?? '';
$mFname    = $_POST['m_fname'] ?? '';
$mSurname  = $_POST['m_surname'] ?? '';

if (empty($userId)) {
    echo json_encode(["status" => "error", "message" => "User ID required"]);
    exit;
}

$updates = [];
$types   = "";
$params  = [];

if (!empty($contact)) {
    $updates[] = "contact_number = ?";
    $types    .= "s";
    $params[]  = $contact;
}

if (!empty($mFname)) {
    $updates[] = "firstname = ?";
    $types    .= "s";
    $params[]  = $mFname;
}

if (!empty($mSurname)) {
    $updates[] = "lastname = ?";
    $types    .= "s";
    $params[]  = $mSurname;
}

if (empty($updates)) {
    echo json_encode(["status" => "error", "message" => "No fields to update"]);
    exit;
}

$sql    = "UPDATE members SET " . implode(", ", $updates) . " WHERE member_id = ?";
$types .= "s";
$params[] = $userId;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Profile updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Update failed: " . $conn->error]);
}
