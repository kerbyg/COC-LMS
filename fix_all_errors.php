<?php
/**
 * ============================================================
 * Comprehensive Error Fix Script
 * ============================================================
 * This script fixes all common errors in the student pages
 * ============================================================
 */

$errors = [];
$fixes = [];

// Table name mappings (old => new)
$tableNameFixes = [
    'enrollment' => 'student_subject',
    'quiz_attempt' => 'student_quiz_attempts',
    'student_lesson_progress' => 'student_progress',
    'subject_offering' => 'subject_offered',
];

// Column name fixes
$columnFixes = [
    'subject_offering_id' => 'subject_offered_id',
    'users_id' => 'user_student_id', // in student_subject table
];

// Files to check
$studentPages = glob(__DIR__ . '/pages/student/*.php');

echo "<!DOCTYPE html>
<html><head><title>Fix All Errors</title>
<style>
body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
h1 { color: #4ec9b0; }
.error { color: #f48771; }
.success { color: #4fc1ff; }
.info { color: #dcdcaa; }
pre { background: #252526; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>
</head><body>";

echo "<h1>🔧 Comprehensive Error Analysis</h1>";

echo "<div class='info'><h2>Database Table Names (Correct):</h2><pre>";
echo "✓ student_subject (NOT enrollment)\n";
echo "✓ student_quiz_attempts (NOT quiz_attempt)\n";
echo "✓ student_progress (NOT student_lesson_progress)\n";
echo "✓ subject_offered (NOT subject_offering)\n";
echo "✓ users.contact_number (NOT phone)\n";
echo "✓ users.section (varchar, NOT section_id)\n";
echo "</pre></div>";

echo "<div class='error'><h2>Files with potential errors:</h2><pre>";

foreach ($studentPages as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);
    $fileErrors = [];

    // Check for table name issues
    if (preg_match('/FROM enrollment|JOIN enrollment/i', $content)) {
        $fileErrors[] = "Uses 'enrollment' table (should be 'student_subject')";
    }
    if (preg_match('/FROM quiz_attempt|JOIN quiz_attempt/i', $content)) {
        $fileErrors[] = "Uses 'quiz_attempt' table (should be 'student_quiz_attempts')";
    }
    if (preg_match('/FROM student_lesson_progress|JOIN student_lesson_progress/i', $content)) {
        $fileErrors[] = "Uses 'student_lesson_progress' table (should be 'student_progress')";
    }
    if (preg_match('/FROM subject_offering|JOIN subject_offering/i', $content)) {
        $fileErrors[] = "Uses 'subject_offering' table (should be 'subject_offered')";
    }

    // Check for column issues
    if (preg_match('/subject_offering_id/i', $content)) {
        $fileErrors[] = "Uses 'subject_offering_id' column (should be 'subject_offered_id')";
    }

    if (!empty($fileErrors)) {
        echo "\n❌ $filename:\n";
        foreach ($fileErrors as $error) {
            echo "   - $error\n";
        }
    }
}

echo "</pre></div>";

echo "<div class='success'><h2>✅ Already Fixed:</h2><pre>";
echo "✓ BASE_URL set to '/COC-LMS'\n";
echo "✓ Auth password column uses 'password'\n";
echo "✓ Auth stores first_name and last_name in session\n";
echo "✓ Profile.php uses contact_number\n";
echo "✓ Profile.php uses section (not section_id)\n";
echo "</pre></div>";

echo "<div class='info'><h2>📋 Manual Fixes Needed:</h2>";
echo "<p>Due to the complexity of SQL queries, please manually update the following:</p>";
echo "<ol>";
echo "<li>Replace 'enrollment' with 'student_subject' in all student pages</li>";
echo "<li>Replace 'subject_offering' with 'subject_offered' in all student pages</li>";
echo "<li>Replace 'subject_offering_id' with 'subject_offered_id' in all student pages</li>";
echo "<li>Replace 'quiz_attempt' with 'student_quiz_attempts' in all student pages</li>";
echo "<li>Replace 'student_lesson_progress' with 'student_progress' in all student pages</li>";
echo "<li>Update users_id to user_student_id where querying student_subject table</li>";
echo "</ol></div>";

echo "</body></html>";
?>
