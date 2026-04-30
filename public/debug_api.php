<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../src/core.php';
require_auth('cashier');

$tid = tid();
$bid = brid();

echo "<pre>";
echo "tid=$tid bid=$bid\n\n";

// Test 1: basic query without vat_pct
echo "=== Test 1: Basic query ===\n";
try {
    $s = db()->prepare("SELECT id, name, quantity, unit_price FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 LIMIT 3");
    $s->execute([$tid, $bid]);
    print_r($s->fetchAll());
} catch(Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }

// Test 2: with vat_pct
echo "\n=== Test 2: With vat_pct ===\n";
try {
    $s = db()->prepare("SELECT id, name, vat_pct FROM items WHERE tenant_id=? AND branch_id=? LIMIT 3");
    $s->execute([$tid, $bid]);
    print_r($s->fetchAll());
} catch(Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }

// Test 3: full query like api-pos-items.php
echo "\n=== Test 3: Full query ===\n";
try {
    $s = db()->prepare("SELECT id, name, sku, barcode, category, unit, quantity, unit_price, reorder_level, vat_pct, image_path FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 ORDER BY category ASC, name ASC");
    $s->execute([$tid, $bid]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Rows found: " . count($rows) . "\n";
    echo "First row: "; print_r($rows[0] ?? 'none');
} catch(Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }

// Test 4: what does the API actually return?
echo "\n=== Test 4: JSON output of API ===\n";
try {
    $s = db()->prepare("SELECT id, name, sku, barcode, category, unit, quantity, unit_price, reorder_level, vat_pct, image_path FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 ORDER BY name LIMIT 3");
    $s->execute([$tid, $bid]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id']         = (int)$r['id'];
        $r['quantity']   = (float)$r['quantity'];
        $r['unit_price'] = (float)$r['unit_price'];
        $r['vat_pct']    = (float)($r['vat_pct'] ?? 0);
    }
    echo json_encode($rows, JSON_PRETTY_PRINT);
} catch(Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }

echo "</pre>";
