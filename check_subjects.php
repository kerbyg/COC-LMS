<?php
require_once __DIR__ . '/config/database.php';

$subjects = db()->fetchAll('SELECT subject_id, subject_code, subject_name, program_id, year_level, semester, status FROM subject ORDER BY subject_code');

echo "Total Subjects: " . count($subjects) . "\n\n";

if (empty($subjects)) {
    echo "❌ No subjects found in database!\n";
} else {
    echo "Subjects in database:\n";
    echo str_repeat("=", 80) . "\n";
    foreach($subjects as $s) {
        $status = $s['status'] === 'active' ? '✓' : '✗';
        echo sprintf(
            "%s [%s] %s - %s (Prog: %s, Year: %s, Sem: %s)\n",
            $status,
            $s['subject_code'],
            $s['subject_name'],
            $s['status'],
            $s['program_id'] ?: 'Not Set',
            $s['year_level'] ?: 'Not Set',
            $s['semester'] ?: 'Not Set'
        );
    }
}
?>
