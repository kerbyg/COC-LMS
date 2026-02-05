<?php
require_once __DIR__ . '/config/database.php';

echo "=== Quiz Table Constraints ===\n\n";

$constraints = db()->fetchAll("
    SELECT
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'cit_lms'
    AND TABLE_NAME = 'quiz'
    AND REFERENCED_TABLE_NAME IS NOT NULL
");

echo "Foreign Key Constraints:\n";
foreach ($constraints as $c) {
    echo "  {$c['CONSTRAINT_NAME']}: {$c['COLUMN_NAME']} -> {$c['REFERENCED_TABLE_NAME']}.{$c['REFERENCED_COLUMN_NAME']}\n";
}

echo "\n=== Checking lessons_id column details ===\n";
$lessonCol = db()->fetchOne("
    SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'cit_lms'
    AND TABLE_NAME = 'quiz'
    AND COLUMN_NAME = 'lessons_id'
");

print_r($lessonCol);

echo "\n=== Testing INSERT with NULL lessons_id ===\n";

try {
    $result = db()->execute(
        "INSERT INTO quiz (subject_id, lessons_id, user_teacher_id, quiz_title, quiz_description, time_limit, passing_rate, due_date, status, created_at, updated_at)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
        [8, 2, "Test Quiz NULL", "Test Description", 30, 60, null, 'published']
    );

    $newId = db()->lastInsertId();
    echo "✓ INSERT with NULL lessons_id successful! New ID: $newId\n";

    $check = db()->fetchOne("SELECT quiz_id, lessons_id FROM quiz WHERE quiz_id = ?", [$newId]);
    echo "Record: quiz_id={$check['quiz_id']}, lessons_id=" . ($check['lessons_id'] ?: 'NULL') . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
