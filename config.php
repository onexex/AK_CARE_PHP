<?php
  // config.php
  header('Content-Type: application/json');

  $servername = "184.168.100.242"; 
  $username = "akqrUSR";
  $password = "o3eeA4o]}zu}";
  $db = "akKardMaster";

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
  $conn->set_charset("utf8");
?>