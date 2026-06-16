<?php
header('Content-Type: application/json');

$uploadDir = '../uploads/community/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

if (!isset($_FILES['image'])) {
    echo json_encode(["status" => "error", "message" => "No image uploaded"]);
    exit;
}

$file = $_FILES['image'];
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('post_') . '.' . $ext;
$path = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $path)) {
    echo json_encode([
        "status" => "success",
        "path"   => "uploads/community/" . $filename
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Upload failed"]);
}