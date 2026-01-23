<?php
/**
 * Debug: Quiz Table Structure
 */
require_once __DIR__ . '/config/database.php';

echo "<h2>Quiz Table Structure</h2>";
echo "<pre>";

// Get table structure
$cols = db()->fetchAll("DESCRIBE quiz");
echo "=== COLUMNS ===\n";
foreach ($cols as $c) {
    echo $c['Field'] . " | " . $c['Type'] . " | Null: " . $c['Null'] . " | Default: " . ($c['Default'] ?? 'NULL') . "\n";
}

echo "\n=== CHECK FOR REQUIRED COLUMNS ===\n";
$colNames = array_column($cols, 'Field');
$requiredCols = ['quiz_id', 'subject_id', 'lesson_id', 'user_teacher_id', 'quiz_title', 'quiz_description', 'time_limit', 'passing_rate', 'status', 'created_at', 'updated_at'];
$optionalCols = ['quiz_type', 'linked_quiz_id', 'require_lessons', 'due_date'];

foreach ($requiredCols as $col) {
    $exists = in_array($col, $colNames);
    echo ($exists ? "✓" : "✗") . " $col " . ($exists ? "EXISTS" : "MISSING") . "\n";
}

echo "\n=== OPTIONAL COLUMNS ===\n";
foreach ($optionalCols as $col) {
    $exists = in_array($col, $colNames);
    echo ($exists ? "✓" : "○") . " $col " . ($exists ? "EXISTS" : "NOT PRESENT") . "\n";
}

// Test insert with minimal data
echo "\n=== TEST INSERT (simulation) ===\n";
$testSql = "INSERT INTO quiz (user_teacher_id, subject_id, lesson_id, quiz_title, quiz_description, time_limit, passing_rate, status, created_at, updated_at)
            VALUES (1, 6, 0, 'Test Quiz', 'Test Description', 30, 60, 'draft', NOW(), NOW())";
echo "SQL: " . $testSql . "\n\n";

// Check if lesson_id allows 0
$lessonIdCol = null;
foreach ($cols as $c) {
    if ($c['Field'] === 'lesson_id') {
        $lessonIdCol = $c;
        break;
    }
}

if ($lessonIdCol) {
    echo "lesson_id column details:\n";
    echo "  Type: " . $lessonIdCol['Type'] . "\n";
    echo "  Null: " . $lessonIdCol['Null'] . "\n";
    echo "  Default: " . ($lessonIdCol['Default'] ?? 'NULL') . "\n";
    echo "  Key: " . $lessonIdCol['Key'] . "\n";

    // Check if there's a foreign key constraint
    $fkCheck = db()->fetchAll(
        "SELECT * FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'quiz'
         AND COLUMN_NAME = 'lesson_id'
         AND REFERENCED_TABLE_NAME IS NOT NULL"
    );

    if ($fkCheck) {
        echo "\n⚠️ FOREIGN KEY CONSTRAINT EXISTS:\n";
        foreach ($fkCheck as $fk) {
            echo "  References: " . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] . "\n";
        }
        echo "\nThis may be causing the issue - lesson_id=0 doesn't exist in lessons table!\n";
    } else {
        echo "\n✓ No foreign key constraint on lesson_id\n";
    }
}

echo "</pre>";
