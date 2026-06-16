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

// 1. Check if phone number is registered
$checkUser = $conn->prepare("SELECT member_id FROM members WHERE contact_number = ?");
$checkUser->bind_param("s", $phone);
$checkUser->execute();
$userResult = $checkUser->get_result();

if ($userResult->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Phone number not registered"]);
    exit;
}

// 2. Generate 4-digit OTP
$otp = rand(1000, 9999);

// 3. Store OTP in logs
$insertOtp = $conn->prepare("INSERT INTO otp_logs (phone_number, otp_code) VALUES (?, ?)");
$insertOtp->bind_param("ss", $phone, $otp);

if (!$insertOtp->execute()) {
    echo json_encode(["status" => "error", "message" => "SQL Error: " . $conn->error]);
    exit;
}

// 4. Load SMS gateway config from database
$gatewayResult = $conn->query("SELECT * FROM gateways LIMIT 1");

if (!$gatewayResult || $gatewayResult->num_rows === 0) {
    // No gateway configured — fall back to returning OTP in response
    echo json_encode([
        "status" => "success",
        "message" => "OTP generated (no SMS gateway configured)",
        "otp"    => $otp
    ]);
    exit;
}

$gateway = $gatewayResult->fetch_assoc();

$apiKey     = $gateway['username'];
$deviceKey  = $gateway['password'];
$baseUrl    = $gateway['ip'] ?? 'https://api.textbee.dev/api/v1';
$recipient  = $phone;

// 5. Send OTP via TextBee SMS
$url = "{$baseUrl}/gateway/devices/{$deviceKey}/send-sms";

$payload = json_encode([
    'recipients' => [$recipient],
    'message'    => "Your AK MIYEMBRO verification code is: $otp"
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'x-api-key: ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 15,
]);

$responseBody = curl_exec($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError    = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode([
        "status"  => "error",
        "message" => "SMS gateway connection failed: " . $curlError
    ]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    echo json_encode([
        "status"  => "error",
        "message" => "SMS gateway returned HTTP $httpCode. The device may be offline."
    ]);
    exit;
}

// 6. Success — SMS sent
echo json_encode([
    "status"  => "success",
    "message" => "Verification code sent to your device"
]);