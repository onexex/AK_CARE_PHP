<?php
 

include 'config.php';

if (!isset($_POST['phone_number'])) {
    echo json_encode(["status" => "error", "message" => "No phone number provided"]);
    exit;
}

$phone = $_POST['phone_number'];

if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$checkUser = $conn->prepare("SELECT id FROM tblcrms WHERE contact = ?");
$checkUser->bind_param("s", $phone);
$checkUser->execute();
$userResult = $checkUser->get_result();

if ($userResult->num_rows > 0) {
    $otp = rand(100000, 999999);
    
    $insertOtp = $conn->prepare("INSERT INTO otp_logs (phone_number, otp_code) VALUES (?, ?)");
    $insertOtp->bind_param("ss", $phone, $otp);
    
    if($insertOtp->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "OTP generated",
            "otp" => $otp 
        ]);
    } else {
        // I-return ang mismong error ng MySQL para malaman kung bakit failed
        echo json_encode(["status" => "error", "message" => "SQL Error: " . $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Phone number not registered"]);
}
?>