<?php
// branch-switch.php
require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$new_bid = (int)($_POST['branch_id'] ?? 0);
$branches = get_branches();
foreach ($branches as $b) {
    if ($b['id'] == $new_bid) { $_SESSION['active_branch'] = $new_bid; break; }
}
$ref = $_SERVER['HTTP_REFERER'] ?? (APP_URL . '/index.php');
header('Location: ' . $ref); exit;
