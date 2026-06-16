<?php
header('Content-Type: application/json');
require '../config.php';

function decryptLaravelData($payload, $appKeyBase64) {
    try {
        $key = base64_decode(substr($appKeyBase64, 7));
        $data = json_decode(base64_decode($payload), true);
        if (!$data || !isset($data['iv'], $data['value'])) return null;
        $iv = base64_decode($data['iv']);
        $value = $data['value'];
        $decrypted = openssl_decrypt($value, 'aes-256-cbc', $key, 0, $iv);
        if ($decrypted === false) return null;
        $unserialized = @unserialize($decrypted);
        return ($unserialized !== false) ? $unserialized : $decrypted;
    } catch (Exception $e) { return null; }
}

$appKey = "base64:NusO0N5yu2WM4bbP7qDg9DfJc9FpglsPgtvSapEHxpM=";
$postId = $_GET['post_id'] ?? '';

if (empty($postId)) {
    echo json_encode(["status" => "error", "message" => "post_id required"]);
    exit;
}

$stmt = $conn->prepare("SELECT l.user_id, l.reaction_type, l.created_at, u.contact, u.m_fname, u.m_surname 
    FROM community_post_likes l 
    LEFT JOIN tblcrms u ON l.user_id = u.id 
    WHERE l.post_id = ? 
    ORDER BY l.created_at DESC");

$stmt->bind_param("i", $postId);
$stmt->execute();
$result = $stmt->get_result();

$likes = [];
while ($row = $result->fetch_assoc()) {
    $fname = decryptLaravelData($row['m_fname'] ?? '', $appKey);
    $sname = decryptLaravelData($row['m_surname'] ?? '', $appKey);
    $fullName = trim(($fname ?? '') . ' ' . ($sname ?? ''));

    $likes[] = [
        'user_id'       => $row['user_id'],
        'reaction_type' => $row['reaction_type'],
        'created_at'    => $row['created_at'],
        'user' => [
            'id'        => $row['user_id'],
            'contact'   => $row['contact'],
            'full_name' => $fullName ?: ($row['contact'] ?? 'Unknown'),
        ],
    ];
}

echo json_encode(["status" => "success", "data" => $likes]);