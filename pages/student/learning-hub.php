<?php
/**
 * Learning Hub - Clean Green Theme
 * Main page showing Pre-Test → Lessons → Post-Test flow for a subject
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
     LEFT JOIN student_progress sp ON l.lessons_id = sp.lessons_id AND sp.user_student_id = ?
     WHERE l.subject_id = ? AND l.status = 'published'
     ORDER BY l.lesson_order",
    [$userId, $subjectId]
) ?: [];

$totalLessons = count($lessons);
$completedLessons = count(array_filter($lessons, fn($l) => $l['is_completed']));
$allLessonsCompleted = $totalLessons > 0 && $completedLessons >= $totalLessons;
$lessonsProgress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

// Determine learning flow status
$preTestStatus = 'available';
$lessonsStatus = 'locked';
$postTestStatus = 'locked';

if ($preTestAttempt) {
    $preTestStatus = $preTestPassed ? 'completed_passed' : 'completed_failed';
}

if ($preTestPassed) {
    $lessonsStatus = $allLessonsCompleted ? 'completed' : 'optional';
} else if ($preTestAttempt) {
    $lessonsStatus = $allLessonsCompleted ? 'completed' : ($completedLessons > 0 ? 'in_progress' : 'available');
} else {
    $lessonsStatus = $allLessonsCompleted ? 'completed' : ($completedLessons > 0 ? 'in_progress' : 'available');
}

if ($postTestAttempt) {
    $postTestStatus = $postTestPassed ? 'completed_passed' : 'completed_failed';
} else if ($preTestPassed || $allLessonsCompleted) {
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

    <div class="hub-wrap">
        <a href="my-subjects.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to My Subjects
        </a>

        <!-- Subject Header -->
        <div class="hub-header">
            <div class="hub-info">
                <span class="subject-badge"><?= e($subject['subject_code']) ?></span>
                <h1><?= e($subject['subject_name']) ?></h1>
                <div class="instructor">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <?= e($subject['instructor_name'] ?? 'TBA') ?>
                </div>
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

        <!-- Improvement Banner -->
        <?php if ($improvement): ?>
        <div class="improvement-banner <?= $improvement['change'] > 0 ? 'positive' : ($improvement['change'] < 0 ? 'negative' : 'neutral') ?>">
            <div class="improvement-icon">
                <?php if ($improvement['change'] > 0): ?>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                        <polyline points="17 6 23 6 23 12"/>
                    </svg>
                <?php else: ?>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
                        <polyline points="17 18 23 18 23 12"/>
                    </svg>
                <?php endif; ?>
            </div>
            <div class="improvement-content">
                <h3>Your Learning Progress</h3>
                <div class="scores">
                    <span class="score-item">Pre-Test: <strong><?= round($improvement['pretest']) ?>%</strong></span>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
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
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                        <?php elseif ($preTestStatus == 'completed_failed'): ?>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path d="M12 9v4M12 17h.01"/>
                            </svg>
                        <?php else: ?>
                            <span>1</span>
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
                        <div class="step-meta">
                            <span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <?= $preTest['time_limit'] ?? 30 ?> mins
                            </span>
                            <span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 6v6l4 2"/>
                                </svg>
                                Passing: <?= $preTest['passing_rate'] ?? 60 ?>%
                            </span>
                            <span class="one-attempt">One attempt only</span>
                        </div>

                        <?php if ($preTestStatus == 'completed_passed'): ?>
                            <div class="step-message success">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 6L9 17l-5-5"/>
                                </svg>
                                Excellent! You passed. You can skip lessons and go directly to Post-Test.
                            </div>
                        <?php elseif ($preTestStatus == 'completed_failed'): ?>
                            <div class="step-message warning">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                You didn't pass. Complete all lessons below to unlock the Post-Test.
                            </div>
                        <?php else: ?>
                            <div class="step-message info">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 16v-4M12 8h.01"/>
                                </svg>
                                This assesses your current knowledge. You only have ONE attempt.
                            </div>
                        <?php endif; ?>

                        <div class="step-actions">
                            <?php if (!$preTestAttempt): ?>
                                <a href="take-quiz.php?id=<?= $preTest['quiz_id'] ?>" class="btn-primary">
                                    Take Pre-Test
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14M12 5l7 7-7 7"/>
                                    </svg>
                                </a>
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
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                        <?php elseif ($lessonsStatus == 'locked'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        <?php else: ?>
                            <span><?= $preTest ? '2' : '1' ?></span>
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
                            <a href="lesson-view.php?id=<?= $lesson['lessons_id'] ?>" class="lesson-item <?= $lesson['is_completed'] ? 'completed' : '' ?>">
                                <span class="lesson-status">
                                    <?php if ($lesson['is_completed']): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                            <path d="M20 6L9 17l-5-5"/>
                                        </svg>
                                    <?php else: ?>
                                        <?= $i + 1 ?>
                                    <?php endif; ?>
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
                                    <?= $completedLessons > 0 ? 'Continue Learning' : 'Start Learning' ?>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14M12 5l7 7-7 7"/>
                                    </svg>
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
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                        <?php elseif ($postTestStatus == 'completed_failed'): ?>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <path d="M12 9v4M12 17h.01"/>
                            </svg>
                        <?php elseif ($postTestStatus == 'locked'): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        <?php else: ?>
                            <span><?= $preTest ? '3' : '2' ?></span>
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
                        <div class="step-meta">
                            <span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                <?= $postTest['time_limit'] ?? 30 ?> mins
                            </span>
                            <span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 6v6l4 2"/>
                                </svg>
                                Passing: <?= $postTest['passing_rate'] ?? 60 ?>%
                            </span>
                        </div>

                        <?php if ($postTestStatus == 'locked'): ?>
                            <div class="step-message info">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M12 16v-4M12 8h.01"/>
                                </svg>
                                <?php if (!$preTestAttempt): ?>
                                    Take the Pre-Test first, or complete all lessons to unlock.
                                <?php else: ?>
                                    Complete all <?= $totalLessons ?> lessons to unlock the Post-Test.
                                <?php endif; ?>
                            </div>
                        <?php elseif ($postTestStatus == 'completed_passed'): ?>
                            <div class="step-message success">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                </svg>
                                Congratulations! You have completed this learning module!
                            </div>
                        <?php elseif ($postTestStatus == 'completed_failed'): ?>
                            <div class="step-message warning">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
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
                                <a href="take-quiz.php?id=<?= $postTest['quiz_id'] ?>" class="btn-primary">
                                    Retake Post-Test
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14M12 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            <?php else: ?>
                                <a href="take-quiz.php?id=<?= $postTest['quiz_id'] ?>" class="btn-primary">
                                    Take Post-Test
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14M12 5l7 7-7 7"/>
                                    </svg>
                                </a>
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
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11l3 3L22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                All Quizzes
            </a>
            <a href="lessons.php?id=<?= $subjectOfferingId ?>" class="quick-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                </svg>
                All Lessons
            </a>
            <a href="announcements.php?id=<?= $subjectOfferingId ?>" class="quick-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                Announcements
            </a>
            <?php if ($improvement): ?>
            <a href="progress-comparison.php?id=<?= $subjectOfferingId ?>" class="quick-link">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>
                Progress Report
            </a>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
/* Learning Hub - Green/Cream Theme */
.hub-wrap {
    padding: 24px;
    max-width: 900px;
    margin: 0 auto;
}

/* Back Link */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #1B4D3E;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 20px;
}
.back-link:hover { text-decoration: underline; }

/* Header */
.hub-header {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    transition: all 0.2s;
}
.hub-header:hover {
    border-color: #1B4D3E;
}
.hub-info { flex: 1; }
.subject-badge {
    display: inline-block;
    background: #1B4D3E;
    color: #fff;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 10px;
}
.hub-info h1 {
    font-size: 22px;
    margin: 0 0 8px;
    color: #333;
    font-weight: 700;
}
.instructor {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #666;
    font-size: 14px;
}
.instructor svg { color: #1B4D3E; }

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
    stroke-width: 6;
}
.progress-circle .bg { stroke: #e8e8e8; }
.progress-circle .fill {
    stroke: #1B4D3E;
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
    color: #1B4D3E;
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
.improvement-banner.positive {
    background: #E8F5E9;
    border: 1px solid #A5D6A7;
}
.improvement-banner.positive .improvement-icon svg { color: #1B4D3E; }
.improvement-banner.negative {
    background: #FFEBEE;
    border: 1px solid #EF9A9A;
}
.improvement-banner.negative .improvement-icon svg { color: #C62828; }
.improvement-banner.neutral {
    background: #f5f5f5;
    border: 1px solid #e8e8e8;
}
.improvement-content h3 { font-size: 14px; margin: 0 0 8px; color: #333; }
.scores {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.score-item { font-size: 14px; color: #666; }
.score-item strong { color: #333; }
.scores svg { color: #999; }
.scores .change {
    padding: 4px 10px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 14px;
}
.change.up { background: #1B4D3E; color: #fff; }
.change.down { background: #C62828; color: #fff; }

/* Learning Flow */
.learning-flow { margin-bottom: 32px; }
.learning-flow h2 {
    font-size: 18px;
    margin: 0 0 20px;
    color: #333;
    font-weight: 600;
}

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
    background: #e8e8e8;
}
.flow-step.final .step-connector { display: none; }
.flow-step.completed .step-connector,
.flow-step.completed_passed .step-connector { background: #1B4D3E; }

.step-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 18px;
    font-weight: 700;
    background: #f5f5f5;
    color: #999;
    border: 3px solid #e8e8e8;
    z-index: 1;
}
.flow-step.available .step-icon,
.flow-step.in_progress .step-icon,
.flow-step.optional .step-icon {
    background: #fff;
    border-color: #1B4D3E;
    color: #1B4D3E;
}
.flow-step.completed .step-icon,
.flow-step.completed_passed .step-icon {
    background: #1B4D3E;
    border-color: #1B4D3E;
    color: #fff;
}
.flow-step.completed_failed .step-icon {
    background: #F9A825;
    border-color: #F9A825;
    color: #fff;
}
.flow-step.locked .step-icon {
    background: #f5f5f5;
    border-color: #e8e8e8;
    color: #999;
}

.step-content {
    flex: 1;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s;
}
.step-content:hover {
    border-color: #1B4D3E;
}
.flow-step.completed .step-content,
.flow-step.completed_passed .step-content {
    background: #E8F5E9;
    border-color: #A5D6A7;
}
.flow-step.locked .step-content {
    background: #fafafa;
    opacity: 0.8;
}
.flow-step.locked .step-content:hover {
    border-color: #e8e8e8;
}

.step-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
    flex-wrap: wrap;
    gap: 8px;
}
.step-header h3 { font-size: 16px; margin: 0; color: #333; font-weight: 600; }

.status-badge {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}
.status-badge.passed { background: #E8F5E9; color: #1B4D3E; }
.status-badge.failed { background: #FFEBEE; color: #C62828; }
.status-badge.available { background: #E3F2FD; color: #1565C0; }
.status-badge.progress { background: #FFF8E1; color: #F9A825; }
.status-badge.locked { background: #f5f5f5; color: #999; }
.status-badge.optional { background: #F3E5F5; color: #7B1FA2; }

.step-desc { color: #666; font-size: 14px; margin: 0 0 8px; }
.step-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.step-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #666;
}
.step-meta svg { color: #1B4D3E; }
.one-attempt {
    background: #FFF8E1;
    color: #F9A825 !important;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
}

.step-message {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 12px;
    line-height: 1.4;
}
.step-message svg { flex-shrink: 0; margin-top: 1px; }
.step-message.success {
    background: #E8F5E9;
    color: #1B4D3E;
}
.step-message.warning {
    background: #FFF8E1;
    color: #F9A825;
}
.step-message.info {
    background: #E3F2FD;
    color: #1565C0;
}

/* Lessons Progress */
.lessons-progress { margin: 12px 0; }
.progress-bar {
    height: 8px;
    background: #e8e8e8;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 6px;
}
.progress-fill {
    height: 100%;
    background: #1B4D3E;
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
    border: 1px solid transparent;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    font-size: 13px;
    transition: all 0.2s;
}
.lesson-item:hover {
    background: #f0f0f0;
    border-color: #1B4D3E;
}
.lesson-item.completed { background: #E8F5E9; }
.lesson-status {
    width: 24px;
    height: 24px;
    background: #e8e8e8;
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
    background: #1B4D3E;
    color: #fff;
}
.lesson-title { flex: 1; }
.lesson-done {
    font-size: 11px;
    color: #1B4D3E;
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
    display: inline-flex;
    align-items: center;
    gap: 6px;
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
    background: #1B4D3E;
    color: #fff;
}
.btn-primary:hover { background: #2D6A4F; }
.btn-secondary {
    background: #fff;
    color: #1B4D3E;
    border: 1px solid #1B4D3E;
}
.btn-secondary:hover { background: #E8F5E9; }
.btn-disabled {
    background: #e8e8e8;
    color: #999;
    cursor: not-allowed;
}

/* Quick Links */
.quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
}
.quick-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 10px;
    text-decoration: none;
    color: #333;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}
.quick-link:hover {
    border-color: #1B4D3E;
    background: #E8F5E9;
}
.quick-link svg { color: #1B4D3E; }

/* Responsive */
@media (max-width: 768px) {
    .hub-wrap { padding: 16px; }
    .hub-header { flex-direction: column; text-align: center; }
    .instructor { justify-content: center; }
    .flow-step { flex-direction: column; }
    .step-connector { display: none; }
    .step-icon { margin: 0 auto; }
    .step-content { text-align: center; }
    .step-header { justify-content: center; }
    .step-meta { justify-content: center; }
    .step-actions { justify-content: center; }
    .step-message { text-align: left; }
    .lessons-list { text-align: left; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
