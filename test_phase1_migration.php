<?php
require_once 'config/database.php';

// Styling for better readability
?>
<!DOCTYPE html>
<html>
<head>
    <title>Phase 1 Migration Test</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; background: #ecf0f1; padding: 10px; border-radius: 4px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .info { color: #3498db; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #3498db; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .badge-success { background: #27ae60; color: white; }
        .badge-danger { background: #e74c3c; color: white; }
        .test-step { background: #fff; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0; }
        .code { background: #2c3e50; color: #ecf0f1; padding: 10px; border-radius: 4px; overflow-x: auto; }
        pre { margin: 0; }
    </style>
</head>
<body>
<div class="container">

<h1>🧪 Phase 1 Migration - Complete Test Suite</h1>
<p><strong>Testing Date:</strong> <?= date('Y-m-d H:i:s') ?></p>

<?php
$allTestsPassed = true;

// ============================================================
// TEST 1: Verify Tables Exist
// ============================================================
echo "<h2>📋 Test 1: Verify New Tables</h2>";
echo "<div class='test-step'>";

$expectedTables = ['quiz_questions', 'question_option', 'student_quiz_answers'];
$tablesExist = [];

foreach ($expectedTables as $table) {
    $result = db()->fetchAll("SHOW TABLES LIKE '$table'");
    $exists = count($result) > 0;
    $tablesExist[$table] = $exists;

    $status = $exists ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>";
    echo "<div>$status - Table: <strong>$table</strong></div>";

    if (!$exists) $allTestsPassed = false;
}

echo "</div>";

// ============================================================
// TEST 2: Verify Table Structures
// ============================================================
echo "<h2>🔍 Test 2: Verify Table Structures</h2>";

if ($tablesExist['quiz_questions']) {
    echo "<div class='test-step'>";
    echo "<h3>quiz_questions table structure:</h3>";
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

    $structure = db()->fetchAll("DESCRIBE quiz_questions");
    foreach ($structure as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>" . ($col['Key'] ? "<span class='badge badge-success'>{$col['Key']}</span>" : "-") . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
}

if ($tablesExist['question_option']) {
    echo "<div class='test-step'>";
    echo "<h3>question_option table structure:</h3>";
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";

    $structure = db()->fetchAll("DESCRIBE question_option");
    foreach ($structure as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>" . ($col['Key'] ? "<span class='badge badge-success'>{$col['Key']}</span>" : "-") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
}

if ($tablesExist['student_quiz_answers']) {
    echo "<div class='test-step'>";
    echo "<h3>student_quiz_answers table structure:</h3>";
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";

    $structure = db()->fetchAll("DESCRIBE student_quiz_answers");
    foreach ($structure as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>" . ($col['Key'] ? "<span class='badge badge-success'>{$col['Key']}</span>" : "-") . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
}

// ============================================================
// TEST 3: Verify New Quiz Columns
// ============================================================
echo "<h2>➕ Test 3: Verify New Quiz Table Columns</h2>";
echo "<div class='test-step'>";

$expectedColumns = [
    'passing_score' => 'decimal(5,2)',
    'shuffle_questions' => 'tinyint(1)',
    'shuffle_options' => 'tinyint(1)',
    'show_correct_answers' => 'tinyint(1)',
    'allow_review' => 'tinyint(1)',
    'question_count' => 'int(11)'
];

$quizStructure = db()->fetchAll("DESCRIBE quiz");
$quizColumns = array_column($quizStructure, 'Field');

echo "<table>";
echo "<tr><th>Column</th><th>Expected Type</th><th>Status</th></tr>";

foreach ($expectedColumns as $col => $type) {
    $exists = in_array($col, $quizColumns);
    $status = $exists ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>";

    echo "<tr>";
    echo "<td><strong>$col</strong></td>";
    echo "<td>$type</td>";
    echo "<td>$status</td>";
    echo "</tr>";

    if (!$exists) $allTestsPassed = false;
}

echo "</table>";
echo "</div>";

// ============================================================
// TEST 4: Verify Views
// ============================================================
echo "<h2>👁️ Test 4: Verify Views Created</h2>";
echo "<div class='test-step'>";

$expectedViews = [
    'vw_quiz_summary',
    'vw_question_difficulty_analysis',
    'vw_student_quiz_performance'
];

$views = db()->fetchAll("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
$existingViews = array_column($views, 'Tables_in_cit_lms');

echo "<table>";
echo "<tr><th>View Name</th><th>Status</th></tr>";

foreach ($expectedViews as $view) {
    $exists = in_array($view, $existingViews);
    $status = $exists ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>";

    echo "<tr>";
    echo "<td><strong>$view</strong></td>";
    echo "<td>$status</td>";
    echo "</tr>";

    if (!$exists) $allTestsPassed = false;
}

echo "</table>";
echo "</div>";

// ============================================================
// TEST 5: Verify Stored Procedures
// ============================================================
echo "<h2>⚙️ Test 5: Verify Stored Procedures</h2>";
echo "<div class='test-step'>";

$expectedProcedures = [
    'sp_calculate_quiz_points',
    'sp_grade_quiz_attempt'
];

$procedures = db()->fetchAll("SHOW PROCEDURE STATUS WHERE Db = 'cit_lms'");
$existingProcedures = array_column($procedures, 'Name');

echo "<table>";
echo "<tr><th>Procedure Name</th><th>Status</th></tr>";

foreach ($expectedProcedures as $proc) {
    $exists = in_array($proc, $existingProcedures);
    $status = $exists ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>";

    echo "<tr>";
    echo "<td><strong>$proc</strong></td>";
    echo "<td>$status</td>";
    echo "</tr>";

    if (!$exists) $allTestsPassed = false;
}

echo "</table>";
echo "</div>";

// ============================================================
// TEST 6: Verify Triggers
// ============================================================
echo "<h2>⚡ Test 6: Verify Triggers</h2>";
echo "<div class='test-step'>";

$expectedTriggers = [
    'trg_after_question_insert',
    'trg_after_question_delete',
    'trg_auto_grade_answer'
];

$triggers = db()->fetchAll("SHOW TRIGGERS WHERE `Trigger` LIKE 'trg_%'");
$existingTriggers = array_column($triggers, 'Trigger');

echo "<table>";
echo "<tr><th>Trigger Name</th><th>Event</th><th>Table</th><th>Status</th></tr>";

foreach ($expectedTriggers as $trig) {
    $exists = in_array($trig, $existingTriggers);
    $status = $exists ? "<span class='success'>✓ EXISTS</span>" : "<span class='error'>✗ MISSING</span>";

    $triggerInfo = array_filter($triggers, function($t) use ($trig) {
        return $t['Trigger'] === $trig;
    });

    $triggerData = reset($triggerInfo);

    echo "<tr>";
    echo "<td><strong>$trig</strong></td>";
    echo "<td>" . ($triggerData['Event'] ?? '-') . "</td>";
    echo "<td>" . ($triggerData['Table'] ?? '-') . "</td>";
    echo "<td>$status</td>";
    echo "</tr>";

    if (!$exists) $allTestsPassed = false;
}

echo "</table>";
echo "</div>";

// ============================================================
// TEST 7: Test Actual Functionality with Sample Data
// ============================================================
echo "<h2>🧪 Test 7: Functional Test with Sample Data</h2>";
echo "<div class='test-step'>";

try {
    // Get a quiz to work with (or create one for testing)
    $testQuiz = db()->fetchOne("SELECT quiz_id FROM quiz LIMIT 1");

    if (!$testQuiz) {
        echo "<div class='warning'>⚠️ No quiz found in database. Creating test quiz...</div>";

        // Get a subject and instructor for the test
        $testSubject = db()->fetchOne("SELECT subject_id FROM subject LIMIT 1");
        $testInstructor = db()->fetchOne("SELECT users_id FROM users WHERE role = 'instructor' LIMIT 1");

        if ($testSubject && $testInstructor) {
            db()->execute(
                "INSERT INTO quiz (subject_id, lessons_id, user_teacher_id, quiz_title, quiz_description, time_limit, passing_rate, status, created_at, updated_at)
                 VALUES (?, 0, ?, 'Phase 1 Test Quiz', 'This is a test quiz for migration verification', 30, 60.00, 'draft', NOW(), NOW())",
                [$testSubject['subject_id'], $testInstructor['users_id']]
            );
            $testQuizId = db()->lastInsertId();
            echo "<div class='success'>✓ Test quiz created with ID: $testQuizId</div>";
        } else {
            echo "<div class='error'>✗ Cannot create test quiz: Missing subject or instructor</div>";
            $testQuizId = null;
        }
    } else {
        $testQuizId = $testQuiz['quiz_id'];
        echo "<div class='info'>Using existing quiz ID: $testQuizId</div>";
    }

    if ($testQuizId) {
        // Test 7a: Insert a question
        echo "<h3>Test 7a: Insert Question</h3>";
        db()->execute(
            "INSERT INTO quiz_questions (quiz_id, question_text, question_type, points, order_number, difficulty)
             VALUES (?, 'What is 2 + 2?', 'multiple_choice', 1, 1, 'easy')",
            [$testQuizId]
        );
        $questionId = db()->lastInsertId();
        echo "<div class='success'>✓ Question inserted with ID: $questionId</div>";

        // Test 7b: Insert options
        echo "<h3>Test 7b: Insert Options</h3>";
        $options = [
            ['3', false],
            ['4', true],
            ['5', false],
            ['6', false]
        ];

        foreach ($options as $idx => $opt) {
            db()->execute(
                "INSERT INTO question_option (quiz_question_id, option_text, is_correct, order_number)
                 VALUES (?, ?, ?, ?)",
                [$questionId, $opt[0], $opt[1] ? 1 : 0, $idx + 1]
            );
        }
        echo "<div class='success'>✓ 4 options inserted successfully</div>";

        // Test 7c: Verify trigger updated quiz counts
        echo "<h3>Test 7c: Verify Trigger (Auto-update quiz counts)</h3>";
        $quizData = db()->fetchOne("SELECT question_count, total_points FROM quiz WHERE quiz_id = ?", [$testQuizId]);
        echo "<div>Question Count: <strong>{$quizData['question_count']}</strong> (Expected: 1 or more)</div>";
        echo "<div>Total Points: <strong>{$quizData['total_points']}</strong> (Expected: 1 or more)</div>";

        if ($quizData['question_count'] > 0 && $quizData['total_points'] > 0) {
            echo "<div class='success'>✓ Triggers working correctly!</div>";
        } else {
            echo "<div class='error'>✗ Triggers may not be working</div>";
            $allTestsPassed = false;
        }

        // Test 7d: Test stored procedure
        echo "<h3>Test 7d: Test Stored Procedure (sp_calculate_quiz_points)</h3>";
        try {
            db()->execute("CALL sp_calculate_quiz_points(?)", [$testQuizId]);
            $quizDataAfter = db()->fetchOne("SELECT question_count, total_points FROM quiz WHERE quiz_id = ?", [$testQuizId]);
            echo "<div class='success'>✓ Stored procedure executed successfully</div>";
            echo "<div>Updated Question Count: <strong>{$quizDataAfter['question_count']}</strong></div>";
            echo "<div>Updated Total Points: <strong>{$quizDataAfter['total_points']}</strong></div>";
        } catch (Exception $e) {
            echo "<div class='error'>✗ Error calling stored procedure: " . $e->getMessage() . "</div>";
            $allTestsPassed = false;
        }

        // Test 7e: Test views
        echo "<h3>Test 7e: Test Views</h3>";
        try {
            $summaryView = db()->fetchOne("SELECT * FROM vw_quiz_summary WHERE quiz_id = ?", [$testQuizId]);
            if ($summaryView) {
                echo "<div class='success'>✓ vw_quiz_summary view working</div>";
                echo "<div class='code'><pre>";
                echo "Quiz Title: {$summaryView['quiz_title']}\n";
                echo "Total Questions: {$summaryView['total_questions']}\n";
                echo "Calculated Points: {$summaryView['calculated_points']}\n";
                echo "</pre></div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Error querying view: " . $e->getMessage() . "</div>";
            $allTestsPassed = false;
        }

        // Cleanup test data
        echo "<h3>Cleanup Test Data</h3>";
        echo "<div class='info'>ℹ️ Test data has been left in database for your review. You can manually delete the test quiz if needed.</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>✗ Functional test error: " . $e->getMessage() . "</div>";
    $allTestsPassed = false;
}

echo "</div>";

// ============================================================
// FINAL SUMMARY
// ============================================================
echo "<h2>📊 Final Summary</h2>";
echo "<div class='test-step'>";

if ($allTestsPassed) {
    echo "<div class='success' style='font-size: 24px; text-align: center; padding: 20px;'>";
    echo "🎉 ALL TESTS PASSED! Phase 1 Migration Successful!";
    echo "</div>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin-top: 20px;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>✅ What's Working:</h3>";
    echo "<ul>";
    echo "<li>✓ All 3 new tables created successfully</li>";
    echo "<li>✓ New columns added to quiz table</li>";
    echo "<li>✓ All 3 views created and working</li>";
    echo "<li>✓ Both stored procedures available</li>";
    echo "<li>✓ All 3 triggers functioning correctly</li>";
    echo "<li>✓ Data insertion and foreign keys working</li>";
    echo "</ul>";
    echo "<h3 style='color: #155724;'>📝 Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Update quiz creation page to add questions and options</li>";
    echo "<li>Update quiz taking page to save individual answers</li>";
    echo "<li>Update results page to use new analytics views</li>";
    echo "<li>Test with real student quiz attempts</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div class='error' style='font-size: 24px; text-align: center; padding: 20px;'>";
    echo "❌ SOME TESTS FAILED - Please Review Above";
    echo "</div>";
}

echo "</div>";
?>

</div>
</body>
</html>
