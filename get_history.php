<?php
header('Content-Type: application/json');
include 'config.php';

$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

// A member's consultations are logged against whichever spelling of their number
// the CRM held at the time, so an exact match returned only part of the history
// — 2 of 7 rows for a member whose records span both forms. Silent truncation of
// medical history is worse than an empty screen, because it looks like it worked.
$phones = ph_mobile_variants($user_id);

if ($phones === []) {
    echo json_encode(["status" => "error", "message" => "Invalid mobile number"]);
    exit;
}

$in = implode(',', array_fill(0, count($phones), '?'));
$stmt = $conn->prepare("SELECT * FROM tblcrmlogsdata WHERE p_contact IN ({$in}) ORDER BY created_at DESC");
$stmt->bind_param(str_repeat('s', count($phones)), ...$phones);
$stmt->execute();
$result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        // doctor_status is the only review signal this table carries, and it is
        // only trustworthy in one direction. Where it is 1 a doctor demonstrably
        // acted: 440 of those 799 rows carry a doctor's comment and 390 are
        // approved, against 27 comments across the 1275 rows where it is 0.
        //
        // Where it is 0 the record is ambiguous, and for recent consultations it
        // is simply unmaintained — every row from 2025 onward is 0, with no
        // comment and no approval anywhere among them, so the flag stopped being
        // written around mid-2024 rather than 500-odd consultations sitting
        // unread. Reporting those as "Pending" is the fabrication that had this
        // badge removed in the first place, so they are reported as nothing.
        $row['review_status'] = ((int) $row['doctor_status'] === 1) ? 'reviewed' : null;

        $history[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $history
    ]);
?>