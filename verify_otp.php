<?php
// verify_otp.php
header('Content-Type: application/json');
include 'config.php';

// I-setup ang Laravel App Key para sa decryption
$appKey = "base64:NusO0N5yu2WM4bbP7qDg9DfJc9FpglsPgtvSapEHxpM=";

// Function para ma-decrypt ang data na galing sa Laravel 'encrypted' cast
function decryptLaravelData($payload, $appKeyBase64) {
    try {
        $key = base64_decode(substr($appKeyBase64, 7));
        $data = json_decode(base64_decode($payload), true);

        if (!$data || !isset($data['iv'], $data['value'])) {
            return null;
        }

        $iv = base64_decode($data['iv']);
        $value = $data['value'];

        // AES-256-CBC decryption
        $decrypted = openssl_decrypt($value, 'aes-256-cbc', $key, 0, $iv);

        if ($decrypted === false) return null;

        // Laravel serializes data, so we attempt to unserialize
        $unserialized = @unserialize($decrypted);
        return ($unserialized !== false) ? $unserialized : $decrypted;
    } catch (Exception $e) {
        return null;
    }
}

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
 
    $userStmt = $conn->prepare("SELECT contact, id, m_fname, m_surname FROM tblcrms WHERE contact = ?");
    $userStmt->bind_param("s", $phone);
    $userStmt->execute();
    $userData = $userStmt->get_result()->fetch_assoc();

    if ($userData) {
         
        $decryptedFname = decryptLaravelData($userData['m_fname'], $appKey);
        $decryptedSurname = decryptLaravelData($userData['m_surname'], $appKey);

    
        echo json_encode([
            "status" => "success",
            "user" => [
                "id" => $userData['id'],
                "contact" => $userData['contact'],
                "full_name" => trim(($decryptedFname ?? '') . ' ' . ($decryptedSurname ?? '')),
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