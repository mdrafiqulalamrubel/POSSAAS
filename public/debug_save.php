<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<div style='background:red;color:white;padding:20px;font-family:monospace'>";
        echo "<b>FATAL on line " . $e['line'] . " in " . basename($e['file']) . ":</b><br>";
        echo htmlspecialchars($e['message']);
        echo "</div>";
    }
});

set_exception_handler(function($ex) {
    echo "<div style='background:#fee2e2;border:2px solid red;padding:20px;font-family:monospace'>";
    echo "<b>EXCEPTION:</b> " . htmlspecialchars($ex->getMessage()) . "<br>";
    echo "File: " . basename($ex->getFile()) . " Line: " . $ex->getLine();
    echo "</div>";
});

require_once __DIR__ . '/../src/core.php';
$user = require_auth();
$tid = tid(); $bid = brid();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Just echo what was submitted so we can see it
    echo "<h2>POST Data Received:</h2><pre>";
    print_r($_POST);
    echo "</pre>";
    echo "<h2>Now trying to save...</h2>";

    // Now include the actual income-add.php to process it
    require __DIR__ . '/income-add.php';
} else {
    // Show a simple test form
    echo '<!DOCTYPE html><html><body>';
    echo '<h2>Test Invoice Save</h2>';
    echo '<form method="POST">';
    echo '<input type="hidden" name="customer_name" value="Test Customer">';
    echo '<input type="hidden" name="date" value="' . date('Y-m-d') . '">';
    echo '<input type="hidden" name="paid" value="0">';
    echo '<input type="hidden" name="tax_pct" value="0">';
    echo '<input type="hidden" name="discount" value="0">';
    echo '<input type="hidden" name="notes" value="">';
    echo '<input type="hidden" name="status" value="unpaid">';
    echo '<input type="hidden" name="items[description][]" value="Test Item">';
    echo '<input type="hidden" name="items[qty][]" value="1">';
    echo '<input type="hidden" name="items[unit_price][]" value="100">';
    echo '<input type="hidden" name="items[item_id][]" value="">';
    echo '<button type="submit">Test Save Invoice</button>';
    echo '</form></body></html>';
}
