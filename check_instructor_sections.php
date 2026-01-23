<?php
require_once 'config/database.php';

echo "<h2>Debug: Instructor Assignments per Section</h2>";

// Get all sections with their instructor info
$sections = db()->fetchAll(
    "SELECT sec.section_id, sec.section_name, so.subject_offered_id, s.subject_code,
        (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM faculty_subject fs JOIN users u ON fs.user_teacher_id = u.users_id WHERE fs.section_id = sec.section_id LIMIT 1) as instructor_name,
        (SELECT fs.faculty_subject_id FROM faculty_subject fs WHERE fs.section_id = sec.section_id LIMIT 1) as fs_id
     FROM section sec
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     ORDER BY sec.section_id"
);

echo "<h3>All Sections:</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
echo "<tr style='background:#f0f0f0'><th>Section ID</th><th>Section Name</th><th>Subject</th><th>Offering ID</th><th>Instructor</th><th>FS ID</th><th>Has Instructor?</th></tr>";

$noInstructorCount = 0;
foreach ($sections as $sec) {
    $hasInstructor = !empty($sec['instructor_name']);
    $style = $hasInstructor ? "" : "background:#fff3cd";
    if (!$hasInstructor) $noInstructorCount++;

    echo "<tr style='$style'>";
    echo "<td>{$sec['section_id']}</td>";
    echo "<td>{$sec['section_name']}</td>";
    echo "<td>{$sec['subject_code']}</td>";
    echo "<td>{$sec['subject_offered_id']}</td>";
    echo "<td>" . ($sec['instructor_name'] ?: '<em>NONE</em>') . "</td>";
    echo "<td>" . ($sec['fs_id'] ?: 'NULL') . "</td>";
    echo "<td>" . ($hasInstructor ? '✅ Yes' : '❌ No') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Summary: $noInstructorCount sections without instructor</h3>";

echo "<hr>";
echo "<h3>Faculty Subject Table (all entries):</h3>";
$fs = db()->fetchAll("SELECT * FROM faculty_subject ORDER BY faculty_subject_id");
echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
echo "<tr style='background:#f0f0f0'><th>FS ID</th><th>Teacher ID</th><th>Offering ID</th><th>Section ID</th><th>Status</th></tr>";
foreach ($fs as $f) {
    $style = empty($f['section_id']) ? "background:#ffcccc" : "";
    echo "<tr style='$style'>";
    echo "<td>{$f['faculty_subject_id']}</td>";
    echo "<td>{$f['user_teacher_id']}</td>";
    echo "<td>{$f['subject_offered_id']}</td>";
    echo "<td>" . ($f['section_id'] ?: '<em>NULL</em>') . "</td>";
    echo "<td>{$f['status']}</td>";
    echo "</tr>";
}
echo "</table>";
