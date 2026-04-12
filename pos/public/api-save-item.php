<?php
require_once __DIR__ . '/../src/core.php';
require_auth('cashier');
header('Content-Type: application/json');

$name  = trim($_POST['name']       ?? '');
$sku   = trim($_POST['sku']        ?? '');
$price = (float)($_POST['unit_price'] ?? 0);
$unit  = trim($_POST['unit']       ?? 'pcs');
$cat   = trim($_POST['category']   ?? '');
$tid   = tid();
$bid   = brid();

if (!$name) { echo json_encode(['error'=>'Name required']); exit; }

// Check if SKU already exists
if ($sku) {
    $chk = db()->prepare('SELECT id,name,sku,unit_price,quantity,unit FROM items WHERE tenant_id=? AND branch_id=? AND sku=? LIMIT 1');
    $chk->execute([$tid, $bid, $sku]);
    $existing = $chk->fetch();
    if ($existing) {
        echo json_encode(['id'=>(int)$existing['id'],'name'=>$existing['name'],'sku'=>$existing['sku'],
                          'unit_price'=>(float)$existing['unit_price'],'quantity'=>(float)$existing['quantity'],
                          'unit'=>$existing['unit'],'saved'=>false,'msg'=>'Item with this barcode already exists']);
        exit;
    }
}

db()->prepare('INSERT INTO items (tenant_id,branch_id,name,sku,category,unit,quantity,unit_price,is_active,created_by)
               VALUES (?,?,?,?,?,?,0,?,1,?)')
    ->execute([$tid, $bid, $name, $sku, $cat, $unit, $price, uid()]);
$id = (int)db()->lastInsertId();

echo json_encode(['id'=>$id,'name'=>$name,'sku'=>$sku,'unit_price'=>$price,'quantity'=>0,
                  'unit'=>$unit,'saved'=>true,'msg'=>"Item '$name' saved to inventory"]);
