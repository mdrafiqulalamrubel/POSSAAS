<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../src/core.php';
require_auth();
$tid = tid(); $bid = brid();

echo "<pre style='font-family:monospace;font-size:13px;padding:20px'>";
echo "=== STOCK DEBUG ===\n";
echo "Tenant ID: $tid | Branch ID: $bid\n\n";

// 1. Check income_items columns
echo "=== income_items COLUMNS ===\n";
$cols = db()->query("SHOW COLUMNS FROM income_items")->fetchAll(PDO::FETCH_ASSOC);
$col_names = array_column($cols, 'Field');
foreach ($cols as $c) echo "  " . $c['Field'] . " | " . $c['Type'] . "\n";

echo "\nHas item_id column: "   . (in_array('item_id',    $col_names) ? "✅ YES" : "❌ NO — run ALTER TABLE!") . "\n";
echo "Has vat_pct column: "    . (in_array('vat_pct',    $col_names) ? "✅ YES" : "❌ NO — run ALTER TABLE!") . "\n";
echo "Has vat_amount column: " . (in_array('vat_amount', $col_names) ? "✅ YES" : "❌ NO — run ALTER TABLE!") . "\n";

// 2. Check last 3 invoices and their items
echo "\n=== LAST 3 INVOICES & ITEMS ===\n";
$invoices = db()->prepare("SELECT id, invoice_no, total, created_at FROM income WHERE tenant_id=? AND branch_id=? ORDER BY id DESC LIMIT 3");
$invoices->execute([$tid, $bid]);
foreach ($invoices->fetchAll() as $inv) {
    echo "\nInvoice #{$inv['id']} — {$inv['invoice_no']} — {$inv['created_at']}\n";
    $items = db()->prepare("SELECT * FROM income_items WHERE income_id=?");
    $items->execute([$inv['id']]);
    foreach ($items->fetchAll() as $it) {
        echo "  Item: {$it['description']} | qty={$it['qty']} | price={$it['unit_price']}";
        echo " | item_id=" . ($it['item_id'] ?? 'NULL');
        echo " | vat_pct=" . ($it['vat_pct'] ?? 'N/A');
        echo "\n";
        if (!empty($it['item_id'])) {
            $stock = db()->prepare("SELECT name, quantity FROM items WHERE id=?");
            $stock->execute([$it['item_id']]);
            $s = $stock->fetch();
            echo "    → Item in DB: {$s['name']} | Current stock: {$s['quantity']}\n";
        } else {
            echo "    → ⚠ item_id is NULL — stock NOT deducted (no link to inventory)\n";
        }
    }
}

// 3. Check items table has vat_pct column
echo "\n=== items TABLE vat_pct COLUMN ===\n";
$icols = db()->query("SHOW COLUMNS FROM items")->fetchAll(PDO::FETCH_ASSOC);
$inames = array_column($icols, 'Field');
echo "Has vat_pct: " . (in_array('vat_pct', $inames) ? "✅ YES" : "❌ NO — run ALTER TABLE items ADD COLUMN vat_pct DECIMAL(5,2) DEFAULT 0!") . "\n";

// 4. Show current stock levels
echo "\n=== CURRENT STOCK LEVELS (first 10 items) ===\n";
$stk = db()->prepare("SELECT id, name, quantity, unit FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 ORDER BY name LIMIT 10");
$stk->execute([$tid, $bid]);
foreach ($stk->fetchAll() as $s) {
    echo "  [{$s['id']}] {$s['name']} — {$s['quantity']} {$s['unit']}\n";
}

// 5. Test a direct stock deduction (DRY RUN)
echo "\n=== DRY RUN: Test stock UPDATE ===\n";
$first = db()->prepare("SELECT id, name, quantity FROM items WHERE tenant_id=? AND branch_id=? AND is_active=1 LIMIT 1");
$first->execute([$tid, $bid]);
$testItem = $first->fetch();
if ($testItem) {
    echo "Testing UPDATE on item [{$testItem['id']}] {$testItem['name']} (qty={$testItem['quantity']})...\n";
    try {
        db()->beginTransaction();
        $affected = db()->prepare("UPDATE items SET quantity = quantity - 0.001 WHERE id=? AND tenant_id=? AND branch_id=?");
        $affected->execute([$testItem['id'], $tid, $bid]);
        $rows = $affected->rowCount();
        db()->rollBack();
        echo "✅ UPDATE worked! Rows affected: $rows (rolled back — no real change)\n";
    } catch (Exception $e) {
        db()->rollBack();
        echo "❌ UPDATE FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo "No items found to test\n";
}

echo "\n</pre>";
echo "<hr><b style='color:red'>⚠ Delete debug_stock.php after reading!</b>";
