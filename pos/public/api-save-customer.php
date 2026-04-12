<?php
require_once __DIR__ . '/../src/core.php';
require_auth('cashier');
header('Content-Type: application/json');

$name    = trim($_POST['name']    ?? '');
$phone   = trim($_POST['phone']   ?? '');
$email   = trim($_POST['email']   ?? '');
$address = trim($_POST['address'] ?? '');
$tid     = tid();

if (!$name) { echo json_encode(['error'=>'Name required']); exit; }

// Check if already exists by phone/name
if ($phone) {
    $chk = db()->prepare('SELECT id,name,phone,email FROM customers WHERE tenant_id=? AND phone=? LIMIT 1');
    $chk->execute([$tid, $phone]);
    $existing = $chk->fetch();
    if ($existing) {
        echo json_encode(['id'=>(int)$existing['id'],'name'=>$existing['name'],
                          'phone'=>$existing['phone'],'email'=>$existing['email'],'saved'=>false,'msg'=>'Existing customer found']);
        exit;
    }
}

db()->prepare('INSERT INTO customers (tenant_id,name,phone,email,address) VALUES (?,?,?,?,?)')
    ->execute([$tid, $name, $phone, $email, $address]);
$id = (int)db()->lastInsertId();

echo json_encode(['id'=>$id,'name'=>$name,'phone'=>$phone,'email'=>$email,'saved'=>true,'msg'=>'Customer saved']);
