<?php
require_once __DIR__ . '/config/database.php';

echo "=== Checking Subject Offered ID 8 ===\n\n";

// Check if subject_offered_id 8 exists
$offering = db()->fetchOne("SELECT * FROM subject_offered WHERE subject_offered_id = 8");

if ($offering) {
    echo "Subject Offered #8 exists:\n";
    print_r($offering);
    echo "\n";

    // Check the subject it links to
    $subject = db()->fetchOne("SELECT * FROM subject WHERE subject_id = ?", [$offering['subject_id']]);
    echo "Subject details:\n";
    print_r($subject);
} else {
    echo "❌ Subject Offered #8 does NOT exist!\n";
    echo "This is why enrollment fails silently.\n\n";

    echo "Section BSIT26 (ID: 1) is trying to link to subject_offered_id 8 which doesn't exist.\n";
}

echo "\n=== All Subject Offerings ===\n";
$allOfferings = db()->fetchAll("SELECT subject_offered_id, subject_id, academic_year, semester FROM subject_offered ORDER BY subject_offered_id");
foreach ($allOfferings as $off) {
    echo "ID: {$off['subject_offered_id']}, Subject: {$off['subject_id']}, Year: {$off['academic_year']}, Semester: {$off['semester']}\n";
}
?>
