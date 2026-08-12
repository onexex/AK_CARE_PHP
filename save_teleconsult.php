<?php

ob_start();
header('Content-Type: application/json');

include 'config.php'; 

$user_id = $_POST['user_id'] ?? '';
$reason = $_POST['consultation_reason'] ?? '';
$preferred_date = $_POST['preferred_date'] ?? '';
$phone = $_POST['phone_number'] ?? '';

if (empty($user_id) || empty($reason) || empty($preferred_date)) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Please provide all required fields."]);
    exit;
}

// Store one spelling only. Existing rows are 09XXXXXXXXX, and the app sends
// whichever form the member's row happens to hold, so writing it through
// unchanged was fragmenting the table against itself.
$phone = ph_mobile_local($phone);

if ($phone === '') {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Invalid mobile number"]);
    exit;
}

// Refuse to file a request under a member_id that does not exist, so the column
// cannot fill up with values nothing can ever match.
$memberCheck = $conn->prepare("SELECT 1 FROM members WHERE member_id = ? LIMIT 1");
$memberCheck->bind_param('s', $user_id);
$memberCheck->execute();

if (!$memberCheck->get_result()->fetch_row()) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Unknown member"]);
    exit;
}
$memberCheck->close();

try {
    $conn->begin_transaction();

    // $user_id is the member_id the app holds in its session. It was previously
    // checked for emptiness and then dropped, leaving phone_number as the only
    // link back to the member — and numbers are neither unique nor stored in one
    // spelling. Recording it makes ownership exact for every new row.
    $sql = "INSERT INTO teleconsult_requests
            ( member_id, consultation_reason, preferred_date, phone_number)
            VALUES ( ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssss", $user_id, $reason, $preferred_date, $phone);

    if ($stmt->execute()) {
        $conn->commit();
        
        ob_clean();
        echo json_encode([
            "status" => "success", 
            "message" => "Teleconsult request submitted successfully!"
        ]);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

} catch (Exception $e) {
    $conn->rollback();
    
    ob_clean();
    echo json_encode([
        "status" => "error", 
        "message" => "Server error: " . $e->getMessage()
    ]);
}

if (isset($stmt)) $stmt->close();
$conn->close();
?>