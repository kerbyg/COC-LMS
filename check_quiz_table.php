<?php
require_once __DIR__ . '/config/database.php';

echo "=== Quiz Table Structure ===\n\n";

$structure = db()->fetchAll("DESCRIBE quiz");

echo "Columns:\n";
foreach ($structure as $col) {
    echo str_pad($col['Field'], 30);
    echo str_pad($col['Type'], 20);
    echo str_pad($col['Null'], 10);
    echo str_pad($col['Key'], 10);
    echo $col['Default'];
    echo "\n";
}

echo "\n=== Test INSERT ===\n\n";

// Test data
$subjectId = 8; // GE102
$lessonId = 0;
$teacherId = 2; // Assuming instructor ID 2
$quizTitle = "Test Quiz";
$description = "Test Description";
$timeLimit = 30;
$passingRate = 60;
$dueDate = null;
$status = 'published';

try {
    echo "Attempting to insert quiz:\n";
    echo "  subject_id: $subjectId\n";
    echo "  lessons_id: $lessonId\n";
    echo "  user_teacher_id: $teacherId\n";
    echo "  quiz_title: $quizTitle\n";
    echo "  time_limit: $timeLimit\n";
    echo "  passing_rate: $passingRate\n";
    echo "  status: $status\n\n";

    $result = db()->execute(
        "INSERT INTO quiz (subject_id, lessons_id, user_teacher_id, quiz_title, quiz_description, time_limit, passing_rate, due_date, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
        [$subjectId, $lessonId, $teacherId, $quizTitle, $description, $timeLimit, $passingRate, $dueDate, $status]
    );

    $newId = db()->lastInsertId();
    echo "✓ INSERT successful!\n";
    echo "New quiz_id: $newId\n\n";

    // Check if actually inserted
    $check = db()->fetchOne(
        "SELECT * FROM quiz WHERE quiz_id = ?",
        [$newId]
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
