<?php
require_once __DIR__ . '/config/database.php';

echo "=== Enrollment Debug ===\n\n";

// Check sections
$sections = db()->fetchAll("SELECT section_id, section_name, enrollment_code, subject_offered_id FROM section");
echo "SECTIONS (" . count($sections) . " total):\n";
foreach ($sections as $sec) {
    echo "  ID: {$sec['section_id']}, Name: {$sec['section_name']}, Code: " . ($sec['enrollment_code'] ?: 'NULL') . ", Offered: {$sec['subject_offered_id']}\n";
}

echo "\n";

// Check student enrollments
$enrollments = db()->fetchAll("SELECT ss.*, u.first_name, u.last_name FROM student_subject ss JOIN users u ON ss.user_student_id = u.users_id");
echo "STUDENT ENROLLMENTS (" . count($enrollments) . " total):\n";
foreach ($enrollments as $enr) {
    echo "  Student: {$enr['first_name']} {$enr['last_name']}, Section ID: {$enr['section_id']}, Status: {$enr['status']}, Enrolled: {$enr['enrolled_at']}\n";
}

echo "\n";

// Check subject offerings
$offerings = db()->fetchAll("SELECT subject_offered_id, subject_id, academic_year, semester, status FROM subject_offered");
echo "SUBJECT OFFERINGS (" . count($offerings) . " total):\n";
foreach ($offerings as $off) {
    echo "  ID: {$off['subject_offered_id']}, Subject: {$off['subject_id']}, Year: {$off['academic_year']}, Semester: {$off['semester']}, Status: {$off['status']}\n";
}

echo "\n=== Done ===\n";
?>
