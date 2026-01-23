<?php
require_once __DIR__ . '/config/database.php';

echo "=== Testing Quiz Creation (Simulating quiz-edit.php form submission) ===\n\n";

// Simulate form data from quiz-edit.php
$subjectId = 8; // GE102
$lessonId = null; // Independent quiz (not linked to lesson)
$teacherId = 2; // Instructor ID
$quizTitle = "Midterm Exam - Philippine History";
$description = "This exam covers chapters 1-5 of Philippine History";
$timeLimit = 60;
$passingRate = 75;
$dueDate = '2026-02-15';
$status = 'published';

try {
    echo "Creating quiz with the following data:\n";
    echo "  Subject ID: $subjectId\n";
    echo "  Lesson ID: " . ($lessonId ? $lessonId : 'NULL (independent)') . "\n";
    echo "  Teacher ID: $teacherId\n";
    echo "  Quiz Title: $quizTitle\n";
    echo "  Description: $description\n";
    echo "  Time Limit: $timeLimit minutes\n";
    echo "  Passing Rate: $passingRate%\n";
    echo "  Due Date: $dueDate\n";
    echo "  Status: $status\n\n";

    // Execute the same INSERT as quiz-edit.php line 89
    db()->execute(
        "INSERT INTO quiz (subject_id, lesson_id, user_teacher_id, quiz_title, quiz_description, time_limit, passing_rate, due_date, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
        [$subjectId, $lessonId, $teacherId, $quizTitle, $description, $timeLimit, $passingRate, $dueDate, $status]
    );

    $newQuizId = db()->lastInsertId();

    echo "✅ Quiz created successfully!\n";
    echo "New quiz_id: $newQuizId\n\n";

    // Verify the quiz was created
    $quiz = db()->fetchOne(
        "SELECT q.*, s.subject_code, s.subject_name
         FROM quiz q
         JOIN subject s ON q.subject_id = s.subject_id
         WHERE q.quiz_id = ?",
        [$newQuizId]
    );

    if ($quiz) {
        echo "✓ Quiz details:\n";
        echo "  Quiz ID: {$quiz['quiz_id']}\n";
        echo "  Title: {$quiz['quiz_title']}\n";
        echo "  Subject: {$quiz['subject_code']} - {$quiz['subject_name']}\n";
        echo "  Lesson ID: " . ($quiz['lesson_id'] ? $quiz['lesson_id'] : 'NULL (independent)') . "\n";
        echo "  Time Limit: {$quiz['time_limit']} minutes\n";
        echo "  Passing Rate: {$quiz['passing_rate']}%\n";
        echo "  Status: {$quiz['status']}\n";
        echo "  Due Date: {$quiz['due_date']}\n";
        echo "\n✅ The quiz creation system is working correctly!\n";
        echo "Next step would be: Redirect to quiz-questions.php?quiz_id=$newQuizId\n";
    } else {
        echo "❌ Quiz record not found after insert!\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
