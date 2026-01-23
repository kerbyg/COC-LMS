<?php
require_once __DIR__ . '/config/database.php';

// Check the specific section and enrollment
echo "=== Current State Debug ===\n\n";

// Check the BSIT26 section
$section = db()->fetchOne("SELECT * FROM section WHERE section_name = 'BSIT26'");
echo "BSIT26 Section:\n";
print_r($section);
echo "\n";

// Check Maria Santos's enrollments
$enrollments = db()->fetchAll(
    "SELECT ss.*, sec.section_name, sec.enrollment_code
     FROM student_subject ss
     LEFT JOIN section sec ON ss.section_id = sec.section_id
     WHERE ss.user_student_id = (SELECT users_id FROM users WHERE first_name = 'Maria' AND last_name = 'Santos' LIMIT 1)"
);
echo "Maria Santos Enrollments:\n";
print_r($enrollments);
echo "\n";

// Check all sections
$allSections = db()->fetchAll("SELECT section_id, section_name, subject_offered_id, enrollment_code FROM section");
echo "All Sections:\n";
print_r($allSections);
?>
