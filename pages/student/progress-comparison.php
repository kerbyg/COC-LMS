<?php
/**
 * CIT-LMS - Progress Comparison Page
 * Shows pre-test vs post-test comparison for students
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$subjectOfferedId = $_GET['id'] ?? 0;

// Get subject info
$subject = db()->fetchOne(
    "SELECT so.*, s.subject_code, s.subject_name
     FROM subject_offered so
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE so.subject_offered_id = ?",
    [$subjectOfferedId]
);

if (!$subject) {
    header('Location: my-subjects.php');
    exit;
}

// Verify enrollment
$enrollment = db()->fetchOne(
    "SELECT * FROM student_subject
     WHERE user_student_id = ? AND subject_offered_id = ? AND status = 'enrolled'",
    [$userId, $subjectOfferedId]
);

if (!$enrollment) {
    header('Location: my-subjects.php');
    exit;
}

// Get pre-test and post-test comparison
$comparisons = db()->fetchAll(
    "SELECT
        pre_quiz.quiz_id as pretest_id,
        pre_quiz.quiz_title as pretest_title,
        post_quiz.quiz_id as posttest_id,
        post_quiz.quiz_title as posttest_title,
        pre_attempt.percentage as pretest_score,
        pre_attempt.passed as pretest_passed,
        pre_attempt.completed_at as pretest_date,
        post_attempt.percentage as posttest_score,
        post_attempt.passed as posttest_passed,
        post_attempt.completed_at as posttest_date,
        (post_attempt.percentage - pre_attempt.percentage) as improvement
    FROM quiz pre_quiz
    LEFT JOIN quiz post_quiz ON pre_quiz.linked_quiz_id = post_quiz.quiz_id
    LEFT JOIN student_quiz_attempts pre_attempt ON pre_quiz.quiz_id = pre_attempt.quiz_id
        AND pre_attempt.user_student_id = ? AND pre_attempt.attempt_number = 1
    LEFT JOIN student_quiz_attempts post_attempt ON post_quiz.quiz_id = post_attempt.quiz_id
        AND post_attempt.user_student_id = ? AND post_attempt.attempt_number = 1
    WHERE pre_quiz.subject_id = ?
    AND pre_quiz.quiz_type = 'pre_test'
    AND pre_quiz.status = 'published'
    ORDER BY pre_quiz.created_at",
    [$userId, $userId, $subject['subject_id']]
);

// Get lesson progress
$lessonProgress = db()->fetchAll(
    "SELECT
        l.lessons_id,
        l.lesson_title,
        l.lesson_order,
        CASE WHEN sp.status = 'completed' THEN 100 ELSE 0 END as completion_percentage,
        CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END as is_completed,
        sp.completed_at
    FROM lessons l
    LEFT JOIN student_progress sp ON l.lessons_id = sp.lessons_id AND sp.user_student_id = ?
    WHERE l.subject_id = ? AND l.status = 'published'
    ORDER BY l.lesson_order",
    [$userId, $subject['subject_id']]
);

$totalLessons = count($lessonProgress);
$completedLessons = count(array_filter($lessonProgress, fn($l) => $l['is_completed']));
$overallProgress = $totalLessons > 0 ? ($completedLessons / $totalLessons * 100) : 0;

$pageTitle = 'Learning Progress - ' . $subject['subject_code'];
$currentPage = 'subjects';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/student_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <!-- Back Link -->
        <div class="page-top">
            <a href="subject-details.php?id=<?= $subjectOfferedId ?>" class="back-link">
                ← Back to <?= e($subject['subject_code']) ?>
            </a>
        </div>

        <!-- Header -->
        <div class="progress-header">
            <div class="header-info">
                <span class="subj-code"><?= e($subject['subject_code']) ?></span>
                <h1>Learning Progress</h1>
                <p><?= e($subject['subject_name']) ?></p>
            </div>
            <div class="overall-progress">
                <div class="progress-circle" data-progress="<?= $overallProgress ?>">
                    <svg viewBox="0 0 100 100">
                        <circle class="bg" cx="50" cy="50" r="45"/>
                        <circle class="fill" cx="50" cy="50" r="45"
                            stroke-dasharray="<?= 283 * ($overallProgress / 100) ?> 283"/>
                    </svg>
                    <span class="percent"><?= number_format($overallProgress, 0) ?>%</span>
                </div>
                <span class="label">Overall Progress</span>
            </div>
        </div>

        <!-- Pre-Test vs Post-Test Comparison -->
        <?php if (!empty($comparisons)): ?>
        <div class="section">
            <h2>Pre-Test vs Post-Test Comparison</h2>
            <p class="section-desc">Track your learning improvement by comparing your pre-test and post-test scores.</p>

            <div class="comparison-cards">
                <?php foreach ($comparisons as $comp): ?>
                <div class="comparison-card">
                    <div class="comparison-header">
                        <h3><?= e($comp['pretest_title']) ?></h3>
                    </div>

                    <div class="comparison-body">
                        <div class="test-result pretest">
                            <span class="test-label">Pre-Test</span>
                            <?php if ($comp['pretest_score'] !== null): ?>
                                <span class="test-score <?= $comp['pretest_passed'] ? 'passed' : 'failed' ?>">
                                    <?= number_format($comp['pretest_score'], 1) ?>%
                                </span>
                                <span class="test-date"><?= date('M j, Y', strtotime($comp['pretest_date'])) ?></span>
                            <?php else: ?>
                                <span class="test-score pending">Not Taken</span>
                                <a href="take-quiz.php?id=<?= $comp['pretest_id'] ?>" class="take-btn">Take Now</a>
                            <?php endif; ?>
                        </div>

                        <div class="comparison-arrow">
                            <?php if ($comp['improvement'] !== null): ?>
                                <?php if ($comp['improvement'] > 0): ?>
                                    <span class="arrow up">↑</span>
                                    <span class="improvement positive">+<?= number_format($comp['improvement'], 1) ?>%</span>
                                <?php elseif ($comp['improvement'] < 0): ?>
                                    <span class="arrow down">↓</span>
                                    <span class="improvement negative"><?= number_format($comp['improvement'], 1) ?>%</span>
                                <?php else: ?>
                                    <span class="arrow">→</span>
                                    <span class="improvement">No Change</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="arrow pending">...</span>
                            <?php endif; ?>
                        </div>

                        <div class="test-result posttest">
                            <span class="test-label">Post-Test</span>
                            <?php if ($comp['posttest_score'] !== null): ?>
                                <span class="test-score <?= $comp['posttest_passed'] ? 'passed' : 'failed' ?>">
                                    <?= number_format($comp['posttest_score'], 1) ?>%
                                </span>
                                <span class="test-date"><?= date('M j, Y', strtotime($comp['posttest_date'])) ?></span>
                            <?php elseif ($comp['pretest_score'] !== null && $comp['posttest_id']): ?>
                                <span class="test-score pending">Pending</span>
                                <a href="take-quiz.php?id=<?= $comp['posttest_id'] ?>" class="take-btn">Take Post-Test</a>
                            <?php else: ?>
                                <span class="test-score pending">Pending</span>
                                <span class="test-hint">Complete pre-test first</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($comp['improvement'] !== null && $comp['improvement'] > 0): ?>
                    <div class="comparison-footer success">
                        Great job! You improved by <?= number_format($comp['improvement'], 1) ?>%
                    </div>
                    <?php elseif ($comp['pretest_score'] !== null && $comp['posttest_score'] === null): ?>
                    <div class="comparison-footer info">
                        <?php if (!$comp['pretest_passed']): ?>
                            Complete the lessons below to unlock the post-test
                        <?php else: ?>
                            You passed the pre-test! You can proceed to the post-test
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lesson Progress -->
        <div class="section">
            <h2>Lesson Progress</h2>
            <p class="section-desc">Complete all lessons to unlock the post-test.</p>

            <div class="lessons-progress">
                <div class="progress-summary">
                    <div class="summary-stat">
                        <span class="stat-value"><?= $completedLessons ?></span>
                        <span class="stat-label">Completed</span>
                    </div>
                    <div class="summary-divider">/</div>
                    <div class="summary-stat">
                        <span class="stat-value"><?= $totalLessons ?></span>
                        <span class="stat-label">Total Lessons</span>
                    </div>
                </div>

                <div class="lessons-list">
                    <?php foreach ($lessonProgress as $lesson): ?>
                    <div class="lesson-item <?= $lesson['is_completed'] ? 'completed' : '' ?>">
                        <div class="lesson-status">
                            <?php if ($lesson['is_completed']): ?>
                                <span class="status-icon done">✓</span>
                            <?php else: ?>
                                <span class="status-icon"><?= $lesson['lesson_order'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="lesson-info">
                            <h4><?= e($lesson['lesson_title']) ?></h4>
                            <?php if ($lesson['is_completed']): ?>
                                <span class="lesson-date">Completed <?= date('M j, Y', strtotime($lesson['completed_at'])) ?></span>
                            <?php else: ?>
                                <span class="lesson-pending">Not completed</span>
                            <?php endif; ?>
                        </div>
                        <a href="lesson-view.php?id=<?= $lesson['lessons_id'] ?>" class="lesson-link">
                            <?= $lesson['is_completed'] ? 'Review' : 'Start' ?> →
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>
</main>

<style>
/* Progress Page Styles */

.page-top { margin-bottom: 16px; }
.back-link {
    color: #16a34a;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}
.back-link:hover { text-decoration: underline; }

/* Header */
.progress-header {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border-radius: 16px;
    padding: 32px;
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}
.subj-code {
    background: rgba(255,255,255,0.2);
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 8px;
}
.progress-header h1 {
    font-size: 28px;
    margin: 0 0 8px;
}
.progress-header p {
    margin: 0;
    opacity: 0.9;
}

/* Progress Circle */
.overall-progress {
    text-align: center;
}
.progress-circle {
    width: 100px;
    height: 100px;
    position: relative;
}
.progress-circle svg {
    transform: rotate(-90deg);
}
.progress-circle circle {
    fill: none;
    stroke-width: 8;
}
.progress-circle .bg {
    stroke: rgba(255,255,255,0.2);
}
.progress-circle .fill {
    stroke: #fff;
    stroke-linecap: round;
    transition: 0.5s;
}
.progress-circle .percent {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 700;
}
.overall-progress .label {
    display: block;
    margin-top: 8px;
    font-size: 13px;
    opacity: 0.9;
}

/* Sections */
.section {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
}
.section h2 {
    font-size: 18px;
    margin: 0 0 8px;
    color: #1c1917;
}
.section-desc {
    color: #78716c;
    font-size: 14px;
    margin: 0 0 20px;
}

/* Comparison Cards */
.comparison-cards {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.comparison-card {
    border: 1px solid #f5f0e8;
    border-radius: 12px;
    overflow: hidden;
}
.comparison-header {
    background: #fdfbf7;
    padding: 16px;
    border-bottom: 1px solid #f5f0e8;
}
.comparison-header h3 {
    font-size: 15px;
    margin: 0;
    color: #1c1917;
}
.comparison-body {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 20px;
    padding: 24px;
    align-items: center;
}
.test-result {
    text-align: center;
}
.test-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #78716c;
    text-transform: uppercase;
    margin-bottom: 8px;
}
.test-score {
    display: block;
    font-size: 32px;
    font-weight: 700;
}
.test-score.passed { color: #16a34a; }
.test-score.failed { color: #dc2626; }
.test-score.pending { color: #a8a29e; font-size: 16px; }
.test-date {
    display: block;
    font-size: 12px;
    color: #78716c;
    margin-top: 4px;
}
.test-hint {
    display: block;
    font-size: 12px;
    color: #a8a29e;
    margin-top: 4px;
}
.take-btn {
    display: inline-block;
    background: #16a34a;
    color: #fff;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    margin-top: 8px;
}
.take-btn:hover { background: #15803d; }

/* Comparison Arrow */
.comparison-arrow {
    text-align: center;
}
.comparison-arrow .arrow {
    display: block;
    font-size: 24px;
    margin-bottom: 4px;
}
.comparison-arrow .arrow.up { color: #16a34a; }
.comparison-arrow .arrow.down { color: #dc2626; }
.comparison-arrow .arrow.pending { color: #a8a29e; }
.improvement {
    font-size: 14px;
    font-weight: 600;
}
.improvement.positive { color: #16a34a; }
.improvement.negative { color: #dc2626; }

/* Comparison Footer */
.comparison-footer {
    padding: 12px 16px;
    font-size: 13px;
    text-align: center;
}
.comparison-footer.success {
    background: #dcfce7;
    color: #16a34a;
}
.comparison-footer.info {
    background: #fef3c7;
    color: #92400e;
}

/* Lessons Progress */
.progress-summary {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    padding: 20px;
    background: #fdfbf7;
    border-radius: 12px;
    margin-bottom: 20px;
}
.summary-stat {
    text-align: center;
}
.stat-value {
    display: block;
    font-size: 36px;
    font-weight: 700;
    color: #16a34a;
}
.stat-label {
    font-size: 12px;
    color: #78716c;
}
.summary-divider {
    font-size: 24px;
    color: #d6d3d1;
}

/* Lessons List */
.lessons-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.lesson-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 10px;
    transition: 0.2s;
}
.lesson-item:hover {
    border-color: #16a34a;
}
.lesson-item.completed {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.lesson-status {
    flex-shrink: 0;
}
.status-icon {
    width: 32px;
    height: 32px;
    background: #f5f0e8;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    color: #78716c;
}
.status-icon.done {
    background: #16a34a;
    color: #fff;
}
.lesson-info {
    flex: 1;
}
.lesson-info h4 {
    font-size: 14px;
    margin: 0 0 4px;
    color: #1c1917;
}
.lesson-date {
    font-size: 12px;
    color: #16a34a;
}
.lesson-pending {
    font-size: 12px;
    color: #a8a29e;
}
.lesson-link {
    color: #16a34a;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}
.lesson-link:hover {
    text-decoration: underline;
}

/* Responsive */
@media (max-width: 768px) {
    .progress-header {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    .comparison-body {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    .comparison-arrow {
        transform: rotate(90deg);
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
