<?php
/**
 * Create Sections for Old Enrollments
 * Creates default sections for subject offerings that have enrollments but no sections
 */

require_once __DIR__ . '/config/database.php';

echo "=== Creating Sections for Old Enrollments ===\n\n";

// Function to generate unique enrollment code
function generateEnrollmentCode() {
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $numbers = '0123456789';

    $code = '';
    for ($i = 0; $i < 3; $i++) {
        $code .= $letters[random_int(0, strlen($letters) - 1)];
    }
    $code .= '-';
    for ($i = 0; $i < 4; $i++) {
        $code .= $numbers[random_int(0, strlen($numbers) - 1)];
    }

    return $code;
}

try {
    // Find subject offerings that have enrollments but no sections
    $offeringsNeedingSections = db()->fetchAll(
        "SELECT DISTINCT ss.subject_offered_id, so.academic_year, so.semester, s.subject_code, s.subject_name
         FROM student_subject ss
         JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
         JOIN subject s ON so.subject_id = s.subject_id
         WHERE ss.section_id IS NULL OR ss.section_id = 0"
    );

    echo "Found " . count($offeringsNeedingSections) . " subject offerings that need sections\n\n";

    if (empty($offeringsNeedingSections)) {
        echo "✓ All offerings already have sections!\n";
        exit(0);
    }

    $created = 0;

    foreach ($offeringsNeedingSections as $offering) {
        // Check if section already exists for this offering
        $existingSection = db()->fetchOne(
            "SELECT section_id FROM section WHERE subject_offered_id = ?",
            [$offering['subject_offered_id']]
        );

        if ($existingSection) {
            echo "→ Section already exists for {$offering['subject_code']} ({$offering['academic_year']} - {$offering['semester']})\n";
            continue;
        }

        // Generate unique enrollment code
        $enrollmentCode = null;
        $attempts = 0;
        while ($attempts < 10) {
            $code = generateEnrollmentCode();
            $existing = db()->fetchOne("SELECT section_id FROM section WHERE enrollment_code = ?", [$code]);
            if (!$existing) {
                $enrollmentCode = $code;
                break;
            }
            $attempts++;
        }

        // Create default section for this offering
        db()->execute(
            "INSERT INTO section (subject_offered_id, section_name, enrollment_code, schedule, room, max_students, status, created_at, updated_at)
             VALUES (?, 'A', ?, NULL, NULL, 40, 'active', NOW(), NOW())",
            [$offering['subject_offered_id'], $enrollmentCode]
        );

        $newSectionId = db()->lastInsertId();

        echo "✓ Created Section A for {$offering['subject_code']} - {$offering['subject_name']}\n";
        echo "   ({$offering['academic_year']} - {$offering['semester']}) - Code: {$enrollmentCode}\n";

        // Now update enrollments for this offering
        $updated = db()->execute(
            "UPDATE student_subject SET section_id = ?, updated_at = NOW()
             WHERE subject_offered_id = ? AND (section_id IS NULL OR section_id = 0)",
            [$newSectionId, $offering['subject_offered_id']]
        );

        echo "   → Migrated enrollments to this section\n\n";
        $created++;
    }

    echo "\n=== Summary ===\n";
    echo "Created: $created new sections\n";
    echo "✅ All old enrollments now have sections!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
