<?php
require_once __DIR__ . '/config/database.php';

echo "=== Fixing Faculty Subject Assignments ===\n\n";

echo "Problem: Old faculty_subject records have NULL section_id\n";
echo "Solution: Update them with the correct section_id\n\n";

// Get all faculty assignments with NULL section_id
$nullAssignments = db()->fetchAll(
    "SELECT fs.faculty_subject_id, fs.user_teacher_id, fs.subject_offered_id,
            u.first_name, u.last_name,
            s.subject_code, s.subject_name
     FROM faculty_subject fs
     JOIN users u ON fs.user_teacher_id = u.users_id
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE fs.section_id IS NULL"
);

echo "Found " . count($nullAssignments) . " assignments with NULL section_id:\n\n";

foreach ($nullAssignments as $assignment) {
    echo "Processing: {$assignment['first_name']} {$assignment['last_name']} - {$assignment['subject_code']}\n";

    // Find the section for this subject_offered
    $section = db()->fetchOne(
        "SELECT section_id, section_name FROM section
         WHERE subject_offered_id = ?
         LIMIT 1",
        [$assignment['subject_offered_id']]
    );

    if ($section) {
        // Update the assignment with the section_id
        db()->execute(
            "UPDATE faculty_subject SET section_id = ?, updated_at = NOW()
             WHERE faculty_subject_id = ?",
            [$section['section_id'], $assignment['faculty_subject_id']]
        );

        echo "  ✅ Updated: Assigned to Section {$section['section_name']} (section_id: {$section['section_id']})\n\n";
    } else {
        echo "  ⚠️  No section found for subject_offered_id {$assignment['subject_offered_id']}\n\n";
    }
}

echo "\n=== Verification ===\n\n";

$instructorId = 2;
$allAssignments = db()->fetchAll(
    "SELECT fs.faculty_subject_id, s.subject_code, s.subject_name, sec.section_name,
            fs.section_id, fs.subject_offered_id,
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

echo "Juan Dela Cruz is now teaching " . count($allAssignments) . " sections:\n\n";
foreach ($allAssignments as $a) {
    echo "  ✅ {$a['subject_code']} - {$a['subject_name']} Section {$a['section_name']}\n";
    echo "     Section ID: {$a['section_id']}, Offering ID: {$a['subject_offered_id']}\n";
    echo "     Students: {$a['student_count']}\n\n";
}

echo "✅ Done! Refresh the instructor students page to see all students.\n";
