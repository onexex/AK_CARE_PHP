<?php
header('Content-Type: application/json');
require '../config.php';

$authenticated = require_member($conn);
$userId = $authenticated['member_id'];

// The app sends members.member_id — a varchar like 'AKM-787' or '001-0005-0701'.
// Two of the tables below are keyed on member_id and two on the member's phone
// number, so resolve the contact once and query each with the key it expects.
$memberStmt = $conn->prepare("SELECT contact_number FROM members WHERE member_id = ? LIMIT 1");
$memberStmt->bind_param("s", $userId);
$memberStmt->execute();
$member = $memberStmt->get_result()->fetch_assoc();

// Numbers are stored in both 09XXXXXXXXX and 9XXXXXXXXX form, hence the variants.
$contacts = $member ? ph_mobile_variants($member['contact_number'] ?? '') : [];
$contactIn = $contacts === [] ? '' : implode(',', array_fill(0, count($contacts), '?'));
$contactTypes = str_repeat('s', count($contacts));

// community_posts.user_id is varchar(50) holding member_id. Binding it as "i"
// made PHP cast the value first: '001-0005-0701' became 1 and matched the row
// belonging to user_id '1' — another member's post. Bind as string.
$postStmt = $conn->prepare("SELECT content, created_at FROM community_posts WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$postStmt->bind_param("s", $userId);
$postStmt->execute();
$latestPost = $postStmt->get_result()->fetch_assoc();

// tblcrmlogsdata.p_contact holds a phone number, not a member_id.
$latestHistory = null;
if ($contactIn !== '') {
    $histStmt = $conn->prepare("SELECT p_patient, created_at FROM tblcrmlogsdata WHERE p_contact IN ({$contactIn}) ORDER BY created_at DESC LIMIT 1");
    $histStmt->bind_param($contactTypes, ...$contacts);
    $histStmt->execute();
    $latestHistory = $histStmt->get_result()->fetch_assoc();
}

// teleconsult_requests.phone_number likewise.
$pendingCount = 0;
if ($contactIn !== '') {
    $reqStmt = $conn->prepare("SELECT COUNT(*) AS c FROM teleconsult_requests WHERE phone_number IN ({$contactIn}) AND status = 'Pending'");
    $reqStmt->bind_param($contactTypes, ...$contacts);
    $reqStmt->execute();
    $pendingCount = $reqStmt->get_result()->fetch_assoc()['c'];
}

// community_notifications.user_id is varchar(50) holding member_id — same fix as posts.
$notifStmt = $conn->prepare("SELECT COUNT(*) AS c FROM community_notifications WHERE user_id = ? AND is_read = 0");
$notifStmt->bind_param("s", $userId);
$notifStmt->execute();
$unreadNotifs = $notifStmt->get_result()->fetch_assoc()['c'];

echo json_encode([
    "status" => "success",
    "data" => [
        "latest_post" => $latestPost ? ["content" => mb_strimwidth($latestPost['content'] ?? '', 0, 100, '...'), "created_at" => $latestPost['created_at']] : null,
        "latest_history" => $latestHistory ? ["patient" => $latestHistory['p_patient'], "created_at" => $latestHistory['created_at']] : null,
        "pending_requests" => (int) $pendingCount,
        "unread_notifications" => (int) $unreadNotifs,
    ]
]);
