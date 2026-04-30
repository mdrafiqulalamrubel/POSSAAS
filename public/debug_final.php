<?php
// Must be absolute first lines
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Catch everything including fatals
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e) {
        echo "<div style='background:#fee2e2;border:2px solid red;padding:20px;margin:20px;font-family:monospace;font-size:14px'>";
        echo "<b>💥 FATAL ERROR:</b><br><br>";
        echo "Message: " . htmlspecialchars($e['message']) . "<br><br>";
        echo "File: " . htmlspecialchars($e['file']) . "<br>";
        echo "Line: " . $e['line'];
        echo "</div>";
    }
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "<div style='background:#fef9c3;border:1px solid orange;padding:10px;margin:5px;font-family:monospace;font-size:13px'>";
    echo "⚠️ Error [$errno]: " . htmlspecialchars($errstr) . "<br>";
    echo "File: " . htmlspecialchars($errfile) . " Line: $errline";
    echo "</div>";
    return false;
});

set_exception_handler(function($ex) {
    echo "<div style='background:#fee2e2;border:2px solid red;padding:20px;margin:20px;font-family:monospace'>";
    echo "<b>💥 EXCEPTION:</b><br><br>";
    echo htmlspecialchars($ex->getMessage()) . "<br><br>";
    echo "File: " . htmlspecialchars($ex->getFile()) . " Line: " . $ex->getLine();
    echo "<br><br>Trace:<br>" . nl2br(htmlspecialchars($ex->getTraceAsString()));
    echo "</div>";
});

echo "<div style='background:#dcfce7;padding:10px;margin:10px;font-family:monospace'>✅ Error handlers installed — loading income-add.php now...</div>";

// Now load the actual page
require __DIR__ . '/income-add.php';
