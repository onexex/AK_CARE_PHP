<?php
header('Content-Type: application/json');
require 'config.php';

// Both branches act on the signed-in member's own certificate requests.
$member = require_member($conn);
$userId = $member['member_id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Named columns rather than *: this row now carries the issued certificate,
    // and SELECT * would hand the member internal ids and the reviewer's row
    // along with it. The doctor's name and licence come from users because they
    // are printed on the certificate.
    $sql = "SELECT r.id, r.certificate_no, r.reason, r.preferred_date, r.status,
                   r.patient_name, r.patient_age, r.patient_sex,
                   r.examined_on, r.diagnosis, r.fitness, r.restrictions,
                   r.rest_from, r.rest_to, r.remarks,
                   r.issued_at, r.rejection_reason, r.rejected_at, r.created_at,
                   u.name AS issued_by, u.prc_license_no AS issued_license
            FROM medical_cert_requests r
            LEFT JOIN users u ON u.id = r.issued_by
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC";

    $stmt = $conn->prepare($sql);
    // user_id is a member_id ('AKM-787'), not an integer — bind as a string.
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $data = [];
    foreach ($rows as $row) {
        // One word for the app to branch on, so it never has to infer state
        // from which fields happen to be filled in.
        $row['stage'] = $row['status'] === 'approved' ? 'issued'
            : ($row['status'] === 'rejected' ? 'rejected' : 'pending');
        // Only an issued certificate can be downloaded; a pending request is
        // not a document.
        $row['downloadable'] = $row['stage'] === 'issued' && !empty($row['certificate_no']);
        $data[] = $row;
    }

    echo json_encode(["status" => "success", "data" => $data]);
    exit;
}

if ($method === 'POST') {
    $reason  = $_POST['reason'] ?? '';
    $date    = $_POST['preferred_date'] ?? '';
    if (empty($reason)) { echo json_encode(["status"=>"error","message"=>"Missing fields"]); exit; }

    $stmt = $conn->prepare("INSERT INTO medical_cert_requests (user_id, reason, preferred_date) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $userId, $reason, $date);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Medical certificate request submitted"]);
    exit;
}