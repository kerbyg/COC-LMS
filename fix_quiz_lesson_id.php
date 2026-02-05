<?php
require_once __DIR__ . '/config/database.php';

echo "=== Fixing Quiz Table lessons_id Column ===\n\n";

try {
    // Step 1: Drop the foreign key constraint
    echo "Step 1: Dropping foreign key constraint quiz_ibfk_1...\n";
    db()->execute("ALTER TABLE quiz DROP FOREIGN KEY quiz_ibfk_1");
    echo "✓ Foreign key constraint dropped\n\n";

    // Step 2: Modify column to allow NULL
    echo "Step 2: Modifying lessons_id to allow NULL values...\n";
    db()->execute("ALTER TABLE quiz MODIFY COLUMN lessons_id INT(11) NULL");
    echo "✓ Column modified to allow NULL\n\n";

    // Step 3: Re-add foreign key constraint with NULL support
    echo "Step 3: Re-adding foreign key constraint with ON DELETE SET NULL...\n";
    db()->execute("
        ALTER TABLE quiz
        ADD CONSTRAINT quiz_ibfk_1
        FOREIGN KEY (lessons_id) REFERENCES lessons(lessons_id)
        ON DELETE SET NULL
    ");
    echo "✓ Foreign key constraint re-added with NULL support\n\n";

    // Step 4: Test INSERT with NULL lessons_id
    echo "Step 4: Testing INSERT with NULL lessons_id...\n";
    $result = db()->execute(
        "INSERT INTO quiz (subject_id, lessons_id, user_teacher_id, quiz_title, quiz_description, time_limit, passing_rate, due_date, status, created_at, updated_at)
         VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
        [8, 2, "Test Independent Quiz", "This quiz is not linked to any lesson", 30, 60, null, 'published']
    );

    $newId = db()->lastInsertId();
    echo "✓ INSERT successful! New quiz_id: $newId\n\n";

    // Verify the record
    $check = db()->fetchOne("SELECT quiz_id, quiz_title, lessons_id FROM quiz WHERE quiz_id = ?", [$newId]);
    if ($check) {
        echo "✓ Record verified:\n";
        echo "  quiz_id: {$check['quiz_id']}\n";
        echo "  quiz_title: {$check['quiz_title']}\n";
        echo "  lessons_id: " . ($check['lessons_id'] ? $check['lessons_id'] : 'NULL (independent quiz)') . "\n";
    }

    echo "\n✅ Fix completed successfully!\n";
    echo "You can now create quizzes without linking them to a lesson.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
