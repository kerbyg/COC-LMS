<?php
require_once __DIR__ . '/config/database.php';

echo "=== Assigning Instructor to Sections ===\n\n";

$instructorId = 2; // Juan Dela Cruz
$sectionsToAssign = [2, 3, 4]; // CC101 Section A, CC102 Section A, CC103 Section A

echo "Assigning Juan Dela Cruz (ID: $instructorId) to sections:\n\n";

foreach ($sectionsToAssign as $sectionId) {
    // Get section details
    $section = db()->fetchOne(
        "SELECT sec.section_id, sec.section_name, sec.subject_offered_id,
                s.subject_code, s.subject_name
         FROM section sec
         JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
         JOIN subject s ON so.subject_id = s.subject_id
         WHERE sec.section_id = ?",
        [$sectionId]
    );

    if (!$section) {
        echo "❌ Section ID $sectionId not found\n";
        continue;
    }

    // Check if already assigned
    $existing = db()->fetchOne(
        "SELECT faculty_subject_id FROM faculty_subject
         WHERE user_teacher_id = ? AND section_id = ?",
        [$instructorId, $sectionId]
    );

    if ($existing) {
        echo "⚠️  Already assigned: {$section['subject_code']} Section {$section['section_name']}\n";
        continue;
    }

    // Assign instructor to section
    try {
        db()->execute(
            "INSERT INTO faculty_subject (user_teacher_id, subject_offered_id, section_id, status, assigned_at, updated_at)
             VALUES (?, ?, ?, 'active', NOW(), NOW())",
            [$instructorId, $section['subject_offered_id'], $sectionId]
        );

        echo "✅ Assigned: {$section['subject_code']} - {$section['subject_name']} Section {$section['section_name']}\n";
    } catch (Exception $e) {
        echo "❌ Error assigning to section $sectionId: " . $e->getMessage() . "\n";
    }
}

echo "\n\n=== Verification ===\n\n";

$allAssignments = db()->fetchAll(
    "SELECT s.subject_code, s.subject_name, sec.section_name,
            COUNT(ss.student_subject_id) as student_count
     FROM faculty_subject fs
     JOIN section sec ON fs.section_id = sec.section_id
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     LEFT JOIN student_subject ss ON sec.section_id = ss.section_id AND ss.status = 'enrolled'
     WHERE fs.user_teacher_id = ? AND fs.status = 'active'
     GROUP BY sec.section_id
     ORDER BY s.subject_code",
    [$instructorId]
);

echo "Juan Dela Cruz is now teaching:\n\n";
foreach ($allAssignments as $a) {
    echo "  ✅ {$a['subject_code']} - {$a['subject_name']} Section {$a['section_name']}\n";
    echo "     Students: {$a['student_count']}\n\n";
}

echo "✅ Done! The instructor students page should now show all students.\n";
