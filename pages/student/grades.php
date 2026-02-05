<?php
/**
 * Grades Page - Clean Green Theme
 * Shows student's grades for all enrolled subjects
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$pageTitle = 'My Grades';
$currentPage = 'grades';

// Get grades per subject - simplified query
$subjects = db()->fetchAll(
    "SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        s.units,
        so.subject_offered_id as subject_offering_id
    FROM student_subject ss
    JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
    ORDER BY s.subject_code",
    [$userId]
);

// Now enrich each subject with additional data
foreach ($subjects as &$subj) {
    $sid = $subj['subject_id'];
    $soid = $subj['subject_offering_id'];

    // Get instructor
    $instructor = db()->fetchOne(
        "SELECT CONCAT(u.first_name, ' ', u.last_name) as name
         FROM faculty_subject fs
         JOIN users u ON fs.user_teacher_id = u.users_id
         WHERE fs.subject_offered_id = ? AND fs.status = 'active'
         LIMIT 1",
        [$soid]
    );
    $subj['instructor_name'] = $instructor['name'] ?? null;

    // Quiz stats
    $subj['total_quizzes'] = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM quiz WHERE subject_id = ? AND status = 'published'",
        [$sid]
    )['cnt'] ?? 0;

    $subj['quizzes_taken'] = db()->fetchOne(
        "SELECT COUNT(DISTINCT qa.quiz_id) as cnt
         FROM student_quiz_attempts qa
         JOIN quiz q ON qa.quiz_id = q.quiz_id
         WHERE q.subject_id = ? AND qa.user_student_id = ?",
        [$sid, $userId]
    )['cnt'] ?? 0;

    // Quiz average (best score per quiz, then average)
    $avgResult = db()->fetchOne(
        "SELECT ROUND(AVG(best_score), 1) as avg
         FROM (
             SELECT MAX(qa.percentage) as best_score
             FROM student_quiz_attempts qa
             JOIN quiz q ON qa.quiz_id = q.quiz_id
             WHERE q.subject_id = ? AND qa.user_student_id = ?
             GROUP BY qa.quiz_id
         ) as best_scores",
        [$sid, $userId]
    );
    $subj['quiz_average'] = $avgResult['avg'] ?? null;

    // Lesson stats
    $subj['total_lessons'] = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM lessons WHERE subject_id = ? AND status = 'published'",
        [$sid]
    )['cnt'] ?? 0;

    $subj['lessons_completed'] = db()->fetchOne(
        "SELECT COUNT(*) as cnt
         FROM student_progress sp
         JOIN lessons l ON sp.lessons_id = l.lessons_id
         WHERE l.subject_id = ? AND sp.user_student_id = ? AND sp.status = 'completed'",
        [$sid, $userId]
    )['cnt'] ?? 0;
}
unset($subj);

// Calculate GWA
$totalUnits = 0;
$totalGradePoints = 0;

foreach ($subjects as &$subj) {
    $avg = $subj['quiz_average'] ?? 0;

    // Convert percentage to grade (Philippine grading system)
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

    $subj['lesson_progress'] = $subj['total_lessons'] > 0
        ? round(($subj['lessons_completed'] / $subj['total_lessons']) * 100) : 0;

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

    <div class="grades-wrap">

        <!-- Page Header -->
        <div class="page-header">
            <h1>My Grades</h1>
            <p>View your academic performance</p>
        </div>

        <!-- GWA Card -->
        <div class="gwa-card">
            <div class="gwa-info">
                <div class="gwa-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                </div>
                <div>
                    <h2>General Weighted Average</h2>
                    <p>Based on <?= $totalUnits ?> units with grades</p>
                </div>
            </div>
            <div class="gwa-value <?= $gwa <= 3.00 ? 'passed' : ($gwa > 0 ? 'failed' : '') ?>">
                <?= $gwa > 0 ? number_format($gwa, 2) : 'N/A' ?>
            </div>
        </div>

        <!-- Grade Legend -->
        <div class="grade-legend">
            <div class="legend-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M12 16v-4M12 8h.01"/>
                </svg>
                Grade Legend
            </div>
            <div class="legend-items">
                <span class="legend-item excellent">1.00-1.75 <small>Excellent</small></span>
                <span class="legend-item good">2.00-2.75 <small>Good</small></span>
                <span class="legend-item passing">3.00 <small>Passing</small></span>
                <span class="legend-item failed">5.00 <small>Failed</small></span>
            </div>
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
                        <td colspan="7" class="empty-row">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            <span>No enrolled subjects</span>
                        </td>
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
                                <span class="no-grade">--</span>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <?php if ($subj['quizzes_taken'] > 0): ?>
                                <span class="grade-value <?= $subj['grade_status'] ?>"><?= number_format($subj['grade'], 2) ?></span>
                            <?php else: ?>
                                <span class="no-grade">--</span>
                            <?php endif; ?>
                        </td>
                        <td class="center">
                            <?php if ($subj['quizzes_taken'] > 0): ?>
                                <span class="status-badge <?= $subj['grade_status'] ?>">
                                    <?php if ($subj['grade_status'] === 'passed'): ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <path d="M20 6L9 17l-5-5"/>
                                        </svg>
                                    <?php else: ?>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <line x1="18" y1="6" x2="6" y2="18"/>
                                            <line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    <?php endif; ?>
                                    <?= $subj['grade_status'] === 'passed' ? 'Passed' : 'Failed' ?>
                                </span>
                            <?php else: ?>
                                <span class="status-badge pending">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12 6 12 12 16 14"/>
                                    </svg>
                                    In Progress
                                </span>
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
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 16v-4M12 8h.01"/>
            </svg>
            <div>
                <strong>Note:</strong> Grades are calculated based on quiz performance.
                Final grades may differ based on instructor's grading criteria.
            </div>
        </div>

    </div>
</main>

<style>
/* Grades Page - Green/Cream Theme */
.grades-wrap {
    padding: 24px;
    max-width: 1100px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    margin-bottom: 24px;
}
.page-header h1 {
    font-size: 24px;
    color: #1B4D3E;
    margin: 0 0 4px;
    font-weight: 700;
}
.page-header p {
    color: #666;
    margin: 0;
    font-size: 14px;
}

/* GWA Card */
.gwa-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s;
}
.gwa-card:hover {
    border-color: #1B4D3E;
}
.gwa-info {
    display: flex;
    align-items: center;
    gap: 16px;
}
.gwa-icon {
    width: 48px;
    height: 48px;
    background: #E8F5E9;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.gwa-icon svg { color: #1B4D3E; }
.gwa-info h2 {
    font-size: 18px;
    color: #333;
    margin: 0 0 4px;
    font-weight: 600;
}
.gwa-info p {
    font-size: 14px;
    color: #666;
    margin: 0;
}
.gwa-value {
    font-size: 48px;
    font-weight: 700;
    color: #333;
}
.gwa-value.passed { color: #1B4D3E; }
.gwa-value.failed { color: #C62828; }

/* Legend */
.grade-legend {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: center;
}
.legend-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #333;
    font-size: 14px;
}
.legend-title svg { color: #1B4D3E; }
.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}
.legend-item {
    font-weight: 600;
    font-size: 13px;
    padding: 4px 12px;
    border-radius: 6px;
}
.legend-item small {
    font-weight: 400;
    margin-left: 4px;
    opacity: 0.8;
}
.legend-item.excellent { background: #E8F5E9; color: #1B4D3E; }
.legend-item.good { background: #E3F2FD; color: #1565C0; }
.legend-item.passing { background: #FFF8E1; color: #F9A825; }
.legend-item.failed { background: #FFEBEE; color: #C62828; }

/* Table Wrapper */
.grades-table-wrapper {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 16px;
}

.grades-table {
    width: 100%;
    border-collapse: collapse;
}
.grades-table th {
    background: #fafafa;
    padding: 14px 16px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #666;
    border-bottom: 1px solid #e8e8e8;
}
.grades-table td {
    padding: 16px;
    border-bottom: 1px solid #e8e8e8;
    font-size: 14px;
}
.grades-table tr:last-child td {
    border-bottom: none;
}
.grades-table tbody tr {
    transition: all 0.2s;
}
.grades-table tbody tr:hover {
    background: #fafafa;
}

.center { text-align: center; }

.subject-cell {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.subj-code {
    display: inline-block;
    background: #1B4D3E;
    color: #fff;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    width: fit-content;
}
.subj-name {
    color: #333;
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
    background: #e8e8e8;
    border-radius: 3px;
    overflow: hidden;
}
.progress-fill-mini {
    height: 100%;
    background: #1B4D3E;
    border-radius: 3px;
    transition: width 0.3s;
}
.progress-mini span {
    font-size: 12px;
    color: #666;
}

.avg-score {
    font-weight: 600;
    color: #1B4D3E;
}
.no-grade {
    color: #ccc;
}

.grade-value {
    font-weight: 700;
    font-size: 16px;
}
.grade-value.passed { color: #1B4D3E; }
.grade-value.failed { color: #C62828; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.status-badge.passed {
    background: #E8F5E9;
    color: #1B4D3E;
}
.status-badge.failed {
    background: #FFEBEE;
    color: #C62828;
}
.status-badge.pending {
    background: #FFF8E1;
    color: #F9A825;
}

.empty-row {
    text-align: center;
    color: #999;
    padding: 60px !important;
}
.empty-row svg {
    display: block;
    margin: 0 auto 12px;
    color: #ccc;
}
.empty-row span {
    display: block;
    font-size: 14px;
}

/* Note */
.grades-note {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    background: #E8F5E9;
    border: 1px solid #A5D6A7;
    border-radius: 10px;
    padding: 14px 18px;
    font-size: 13px;
    color: #1B4D3E;
    line-height: 1.5;
}
.grades-note svg {
    flex-shrink: 0;
    margin-top: 2px;
}

/* Responsive */
@media (max-width: 900px) {
    .grades-wrap { padding: 16px; }
    .gwa-card {
        flex-direction: column;
        text-align: center;
        gap: 16px;
    }
    .gwa-info {
        flex-direction: column;
    }
    .grades-table-wrapper {
        overflow-x: auto;
    }
    .grades-table {
        min-width: 700px;
    }
    .grade-legend {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
