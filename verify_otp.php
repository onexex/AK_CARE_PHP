<?php
// verify_otp.php
header('Content-Type: application/json');
include 'config.php';

$phone = $_POST['phone_number'] ?? '';
$otp = $_POST['otp_code'] ?? '';

if (empty($phone) || empty($otp)) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

// 1. Check the most recent OTP log
$stmt = $conn->prepare("SELECT * FROM otp_logs WHERE phone_number = ? AND otp_code = ? AND is_verified = 0 ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("ss", $phone, $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    
    $update = $conn->prepare("UPDATE otp_logs SET is_verified = 1 WHERE phone_number = ? AND otp_code = ?");
    $update->bind_param("ss", $phone, $otp);
    $update->execute();
 
    $userStmt = $conn->prepare("SELECT member_id, contact_number, firstname, lastname FROM members WHERE contact_number = ?");
    $userStmt->bind_param("s", $phone);
    $userStmt->execute();
    $userData = $userStmt->get_result()->fetch_assoc();

    if ($userData) {
        echo json_encode([
            "status" => "success",
            "user" => [
                "id" => $userData['member_id'],
                "contact" => $userData['contact_number'],
                "full_name" => trim($userData['firstname'] . ' ' . $userData['lastname']),
                "rank" => "Member"
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Member record not found"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid or expired OTP"]);
}
?>
