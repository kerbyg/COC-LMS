<?php
require_once 'config/database.php';

echo "=== Checking Quiz Table ===\n\n";

// Check if quiz table exists
$tables = db()->fetchAll("SHOW TABLES LIKE 'quiz'");
echo "Quiz table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n\n";

if (count($tables) > 0) {
    echo "Quiz table structure:\n";
    $structure = db()->fetchAll("DESCRIBE quiz");
    foreach ($structure as $row) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
}

echo "\n=== Checking quiz_questions Table ===\n\n";

// Check if quiz_questions table exists
$tables = db()->fetchAll("SHOW TABLES LIKE 'quiz_questions'");
echo "quiz_questions table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n\n";

if (count($tables) > 0) {
    echo "quiz_questions table structure:\n";
    $structure = db()->fetchAll("DESCRIBE quiz_questions");
    foreach ($structure as $row) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }

    echo "\nIndexes on quiz_questions:\n";
    $indexes = db()->fetchAll("SHOW INDEX FROM quiz_questions");
    foreach ($indexes as $idx) {
        echo "  - {$idx['Key_name']} on {$idx['Column_name']}\n";
    }
}

echo "\n=== Checking question_option Table ===\n\n";

$tables = db()->fetchAll("SHOW TABLES LIKE 'question_option'");
echo "question_option table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n";

if (count($tables) > 0) {
    $structure = db()->fetchAll("DESCRIBE question_option");
    foreach ($structure as $row) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
}

echo "\n=== Checking student_quiz_answers Table ===\n\n";

$tables = db()->fetchAll("SHOW TABLES LIKE 'student_quiz_answers'");
echo "student_quiz_answers table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n";

if (count($tables) > 0) {
    $structure = db()->fetchAll("DESCRIBE student_quiz_answers");
    foreach ($structure as $row) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
}
