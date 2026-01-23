<?php
require_once 'config/database.php';

echo "=== Quiz Table Structure ===\n\n";

try {
    $structure = db()->query("DESCRIBE quiz");

    echo "Columns in quiz table:\n";
    foreach ($structure as $row) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }

    echo "\n=== Sample Query Test ===\n\n";

    // Test if we can query the table
    $sample = db()->query("SELECT * FROM quiz LIMIT 1");
    if (count($sample) > 0) {
        echo "Sample record columns:\n";
        foreach (array_keys($sample[0]) as $col) {
            echo "  - $col\n";
        }
    } else {
        echo "No records in quiz table yet.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
