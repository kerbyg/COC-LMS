<?php
require_once __DIR__ . '/config/database.php';

echo "=== Debug Instructor Students Query ===\n\n";

$userId = 2; // Assuming instructor ID 2

// Step 1: Check what sections the instructor teaches
echo "Step 1: Instructor's Teaching Assignments\n";
$assignments = db()->fetchAll(
    "SELECT fs.faculty_subject_id, fs.section_id, fs.subject_offered_id,
            s.subject_code, s.subject_name,
            sec.section_name, sec.enrollment_code
     FROM faculty_subject fs
     JOIN section sec ON fs.section_id = sec.section_id
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE fs.user_teacher_id = ? AND fs.status = 'active'",
    [$userId]
);

echo "Found " . count($assignments) . " teaching assignments:\n";
foreach ($assignments as $a) {
    echo "  - {$a['subject_code']} Section {$a['section_name']} (section_id: {$a['section_id']}, code: {$a['enrollment_code']})\n";
}

// Step 2: Check students enrolled in those sections
echo "\n\nStep 2: Students Enrolled in Instructor's Sections\n";
$students = db()->fetchAll(
    "SELECT ss.student_subject_id, ss.user_student_id, ss.section_id, ss.status,
            ss.enrollment_date,
            u.first_name, u.last_name, u.email, u.student_id,
            s.subject_code, sec.section_name
     FROM student_subject ss
     JOIN users u ON ss.user_student_id = u.users_id
     JOIN section sec ON ss.section_id = sec.section_id
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE ss.status = 'enrolled'",
    []
);

echo "Found " . count($students) . " enrolled students (all sections):\n";
foreach ($students as $st) {
    echo "  - {$st['first_name']} {$st['last_name']} in {$st['subject_code']} Section {$st['section_name']} (section_id: {$st['section_id']}, status: {$st['status']})\n";
}

// Step 3: Test the original query from students.php (with correct column name)
echo "\n\nStep 3: Testing students.php Query (FIXED)\n";
try {
    $studentsFixed = db()->fetchAll(
        "SELECT
            u.users_id,
            u.student_id,
            u.first_name,
            u.last_name,
            u.email,
            s.subject_id,
            s.subject_code,
            s.subject_name,
            so.subject_offered_id,
            sec.section_id,
            sec.section_name,
            ss.student_subject_id,
            ss.enrollment_date
        FROM student_subject ss
        JOIN users u ON ss.user_student_id = u.users_id
        JOIN section sec ON ss.section_id = sec.section_id
        JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
        JOIN subject s ON so.subject_id = s.subject_id
        JOIN faculty_subject fs ON sec.section_id = fs.section_id
        WHERE fs.user_teacher_id = ? AND ss.status = 'enrolled'
        ORDER BY s.subject_code, sec.section_name, u.last_name, u.first_name",
        [$userId]
    );

    echo "✓ Query successful! Found " . count($studentsFixed) . " students:\n";
    foreach ($studentsFixed as $st) {
        echo "  - {$st['first_name']} {$st['last_name']} in {$st['subject_code']} Section {$st['section_name']}\n";
    }
} catch (Exception $e) {
    echo "❌ Query failed: " . $e->getMessage() . "\n";
}

// Step 4: Test the original query with wrong column name
echo "\n\nStep 4: Testing students.php Query (BROKEN - with enrolled_at)\n";
try {
    $studentsBroken = db()->fetchAll(
        "SELECT
            u.users_id,
            ss.enrolled_at
        FROM student_subject ss
        JOIN users u ON ss.user_student_id = u.users_id
        JOIN section sec ON ss.section_id = sec.section_id
        JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
        JOIN subject s ON so.subject_id = s.subject_id
        JOIN faculty_subject fs ON sec.section_id = fs.section_id
        WHERE fs.user_teacher_id = ? AND ss.status = 'enrolled'",
        [$userId]
    );

    echo "Query executed (shouldn't reach here)\n";
} catch (Exception $e) {
    echo "❌ Query failed as expected: " . $e->getMessage() . "\n";
}
