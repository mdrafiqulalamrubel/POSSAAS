<?php
// income-edit.php — just forwards to income-add.php with id
require_once __DIR__ . '/../src/core.php';
require_auth();
$_GET['id'] = $_GET['id'] ?? 0;
include __DIR__ . '/income-add.php';
