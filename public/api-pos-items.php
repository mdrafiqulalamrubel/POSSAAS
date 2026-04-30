<?php
require_once __DIR__ . '/../src/core.php';
require_auth('cashier');
header('Content-Type: application/json');

$tid = tid();
$bid = brid();

try {
    // Check which columns exist to avoid errors on older DB schemas
    $existingCols = db()->query("SHOW COLUMNS FROM items")->fetchAll(PDO::FETCH_COLUMN);

    $want   = ['id','name','sku','barcode','category','unit','quantity',
               'unit_price','cost_price','reorder_level','vat_pct','image_path'];
    $select = implode(',', array_filter($want, fn($c) => in_array($c, $existingCols)));

    $stmt = db()->prepare("
        SELECT $select
        FROM items
        WHERE tenant_id = ? AND branch_id = ? AND is_active = 1
        ORDER BY category ASC, name ASC
    ");
    $stmt->execute([$tid, $bid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['id']            = (int)   ($r['id']            ?? 0);
        $r['quantity']      = (float) ($r['quantity']      ?? 0);
        $r['unit_price']    = (float) ($r['unit_price']    ?? 0);
        $r['reorder_level'] = (float) ($r['reorder_level'] ?? 0);
        $r['vat_pct']       = (float) ($r['vat_pct']       ?? 0);
        $r['image_path']    = $r['image_path'] ?? null;
        $r['sku']           = $r['sku']        ?? '';
        $r['barcode']       = $r['barcode']    ?? '';
        $r['category']      = $r['category']   ?? '';
        $r['unit']          = $r['unit']       ?? 'pcs';
    }

    echo json_encode(array_values($rows));

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
