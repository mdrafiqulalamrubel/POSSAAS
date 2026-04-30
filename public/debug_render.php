<?php
// Force all errors to show - must be VERY first lines
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Catch fatal errors
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "\n\n<!-- FATAL ERROR -->\n";
        echo "<div style='background:red;color:white;padding:20px;font-size:16px;position:fixed;top:0;left:0;right:0;z-index:99999'>";
        echo "<b>FATAL ERROR line " . $e['line'] . " in " . basename($e['file']) . ":</b><br>";
        echo htmlspecialchars($e['message']);
        echo "</div>";
    }
});

// Now just include the actual file
require_once __DIR__ . '/income-add.php';
