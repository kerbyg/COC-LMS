<?php
/**
 * CIT-LMS - Grades Page
 * Shows student's grades for all enrolled subjects
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$pageTitle = 'My Grades';
$currentPage = 'grades';

// Get grades per subject
$subjects = db()->fetchAll(
    "SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        s.units,
        so.subject_offered_id as subject_offering_id,
        CONCAT(u.first_name, ' ', u.last_name) as instructor_name,

        -- Quiz stats
        (SELECT COUNT(*) FROM quiz q WHERE q.subject_id = s.subject_id AND q.status = 'published') as total_quizzes,
        (SELECT COUNT(DISTINCT qa.quiz_id) FROM student_quiz_attempts qa
         JOIN quiz q ON qa.quiz_id = q.quiz_id
         WHERE q.subject_id = s.subject_id AND qa.user_student_id = ?) as quizzes_taken,
        (SELECT ROUND(AVG(best.score), 1) FROM (
            SELECT MAX(qa.percentage) as score
            FROM student_quiz_attempts qa
            JOIN quiz q ON qa.quiz_id = q.quiz_id
            WHERE q.subject_id = s.subject_id AND qa.user_student_id = ?
            GROUP BY qa.quiz_id
        ) as best) as quiz_average,

        -- Lesson stats
        (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.subject_id AND l.status = 'published') as total_lessons,
        (SELECT COUNT(*) FROM student_progress sp
         JOIN lessons l ON sp.lesson_id = l.lesson_id
         WHERE l.subject_id = s.subject_id AND sp.user_student_id = ? AND sp.status = 'completed') as lessons_completed

    FROM student_subject ss
    JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    LEFT JOIN users u ON so.user_teacher_id = u.users_id
    WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
    ORDER BY s.subject_code",
    [$userId, $userId, $userId, $userId]
);

// Calculate GWA
$totalUnits = 0;
$totalGradePoints = 0;

foreach ($subjects as &$subj) {
    // Calculate grade (simplified: based on quiz average)
    $avg = $subj['quiz_average'] ?? 0;
    
    // Convert percentage to grade (Philippine grading system example)
    if ($avg >= 97) $grade = 1.00;
    elseif ($avg >= 94) $grade = 1.25;
    elseif ($avg >= 91) $grade = 1.50;
    elseif ($avg >= 88) $grade = 1.75;
    elseif ($avg >= 85) $grade = 2.00;
    elseif ($avg >= 82) $grade = 2.25;
    elseif ($avg >= 79) $grade = 2.50;
    elseif ($avg >= 76) $grade = 2.75;
    elseif ($avg >= 75) $grade = 3.00;
    else $grade = 5.00;
    
    $subj['grade'] = $grade;
    $subj['grade_status'] = $grade <= 3.00 ? 'passed' : 'failed';
    
    // Lesson completion percentage
    $subj['lesson_progress'] = $subj['total_lessons'] > 0 
        ? round(($subj['lessons_completed'] / $subj['total_lessons']) * 100) : 0;
    
    // Only count if has quiz grade
    if ($subj['quizzes_taken'] > 0) {
        $totalUnits += $subj['units'];
        $totalGradePoints += ($grade * $subj['units']);
    }
}

$gwa = $totalUnits > 0 ? round($totalGradePoints / $totalUnits, 2) : 0;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <!-- Page Header -->
        <div class="page-head">
            <div>
                <h1>📊 My Grades</h1>
                <p>View your academic performance</p>
            </div>
        </div>
        
        <!-- GWA Card -->
        <div class="gwa-card">
            <div class="gwa-info">
                <h2>General Weighted Average</h2>
                <p>Based on <?= $totalUnits ?> units with grades</p>
            </div>
            <div class="gwa-value <?= $gwa <= 3.00 ? 'passed' : ($gwa > 0 ? 'failed' : '') ?>">
                <?= $gwa > 0 ? number_format($gwa, 2) : 'N/A' ?>
            </div>
        </div>
        
        <!-- Grade Legend -->
        <div class="grade-legend">
            <span class="legend-title">Grade Legend:</span>
            <span class="legend-item">1.00-1.75 <small>Excellent</small></span>
            <span class="legend-item">2.00-2.75 <small>Good</small></span>
            <span class="legend-item">3.00 <small>Passing</small></span>
            <span class="legend-item failed">5.00 <small>Failed</small></span>
        </div>
        
        <!-- Grades Table -->
        <div class="grades-table-wrapper">
            <table class="grades-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Units</th>
                        <th>Lessons</th>
                        <th>Quizzes</th>
                        <th>Average</th>
                        <th>Grade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="7" class="empty-row">No enrolled subjects</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($subjects as $subj): ?>
                    <tr>
                        <td class="subject-cell">
                            <span class="subj-code"><?= e($subj['subject_code']) ?></span>
                            <span class="subj-name"><?= e($subj['subject_name']) ?></span>
                        </td>
                        <td class="center"><?= $subj['units'] ?></td>
                        <td class="center">
                            <div class="progress-mini">
                                <div class="progress-bar-mini">
                                    <div class="progress-fill-mini" style="width: <?= $subj['lesson_progress'] ?>%"></div>
                                </div>
                                <span><?= $subj['lessons_completed'] ?>/<?= $subj['total_lessons'] ?></span>
                            </div>
                        </td>
                        <td class="center"><?= $subj['quizzes_taken'] ?>/<?= $subj['total_quizzes'] ?></td>
                        <td class="center">
                            <?php if ($subj['quiz_average']): ?>
                                <span class="avg-score"><?= $subj['quiz_average'] ?>%</span>
                            <?php else: ?>
                                <span class="no-grade">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <?php if ($subj['quizzes_taken'] > 0): ?>
                                <span class="grade-value <?= $subj['grade_status'] ?>"><?= number_format($subj['grade'], 2) ?></span>
                            <?php else: ?>
                                <span class="no-grade">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <?php if ($subj['quizzes_taken'] > 0): ?>
                                <span class="status-badge <?= $subj['grade_status'] ?>">
                                    <?= $subj['grade_status'] === 'passed' ? 'Passed' : 'Failed' ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge pending">In Progress</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Note -->
        <div class="grades-note">
            <strong>Note:</strong> Grades are calculated based on quiz performance. 
            Final grades may differ based on instructor's grading criteria.
        </div>
        
    </div>
</main>

<style>
/* Grades Page Styles */

.page-head {
    margin-bottom: 24px;
}
.page-head h1 {
    font-size: 22px;
    color: #1c1917;
    margin: 0 0 4px;
}
.page-head p {
    color: #78716c;
    margin: 0;
    font-size: 14px;
}

/* GWA Card */
.gwa-card {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.gwa-info h2 {
    font-size: 18px;
    color: #1c1917;
    margin: 0 0 4px;
}
.gwa-info p {
    font-size: 14px;
    color: #78716c;
    margin: 0;
}
.gwa-value {
    font-size: 48px;
    font-weight: 700;
    color: #1c1917;
}
.gwa-value.passed { color: #16a34a; }
.gwa-value.failed { color: #dc2626; }

/* Legend */
.grade-legend {
    background: #fdfbf7;
    border: 1px solid #f5f0e8;
    border-radius: 10px;
    padding: 12px 20px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    font-size: 13px;
}
.legend-title {
    font-weight: 600;
    color: #57534e;
}
.legend-item {
    color: #16a34a;
    font-weight: 600;
}
.legend-item small {
    color: #78716c;
    font-weight: 400;
    margin-left: 4px;
}
.legend-item.failed {
    color: #dc2626;
}

/* Table Wrapper */
.grades-table-wrapper {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 16px;
}

.grades-table {
    width: 100%;
    border-collapse: collapse;
}
.grades-table th {
    background: #fdfbf7;
    padding: 14px 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #57534e;
    border-bottom: 1px solid #f5f0e8;
}
.grades-table td {
    padding: 16px;
    border-bottom: 1px solid #f5f0e8;
    font-size: 14px;
}
.grades-table tr:last-child td {
    border-bottom: none;
}
.grades-table tr:hover {
    background: #fdfbf7;
}

.center { text-align: center; }

.subject-cell {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.subj-code {
    display: inline-block;
    background: #16a34a;
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    width: fit-content;
}
.subj-name {
    color: #1c1917;
    font-weight: 500;
}

/* Mini Progress Bar */
.progress-mini {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
}
.progress-bar-mini {
    width: 60px;
    height: 6px;
    background: #e7e5e4;
    border-radius: 3px;
    overflow: hidden;
}
.progress-fill-mini {
    height: 100%;
    background: #16a34a;
    border-radius: 3px;
}
.progress-mini span {
    font-size: 12px;
    color: #78716c;
}

.avg-score {
    font-weight: 600;
    color: #16a34a;
}
.no-grade {
    color: #a8a29e;
}

.grade-value {
    font-weight: 700;
    font-size: 16px;
}
.grade-value.passed { color: #16a34a; }
.grade-value.failed { color: #dc2626; }

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.status-badge.passed {
    background: #dcfce7;
    color: #16a34a;
}
.status-badge.failed {
    background: #fee2e2;
    color: #dc2626;
}
.status-badge.pending {
    background: #fef3c7;
    color: #b45309;
}

.empty-row {
    text-align: center;
    color: #78716c;
    padding: 40px !important;
}

/* Note */
.grades-note {
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 8px;
    padding: 12px 16px;
    font-size: 13px;
    color: #92400e;
}

/* Responsive */
@media (max-width: 768px) {
    .gwa-card {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }
    .grades-table-wrapper {
        overflow-x: auto;
    }
    .grades-table {
        min-width: 700px;
    }
    .grade-legend {
        justify-content: center;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>