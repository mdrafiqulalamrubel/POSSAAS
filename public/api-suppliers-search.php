<?php
require_once __DIR__ . '/../src/core.php';
require_auth('cashier');
header('Content-Type: application/json');
$q   = trim($_GET['q'] ?? '');
$tid = tid();
if (strlen($q) < 1) { echo json_encode([]); exit; }
$s = db()->prepare('SELECT id, name, phone, email FROM suppliers WHERE tenant_id=? AND is_active=1 AND (name LIKE ? OR phone LIKE ?) ORDER BY name LIMIT 15');
$s->execute([$tid, "%$q%", "%$q%"]);
echo json_encode(array_values($s->fetchAll()));
