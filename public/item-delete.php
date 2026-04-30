<?php
require_once __DIR__ . '/../src/core.php';
require_auth('manager');
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    db()->prepare('UPDATE items SET is_active=0 WHERE id=? AND tenant_id=?')->execute([$id, tid()]);
    log_activity('delete','Inventory','Item deactivated','ID:'.$id);
    flash('success', 'Item removed.');
}
redirect('items.php');
