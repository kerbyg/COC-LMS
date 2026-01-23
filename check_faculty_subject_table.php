<?php
require_once __DIR__ . '/config/database.php';

echo "=== Checking faculty_subject Table ===\n\n";

$allAssignments = db()->fetchAll(
    "SELECT * FROM faculty_subject ORDER BY faculty_subject_id"
);

echo "All records in faculty_subject table:\n\n";
foreach ($allAssignments as $a) {
    echo "ID: {$a['faculty_subject_id']}\n";
    echo "  Teacher ID: {$a['user_teacher_id']}\n";
    echo "  Offering ID: {$a['subject_offered_id']}\n";
    echo "  Section ID: {$a['section_id']}\n";
    echo "  Status: {$a['status']}\n";
    echo "  Assigned: {$a['assigned_at']}\n\n";
}

echo "\n=== Checking for duplicate key constraint ===\n";

$constraints = db()->fetchAll("SHOW CREATE TABLE faculty_subject");
print_r($constraints);
