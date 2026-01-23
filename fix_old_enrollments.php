<?php
/**
 * Fix Old Enrollments - Link to Sections
 * Updates student_subject records that have subject_offered_id but no section_id
 */

require_once __DIR__ . '/config/database.php';

echo "=== Fixing Old Enrollments ===\n\n";

try {
    // Find enrollments without section_id
    $oldEnrollments = db()->fetchAll(
        "SELECT student_subject_id, user_student_id, subject_offered_id
         FROM student_subject
         WHERE section_id IS NULL OR section_id = 0"
    );

    echo "Found " . count($oldEnrollments) . " enrollments without section_id\n\n";

    if (empty($oldEnrollments)) {
        echo "✓ All enrollments already have sections assigned!\n";
        exit(0);
    }

    $fixed = 0;
    $skipped = 0;

    foreach ($oldEnrollments as $enrollment) {
        // Try to find a section for this subject_offered_id
        $section = db()->fetchOne(
            "SELECT section_id FROM section WHERE subject_offered_id = ? LIMIT 1",
            [$enrollment['subject_offered_id']]
        );

        if ($section) {
            // Update the enrollment with the section_id
            db()->execute(
                "UPDATE student_subject SET section_id = ?, updated_at = NOW() WHERE student_subject_id = ?",
                [$section['section_id'], $enrollment['student_subject_id']]
            );

            echo "✓ Fixed enrollment #{$enrollment['student_subject_id']} → Section #{$section['section_id']}\n";
            $fixed++;
        } else {
            echo "⚠ Skipped enrollment #{$enrollment['student_subject_id']} (no section found for offering #{$enrollment['subject_offered_id']})\n";
            $skipped++;
        }
    }

    echo "\n=== Summary ===\n";
    echo "Fixed: $fixed enrollments\n";
    echo "Skipped: $skipped enrollments\n";
    echo "\n✅ Done!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
