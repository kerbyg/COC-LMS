<?php
/**
 * Database Migration: Add Enrollment Codes to Sections
 * Adds enrollment_code column and generates unique codes for existing sections
 */

require_once __DIR__ . '/config/database.php';

echo "=== Adding Enrollment Code System ===\n\n";

try {
    // Check if enrollment_code column exists
    $columns = db()->fetchAll("DESCRIBE section");
    $existingColumns = array_column($columns, 'Field');

    if (!in_array('enrollment_code', $existingColumns)) {
        echo "Adding enrollment_code column to section table...\n";
        db()->execute("ALTER TABLE section ADD COLUMN enrollment_code VARCHAR(10) UNIQUE NULL AFTER section_name");
        echo "✓ enrollment_code column added\n\n";
    } else {
        echo "✓ enrollment_code column already exists\n\n";
    }

    // Function to generate unique enrollment code
    function generateEnrollmentCode() {
        // Format: ABC-1234 (3 letters + 4 numbers)
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // Excluding I and O to avoid confusion
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

    // Generate codes for existing sections
    $sections = db()->fetchAll("SELECT section_id, section_name FROM section WHERE enrollment_code IS NULL");

    if (!empty($sections)) {
        echo "Generating enrollment codes for " . count($sections) . " existing sections...\n";

        foreach ($sections as $section) {
            $attempts = 0;
            $maxAttempts = 10;

            while ($attempts < $maxAttempts) {
                $code = generateEnrollmentCode();

                // Check if code is unique
                $existing = db()->fetchOne("SELECT section_id FROM section WHERE enrollment_code = ?", [$code]);

                if (!$existing) {
                    db()->execute("UPDATE section SET enrollment_code = ? WHERE section_id = ?", [$code, $section['section_id']]);
                    echo "  ✓ Section '{$section['section_name']}' → {$code}\n";
                    break;
                }

                $attempts++;
            }
        }
        echo "\n";
    } else {
        echo "No sections need enrollment codes.\n\n";
    }

    echo "✅ Enrollment code system setup complete!\n\n";
    echo "Students can now enroll using section codes.\n";
    echo "Format: XXX-9999 (e.g., ABC-1234)\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
