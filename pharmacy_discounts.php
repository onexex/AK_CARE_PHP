<?php
header('Content-Type: application/json');
require 'config.php';

$r = $conn->query("SELECT * FROM pharmacy_partners WHERE status='active' ORDER BY name ASC");

$pharmacies = [];
while ($row = $r->fetch_assoc()) {
    $pharmacies[] = [
        'id'        => $row['id'],
        'name'      => $row['name'],
        'discount'  => $row['discount_percentage'] ?? '10%',
        'address'   => $row['address'] ?? '',
        'phone'     => $row['phone'] ?? '',
        'logo' => $row['logo'] ?? null,
    ];
}

echo json_encode(["status" => "success", "data" => $pharmacies]);