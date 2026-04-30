<?php
require_once __DIR__ . '/../src/core.php';
require_auth('manager');
$tid = tid();
$id  = (int)($_GET['id'] ?? 0);
if ($id) {
    $pur = db()->prepare('SELECT status FROM purchases WHERE id=? AND tenant_id=?');
    $pur->execute([$id,$tid]); $pur = $pur->fetch();
    if ($pur && $pur['status'] !== 'cancelled') {
        db()->beginTransaction();
        // Restore stock
        $items = db()->prepare('SELECT item_id, qty FROM purchase_items WHERE purchase_id=? AND item_id IS NOT NULL');
        $items->execute([$id]);
        foreach ($items->fetchAll() as $it) {
            db()->prepare('UPDATE items SET quantity = GREATEST(0, quantity - ?) WHERE id=? AND tenant_id=?')
               ->execute([$it['qty'], $it['item_id'], $tid]);
        }
        db()->prepare("UPDATE purchases SET status='cancelled' WHERE id=? AND tenant_id=?")->execute([$id,$tid]);
        db()->commit();
        flash('success', 'Purchase cancelled and stock restored.');
    }
}
redirect('purchases.php');
