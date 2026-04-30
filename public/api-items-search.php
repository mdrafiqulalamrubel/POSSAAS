<?php
require_once __DIR__ . '/../src/core.php';
require_auth('cashier');
header('Content-Type: application/json');

$tid = tid();
$bid = brid();
$q   = trim($_GET['q'] ?? '');

if ($q === '' || strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$stmt = db()->prepare("
    SELECT id, name, sku, barcode, category, unit,
           quantity, unit_price, reorder_level, vat_pct, image_path
    FROM items
    WHERE tenant_id = ? AND branch_id = ? AND is_active = 1
      AND (name LIKE ? OR sku LIKE ? OR barcode LIKE ? OR category LIKE ?)
    ORDER BY name ASC
    LIMIT 30
");

$like = '%' . $q . '%';
$stmt->execute([$tid, $bid, $like, $like, $like, $like]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$r) {
    $r['id']            = (int)   $r['id'];
    $r['quantity']      = (float) $r['quantity'];
    $r['unit_price']    = (float) $r['unit_price'];
    $r['reorder_level'] = (float) $r['reorder_level'];
    $r['vat_pct']       = (float) ($r['vat_pct'] ?? 0);
}

echo json_encode(array_values($rows));
