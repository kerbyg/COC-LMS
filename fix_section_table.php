<?php
/**
 * Database Migration Script
 * Fixes section table structure
 */

require_once __DIR__ . '/config/database.php';

echo "=== Section Table Fix Script ===\n\n";

try {
    // Check current structure
    $columns = db()->fetchAll("DESCRIBE section");
    $existingColumns = array_column($columns, 'Field');

    echo "Current columns in section table:\n";
    foreach ($existingColumns as $col) {
        echo "  - $col\n";
    }
    echo "\n";

    // Add updated_at column if missing
    if (!in_array('updated_at', $existingColumns)) {
        echo "Adding updated_at column...\n";
        db()->execute("ALTER TABLE section ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        echo "✓ updated_at column added\n\n";
    } else {
        echo "✓ updated_at column already exists\n\n";
    }

    // Check faculty_subject table structure
    echo "Checking faculty_subject table...\n";
    $facultyColumns = db()->fetchAll("DESCRIBE faculty_subject");
    $existingFacultyColumns = array_column($facultyColumns, 'Field');

    echo "Current columns in faculty_subject table:\n";
    foreach ($existingFacultyColumns as $col) {
        echo "  - $col\n";
    }
    echo "\n";

    // Add section_id to faculty_subject if missing (it already exists based on structure)
    if (!in_array('section_id', $existingFacultyColumns)) {
        echo "Adding section_id column to faculty_subject...\n";
        db()->execute("ALTER TABLE faculty_subject ADD COLUMN section_id INT(11) NULL AFTER subject_offered_id");
        db()->execute("ALTER TABLE faculty_subject ADD INDEX idx_section_id (section_id)");
        echo "✓ section_id column added to faculty_subject\n\n";
    } else {
        echo "✓ section_id column already exists in faculty_subject\n\n";
    }

    // Add updated_at to faculty_subject if missing
    if (!in_array('updated_at', $existingFacultyColumns)) {
        echo "Adding updated_at column to faculty_subject...\n";
        db()->execute("ALTER TABLE faculty_subject ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER assigned_at");
        echo "✓ updated_at column added to faculty_subject\n\n";
    } else {
        echo "✓ updated_at column already exists in faculty_subject\n\n";
    }

    // Show updated structures
    echo "Updated section table structure:\n";
    $updatedColumns = db()->fetchAll("DESCRIBE section");
    foreach ($updatedColumns as $col) {
        echo sprintf("  %-20s %-20s\n", $col['Field'], $col['Type']);
    }

    echo "\n✅ Section table structure fixed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
