<?php
require_once __DIR__ . '/config/database.php';

echo "=== Checking Activity Logs System ===\n\n";

// Check if activity_logs table exists
try {
    $tableCheck = db()->fetchOne("SHOW TABLES LIKE 'activity_logs'");

    if ($tableCheck) {
        echo "✓ activity_logs table exists\n\n";

        // Get table structure
        echo "Table Structure:\n";
        $structure = db()->fetchAll("DESCRIBE activity_logs");
        foreach ($structure as $col) {
            echo "  - {$col['Field']} ({$col['Type']}) " . ($col['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
        }

        // Count records
        $count = db()->fetchOne("SELECT COUNT(*) as count FROM activity_logs")['count'];
        echo "\n✓ Table has $count records\n\n";

        if ($count > 0) {
            // Show sample records
            echo "Sample Records:\n";
            $samples = db()->fetchAll("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
            foreach ($samples as $s) {
                echo "  - {$s['activity_type']}: {$s['description']} (User ID: {$s['users_id']})\n";
            }
        } else {
            echo "⚠️  Table is empty - no activities have been logged\n";
        }

    } else {
        echo "❌ activity_logs table DOES NOT EXIST\n\n";
        echo "This table needs to be created to track system activities.\n\n";

        echo "Suggested table structure:\n";
        echo "CREATE TABLE activity_logs (\n";
        echo "  activity_log_id INT(11) PRIMARY KEY AUTO_INCREMENT,\n";
        echo "  users_id INT(11),\n";
        echo "  activity_type VARCHAR(50),\n";
        echo "  description TEXT,\n";
        echo "  ip_address VARCHAR(45),\n";
        echo "  user_agent TEXT,\n";
        echo "  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        echo "  FOREIGN KEY (users_id) REFERENCES users(users_id) ON DELETE SET NULL\n";
        echo ");\n";
    }

} catch (Exception $e) {
    echo "❌ Error checking table: " . $e->getMessage() . "\n";
}
