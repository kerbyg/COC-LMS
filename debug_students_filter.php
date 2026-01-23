<?php
require_once __DIR__ . '/config/database.php';

echo "=== Debug Students Filter Issue ===\n\n";

$instructorId = 2; // Juan Dela Cruz (instructor)
$offeredId = 1; // CC101 - Introduction to Computing

// Step 1: Check instructor's teaching assignments
echo "Step 1: Instructor's Teaching Assignments (subject_offered_id = $offeredId)\n";
$assignments = db()->fetchAll(
    "SELECT fs.faculty_subject_id, fs.section_id, fs.subject_offered_id,
            s.subject_code, s.subject_name,
            sec.section_name, sec.enrollment_code
     FROM faculty_subject fs
     JOIN section sec ON fs.section_id = sec.section_id
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE fs.user_teacher_id = ? AND fs.subject_offered_id = ? AND fs.status = 'active'",
    [$instructorId, $offeredId]
);

echo "Found " . count($assignments) . " teaching assignments:\n";
foreach ($assignments as $a) {
    echo "  - {$a['subject_code']} Section {$a['section_name']} (section_id: {$a['section_id']}, offered_id: {$a['subject_offered_id']})\n";
}

// Step 2: Check students enrolled in those sections
echo "\n\nStep 2: Students in CC101 Sections\n";
$students = db()->fetchAll(
    "SELECT ss.student_subject_id, ss.user_student_id, ss.section_id,
            ss.subject_offered_id, ss.status,
            u.first_name, u.last_name,
            sec.section_name, s.subject_code
     FROM student_subject ss
     JOIN users u ON ss.user_student_id = u.users_id
     JOIN section sec ON ss.section_id = sec.section_id
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE sec.subject_offered_id = ? AND ss.status = 'enrolled'",
    [$offeredId]
);

echo "Found " . count($students) . " students enrolled in CC101:\n";
foreach ($students as $st) {
    echo "  - {$st['first_name']} {$st['last_name']} (section: {$st['section_name']}, ss.subject_offered_id: {$st['subject_offered_id']}, sec.subject_offered_id via section)\n";
}

// Step 3: Test the CURRENT query (the broken one)
echo "\n\nStep 3: Current Query (CHECKING ss.subject_offered_id)\n";
try {
    $currentQuery = db()->fetchAll(
        "SELECT u.first_name, u.last_name, s.subject_code, sec.section_name,
                ss.subject_offered_id, so.subject_offered_id as so_id
         FROM student_subject ss
         JOIN users u ON ss.user_student_id = u.users_id
         JOIN section sec ON ss.section_id = sec.section_id
         JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
         JOIN subject s ON so.subject_id = s.subject_id
         JOIN faculty_subject fs ON sec.section_id = fs.section_id
         WHERE fs.user_teacher_id = ? AND ss.status = 'enrolled'
           AND ss.subject_offered_id = ?",
        [$instructorId, $offeredId]
    );

    echo "Results: " . count($currentQuery) . " students\n";
    foreach ($currentQuery as $st) {
        echo "  - {$st['first_name']} {$st['last_name']} (ss.subject_offered_id={$st['subject_offered_id']}, so.subject_offered_id={$st['so_id']})\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Step 4: Test FIXED query (filter by section's subject_offered_id)
echo "\n\nStep 4: FIXED Query (CHECKING so.subject_offered_id via section)\n";
try {
    $fixedQuery = db()->fetchAll(
        "SELECT u.first_name, u.last_name, s.subject_code, sec.section_name,
                ss.subject_offered_id, so.subject_offered_id as so_id
         FROM student_subject ss
         JOIN users u ON ss.user_student_id = u.users_id
         JOIN section sec ON ss.section_id = sec.section_id
         JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
         JOIN subject s ON so.subject_id = s.subject_id
         JOIN faculty_subject fs ON sec.section_id = fs.section_id
         WHERE fs.user_teacher_id = ? AND ss.status = 'enrolled'
           AND so.subject_offered_id = ?",
        [$instructorId, $offeredId]
    );

    echo "Results: " . count($fixedQuery) . " students\n";
    foreach ($fixedQuery as $st) {
        echo "  - {$st['first_name']} {$st['last_name']} (ss.subject_offered_id={$st['subject_offered_id']}, so.subject_offered_id={$st['so_id']})\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n\n=== Analysis ===\n";
echo "The issue is that student_subject.subject_offered_id might not match\n";
echo "the section's subject_offered_id in the new section-based system.\n";
echo "We should filter by so.subject_offered_id (from section table) instead.\n";
