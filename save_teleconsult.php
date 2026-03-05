<?php
// Linisin agad ang output buffer para iwas sa extra characters/warnings
ob_start();
header('Content-Type: application/json');

include 'config.php'; 

// Kunin ang POST data
$user_id = $_POST['user_id'] ?? '';
$reason = $_POST['consultation_reason'] ?? '';
$preferred_date = $_POST['preferred_date'] ?? '';
$phone = $_POST['phone_number'] ?? '';

// Basic validation
if (empty($user_id) || empty($reason) || empty($preferred_date)) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Please provide all required fields."]);
    exit;
}

try {
    // 1. MySQLi style transaction
    $conn->begin_transaction();

    // 2. SQL Query (Gumagamit ng ? para sa MySQLi)
    $sql = "INSERT INTO teleconsult_requests 
            ( consultation_reason, preferred_date, phone_number) 
            VALUES ( ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // 3. Bind Parameters (ssss means 4 strings)
    $stmt->bind_param("sss",  $reason, $preferred_date, $phone);

    // 4. Execute
    if ($stmt->execute()) {
        $conn->commit();
        
        // Siguraduhing JSON lang ang lalabas
        ob_clean();
        echo json_encode([
            "status" => "success", 
            "message" => "Teleconsult request submitted successfully!"
        ]);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

} catch (Exception $e) {
    // Rollback kung may error
    $conn->rollback();
    
    ob_clean();
    echo json_encode([
        "status" => "error", 
        "message" => "Server error: " . $e->getMessage()
    ]);
}

// Isara ang connections
if (isset($stmt)) $stmt->close();
$conn->close();
?>