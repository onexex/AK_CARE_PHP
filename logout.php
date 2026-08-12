<?php
// Signs this handset out by revoking the token it presented.
//
// Deliberately succeeds either way: a member tapping Sign Out with an already
// dead token should still end up signed out, not stuck on an error. Sessions on
// the member's other devices are untouched.
header('Content-Type: application/json');
include 'config.php';

revoke_current_session($conn);

echo json_encode(["status" => "success", "message" => "Signed out."]);
