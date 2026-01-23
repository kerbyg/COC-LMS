<?php
require_once 'config/database.php';

$attemptId = 27;

echo "=== Debugging Quiz Attempt #27 ===\n\n";

// Get attempt info
$attempt = db()->fetchOne("SELECT * FROM student_quiz_attempts WHERE attempt_id = ?", [$attemptId]);
echo "Attempt Info:\n";
print_r($attempt);

echo "\n=== Quiz Questions ===\n";
$questions = db()->fetchAll("SELECT * FROM quiz_questions WHERE quiz_id = ?", [$attempt['quiz_id']]);
foreach ($questions as $q) {
    echo "\nQuestion ID: {$q['question_id']}\n";
    echo "Type: {$q['question_type']}\n";
    echo "Text: {$q['question_text']}\n";
    echo "Points: {$q['points']}\n";

    // Get options
    echo "Options:\n";
    $options = db()->fetchAll("SELECT * FROM question_option WHERE quiz_question_id = ? ORDER BY order_number", [$q['question_id']]);
    foreach ($options as $opt) {
        $correct = $opt['is_correct'] ? " ✓ CORRECT" : "";
        echo "  - Option ID {$opt['option_id']}: {$opt['option_text']}{$correct}\n";
    }
}

echo "\n=== Student Answers ===\n";
$answers = db()->fetchAll("SELECT * FROM student_quiz_answers WHERE attempt_id = ?", [$attemptId]);
foreach ($answers as $ans) {
    echo "\nQuestion ID: {$ans['question_id']}\n";
    echo "Selected Option ID: {$ans['selected_option_id']}\n";
    echo "Is Correct: " . ($ans['is_correct'] ? 'YES' : 'NO') . "\n";
    echo "Points Earned: {$ans['points_earned']}\n";

    // Get what option was selected
    if ($ans['selected_option_id']) {
        $selectedOpt = db()->fetchOne("SELECT * FROM question_option WHERE option_id = ?", [$ans['selected_option_id']]);
        if ($selectedOpt) {
            echo "Selected: {$selectedOpt['option_text']} (is_correct=" . $selectedOpt['is_correct'] . ")\n";
        }
    }
}
