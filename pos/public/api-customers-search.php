<?php
require_once __DIR__ . '/../src/core.php';
require_auth('cashier');
header('Content-Type: application/json');

$q   = trim($_GET['q'] ?? '');
$tid = tid();

if (strlen($q) < 1) { echo json_encode([]); exit; }

$stmt = db()->prepare("
    SELECT id, name, phone, email, address
    FROM customers
    WHERE tenant_id=?
      AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)
    ORDER BY
        CASE WHEN name LIKE ? THEN 0 ELSE 1 END,
        name ASC
    LIMIT 10
");
$like   = "%$q%";
$starts = "$q%";
$stmt->execute([$tid, $like, $like, $like, $starts]);
$rows = $stmt->fetchAll();

echo json_encode(array_values($rows));
