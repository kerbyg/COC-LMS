<?php
require_once 'config/database.php';
require_once 'config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();

echo "<h2>Debug: Student Quizzes Grouping</h2>";
echo "<p>User ID: $userId</p>";

// Get all quizzes
$quizzes = db()->fetchAll(
    "SELECT
        q.quiz_id,
        q.quiz_title as title,
        q.subject_id,
        s.subject_code,
        s.subject_name,
        so.subject_offered_id
    FROM quiz q
    JOIN subject s ON q.subject_id = s.subject_id
    JOIN subject_offered so ON so.subject_id = s.subject_id
    JOIN student_subject ss ON ss.subject_offered_id = so.subject_offered_id
    WHERE ss.user_student_id = ? AND ss.status = 'enrolled' AND q.status = 'published'
    ORDER BY s.subject_code, q.created_at ASC",
    [$userId]
);

echo "<h3>Raw Quizzes:</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
echo "<tr style='background:#f0f0f0'><th>Quiz ID</th><th>Title</th><th>Subject Code</th><th>Subject Name</th><th>Offering ID</th></tr>";
foreach ($quizzes as $q) {
    echo "<tr>";
    echo "<td>{$q['quiz_id']}</td>";
    echo "<td>{$q['title']}</td>";
    echo "<td>{$q['subject_code']}</td>";
    echo "<td>{$q['subject_name']}</td>";
    echo "<td>{$q['subject_offered_id']}</td>";
    echo "</tr>";
}
echo "</table>";

// Group by subject
$quizzesBySubject = [];
foreach ($quizzes as $quiz) {
    $key = $quiz['subject_offered_id'];
    if (!isset($quizzesBySubject[$key])) {
        $quizzesBySubject[$key] = [
            'subject_code' => $quiz['subject_code'],
            'subject_name' => $quiz['subject_name'],
            'subject_offered_id' => $quiz['subject_offered_id'],
            'quizzes' => []
        ];
    }
    $quizzesBySubject[$key]['quizzes'][] = $quiz;
}

echo "<h3>Grouped by Subject Offering:</h3>";
echo "<p>Total groups: <strong>" . count($quizzesBySubject) . "</strong></p>";

foreach ($quizzesBySubject as $key => $data) {
    echo "<div style='margin:10px 0;padding:15px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px'>";
    echo "<h4 style='margin:0 0 10px'><span style='background:#16a34a;color:#fff;padding:4px 10px;border-radius:4px;margin-right:10px'>{$data['subject_code']}</span> {$data['subject_name']}</h4>";
    echo "<p style='margin:0;color:#666'>Offering ID: {$data['subject_offered_id']} | Quizzes: " . count($data['quizzes']) . "</p>";
    echo "<ul style='margin:10px 0 0;padding-left:20px'>";
    foreach ($data['quizzes'] as $q) {
        echo "<li>{$q['title']}</li>";
    }
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='pages/student/quizzes.php' style='background:#16a34a;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block'>Go to Quizzes Page →</a></p>";
