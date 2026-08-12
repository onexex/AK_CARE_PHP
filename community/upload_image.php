<?php
header('Content-Type: application/json');
require '../config.php';

// This endpoint did not include config.php at all, so it had no session, no
// database, and no checks: anyone who knew the URL could write a file into the
// web root. It took the extension straight from the uploaded filename, so
// "shell.php" was saved as shell.php under uploads/community/ and could then be
// requested and executed. Two things close that — a session, and never trusting
// the client for the file type.
$member = require_member($conn);

$uploadDir = __DIR__ . '/../uploads/community/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status" => "error", "message" => "No image uploaded"]);
    exit;
}

$file = $_FILES['image'];

// 5 MB. Posts carry phone photos, not scans.
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(["status" => "error", "message" => "Image is too large (5MB maximum)"]);
    exit;
}

// The extension is decided here, from what the file actually is. getimagesize()
// fails outright on anything that is not a real image, which is the check that
// matters: a PHP script renamed to .jpg does not survive it.
$allowed = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_GIF  => 'gif',
    IMAGETYPE_WEBP => 'webp',
];

$info = @getimagesize($file['tmp_name']);

if ($info === false || !isset($allowed[$info[2]])) {
    echo json_encode(["status" => "error", "message" => "Only JPG, PNG, GIF or WEBP images are accepted"]);
    exit;
}

$filename = uniqid('post_') . '.' . $allowed[$info[2]];
$path     = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $path)) {
    echo json_encode([
        "status" => "success",
        "path"   => "uploads/community/" . $filename
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Upload failed"]);
}
