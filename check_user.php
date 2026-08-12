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

// Canonical 10-digit form. Every lookup and every log row below uses this, so it
// no longer matters which of the stored formats a member happens to have.
$core = ph_mobile_core($phone);

if ($core === '') {
    echo json_encode(["status" => "error", "message" => "Please enter a valid mobile number"]);
    exit;
}

// 1. Check if phone number is registered, under any of its stored spellings
if (ph_find_member($conn, $phone) === null) {
    echo json_encode(["status" => "error", "message" => "Phone number not registered"]);
    exit;
}

// 2. Generate 4-digit OTP
$otp = rand(1000, 9999);

// 3. Store OTP in logs, keyed on the canonical form so verification matches
//    even if the number is typed differently the second time.
$insertOtp = $conn->prepare("INSERT INTO otp_logs (phone_number, otp_code) VALUES (?, ?)");
$insertOtp->bind_param("ss", $core, $otp);

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
$message    = "Your AK MIYEMBRO verification code is: $otp. It expires in "
            . OTP_VALIDITY_MINUTES . " minutes.";

// 5. Send OTP. Which provider is decided by the base URL stored in `gateways`.`ip`,
//    so switching providers is a one-row DB change and no code edit.
//
//    Brevo   — ip: https://api.brevo.com/v3, username: API key, password: sender name
//    TextBee — ip: https://api.textbee.dev/api/v1, username: API key, password: device id
$isBrevo = stripos($baseUrl, 'brevo') !== false;

if ($isBrevo) {
    // Sender must be alphanumeric, 11 characters max.
    $sender = $deviceKey !== '' ? substr($deviceKey, 0, 11) : 'AKMIYEMBRO';

    $url = rtrim($baseUrl, '/') . '/transactionalSMS/sms';

    $payload = json_encode([
        'type'      => 'transactional',
        'sender'    => $sender,
        // Brevo wants international format with no leading '+', e.g. 639171234567.
        'recipient' => ph_mobile_international($phone),
        'content'   => $message,
    ]);

    $headers = [
        'api-key: ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
    ];
} else {
    $url = "{$baseUrl}/gateway/devices/{$deviceKey}/send-sms";

    $payload = json_encode([
        // The TextBee gateway is an on-site Android handset, so it dials locally.
        'recipients' => [ph_mobile_local($phone)],
        'message'    => $message,
    ]);

    $headers = [
        'x-api-key: ' . $apiKey,
        'Accept: application/json',
        'Content-Type: application/json',
    ];
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
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
        "message" => "SMS gateway returned HTTP $httpCode: " . $responseBody
    ]);
    exit;
}

// 6. Success — SMS sent
echo json_encode([
    "status"  => "success",
    "message" => "Verification code sent to your device"
]);