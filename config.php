<?php
  // config.php
  header('Content-Type: application/json');

  // ── Local development ──
  // Points at the same MySQL database the akop_main Laravel app uses.
  // The previous remote/production credentials are kept in config.php.remote-backup.
  $servername = "127.0.0.1";
  $username   = "root";
  $password   = "";
  $db         = "akop_main";

  // Create connection
  $conn = new mysqli($servername, $username, $password, $db);

  // Check connection
  if ($conn->connect_error) {
      die(json_encode([
          "status" => "error",
          "message" => "Database Connection Failed: " . $conn->connect_error
      ]));
  }

  // Set charset to UTF-8 para sa special characters
  $conn->set_charset("utf8mb4");

  // Phone number normalization helpers, used wherever a member is looked up by number.
  require_once __DIR__ . '/phone.php';

  // Session handling. Endpoints call require_member($conn) instead of reading a
  // user_id, so the caller cannot name anyone but themselves.
  require_once __DIR__ . '/auth.php';

  // How long a verification code stays usable. Enforced in verify_otp.php and
  // quoted in the SMS by check_user.php — both read it from here so they cannot drift.
  define('OTP_VALIDITY_MINUTES', 5);
?>
