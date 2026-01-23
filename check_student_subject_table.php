<?php
require_once __DIR__ . '/config/database.php';

echo "=== Student_Subject Table Structure ===\n\n";

// Get table structure
$structure = db()->fetchAll("DESCRIBE student_subject");

echo "Columns:\n";
foreach ($structure as $col) {
    echo sprintf("%-25s %-15s %-10s %-10s %-15s\n",
        $col['Field'],
        $col['Type'],
        $col['Null'],
        $col['Key'],
        $col['Default'] ?? 'NULL'
    );
}

echo "\n=== Testing INSERT ===\n\n";

// Try to manually insert
try {
    $userId = 3; // Maria Santos
    $sectionId = 1; // BSIT26
    $subjectOfferedId = 8; // GE102

    echo "Attempting to insert:\n";
    echo "  user_student_id: $userId\n";
    echo "  subject_offered_id: $subjectOfferedId\n";
    echo "  section_id: $sectionId\n";
    echo "  status: enrolled\n\n";

    $result = db()->execute(
        "INSERT INTO student_subject (user_student_id, subject_offered_id, section_id, status, enrollment_date, updated_at)
         VALUES (?, ?, ?, 'enrolled', NOW(), NOW())",
        [$userId, $subjectOfferedId, $sectionId]
    );

    echo "✓ INSERT successful!\n";
    echo "Rows affected: " . ($result ? "success" : "0") . "\n\n";

    // Check if it was actually inserted
    $check = db()->fetchOne(
        "SELECT * FROM student_subject WHERE user_student_id = ? AND section_id = ?",
        [$userId, $sectionId]
    );

    if ($check) {
        echo "✓ Record exists in database:\n";
        print_r($check);
    } else {
        echo "❌ Record NOT found in database after insert!\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
