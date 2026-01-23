<?php
// Simple test page
echo "<!DOCTYPE html><html><body>";
echo "<h1>PHP is working!</h1>";

try {
    require_once __DIR__ . '/../../config/database.php';
    echo "<p>✓ Database config loaded</p>";

    require_once __DIR__ . '/../../config/auth.php';
    echo "<p>✓ Auth config loaded</p>";

    echo "<p>User ID: " . Auth::id() . "</p>";
    echo "<p>User Role: " . Auth::role() . "</p>";

    $test = db()->fetchOne("SELECT COUNT(*) as cnt FROM users");
    echo "<p>✓ Database connected! Users count: " . $test['cnt'] . "</p>";

} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
