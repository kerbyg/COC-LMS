<?php
require_once __DIR__ . '/config/database.php';

echo "=== Instructor Teaching Assignments ===\n\n";

$instructorId = 2; // Juan Dela Cruz

echo "Juan Dela Cruz (ID: $instructorId) is assigned to:\n\n";

$assignments = db()->fetchAll(
    "SELECT fs.faculty_subject_id, fs.section_id, fs.subject_offered_id, fs.status,
            s.subject_code, s.subject_name,
            sec.section_name, sec.enrollment_code,
            so.academic_year, so.semester
     FROM faculty_subject fs
     JOIN section sec ON fs.section_id = sec.section_id
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE fs.user_teacher_id = ?
     ORDER BY fs.status, s.subject_code",
    [$instructorId]
);

if (empty($assignments)) {
    echo "❌ NO TEACHING ASSIGNMENTS FOUND!\n";
    echo "This instructor has no sections assigned in faculty_subject table.\n\n";
} else {
    echo "Found " . count($assignments) . " assignments:\n\n";
    foreach ($assignments as $a) {
        $statusIcon = $a['status'] === 'active' ? '✅' : '❌';
        echo "$statusIcon {$a['subject_code']} - {$a['subject_name']}\n";
        echo "   Section: {$a['section_name']}\n";
        echo "   Offering ID: {$a['subject_offered_id']}, Section ID: {$a['section_id']}\n";
        echo "   Status: {$a['status']}\n";
        echo "   Code: {$a['enrollment_code']}\n\n";
    }
}

echo "\n=== All Sections with Students ===\n\n";

$sections = db()->fetchAll(
    "SELECT sec.section_id, sec.section_name, sec.enrollment_code,
            s.subject_code, s.subject_name,
            so.subject_offered_id,
            COUNT(ss.student_subject_id) as student_count,
            (SELECT CONCAT(u.first_name, ' ', u.last_name)
             FROM faculty_subject fs
             JOIN users u ON fs.user_teacher_id = u.users_id
             WHERE fs.section_id = sec.section_id AND fs.status = 'active'
             LIMIT 1) as instructor_name
     FROM section sec
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     LEFT JOIN student_subject ss ON sec.section_id = ss.section_id AND ss.status = 'enrolled'
     GROUP BY sec.section_id
     ORDER BY s.subject_code, sec.section_name"
);

echo "All sections in the system:\n\n";
foreach ($sections as $sec) {
    $hasInstructor = $sec['instructor_name'] ? "👨‍🏫 {$sec['instructor_name']}" : "❌ NO INSTRUCTOR";
    echo "{$sec['subject_code']} Section {$sec['section_name']} - {$sec['student_count']} students\n";
    echo "   Instructor: $hasInstructor\n";
    echo "   Section ID: {$sec['section_id']}, Offering ID: {$sec['subject_offered_id']}\n";
    echo "   Code: {$sec['enrollment_code']}\n\n";
}
