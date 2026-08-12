<?php
// A member's prescriptions and doctor's notes.
//
// This used to read the `eprescriptions` / `eprescription_items` tables, which
// have never held a row — nothing writes to them. The doctor's half of a
// consultation is recorded on `consultations`: `doctor_prescription` and
// `doctors_comment`, filled in by the review screen in the Laravel app.
//
// Note that `doctor_prescription` is empty on all 2097 rows today, legacy and
// new alike — the legacy CRM never captured it, and no doctor has used the new
// review screen yet. So in practice this returns doctors' notes until that
// changes, and prescriptions the moment one is written. Nothing here treats the
// patient's own intake answers (`maintenance_meds`, `medication`) as a
// prescription: those are what the member told the agent they were taking.

ob_start();
header('Content-Type: application/json');
require '../config.php';

$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "user_id required"]);
    exit;
}

// consultations.member_id is the numeric members.id, not the 'AKM-787' string
// the app holds, so the member is resolved first. The contact number is carried
// alongside because the import could only link 1913 of 2097 rows: the rest name
// the member in `member_ref` or nothing at all, and are reachable by number only.
$memberRow = null;
$stmt = $conn->prepare("SELECT id, contact_number FROM members WHERE member_id = ? LIMIT 1");
$stmt->bind_param('s', $userId);
$stmt->execute();
$memberRow = $stmt->get_result()->fetch_assoc();

if (!$memberRow) {
    ob_clean();
    echo json_encode(["status" => "success", "data" => []]);
    exit;
}

$memberPk = (int) $memberRow['id'];
$phones = ph_mobile_variants($memberRow['contact_number'] ?? '');

// 'N/A' and its neighbours mean "nothing recorded" in this data and are dropped
// rather than shown as a doctor's answer, matching how the app's own _hasValue()
// has always treated 'None'.
$placeholders = ["", "n/a", "na", "none", "none.", "-", "--", "wala", "null"];
$isRealText = static function (?string $v) use ($placeholders): bool {
    return !in_array(strtolower(trim((string) $v)), $placeholders, true);
};

$sql = "SELECT c.id, c.consulted_on, c.complaint, c.doctor_prescription,
               c.doctors_comment, c.doctor_status, c.approved_final,
               c.reviewed_at, u.name AS doctor_name
        FROM consultations c
        LEFT JOIN users u ON u.id = c.doctor_id
        WHERE ";

$params = [];
$types  = '';

if ($phones === []) {
    $sql .= "c.member_id = ?";
    $types .= 'i';
    $params[] = $memberPk;
} else {
    $in = implode(',', array_fill(0, count($phones), '?'));
    $sql .= "(c.member_id = ? OR c.contact_number IN ({$in}))";
    $types .= 'i' . str_repeat('s', count($phones));
    $params[] = $memberPk;
    foreach ($phones as $p) {
        $params[] = $p;
    }
}

// Only consultations a doctor actually answered. A ticket with no prescription
// and no note has nothing to show on this screen.
$sql .= " AND ( TRIM(IFNULL(c.doctor_prescription,'')) <> ''
                OR TRIM(IFNULL(c.doctors_comment,'')) <> '' )
          ORDER BY c.consulted_on DESC, c.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$entries = [];
while ($row = $result->fetch_assoc()) {
    $prescription = $isRealText($row['doctor_prescription']) ? trim((string) $row['doctor_prescription']) : '';
    $notes        = $isRealText($row['doctors_comment']) ? trim((string) $row['doctors_comment']) : '';

    // Both fields can survive the SQL filter and still be placeholders.
    if ($prescription === '' && $notes === '') {
        continue;
    }

    $entries[] = [
        'id'            => (int) $row['id'],
        'consulted_on'  => $row['consulted_on'],
        'doctor_name'   => $row['doctor_name'] ?? '',
        'complaint'     => $isRealText($row['complaint']) ? trim((string) $row['complaint']) : '',
        'prescription'  => $prescription,
        'notes'         => $notes,
        // 'approved' only where a second person signed the record off; see
        // get_history.php for why doctor_status is trusted in one direction only.
        'review_status' => $row['approved_final'] ? 'approved'
                            : ((int) $row['doctor_status'] === 1 ? 'reviewed' : null),
    ];
}

ob_clean();
echo json_encode(["status" => "success", "data" => $entries]);
