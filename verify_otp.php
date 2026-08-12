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

// Canonical 10-digit form — check_user.php logs the OTP under this, and the
// member lookup below accepts any spelling the number is stored in.
$core = ph_mobile_core($phone);

if ($core === '') {
    echo json_encode(["status" => "error", "message" => "Please enter a valid mobile number"]);
    exit;
}

// 1. Check the most recent OTP log. A code is usable only while unverified and
//    inside its validity window; the cutoff is computed by MySQL rather than PHP
//    so a timezone difference between the two cannot widen or shrink it.
//    The interval is an integer constant defined in config.php, never user input.
$window = (int) OTP_VALIDITY_MINUTES;
$stmt = $conn->prepare(
    "SELECT * FROM otp_logs
     WHERE phone_number = ?
       AND otp_code = ?
       AND is_verified = 0
       AND created_at >= (NOW() - INTERVAL {$window} MINUTE)
     ORDER BY created_at DESC
     LIMIT 1"
);
$stmt->bind_param("ss", $core, $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {

    $update = $conn->prepare("UPDATE otp_logs SET is_verified = 1 WHERE phone_number = ? AND otp_code = ?");
    $update->bind_param("ss", $core, $otp);
    $update->execute();

    $userData = ph_find_member($conn, $phone, 'member_id, contact_number, firstname, lastname');

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
