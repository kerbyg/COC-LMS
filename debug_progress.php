<?php
require_once 'config/database.php';
require_once 'config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();

echo "<h2>Debug: Student Progress</h2>";
echo "<p>User ID: <strong>$userId</strong></p>";

// 1. Check enrolled subjects
echo "<h3>1. Enrolled Subjects (student_subject table):</h3>";
$enrollments = db()->fetchAll(
    "SELECT ss.*, so.subject_id, so.academic_year, so.semester
     FROM student_subject ss
     JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
     WHERE ss.user_student_id = ? AND ss.status = 'enrolled'",
    [$userId]
);
echo "<p>Found: <strong>" . count($enrollments) . "</strong> enrollments</p>";
if ($enrollments) {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
    echo "<tr style='background:#f0f0f0'><th>ID</th><th>Subject Offered ID</th><th>Subject ID</th><th>Year</th><th>Semester</th><th>Status</th></tr>";
    foreach ($enrollments as $e) {
        echo "<tr>";
        echo "<td>{$e['student_subject_id']}</td>";
        echo "<td>{$e['subject_offered_id']}</td>";
        echo "<td>{$e['subject_id']}</td>";
        echo "<td>{$e['academic_year']}</td>";
        echo "<td>{$e['semester']}</td>";
        echo "<td>{$e['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 2. Check the subject progress query
echo "<h3>2. Subject Progress Query Result:</h3>";
$subjectProgress = db()->fetchAll(
    "SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
        (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.subject_id AND l.status = 'published') as total_lessons,
        (SELECT COUNT(*) FROM student_progress sp
         JOIN lessons l ON sp.lesson_id = l.lesson_id
         WHERE sp.user_student_id = ? AND l.subject_id = s.subject_id AND sp.status = 'completed') as completed_lessons,
        (SELECT COUNT(*) FROM quiz q WHERE q.subject_id = s.subject_id AND q.status = 'published') as total_quizzes,
        (SELECT COUNT(DISTINCT qa.quiz_id) FROM student_quiz_attempts qa
         JOIN quiz q ON qa.quiz_id = q.quiz_id
         WHERE qa.user_student_id = ? AND q.subject_id = s.subject_id AND qa.status = 'completed') as completed_quizzes
    FROM student_subject ss
    JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    LEFT JOIN faculty_subject fs ON so.subject_offered_id = fs.subject_offered_id
    LEFT JOIN users u ON fs.user_teacher_id = u.users_id
    WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
    ORDER BY s.subject_code",
    [$userId, $userId, $userId]
);

echo "<p>Found: <strong>" . count($subjectProgress) . "</strong> subjects</p>";
if ($subjectProgress) {
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
    echo "<tr style='background:#f0f0f0'><th>Subject ID</th><th>Code</th><th>Name</th><th>Instructor</th><th>Lessons</th><th>Quizzes</th></tr>";
    foreach ($subjectProgress as $sp) {
        echo "<tr>";
        echo "<td>{$sp['subject_id']}</td>";
        echo "<td>{$sp['subject_code']}</td>";
        echo "<td>{$sp['subject_name']}</td>";
        echo "<td>" . ($sp['instructor_name'] ?: 'N/A') . "</td>";
        echo "<td>{$sp['completed_lessons']}/{$sp['total_lessons']}</td>";
        echo "<td>{$sp['completed_quizzes']}/{$sp['total_quizzes']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red'>No subjects returned! Let's debug further...</p>";

    // Check if there are any subjects linked
    echo "<h4>Checking subjects table:</h4>";
    $subjects = db()->fetchAll("SELECT subject_id, subject_code, subject_name FROM subject LIMIT 10");
    echo "<pre>" . print_r($subjects, true) . "</pre>";

    echo "<h4>Checking subject_offered table:</h4>";
    $offerings = db()->fetchAll("SELECT * FROM subject_offered LIMIT 10");
    echo "<pre>" . print_r($offerings, true) . "</pre>";
}

// 3. Check stats query
echo "<h3>3. Stats Query:</h3>";
$stats = db()->fetchOne(
    "SELECT
        (SELECT COUNT(*) FROM student_subject WHERE user_student_id = ? AND status = 'enrolled') as total_subjects,
        (SELECT COUNT(*) FROM student_progress WHERE user_student_id = ? AND status = 'completed') as lessons_completed,
        (SELECT COUNT(DISTINCT quiz_id) FROM student_quiz_attempts WHERE user_student_id = ? AND status = 'completed') as quizzes_taken,
        (SELECT ROUND(AVG(percentage), 1) FROM student_quiz_attempts WHERE user_student_id = ? AND status = 'completed') as avg_score
    ",
    [$userId, $userId, $userId, $userId]
);
echo "<pre>" . print_r($stats, true) . "</pre>";

echo "<hr>";
echo "<p><a href='pages/student/progress.php' style='background:#16a34a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block'>Go to Progress Page →</a></p>";
