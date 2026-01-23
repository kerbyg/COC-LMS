<?php
/**
 * CIT-LMS - Learning Hub
 * Main page showing Pre-Test → Lessons → Post-Test flow for a subject
 * Students follow this path: Pre-test → (if fail) Lessons → Post-test → Next Module
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$subjectOfferingId = $_GET['id'] ?? 0;

if (!$subjectOfferingId) {
    header('Location: my-subjects.php');
    exit;
}

// Verify enrollment
$enrollment = db()->fetchOne(
    "SELECT ss.*, so.subject_id FROM student_subject ss
     JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
     WHERE ss.user_student_id = ? AND ss.subject_offered_id = ? AND ss.status = 'enrolled'",
    [$userId, $subjectOfferingId]
);

if (!$enrollment) {
    header('Location: my-subjects.php');
    exit;
}

$subjectId = $enrollment['subject_id'];

// Get subject info
$subject = db()->fetchOne(
    "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as instructor_name
     FROM subject s
     LEFT JOIN faculty_subject fs ON fs.subject_offered_id = ?
     LEFT JOIN users u ON fs.user_teacher_id = u.users_id
     WHERE s.subject_id = ?",
    [$subjectOfferingId, $subjectId]
);

// Check which quiz columns exist
$quizCols = array_column(db()->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'quiz'") ?: [], 'column_name');
$hasQuizType = in_array('quiz_type', $quizCols);
$hasLinkedQuizId = in_array('linked_quiz_id', $quizCols);

// Get Pre-Test quiz (if exists)
$preTest = null;
$preTestAttempt = null;
$preTestPassed = false;

if ($hasQuizType) {
    $preTest = db()->fetchOne(
        "SELECT * FROM quiz WHERE subject_id = ? AND quiz_type = 'pre_test' AND status = 'published' LIMIT 1",
        [$subjectId]
    );

    if ($preTest) {
        $preTestAttempt = db()->fetchOne(
            "SELECT * FROM student_quiz_attempts
             WHERE quiz_id = ? AND user_student_id = ? AND status = 'completed'
             ORDER BY percentage DESC LIMIT 1",
            [$preTest['quiz_id'], $userId]
        );
        $preTestPassed = $preTestAttempt && $preTestAttempt['percentage'] >= $preTest['passing_rate'];
    }
}

// Get Post-Test quiz (if exists)
$postTest = null;
$postTestAttempt = null;
$postTestPassed = false;

if ($hasQuizType) {
    $postTest = db()->fetchOne(
        "SELECT * FROM quiz WHERE subject_id = ? AND quiz_type = 'post_test' AND status = 'published' LIMIT 1",
        [$subjectId]
    );

    if ($postTest) {
        $postTestAttempt = db()->fetchOne(
            "SELECT * FROM student_quiz_attempts
             WHERE quiz_id = ? AND user_student_id = ? AND status = 'completed'
             ORDER BY percentage DESC LIMIT 1",
            [$postTest['quiz_id'], $userId]
        );
        $postTestPassed = $postTestAttempt && $postTestAttempt['percentage'] >= $postTest['passing_rate'];
    }
}

// Get all lessons for this subject
$lessons = db()->fetchAll(
    "SELECT l.*,
     CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END as is_completed,
     sp.completed_at
     FROM lessons l
     LEFT JOIN student_progress sp ON l.lesson_id = sp.lesson_id AND sp.user_student_id = ?
     WHERE l.subject_id = ? AND l.status = 'published'
     ORDER BY l.lesson_order",
    [$userId, $subjectId]
) ?: [];

$totalLessons = count($lessons);
$completedLessons = count(array_filter($lessons, fn($l) => $l['is_completed']));
$allLessonsCompleted = $totalLessons > 0 && $completedLessons >= $totalLessons;
$lessonsProgress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

// Determine learning flow status
// Step 1: Pre-Test (optional but recommended)
// Step 2: Lessons (required if pre-test failed or not taken)
// Step 3: Post-Test (unlocked after lessons completed OR pre-test passed)

$preTestStatus = 'available'; // available, completed_passed, completed_failed
$lessonsStatus = 'locked'; // locked, available, in_progress, completed
$postTestStatus = 'locked'; // locked, available, completed_passed, completed_failed

// Pre-Test Status
if ($preTestAttempt) {
    $preTestStatus = $preTestPassed ? 'completed_passed' : 'completed_failed';
}

// Lessons Status
if ($preTestPassed) {
    // If passed pre-test, lessons are optional (can skip)
    $lessonsStatus = $allLessonsCompleted ? 'completed' : 'optional';
} else if ($preTestAttempt) {
    // Failed pre-test - lessons required
    $lessonsStatus = $allLessonsCompleted ? 'completed' : ($completedLessons > 0 ? 'in_progress' : 'available');
} else {
    // No pre-test taken yet
    $lessonsStatus = $allLessonsCompleted ? 'completed' : ($completedLessons > 0 ? 'in_progress' : 'available');
}

// Post-Test Status
if ($postTestAttempt) {
    $postTestStatus = $postTestPassed ? 'completed_passed' : 'completed_failed';
} else if ($preTestPassed || $allLessonsCompleted) {
    // Unlocked if pre-test passed OR all lessons completed
    $postTestStatus = 'available';
} else {
    $postTestStatus = 'locked';
}

// Calculate overall progress
$overallProgress = 0;
$steps = 0;
$completedSteps = 0;

if ($preTest) {
    $steps++;
    if ($preTestAttempt) $completedSteps++;
}

if ($totalLessons > 0) {
    $steps++;
    if ($allLessonsCompleted) $completedSteps++;
}

if ($postTest) {
    $steps++;
    if ($postTestPassed) $completedSteps++;
}

$overallProgress = $steps > 0 ? round(($completedSteps / $steps) * 100) : 0;

// Get improvement data if both tests completed
$improvement = null;
if ($preTestAttempt && $postTestAttempt) {
    $improvement = [
        'pretest' => $preTestAttempt['percentage'],
        'posttest' => $postTestAttempt['percentage'],
        'change' => $postTestAttempt['percentage'] - $preTestAttempt['percentage']
    ];
}

$pageTitle = 'Learning Hub - ' . $subject['subject_code'];
$currentPage = 'my-subjects';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">
        <a href="my-subjects.php" class="back-link">← Back to My Subjects</a>

        <!-- Subject Header -->
        <div class="hub-header">
            <div class="hub-info">
                <span class="subject-badge"><?= e($subject['subject_code']) ?></span>
                <h1><?= e($subject['subject_name']) ?></h1>
                <p class="instructor">Instructor: <?= e($subject['instructor_name'] ?? 'TBA') ?></p>
            </div>
            <div class="hub-progress">
                <div class="progress-circle" data-progress="<?= $overallProgress ?>">
                    <svg viewBox="0 0 100 100">
                        <circle class="bg" cx="50" cy="50" r="45"></circle>
                        <circle class="fill" cx="50" cy="50" r="45" stroke-dasharray="<?= 2.827 * $overallProgress ?> 282.7"></circle>
                    </svg>
                    <span class="progress-text"><?= $overallProgress ?>%</span>
                </div>
                <span class="progress-label">Overall Progress</span>
            </div>
        </div>

        <!-- Improvement Banner (if both tests taken) -->
        <?php if ($improvement): ?>
        <div class="improvement-banner <?= $improvement['change'] > 0 ? 'positive' : ($improvement['change'] < 0 ? 'negative' : 'neutral') ?>">
            <div class="improvement-icon">
                <?= $improvement['change'] > 0 ? '📈' : ($improvement['change'] < 0 ? '📉' : '➡️') ?>
            </div>
            <div class="improvement-content">
                <h3>Your Learning Progress</h3>
                <div class="scores">
                    <span class="score-item">Pre-Test: <strong><?= round($improvement['pretest']) ?>%</strong></span>
                    <span class="arrow"><?= $improvement['change'] > 0 ? '→' : '→' ?></span>
                    <span class="score-item">Post-Test: <strong><?= round($improvement['posttest']) ?>%</strong></span>
                    <span class="change <?= $improvement['change'] > 0 ? 'up' : ($improvement['change'] < 0 ? 'down' : '') ?>">
                        <?= $improvement['change'] > 0 ? '+' : '' ?><?= round($improvement['change']) ?>%
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Learning Flow Steps -->
        <div class="learning-flow">
            <h2>Your Learning Path</h2>

            <div class="flow-container">
                <!-- Step 1: Pre-Test -->
                <?php if ($preTest): ?>
                <div class="flow-step <?= $preTestStatus ?>">
                    <div class="step-connector"></div>
                    <div class="step-icon">
                        <?php if ($preTestStatus == 'completed_passed'): ?>
                            <span class="icon-done">✓</span>
                        <?php elseif ($preTestStatus == 'completed_failed'): ?>
                            <span class="icon-retry">!</span>
                        <?php else: ?>
                            <span class="icon-num">1</span>
                        <?php endif; ?>
                    </div>
                    <div class="step-content">
                        <div class="step-header">
                            <h3>Pre-Test</h3>
                            <?php if ($preTestStatus == 'completed_passed'): ?>
                                <span class="status-badge passed">Passed <?= round($preTestAttempt['percentage']) ?>%</span>
                            <?php elseif ($preTestStatus == 'completed_failed'): ?>
                                <span class="status-badge failed">Score: <?= round($preTestAttempt['percentage']) ?>%</span>
                            <?php else: ?>
                                <span class="status-badge available">Ready</span>
                            <?php endif; ?>
                        </div>
                        <p class="step-desc"><?= e($preTest['quiz_title']) ?></p>
                        <p class="step-meta">
                            <?= $preTest['time_limit'] ?? 30 ?> mins •
                            Passing: <?= $preTest['passing_rate'] ?? 60 ?>% •
                            <strong>One attempt only</strong>
                        </p>

                        <?php if ($preTestStatus == 'completed_passed'): ?>
                            <div class="step-message success">
                                Excellent! You passed. You can skip lessons and go directly to Post-Test.
                            </div>
                        <?php elseif ($preTestStatus == 'completed_failed'): ?>
                            <div class="step-message warning">
                                You didn't pass. Complete all lessons below to unlock the Post-Test.
                            </div>
                        <?php else: ?>
                            <div class="step-message info">
                                This assesses your current knowledge. You only have ONE attempt.
                            </div>
                        <?php endif; ?>

                        <div class="step-actions">
                            <?php if (!$preTestAttempt): ?>
                                <a href="take-quiz.php?id=<?= $preTest['quiz_id'] ?>" class="btn-primary">Take Pre-Test →</a>
                            <?php else: ?>
                                <a href="quiz-result.php?id=<?= $preTestAttempt['attempt_id'] ?>" class="btn-secondary">View Results</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Step 2: Lessons -->
                <div class="flow-step <?= $lessonsStatus ?>">
                    <div class="step-connector"></div>
                    <div class="step-icon">
                        <?php if ($lessonsStatus == 'completed'): ?>
                            <span class="icon-done">✓</span>
                        <?php elseif ($lessonsStatus == 'locked'): ?>
                            <span class="icon-locked">🔒</span>
                        <?php else: ?>
                            <span class="icon-num"><?= $preTest ? '2' : '1' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="step-content">
                        <div class="step-header">
                            <h3>Lessons</h3>
                            <?php if ($lessonsStatus == 'completed'): ?>
                                <span class="status-badge passed">Completed</span>
                            <?php elseif ($lessonsStatus == 'optional'): ?>
                                <span class="status-badge optional">Optional (Skippable)</span>
                            <?php elseif ($lessonsStatus == 'in_progress'): ?>
                                <span class="status-badge progress"><?= $completedLessons ?>/<?= $totalLessons ?></span>
                            <?php elseif ($lessonsStatus == 'locked'): ?>
                                <span class="status-badge locked">Locked</span>
                            <?php else: ?>
                                <span class="status-badge available">Ready</span>
                            <?php endif; ?>
                        </div>
                        <p class="step-desc">Study the course materials and complete all topics</p>

                        <!-- Lessons Progress -->
                        <div class="lessons-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $lessonsProgress ?>%"></div>
                            </div>
                            <span class="progress-text"><?= $completedLessons ?> of <?= $totalLessons ?> completed</span>
                        </div>

                        <!-- Lessons List Preview -->
                        <?php if ($lessons && $lessonsStatus != 'locked'): ?>
                        <div class="lessons-list">
                            <?php foreach (array_slice($lessons, 0, 5) as $i => $lesson): ?>
                            <a href="lesson-view.php?id=<?= $lesson['lesson_id'] ?>" class="lesson-item <?= $lesson['is_completed'] ? 'completed' : '' ?>">
                                <span class="lesson-status">
                                    <?= $lesson['is_completed'] ? '✓' : ($i + 1) ?>
                                </span>
                                <span class="lesson-title"><?= e($lesson['lesson_title']) ?></span>
                                <?php if ($lesson['is_completed']): ?>
                                    <span class="lesson-done">Done</span>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                            <?php if ($totalLessons > 5): ?>
                            <div class="lessons-more">+<?= $totalLessons - 5 ?> more lessons</div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div class="step-actions">
                            <?php if ($lessonsStatus == 'locked'): ?>
                                <button class="btn-disabled" disabled>Complete Pre-Test First</button>
                            <?php elseif ($lessonsStatus == 'optional'): ?>
                                <a href="lessons.php?id=<?= $subjectOfferingId ?>" class="btn-secondary">Review Lessons (Optional)</a>
                            <?php else: ?>
                                <a href="lessons.php?id=<?= $subjectOfferingId ?>" class="btn-primary">
                                    <?= $completedLessons > 0 ? 'Continue Learning →' : 'Start Learning →' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Post-Test -->
                <?php if ($postTest): ?>
                <div class="flow-step <?= $postTestStatus ?> final">
                    <div class="step-icon">
                        <?php if ($postTestStatus == 'completed_passed'): ?>
                            <span class="icon-done">✓</span>
                        <?php elseif ($postTestStatus == 'completed_failed'): ?>
                            <span class="icon-retry">!</span>
                        <?php elseif ($postTestStatus == 'locked'): ?>
                            <span class="icon-locked">🔒</span>
                        <?php else: ?>
                            <span class="icon-num"><?= $preTest ? '3' : '2' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="step-content">
                        <div class="step-header">
                            <h3>Post-Test</h3>
                            <?php if ($postTestStatus == 'completed_passed'): ?>
                                <span class="status-badge passed">Passed <?= round($postTestAttempt['percentage']) ?>%</span>
                            <?php elseif ($postTestStatus == 'completed_failed'): ?>
                                <span class="status-badge failed">Score: <?= round($postTestAttempt['percentage']) ?>%</span>
                            <?php elseif ($postTestStatus == 'locked'): ?>
                                <span class="status-badge locked">Locked</span>
                            <?php else: ?>
                                <span class="status-badge available">Unlocked</span>
                            <?php endif; ?>
                        </div>
                        <p class="step-desc"><?= e($postTest['quiz_title']) ?></p>
                        <p class="step-meta">
                            <?= $postTest['total_points'] ?? 0 ?> points •
                            <?= $postTest['time_limit'] ?? 30 ?> mins •
                            Passing: <?= $postTest['passing_rate'] ?? 60 ?>%
                        </p>

                        <?php if ($postTestStatus == 'locked'): ?>
                            <div class="step-message info">
                                <?php if (!$preTestAttempt): ?>
                                    Take the Pre-Test first, or complete all lessons to unlock.
                                <?php else: ?>
                                    Complete all <?= $totalLessons ?> lessons to unlock the Post-Test.
                                <?php endif; ?>
                            </div>
                        <?php elseif ($postTestStatus == 'completed_passed'): ?>
                            <div class="step-message success">
                                Congratulations! You have completed this learning module!
                            </div>
                        <?php elseif ($postTestStatus == 'completed_failed'): ?>
                            <div class="step-message warning">
                                Review the lessons and try again to pass the Post-Test.
                            </div>
                        <?php endif; ?>

                        <div class="step-actions">
                            <?php if ($postTestStatus == 'locked'): ?>
                                <button class="btn-disabled" disabled>Locked</button>
                            <?php elseif ($postTestStatus == 'completed_passed'): ?>
                                <a href="quiz-result.php?id=<?= $postTestAttempt['attempt_id'] ?>" class="btn-secondary">View Results</a>
                            <?php elseif ($postTestStatus == 'completed_failed'): ?>
                                <a href="lessons.php?id=<?= $subjectOfferingId ?>" class="btn-secondary">Review Lessons</a>
                                <a href="take-quiz.php?id=<?= $postTest['quiz_id'] ?>" class="btn-primary">Retake Post-Test →</a>
                            <?php else: ?>
                                <a href="take-quiz.php?id=<?= $postTest['quiz_id'] ?>" class="btn-primary">Take Post-Test →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="quick-links">
            <a href="quizzes.php?id=<?= $subjectOfferingId ?>" class="quick-link">
                <span class="ql-icon">📝</span>
                <span class="ql-text">All Quizzes</span>
            </a>
            <a href="lessons.php?id=<?= $subjectOfferingId ?>" class="quick-link">
                <span class="ql-icon">📖</span>
                <span class="ql-text">All Lessons</span>
            </a>
            <a href="announcements.php?id=<?= $subjectOfferingId ?>" class="quick-link">
                <span class="ql-icon">📢</span>
                <span class="ql-text">Announcements</span>
            </a>
            <?php if ($improvement): ?>
            <a href="progress-comparison.php?id=<?= $subjectOfferingId ?>" class="quick-link">
                <span class="ql-icon">📊</span>
                <span class="ql-text">Progress Report</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
/* Learning Hub Styles */
.back-link { color: #16a34a; text-decoration: none; font-size: 14px; display: block; margin-bottom: 20px; }
.back-link:hover { text-decoration: underline; }

/* Header */
.hub-header {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #bbf7d0;
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
}
.hub-info { flex: 1; }
.subject-badge {
    display: inline-block;
    background: #16a34a;
    color: #fff;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 12px;
}
.hub-info h1 { font-size: 24px; margin: 0 0 8px; color: #1a1a1a; }
.instructor { color: #666; font-size: 14px; margin: 0; }

.hub-progress { text-align: center; }
.progress-circle {
    width: 100px;
    height: 100px;
    position: relative;
    margin-bottom: 8px;
}
.progress-circle svg {
    transform: rotate(-90deg);
    width: 100%;
    height: 100%;
}
.progress-circle circle {
    fill: none;
    stroke-width: 8;
}
.progress-circle .bg { stroke: #e5e5e5; }
.progress-circle .fill {
    stroke: #16a34a;
    stroke-linecap: round;
    transition: stroke-dasharray 0.6s ease;
}
.progress-circle .progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 20px;
    font-weight: 700;
    color: #16a34a;
}
.progress-label { font-size: 12px; color: #666; }

/* Improvement Banner */
.improvement-banner {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    border-radius: 12px;
    margin-bottom: 24px;
}
.improvement-banner.positive { background: #dcfce7; border: 1px solid #bbf7d0; }
.improvement-banner.negative { background: #fee2e2; border: 1px solid #fecaca; }
.improvement-banner.neutral { background: #f5f5f5; border: 1px solid #e5e5e5; }
.improvement-icon { font-size: 36px; }
.improvement-content h3 { font-size: 14px; margin: 0 0 8px; color: #333; }
.scores { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.score-item { font-size: 14px; color: #666; }
.score-item strong { color: #1a1a1a; }
.scores .arrow { color: #999; }
.scores .change {
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 14px;
}
.change.up { background: #16a34a; color: #fff; }
.change.down { background: #dc2626; color: #fff; }

/* Learning Flow */
.learning-flow { margin-bottom: 32px; }
.learning-flow h2 { font-size: 18px; margin: 0 0 20px; color: #1a1a1a; }

.flow-container {
    display: flex;
    flex-direction: column;
    gap: 0;
    position: relative;
}

.flow-step {
    display: flex;
    gap: 20px;
    position: relative;
    padding-bottom: 24px;
}
.flow-step.final { padding-bottom: 0; }

.step-connector {
    position: absolute;
    left: 27px;
    top: 56px;
    bottom: 0;
    width: 2px;
    background: #e5e5e5;
}
.flow-step.final .step-connector { display: none; }
.flow-step.completed .step-connector,
.flow-step.completed_passed .step-connector { background: #16a34a; }

.step-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 20px;
    font-weight: 700;
    background: #f5f5f5;
    color: #666;
    border: 3px solid #e5e5e5;
    z-index: 1;
}
.flow-step.available .step-icon,
.flow-step.in_progress .step-icon,
.flow-step.optional .step-icon {
    background: #fff;
    border-color: #16a34a;
    color: #16a34a;
}
.flow-step.completed .step-icon,
.flow-step.completed_passed .step-icon {
    background: #16a34a;
    border-color: #16a34a;
    color: #fff;
}
.flow-step.completed_failed .step-icon {
    background: #f59e0b;
    border-color: #f59e0b;
    color: #fff;
}
.flow-step.locked .step-icon {
    background: #f5f5f5;
    border-color: #e5e5e5;
    color: #999;
}
.icon-done, .icon-retry, .icon-num, .icon-locked { line-height: 1; }

.step-content {
    flex: 1;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 12px;
    padding: 20px;
}
.flow-step.completed .step-content,
.flow-step.completed_passed .step-content {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.flow-step.locked .step-content {
    background: #fafafa;
    opacity: 0.7;
}

.step-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    flex-wrap: wrap;
    gap: 8px;
}
.step-header h3 { font-size: 16px; margin: 0; color: #1a1a1a; }

.status-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}
.status-badge.passed { background: #dcfce7; color: #16a34a; }
.status-badge.failed { background: #fee2e2; color: #dc2626; }
.status-badge.available { background: #dbeafe; color: #2563eb; }
.status-badge.progress { background: #fef3c7; color: #d97706; }
.status-badge.locked { background: #f5f5f5; color: #999; }
.status-badge.optional { background: #e0e7ff; color: #4f46e5; }

.step-desc { color: #555; font-size: 14px; margin: 0 0 4px; }
.step-meta { color: #999; font-size: 12px; margin: 0 0 12px; }

.step-message {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 12px;
}
.step-message.success { background: #dcfce7; color: #166534; }
.step-message.warning { background: #fef3c7; color: #92400e; }
.step-message.info { background: #e0e7ff; color: #3730a3; }

/* Lessons Progress */
.lessons-progress { margin: 12px 0; }
.progress-bar {
    height: 8px;
    background: #e5e5e5;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 6px;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #16a34a, #22c55e);
    border-radius: 4px;
    transition: width 0.4s ease;
}
.lessons-progress .progress-text {
    font-size: 12px;
    color: #666;
}

/* Lessons List */
.lessons-list {
    margin: 12px 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.lesson-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: #fafafa;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    font-size: 13px;
    transition: background 0.2s;
}
.lesson-item:hover { background: #f0f0f0; }
.lesson-item.completed { background: #f0fdf4; }
.lesson-status {
    width: 24px;
    height: 24px;
    background: #e5e5e5;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    color: #666;
    flex-shrink: 0;
}
.lesson-item.completed .lesson-status {
    background: #16a34a;
    color: #fff;
}
.lesson-title { flex: 1; }
.lesson-done {
    font-size: 11px;
    color: #16a34a;
    font-weight: 600;
}
.lessons-more {
    text-align: center;
    font-size: 12px;
    color: #666;
    padding: 8px;
}

/* Step Actions */
.step-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.btn-primary, .btn-secondary, .btn-disabled {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-primary {
    background: #16a34a;
    color: #fff;
}
.btn-primary:hover { background: #15803d; }
.btn-secondary {
    background: #fff;
    color: #16a34a;
    border: 1px solid #16a34a;
}
.btn-secondary:hover { background: #f0fdf4; }
.btn-disabled {
    background: #e5e5e5;
    color: #999;
    cursor: not-allowed;
}

/* Quick Links */
.quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
}
.quick-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 10px;
    text-decoration: none;
    color: #333;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}
.quick-link:hover {
    border-color: #16a34a;
    background: #f0fdf4;
}
.ql-icon { font-size: 20px; }

/* Responsive */
@media (max-width: 768px) {
    .hub-header { flex-direction: column; text-align: center; }
    .flow-step { flex-direction: column; }
    .step-connector { display: none; }
    .step-icon { margin: 0 auto; }
    .step-content { text-align: center; }
    .step-header { justify-content: center; }
    .step-actions { justify-content: center; }
    .lessons-list { text-align: left; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
