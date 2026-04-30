<?php
require_once __DIR__ . '/../src/core.php';
require_auth('cashier');
header('Content-Type: application/json');

$q   = trim($_GET['q'] ?? '');
$tid = tid();
$bid = brid();

if (strlen($q) < 1) { echo json_encode([]); exit; }

$stmt = db()->prepare("
    SELECT id, name, sku, category, unit, quantity, unit_price
    FROM items
    WHERE tenant_id = ?
      AND branch_id = ?
      AND is_active  = 1
      AND (
          name     LIKE ? OR
          sku      LIKE ? OR
          category LIKE ?
      )
    ORDER BY
        CASE WHEN sku  = ?    THEN 0
             WHEN name LIKE ? THEN 1
             ELSE 2 END,
        name ASC
    LIMIT 20
");

$like   = "%$q%";
$starts = "$q%";
$stmt->execute([$tid, $bid, $like, $like, $like, $q, $starts]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cast numeric fields so JS gets numbers, not strings
foreach ($rows as &$r) {
    $r['unit_price'] = (float)$r['unit_price'];
    $r['quantity']   = (float)$r['quantity'];
}

echo json_encode(array_values($rows));
