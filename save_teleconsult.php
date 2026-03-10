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

try {
    $conn->begin_transaction();

    $sql = "INSERT INTO teleconsult_requests 
            ( consultation_reason, preferred_date, phone_number) 
            VALUES ( ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sss",  $reason, $preferred_date, $phone);

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