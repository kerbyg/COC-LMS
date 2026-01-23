<?php
/**
 * CIT-LMS - Quizzes Page (Organized by Subject)
 * Shows all quizzes grouped by subject with collapsible sections
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$subjectOfferingId = $_GET['id'] ?? null;

// Check which quiz columns exist
$quizCols = array_column(db()->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'quiz'") ?: [], 'column_name');
$hasQuizType = in_array('quiz_type', $quizCols);
$hasLinkedQuizId = in_array('linked_quiz_id', $quizCols);

// Build quiz type select clause
$quizTypeSelect = $hasQuizType ? "q.quiz_type," : "'regular' as quiz_type,";
$linkedQuizSelect = $hasLinkedQuizId ? "q.linked_quiz_id," : "NULL as linked_quiz_id,";

if (!$subjectOfferingId) {
    // Get all quizzes from all enrolled subjects
    $quizzes = db()->fetchAll(
        "SELECT
            q.quiz_id,
            q.quiz_title as title,
            q.quiz_description as description,
            q.time_limit,
            q.passing_rate,
            q.max_attempts,
            q.subject_id,
            q.created_at,
            $quizTypeSelect
            $linkedQuizSelect
            s.subject_code,
            s.subject_name,
            so.subject_offered_id,
            (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
            (SELECT COUNT(*) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ?) as attempts_used,
            (SELECT MAX(percentage) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ? AND status = 'completed') as best_score,
            (SELECT attempt_id FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ? AND status = 'completed' ORDER BY percentage DESC LIMIT 1) as best_attempt_id
        FROM quiz q
        JOIN subject s ON q.subject_id = s.subject_id
        JOIN subject_offered so ON so.subject_id = s.subject_id
        JOIN student_subject ss ON ss.subject_offered_id = so.subject_offered_id
        WHERE ss.user_student_id = ? AND ss.status = 'enrolled' AND q.status = 'published'
        ORDER BY s.subject_code, q.quiz_type, q.created_at ASC",
        [$userId, $userId, $userId, $userId]
    );

    $pageTitle = 'All Quizzes';
    $currentPage = 'quizzes';
    $subject = null;
    $subjectId = null;

    // Group quizzes by subject
    $quizzesBySubject = [];
    foreach ($quizzes as $quiz) {
        $key = $quiz['subject_offered_id'];
        if (!isset($quizzesBySubject[$key])) {
            $quizzesBySubject[$key] = [
                'subject_code' => $quiz['subject_code'],
                'subject_name' => $quiz['subject_name'],
                'subject_id' => $quiz['subject_id'],
                'subject_offered_id' => $quiz['subject_offered_id'],
                'quizzes' => []
            ];
        }
        $quizzesBySubject[$key]['quizzes'][] = $quiz;
    }

} else {
    // Show quizzes for specific subject
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

    $subject = db()->fetchOne(
        "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as instructor_name
         FROM subject s
         LEFT JOIN faculty_subject fs ON fs.subject_offered_id = ?
         LEFT JOIN users u ON fs.user_teacher_id = u.users_id
         WHERE s.subject_id = ?",
        [$subjectOfferingId, $subjectId]
    );

    if (!$subject) {
        header('Location: my-subjects.php');
        exit;
    }

    $quizzes = db()->fetchAll(
        "SELECT
            q.quiz_id,
            q.quiz_title as title,
            q.quiz_description as description,
            q.time_limit,
            q.passing_rate,
            q.max_attempts,
            q.subject_id,
            q.created_at,
            $quizTypeSelect
            $linkedQuizSelect
            (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
            (SELECT COUNT(*) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ?) as attempts_used,
            (SELECT MAX(percentage) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ? AND status = 'completed') as best_score,
            (SELECT attempt_id FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ? AND status = 'completed' ORDER BY percentage DESC LIMIT 1) as best_attempt_id
        FROM quiz q
        WHERE q.subject_id = ? AND q.status = 'published'
        ORDER BY
            CASE q.quiz_type
                WHEN 'pre_test' THEN 1
                WHEN 'post_test' THEN 3
                ELSE 2
            END,
            q.created_at ASC",
        [$userId, $userId, $userId, $subjectId]
    );

    $pageTitle = $subject['subject_code'] . ' - Quizzes';
    $currentPage = 'quizzes';
}

// Calculate progress for subject view
$totalQuizzes = count($quizzes);
$completedQuizzes = count(array_filter($quizzes, fn($q) => $q['attempts_used'] > 0));
$passedQuizzes = count(array_filter($quizzes, fn($q) => isset($q['best_score']) && $q['best_score'] >= $q['passing_rate']));

// Get lesson completion data for post-test unlock logic
function getLessonProgress($userId, $subjectId) {
    $totalLessons = db()->fetchOne(
        "SELECT COUNT(*) as count FROM lessons WHERE subject_id = ? AND status = 'published'",
        [$subjectId]
    )['count'] ?? 0;

    $completedLessons = db()->fetchOne(
        "SELECT COUNT(*) as count FROM student_progress sp
         JOIN lessons l ON sp.lesson_id = l.lesson_id
         WHERE sp.user_student_id = ? AND l.subject_id = ? AND sp.status = 'completed'",
        [$userId, $subjectId]
    )['count'] ?? 0;

    return [
        'total' => $totalLessons,
        'completed' => $completedLessons,
        'all_done' => $totalLessons > 0 && $completedLessons >= $totalLessons
    ];
}

// Get pre-test status for a subject
function getPreTestStatus($userId, $subjectId, $quizzes) {
    foreach ($quizzes as $q) {
        if (($q['quiz_type'] ?? '') === 'pre_test' && ($q['subject_id'] ?? 0) == $subjectId) {
            $passed = isset($q['best_score']) && $q['best_score'] >= $q['passing_rate'];
            return [
                'exists' => true,
                'taken' => $q['attempts_used'] > 0,
                'passed' => $passed,
                'score' => $q['best_score'] ?? 0
            ];
        }
    }
    return ['exists' => false, 'taken' => false, 'passed' => false, 'score' => 0];
}

// Calculate subject quiz stats
function getSubjectQuizStats($quizzes) {
    $total = count($quizzes);
    $completed = count(array_filter($quizzes, fn($q) => $q['attempts_used'] > 0));
    $passed = count(array_filter($quizzes, fn($q) => isset($q['best_score']) && $q['best_score'] >= $q['passing_rate']));
    return ['total' => $total, 'completed' => $completed, 'passed' => $passed];
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($subject): ?>
            <!-- Back Link -->
            <div class="page-top">
                <a href="my-subjects.php" class="back-link">← Back to Subjects</a>
            </div>

            <!-- Subject Header -->
            <div class="subject-header">
                <div class="header-left">
                    <span class="subj-code"><?= e($subject['subject_code']) ?></span>
                    <h1 class="subject-title"><?= e($subject['subject_name']) ?></h1>
                    <p class="instructor-info">Instructor: <?= e($subject['instructor_name'] ?? 'TBA') ?></p>
                </div>
                <div class="header-right">
                    <a href="learning-hub.php?id=<?= $subjectOfferingId ?>" class="btn-learning-hub">
                        View Learning Path
                    </a>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <a href="learning-hub.php?id=<?= $subjectOfferingId ?>" class="nav-tab">
                    <span class="tab-icon">🎯</span>
                    <span class="tab-text">Learning Hub</span>
                </a>
                <a href="lessons.php?id=<?= $subjectOfferingId ?>" class="nav-tab">
                    <span class="tab-icon">📖</span>
                    <span class="tab-text">Lessons</span>
                </a>
                <a href="quizzes.php?id=<?= $subjectOfferingId ?>" class="nav-tab active">
                    <span class="tab-icon">📝</span>
                    <span class="tab-text">Quizzes</span>
                </a>
                <a href="announcements.php?id=<?= $subjectOfferingId ?>" class="nav-tab">
                    <span class="tab-icon">📢</span>
                    <span class="tab-text">Announcements</span>
                </a>
            </div>

            <!-- Learning Flow Notice -->
            <?php
            $lessonProgress = getLessonProgress($userId, $subjectId);
            $preTestStatus = getPreTestStatus($userId, $subjectId, $quizzes);
            ?>
            <?php if ($preTestStatus['exists'] && !$preTestStatus['taken']): ?>
            <div class="flow-notice info">
                <span class="notice-icon">💡</span>
                <div class="notice-text">
                    <strong>Start with the Pre-Test!</strong>
                    Take the pre-test first to assess your knowledge. If you pass, you can skip the lessons and go directly to the post-test.
                </div>
            </div>
            <?php elseif ($preTestStatus['exists'] && $preTestStatus['taken'] && !$preTestStatus['passed'] && !$lessonProgress['all_done']): ?>
            <div class="flow-notice warning">
                <span class="notice-icon">📚</span>
                <div class="notice-text">
                    <strong>Complete the Lessons</strong>
                    You need to complete all <?= $lessonProgress['total'] ?> lessons (<?= $lessonProgress['completed'] ?> done) to unlock the Post-Test.
                    <a href="lessons.php?id=<?= $subjectOfferingId ?>">Go to Lessons →</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Single Subject Quizzes -->
            <div class="quizzes-container">
                <?php if (empty($quizzes)): ?>
                    <div class="empty-box">
                        <span>📝</span>
                        <h3>No Quizzes Available</h3>
                        <p>Your instructor hasn't posted any quizzes yet.</p>
                    </div>
                <?php else: ?>
                    <div class="quizzes-list">
                        <?php foreach ($quizzes as $quiz): ?>
                            <?php echo renderQuizCard($quiz, $userId, $quizzes, $subjectId); ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- All Quizzes View - Organized by Subject -->
            <div class="page-head">
                <h1>📝 All Quizzes</h1>
                <p>View all quizzes from your enrolled subjects</p>
            </div>

            <div class="quizzes-container">
                <?php if (empty($quizzesBySubject)): ?>
                    <div class="empty-box">
                        <span>📝</span>
                        <h3>No Quizzes Available</h3>
                        <p>Your instructors haven't posted any quizzes yet.</p>
                    </div>
                <?php else: ?>
                    <div class="subject-accordion">
                        <?php foreach ($quizzesBySubject as $subjectData):
                            $stats = getSubjectQuizStats($subjectData['quizzes']);
                        ?>
                        <div class="subject-section">
                            <div class="subject-header-accordion" onclick="toggleSubject(this)">
                                <div class="subject-info">
                                    <span class="subj-badge"><?= e($subjectData['subject_code']) ?></span>
                                    <span class="subj-name"><?= e($subjectData['subject_name']) ?></span>
                                </div>
                                <div class="subject-stats">
                                    <span class="stat-badge total"><?= $stats['total'] ?> Quiz<?= $stats['total'] != 1 ? 'zes' : '' ?></span>
                                    <?php if ($stats['passed'] > 0): ?>
                                    <span class="stat-badge passed"><?= $stats['passed'] ?> Passed</span>
                                    <?php endif; ?>
                                    <?php if ($stats['completed'] > 0 && $stats['completed'] != $stats['passed']): ?>
                                    <span class="stat-badge attempted"><?= $stats['completed'] - $stats['passed'] ?> Attempted</span>
                                    <?php endif; ?>
                                </div>
                                <div class="subject-actions">
                                    <a href="learning-hub.php?id=<?= $subjectData['subject_offered_id'] ?>" class="hub-link" onclick="event.stopPropagation()">View Learning Path →</a>
                                    <span class="accordion-icon">▼</span>
                                </div>
                            </div>
                            <div class="subject-content">
                                <div class="quizzes-list">
                                    <?php foreach ($subjectData['quizzes'] as $quiz): ?>
                                        <?php echo renderQuizCard($quiz, $userId, $subjectData['quizzes'], $subjectData['subject_id']); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php
// Helper function to render quiz card
function renderQuizCard($quiz, $userId, $allQuizzes, $subjectId) {
    $attemptsLeft = ($quiz['max_attempts'] ?? 3) - $quiz['attempts_used'];
    $hasPassed = isset($quiz['best_score']) && $quiz['best_score'] >= $quiz['passing_rate'];
    $canTake = $attemptsLeft > 0;
    $quizType = $quiz['quiz_type'] ?? 'regular';

    // Determine quiz status
    if ($hasPassed) { $status = 'passed'; $statusText = 'Passed'; }
    elseif ($quiz['attempts_used'] > 0) { $status = 'attempted'; $statusText = 'Attempted'; }
    else { $status = 'available'; $statusText = 'Available'; }

    // Check if post-test is locked
    $isLocked = false;
    $lockReason = '';
    if ($quizType === 'post_test') {
        $subjectLessonProgress = getLessonProgress($userId, $subjectId);
        $subjectPreTestStatus = getPreTestStatus($userId, $subjectId, $allQuizzes);

        if (!$subjectPreTestStatus['passed'] && !$subjectLessonProgress['all_done']) {
            $isLocked = true;
            if (!$subjectPreTestStatus['taken']) {
                $lockReason = 'Take the Pre-Test first or complete all lessons';
            } else {
                $lockReason = 'Complete all ' . $subjectLessonProgress['total'] . ' lessons (' . $subjectLessonProgress['completed'] . ' done)';
            }
        }
    }

    ob_start();
    ?>
    <div class="quiz-card <?= $status ?> <?= $isLocked ? 'locked' : '' ?> type-<?= $quizType ?>">
        <div class="quiz-main">
            <div class="quiz-header">
                <div class="quiz-title-row">
                    <?php if ($quizType === 'pre_test'): ?>
                        <span class="quiz-type-badge pretest">PRE-TEST</span>
                    <?php elseif ($quizType === 'post_test'): ?>
                        <span class="quiz-type-badge posttest">POST-TEST</span>
                    <?php endif; ?>
                    <h3><?= e($quiz['title']) ?></h3>
                </div>
                <span class="quiz-badge <?= $status ?> <?= $isLocked ? 'locked' : '' ?>">
                    <?= $isLocked ? '🔒 Locked' : $statusText ?>
                </span>
            </div>

            <?php if (!empty($quiz['description'])): ?>
                <p class="quiz-desc"><?= e($quiz['description']) ?></p>
            <?php endif; ?>

            <?php if ($isLocked): ?>
                <div class="lock-reason">
                    <span class="lock-icon">🔒</span>
                    <?= e($lockReason) ?>
                </div>
            <?php endif; ?>

            <div class="quiz-meta">
                <span>📋 <?= $quiz['question_count'] ?> Questions</span>
                <span>⏱️ <?= $quiz['time_limit'] ?> Minutes</span>
                <span>🎯 Passing: <?= $quiz['passing_rate'] ?>%</span>
            </div>
        </div>

        <div class="quiz-side">
            <?php if ($quiz['attempts_used'] > 0): ?>
            <div class="score-box <?= $hasPassed ? 'passed' : 'failed' ?>">
                <span class="score-label">Best Score</span>
                <span class="score-value"><?= round($quiz['best_score']) ?>%</span>
            </div>
            <?php endif; ?>

            <div class="attempts-info">
                <?= $quiz['attempts_used'] ?>/<?= $quiz['max_attempts'] ?? 3 ?> Attempts Used
            </div>

            <div class="quiz-actions">
                <?php if ($isLocked): ?>
                    <span class="btn-locked">Max attempts reached</span>
                <?php elseif ($quizType === 'pre_test' && $quiz['attempts_used'] > 0): ?>
                    <span class="btn-completed">✓ Completed</span>
                <?php elseif ($canTake): ?>
                    <a href="take-quiz.php?id=<?= $quiz['quiz_id'] ?>" class="btn-take">
                        <?= $quiz['attempts_used'] > 0 ? 'Retry' : ($quizType === 'pre_test' ? 'Take Pre-Test' : 'Take Quiz') ?> →
                    </a>
                <?php else: ?>
                    <span class="btn-disabled">Max attempts reached</span>
                <?php endif; ?>

                <?php if ($quiz['best_attempt_id']): ?>
                <a href="quiz-result.php?id=<?= $quiz['best_attempt_id'] ?>" class="btn-results">
                    View Results
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<style>
/* Enhanced Quizzes Page Styles */

.page-top { margin-bottom: 20px; }
.back-link {
    color: #16a34a;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}
.back-link:hover { text-decoration: underline; }

/* Subject Header */
.subject-header {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 1px solid #bbf7d0;
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
}

.header-left { flex: 1; }
.subj-code {
    background: #16a34a;
    color: #fff;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
    margin-bottom: 10px;
}
.subject-title {
    font-size: 22px;
    color: #1a1a1a;
    margin: 0 0 6px;
    font-weight: 700;
}
.instructor-info {
    color: #666;
    margin: 0;
    font-size: 14px;
}

.btn-learning-hub {
    background: #16a34a;
    color: #fff;
    padding: 12px 20px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
}
.btn-learning-hub:hover {
    background: #15803d;
}

/* Navigation Tabs */
.nav-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 20px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 12px;
    padding: 5px;
}
.nav-tab {
    flex: 1;
    padding: 12px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    color: #666;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.2s;
}
.nav-tab:hover { background: #f5f5f5; color: #16a34a; }
.nav-tab.active { background: #16a34a; color: #fff; }
.tab-icon { font-size: 14px; }

/* Flow Notice */
.flow-notice {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}
.flow-notice.info { background: #dbeafe; border: 1px solid #93c5fd; }
.flow-notice.warning { background: #fef3c7; border: 1px solid #fcd34d; }
.notice-icon { font-size: 24px; flex-shrink: 0; }
.notice-text { font-size: 14px; color: #333; line-height: 1.5; }
.notice-text strong { display: block; margin-bottom: 4px; }
.notice-text a { color: #16a34a; font-weight: 600; }

/* Page Head */
.page-head { margin-bottom: 24px; }
.page-head h1 { font-size: 26px; color: #1a1a1a; margin: 0 0 8px; }
.page-head p { color: #666; margin: 0; font-size: 14px; }

/* Subject Accordion */
.subject-accordion {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.subject-section {
    background: #fff;
    border: 2px solid #e5e5e5;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s;
}
.subject-section:hover {
    border-color: #16a34a;
}
.subject-section.collapsed .subject-content {
    display: none;
}
.subject-section.collapsed .accordion-icon {
    transform: rotate(-90deg);
}

.subject-header-accordion {
    display: flex;
    align-items: center;
    padding: 18px 24px;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    cursor: pointer;
    gap: 16px;
    transition: all 0.2s;
}
.subject-header-accordion:hover {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
}

.subject-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}
.subj-badge {
    background: #16a34a;
    color: #fff;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
}
.subj-name {
    font-size: 17px;
    font-weight: 700;
    color: #1a1a1a;
}

.subject-stats {
    display: flex;
    gap: 8px;
}
.stat-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.stat-badge.total { background: #e5e7eb; color: #374151; }
.stat-badge.passed { background: #dcfce7; color: #166534; }
.stat-badge.attempted { background: #fef3c7; color: #92400e; }

.subject-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}
.hub-link {
    color: #16a34a;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
}
.hub-link:hover { text-decoration: underline; }

.accordion-icon {
    font-size: 12px;
    color: #666;
    transition: transform 0.3s;
}

.subject-content {
    padding: 20px 24px;
    border-top: 1px solid #e5e5e5;
    background: #fafafa;
}

/* Quiz Cards */
.quizzes-container { margin-top: 20px; }
.quizzes-list { display: flex; flex-direction: column; gap: 12px; }

.quiz-card {
    background: #fff;
    border: 2px solid #e5e5e5;
    border-radius: 12px;
    display: flex;
    overflow: hidden;
    transition: all 0.2s;
}
.quiz-card:hover {
    border-color: #16a34a;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);
}
.quiz-card.locked {
    opacity: 0.85;
    background: #fafafa;
}
.quiz-card.locked:hover {
    border-color: #e5e5e5;
    box-shadow: none;
}

/* Quiz Type Borders */
.quiz-card.type-pre_test { border-left: 4px solid #3b82f6; }
.quiz-card.type-post_test { border-left: 4px solid #8b5cf6; }
.quiz-card.passed { border-left-color: #22c55e; }

.quiz-main { flex: 1; padding: 18px 20px; }
.quiz-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
    gap: 12px;
}
.quiz-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.quiz-type-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.5px;
}
.quiz-type-badge.pretest { background: #dbeafe; color: #1d4ed8; }
.quiz-type-badge.posttest { background: #ede9fe; color: #6d28d9; }

.quiz-header h3 {
    font-size: 16px;
    color: #1a1a1a;
    margin: 0;
    font-weight: 700;
}

.quiz-badge {
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    flex-shrink: 0;
}
.quiz-badge.available { background: #dcfce7; color: #16a34a; }
.quiz-badge.passed { background: #dcfce7; color: #166534; }
.quiz-badge.attempted { background: #fef3c7; color: #d97706; }
.quiz-badge.locked { background: #f3f4f6; color: #6b7280; }

.quiz-desc {
    color: #666;
    margin: 0 0 10px;
    font-size: 13px;
    line-height: 1.5;
}

.lock-reason {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: #f3f4f6;
    border-radius: 6px;
    font-size: 12px;
    color: #4b5563;
    margin-bottom: 10px;
}
.lock-icon { font-size: 14px; }

.quiz-meta {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    font-size: 12px;
    color: #666;
}

.quiz-side {
    width: 200px;
    padding: 18px;
    background: #fafafa;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: center;
    gap: 10px;
    border-left: 1px solid #e5e5e5;
}

.score-box {
    text-align: center;
    padding: 12px;
    border-radius: 8px;
}
.score-box.passed { background: #dcfce7; }
.score-box.failed { background: #fee2e2; }
.score-label {
    display: block;
    font-size: 10px;
    color: #666;
    margin-bottom: 2px;
    font-weight: 600;
    text-transform: uppercase;
}
.score-value {
    font-size: 24px;
    font-weight: 800;
    display: block;
    color: #16a34a;
}
.score-box.failed .score-value { color: #dc2626; }

.attempts-info {
    text-align: center;
    font-size: 11px;
    color: #666;
}

.quiz-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.btn-take {
    display: block;
    padding: 10px 16px;
    background: #16a34a;
    color: #fff;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
    text-align: center;
    transition: all 0.2s;
}
.btn-take:hover { background: #15803d; }
.btn-locked, .btn-disabled {
    display: block;
    padding: 10px 16px;
    background: #e5e7eb;
    color: #6b7280;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
    text-align: center;
    cursor: not-allowed;
}
.btn-completed {
    display: block;
    padding: 10px 16px;
    background: #dcfce7;
    color: #16a34a;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
    text-align: center;
}
.btn-results {
    display: block;
    padding: 8px 16px;
    background: #fff;
    border: 1px solid #e5e5e5;
    color: #666;
    border-radius: 8px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    transition: all 0.2s;
}
.btn-results:hover { border-color: #16a34a; color: #16a34a; }

/* Empty State */
.empty-box {
    text-align: center;
    padding: 60px 40px;
    background: #fff;
    border: 2px dashed #e5e5e5;
    border-radius: 14px;
}
.empty-box span { font-size: 48px; display: block; margin-bottom: 14px; opacity: 0.5; }
.empty-box h3 { font-size: 18px; color: #1a1a1a; margin: 0 0 8px; }
.empty-box p { color: #666; margin: 0; font-size: 14px; }

/* Responsive */
@media (max-width: 900px) {
    .subject-header { flex-direction: column; text-align: center; }
    .subject-header-accordion { flex-direction: column; align-items: flex-start; }
    .subject-stats { margin-top: 10px; }
    .subject-actions { width: 100%; justify-content: space-between; margin-top: 10px; }
    .quiz-card { flex-direction: column; }
    .quiz-side { width: 100%; border-left: none; border-top: 1px solid #e5e5e5; }
    .nav-tabs { flex-wrap: wrap; }
    .nav-tab { min-width: 45%; }
}
</style>

<script>
function toggleSubject(header) {
    const section = header.closest('.subject-section');
    section.classList.toggle('collapsed');
}

// Expand all sections by default on page load
document.addEventListener('DOMContentLoaded', function() {
    // All sections are expanded by default
    // User can click to collapse
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
