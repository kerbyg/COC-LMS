<?php
/**
 * CIT-LMS - Cleanup Duplicate Questions Utility
 * This script identifies and removes duplicate questions within the same quiz
 * Keeps the first occurrence and removes subsequent duplicates
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Only admin/instructor can run this
if (!Auth::check() || !in_array(Auth::user()['role'], ['admin', 'instructor'])) {
    die('Unauthorized access');
}

// Set execution time for potentially large operations
set_time_limit(300);

$dryRun = isset($_GET['dry_run']) ? (bool)$_GET['dry_run'] : true;
$quizId = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : null;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Cleanup Duplicate Questions</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; padding: 40px; background: #f9fafb; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #1c1917; margin: 0 0 10px; }
        .subtitle { color: #78716c; margin-bottom: 30px; }
        .duplicate-group { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .duplicate-group.removed { background: #fee2e2; border-color: #fca5a5; }
        .question-text { font-weight: 600; color: #1c1917; margin-bottom: 8px; }
        .question-id { font-size: 13px; color: #78716c; }
        .action { color: #dc2626; font-weight: 600; margin-top: 8px; }
        .action.kept { color: #16a34a; }
        .stats { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #16a34a; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; margin-right: 10px; }
        .btn-secondary { background: #6b7280; }
        .btn-danger { background: #dc2626; }
        .controls { margin-bottom: 30px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🧹 Cleanup Duplicate Questions</h1>";
echo "<p class='subtitle'>Identifies and removes duplicate questions (same text) within the same quiz</p>";

// Get all quizzes or specific quiz
$whereClause = $quizId ? "WHERE quiz_id = $quizId" : "";
$quizzes = db()->fetchAll("SELECT DISTINCT quiz_id FROM quiz_questions $whereClause ORDER BY quiz_id");

$totalDuplicatesFound = 0;
$totalDuplicatesRemoved = 0;

foreach ($quizzes as $quiz) {
    $qid = $quiz['quiz_id'];

    // Find duplicate question texts in this quiz
    $duplicates = db()->fetchAll(
        "SELECT question_text, COUNT(*) as count, GROUP_CONCAT(question_id ORDER BY question_id) as question_ids
         FROM quiz_questions
         WHERE quiz_id = ?
         GROUP BY question_text
         HAVING count > 1",
        [$qid]
    );

    if (!empty($duplicates)) {
        echo "<h2>Quiz ID: $qid</h2>";

        foreach ($duplicates as $dup) {
            $totalDuplicatesFound++;
            $ids = explode(',', $dup['question_ids']);
            $keepId = $ids[0];
            $removeIds = array_slice($ids, 1);

            echo "<div class='duplicate-group" . (!$dryRun ? " removed" : "") . "'>";
            echo "<div class='question-text'>\"" . htmlspecialchars($dup['question_text']) . "\"</div>";
            echo "<div class='question-id'>Found {$dup['count']} copies with IDs: " . implode(', ', $ids) . "</div>";
            echo "<div class='action kept'>✓ Keeping Question ID: $keepId</div>";

            foreach ($removeIds as $removeId) {
                echo "<div class='action'>✗ " . ($dryRun ? "Would remove" : "Removed") . " Question ID: $removeId</div>";

                if (!$dryRun) {
                    // Delete the duplicate question and its options
                    db()->execute("DELETE FROM question_option WHERE question_id = ?", [(int)$removeId]);
                    db()->execute("DELETE FROM quiz_questions WHERE question_id = ?", [(int)$removeId]);
                    $totalDuplicatesRemoved++;
                }
            }
            echo "</div>";
        }

        // Re-sequence question orders for this quiz if not dry run
        if (!$dryRun) {
            $remainingQuestions = db()->fetchAll(
                "SELECT question_id FROM quiz_questions WHERE quiz_id = ? ORDER BY question_order ASC, question_id ASC",
                [$qid]
            );
            foreach ($remainingQuestions as $index => $q) {
                db()->execute(
                    "UPDATE quiz_questions SET question_order = ? WHERE question_id = ?",
                    [($index + 1), $q['question_id']]
                );
            }
            echo "<div class='stats'>✅ Re-sequenced " . count($remainingQuestions) . " remaining questions</div>";
        }
    }
}

if ($totalDuplicatesFound === 0) {
    echo "<div class='stats'>✨ No duplicate questions found! Your database is clean.</div>";
} else {
    echo "<div class='stats'>";
    echo "<strong>Summary:</strong><br>";
    echo "• Found: $totalDuplicatesFound duplicate question groups<br>";
    if (!$dryRun) {
        echo "• Removed: $totalDuplicatesRemoved duplicate questions<br>";
        echo "• Status: ✅ Cleanup completed successfully";
    } else {
        echo "• Status: ℹ️ DRY RUN MODE - No changes made to database";
    }
    echo "</div>";
}

echo "<div class='controls'>";
if ($dryRun) {
    $confirmUrl = $_SERVER['PHP_SELF'] . "?dry_run=0" . ($quizId ? "&quiz_id=$quizId" : "");
    echo "<a href='$confirmUrl' class='btn btn-danger' onclick='return confirm(\"This will permanently delete duplicate questions. Continue?\")'>⚠️ Execute Cleanup</a>";
}
echo "<a href='../pages/instructor/quizzes.php' class='btn btn-secondary'>← Back to Quizzes</a>";
echo "</div>";

echo "</div></body></html>";
