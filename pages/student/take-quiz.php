<?php
/**
 * CIT-LMS - Take Quiz Page
 * Quiz taking interface with timer and auto-submit
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$quizId = $_GET['id'] ?? 0;

if (!$quizId) {
    header('Location: my-subjects.php');
    exit;
}

/**
 * FIXED: Get quiz with subject info
 * Logic: Join via subject_id to find the correct offering the student is in
 */
$quiz = db()->fetchOne(
    "SELECT
        q.*,
        s.subject_code,
        s.subject_name,
        ss.subject_offered_id
    FROM quiz q
    JOIN subject s ON q.subject_id = s.subject_id
    JOIN subject_offered so ON so.subject_id = s.subject_id
    JOIN student_subject ss ON so.subject_offered_id = ss.subject_offered_id
    WHERE q.quiz_id = ? AND ss.user_student_id = ? AND q.status = 'published'
    LIMIT 1",
    [$quizId, $userId]
);

if (!$quiz) {
    header('Location: my-subjects.php');
    exit;
}

/**
 * FIXED: Verify enrollment using 'student_subject' and 'user_student_id'
 */
$enrollment = db()->fetchOne(
    "SELECT * FROM student_subject
     WHERE user_student_id = ? AND subject_offered_id = ? AND status = 'enrolled'",
    [$userId, $quiz['subject_offered_id']]
);

if (!$enrollment) {
    header('Location: my-subjects.php');
    exit;
}

/**
 * PRE-TEST/POST-TEST ACCESS CHECK
 * If this is a post-test, verify the student has met the requirements:
 * Post-test is UNLOCKED if either:
 * 1. Pre-test was PASSED, OR
 * 2. ALL lessons are completed
 */
$accessDenied = false;
$accessDeniedReason = '';
$preTestScore = null;
$preTestPassed = false;
$lessonsCompleted = 0;
$totalLessons = 0;
$linkedPreTest = null;

// Check if quiz_type column exists
$quizCols = array_column(db()->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'quiz'") ?: [], 'column_name');
$hasQuizType = in_array('quiz_type', $quizCols);
$hasLinkedQuizId = in_array('linked_quiz_id', $quizCols);

$quizType = $hasQuizType ? ($quiz['quiz_type'] ?? 'regular') : 'regular';

// PRE-TEST: Check if already taken (only 1 attempt allowed)
if ($quizType === 'pre_test') {
    $existingAttempt = db()->fetchOne(
        "SELECT * FROM student_quiz_attempts WHERE quiz_id = ? AND user_student_id = ? AND status = 'completed'",
        [$quizId, $userId]
    );

    if ($existingAttempt) {
        // Pre-test already taken - redirect to result
        $accessDenied = true;
        $accessDeniedReason = 'pretest_already_taken';
        $preTestScore = $existingAttempt['percentage'];
        $preTestPassed = $existingAttempt['percentage'] >= $quiz['passing_rate'];
    }
}

if ($quizType === 'post_test') {
    // Find the pre-test for this subject (by linked_quiz_id or by quiz_type)
    $linkedPreTestId = $hasLinkedQuizId ? ($quiz['linked_quiz_id'] ?? null) : null;

    if (!$linkedPreTestId && $hasQuizType) {
        // Find pre-test by quiz_type for same subject
        $preTestQuiz = db()->fetchOne(
            "SELECT quiz_id, quiz_title, passing_rate FROM quiz WHERE subject_id = ? AND quiz_type = 'pre_test' AND status = 'published' LIMIT 1",
            [$quiz['subject_id']]
        );
        if ($preTestQuiz) {
            $linkedPreTestId = $preTestQuiz['quiz_id'];
            $linkedPreTest = $preTestQuiz;
        }
    } else if ($linkedPreTestId) {
        $linkedPreTest = db()->fetchOne(
            "SELECT quiz_id, quiz_title, passing_rate FROM quiz WHERE quiz_id = ?",
            [$linkedPreTestId]
        );
    }

    // Count total and completed lessons for this subject
    $totalLessons = db()->fetchOne(
        "SELECT COUNT(*) as count FROM lessons WHERE subject_id = ? AND status = 'published'",
        [$quiz['subject_id']]
    )['count'] ?? 0;

    $lessonsCompleted = db()->fetchOne(
        "SELECT COUNT(*) as count FROM student_progress sp
         JOIN lessons l ON sp.lessons_id = l.lessons_id
         WHERE sp.user_student_id = ? AND l.subject_id = ? AND sp.status = 'completed'",
        [$userId, $quiz['subject_id']]
    )['count'] ?? 0;

    $allLessonsCompleted = $totalLessons > 0 && $lessonsCompleted >= $totalLessons;

    // Check if pre-test was taken and passed
    if ($linkedPreTestId) {
        $preTestAttempt = db()->fetchOne(
            "SELECT sqa.percentage, sqa.passed, q.passing_rate
             FROM student_quiz_attempts sqa
             JOIN quiz q ON sqa.quiz_id = q.quiz_id
             WHERE sqa.quiz_id = ? AND sqa.user_student_id = ? AND sqa.status = 'completed'
             ORDER BY sqa.percentage DESC LIMIT 1",
            [$linkedPreTestId, $userId]
        );

        if ($preTestAttempt) {
            $preTestScore = $preTestAttempt['percentage'];
            $preTestPassed = $preTestAttempt['percentage'] >= $preTestAttempt['passing_rate'];
        }
    }

    // Post-test access logic: PASSED pre-test OR completed all lessons
    if (!$preTestPassed && !$allLessonsCompleted) {
        $accessDenied = true;

        if (!$preTestScore && $linkedPreTestId) {
            // Pre-test not taken at all
            $accessDeniedReason = 'pre_test_required';
        } else {
            // Pre-test taken but failed, and lessons not completed
            $accessDeniedReason = 'lessons_required';
        }
    }

    // Log this access attempt (only if quiz_access_log table exists)
    try {
        db()->execute(
            "INSERT INTO quiz_access_log (user_student_id, quiz_id, was_granted, denial_reason, pre_test_score, lessons_completed)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$userId, $quizId, $accessDenied ? 0 : 1, $accessDenied ? $accessDeniedReason : null, $preTestScore, $lessonsCompleted]
        );
    } catch (Exception $e) {
        // Table might not exist, ignore
    }
}

// If access is denied, show denial page
if ($accessDenied) {
    $pageTitle = 'Quiz Access Required';
    include __DIR__ . '/../../includes/header.php';
    include __DIR__ . '/../../includes/sidebar.php';
    ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../includes/topbar.php'; ?>
        <div class="page-content">
            <div class="access-denied-container">
                <?php if ($accessDeniedReason === 'pre_test_required'): ?>
                    <div class="access-icon">🔒</div>
                    <h1>Post-Test Locked</h1>
                    <p>Before taking the <strong><?= e($quiz['quiz_title']) ?></strong>, you need to unlock it first.</p>
                    <div class="unlock-options">
                        <h3>How to unlock:</h3>
                        <div class="option-item">
                            <span class="option-num">1</span>
                            <div class="option-text">
                                <strong>Pass the Pre-Test</strong>
                                <p>Take the pre-test and achieve a passing score</p>
                            </div>
                        </div>
                        <div class="option-divider">OR</div>
                        <div class="option-item">
                            <span class="option-num">2</span>
                            <div class="option-text">
                                <strong>Complete All Lessons</strong>
                                <p><?= $lessonsCompleted ?> of <?= $totalLessons ?> lessons completed</p>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($linkedPreTest)): ?>
                        <a href="take-quiz.php?id=<?= $linkedPreTest['quiz_id'] ?>" class="btn-primary">
                            Take Pre-Test →
                        </a>
                    <?php endif; ?>
                    <a href="lessons.php?id=<?= $quiz['subject_offered_id'] ?>" class="btn-secondary">
                        Go to Lessons
                    </a>
                <?php elseif ($accessDeniedReason === 'lessons_required'): ?>
                    <div class="access-icon">📚</div>
                    <h1>Complete Lessons First</h1>
                    <p>Your pre-test score was <strong><?= number_format($preTestScore, 1) ?>%</strong> (below passing).</p>
                    <p>Complete all lessons to unlock the post-test and measure your improvement.</p>
                    <div class="progress-info">
                        <div class="progress-bar-large">
                            <div class="progress-fill-large" style="width: <?= $totalLessons > 0 ? ($lessonsCompleted / $totalLessons * 100) : 0 ?>%"></div>
                        </div>
                        <span class="progress-text-large"><?= $lessonsCompleted ?> / <?= $totalLessons ?> Lessons Completed</span>
                    </div>
                    <a href="lessons.php?id=<?= $quiz['subject_offered_id'] ?>" class="btn-primary">
                        Continue Learning →
                    </a>
                <?php elseif ($accessDeniedReason === 'pretest_already_taken'): ?>
                    <div class="access-icon"><?= $preTestPassed ? '🎉' : '📋' ?></div>
                    <h1>Pre-Test Already Completed</h1>
                    <p>You have already taken the pre-test.</p>
                    <div class="result-summary <?= $preTestPassed ? 'passed' : 'failed' ?>">
                        <span class="score-label">Your Score</span>
                        <span class="score-value"><?= number_format($preTestScore, 1) ?>%</span>
                        <span class="score-status"><?= $preTestPassed ? 'PASSED' : 'NOT PASSED' ?></span>
                    </div>
                    <?php if ($preTestPassed): ?>
                        <p class="next-step-msg success">Great job! You can proceed directly to the Post-Test.</p>
                        <?php
                        // Find post-test for this subject
                        $postTest = db()->fetchOne(
                            "SELECT quiz_id, quiz_title FROM quiz WHERE subject_id = ? AND quiz_type = 'post_test' AND status = 'published' LIMIT 1",
                            [$quiz['subject_id']]
                        );
                        if ($postTest): ?>
                        <a href="take-quiz.php?id=<?= $postTest['quiz_id'] ?>" class="btn-primary">
                            Take Post-Test →
                        </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="next-step-msg warning">Complete the lessons below to unlock the Post-Test.</p>
                        <a href="lessons.php?id=<?= $quiz['subject_offered_id'] ?>" class="btn-primary">
                            Go to Lessons →
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="learning-hub.php?id=<?= $quiz['subject_offered_id'] ?>" class="btn-outline">
                    View Learning Path
                </a>
            </div>
        </div>
    </main>
    <style>
    .access-denied-container { max-width: 520px; margin: 40px auto; text-align: center; background: #fff; padding: 40px; border-radius: 16px; border: 1px solid #e5e5e5; }
    .access-icon { font-size: 56px; margin-bottom: 20px; }
    .access-denied-container h1 { font-size: 22px; margin: 0 0 14px; color: #1a1a1a; }
    .access-denied-container p { color: #666; margin-bottom: 10px; line-height: 1.6; font-size: 14px; }
    .unlock-options { text-align: left; background: #f9fafb; border-radius: 12px; padding: 20px; margin: 20px 0; }
    .unlock-options h3 { font-size: 13px; color: #666; margin: 0 0 14px; text-transform: uppercase; letter-spacing: 0.5px; }
    .option-item { display: flex; gap: 14px; align-items: flex-start; }
    .option-num { width: 28px; height: 28px; background: #16a34a; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; flex-shrink: 0; }
    .option-text strong { display: block; color: #1a1a1a; font-size: 14px; margin-bottom: 2px; }
    .option-text p { color: #666; font-size: 13px; margin: 0; }
    .option-divider { text-align: center; color: #999; font-size: 12px; font-weight: 600; margin: 14px 0; }
    .progress-info { margin: 20px 0; }
    .progress-bar-large { height: 12px; background: #e5e5e5; border-radius: 6px; overflow: hidden; margin-bottom: 8px; }
    .progress-fill-large { height: 100%; background: linear-gradient(90deg, #16a34a, #22c55e); transition: 0.3s; }
    .progress-text-large { font-weight: 600; color: #16a34a; font-size: 14px; }
    .btn-primary { display: inline-block; background: #16a34a; color: #fff; padding: 12px 28px; border-radius: 10px; font-weight: 600; text-decoration: none; margin: 12px 6px 0; transition: 0.2s; font-size: 14px; }
    .btn-primary:hover { background: #15803d; }
    .btn-secondary { display: inline-block; background: #fff; border: 1px solid #16a34a; color: #16a34a; padding: 11px 26px; border-radius: 10px; font-weight: 600; text-decoration: none; margin: 12px 6px 0; transition: 0.2s; font-size: 14px; }
    .btn-secondary:hover { background: #f0fdf4; }
    .btn-outline { display: inline-block; background: transparent; border: 1px solid #e5e5e5; color: #666; padding: 11px 26px; border-radius: 10px; font-weight: 500; text-decoration: none; margin: 12px 6px 0; transition: 0.2s; font-size: 14px; }
    .btn-outline:hover { border-color: #16a34a; color: #16a34a; }
    /* Pre-test result styles */
    .result-summary { padding: 24px; border-radius: 12px; margin: 20px 0; }
    .result-summary.passed { background: #dcfce7; }
    .result-summary.failed { background: #fef3c7; }
    .result-summary .score-label { display: block; font-size: 12px; color: #666; text-transform: uppercase; margin-bottom: 4px; }
    .result-summary .score-value { display: block; font-size: 42px; font-weight: 800; color: #1a1a1a; }
    .result-summary.passed .score-value { color: #16a34a; }
    .result-summary.failed .score-value { color: #d97706; }
    .result-summary .score-status { display: inline-block; margin-top: 8px; padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; }
    .result-summary.passed .score-status { background: #16a34a; color: #fff; }
    .result-summary.failed .score-status { background: #d97706; color: #fff; }
    .next-step-msg { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin: 16px 0; }
    .next-step-msg.success { background: #dcfce7; color: #166534; }
    .next-step-msg.warning { background: #fef3c7; color: #92400e; }
    </style>
    <?php
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// Check attempts using 'user_student_id'
$attemptCount = db()->fetchOne(
    "SELECT COUNT(*) as count FROM student_quiz_attempts WHERE quiz_id = ? AND user_student_id = ?",
    [$quizId, $userId]
)['count'] ?? 0;

if ($attemptCount >= $quiz['max_attempts']) {
    header('Location: quizzes.php?id=' . $quiz['subject_offered_id']);
    exit;
}

/**
 * FIXED: Get questions from 'quiz_questions' table (matches Phase 1 migration structure)
 */
$questions = db()->fetchAll(
    "SELECT
        q.questions_id,
        q.question_text,
        q.question_type,
        q.points
    FROM quiz_questions q
    WHERE q.quiz_id = ?
    ORDER BY " . ($quiz['is_randomized'] ? "RAND()" : "q.order_number"),
    [$quizId]
);

/**
 * FIXED: Get choices from 'question_option' table (matches Phase 1 migration structure)
 */
foreach ($questions as &$q) {
    $q['choices'] = db()->fetchAll(
        "SELECT option_id as choice_id, option_text as choice_text
         FROM question_option
         WHERE quiz_question_id = ?
         ORDER BY " . ($quiz['is_randomized'] ? "RAND()" : "order_number"),
        [$q['questions_id']]
    );
}
unset($q); // Destroy reference to prevent bugs

$totalQuestions = count($questions);
$totalPoints = array_sum(array_column($questions, 'points'));

$pageTitle = 'Taking: ' . $quiz['quiz_title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<div class="quiz-container">
    
    <div class="quiz-header">
        <div class="quiz-info">
            <span class="subj-code"><?= e($quiz['subject_code']) ?></span>
            <h1><?= e($quiz['quiz_title']) ?></h1>
        </div>
        <div class="quiz-timer" id="timer">
            <span class="timer-icon">⏱️</span>
            <span class="timer-value" id="timerValue"><?= $quiz['time_limit'] ?>:00</span>
        </div>
    </div>
    
    <div class="quiz-progress">
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill" style="width: 0%"></div>
        </div>
        <span class="progress-text"><span id="answeredCount">0</span>/<?= $totalQuestions ?> Answered</span>
    </div>
    
    <form id="quizForm" method="POST" action="<?= BASE_URL ?>/api/QuizAttemptsAPI.php?action=submit">
        <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
        <input type="hidden" name="time_taken" id="timeTaken" value="0">
        
        <div class="questions-wrapper">
            <?php foreach ($questions as $index => $q): ?>
            <div class="question-card" id="question-<?= $index + 1 ?>" data-question="<?= $index + 1 ?>">
                <div class="question-header">
                    <span class="question-num">Question <?= $index + 1 ?></span>
                    <span class="question-points"><?= $q['points'] ?> pts</span>
                </div>
                
                <div class="question-text"><?= nl2br(e($q['question_text'])) ?></div>
                
                <div class="choices-list">
                    <?php if ($q['question_type'] === 'multiple_choice' || $q['question_type'] === 'true_false'): ?>
                        <?php foreach ($q['choices'] as $choice): ?>
                        <label class="choice-item">
                            <input type="radio" name="answers[<?= $q['questions_id'] ?>]" value="<?= $choice['choice_id'] ?>" onchange="updateProgress()">
                            <span class="choice-radio"></span>
                            <span class="choice-text"><?= e($choice['choice_text']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="question-nav">
            <div class="nav-title">Navigator</div>
            <div class="nav-grid">
                <?php for ($i = 1; $i <= $totalQuestions; $i++): ?>
                <button type="button" class="nav-btn" data-target="<?= $i ?>" onclick="scrollToQuestion(<?= $i ?>)"><?= $i ?></button>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="quiz-actions">
            <button type="button" class="btn-cancel" onclick="confirmExit()">Cancel</button>
            <button type="button" class="btn-submit" id="submitBtn" onclick="showSubmitModal()">Submit Quiz</button>
        </div>
    </form>
</div>

<div class="modal-overlay" id="submitModal">
    <div class="modal-box">
        <h3>Submit Quiz?</h3>
        <p>You have answered <span id="modalAnswered">0</span> out of <?= $totalQuestions ?> questions.</p>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeModal()">Review</button>
            <button type="button" class="btn-primary" onclick="doSubmit()">Submit</button>
        </div>
    </div>
</div>

<style>
/* Simplified layout based on your design requirements */
body { font-family: 'Inter', sans-serif; background: #fdfbf7; color: #1c1917; }
.quiz-container { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
.quiz-header { background: #fff; border: 1px solid #f5f0e8; border-radius: 16px; padding: 24px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
.subj-code { background: #16a34a; color: #fff; padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 700; margin-bottom: 8px; display: inline-block; }
.timer-value { font-size: 28px; font-weight: 800; color: #dc2626; font-family: monospace; }
.quiz-progress { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #f5f0e8; margin-bottom: 24px; }
.progress-bar { height: 10px; background: #e7e5e4; border-radius: 5px; overflow: hidden; margin-bottom: 8px; }
.progress-fill { height: 100%; background: #16a34a; transition: 0.3s; }
.question-card { background: #fff; border: 1px solid #f5f0e8; border-radius: 16px; padding: 32px; margin-bottom: 20px; scroll-margin-top: 100px; }
.question-header { display: flex; justify-content: space-between; margin-bottom: 16px; }
.question-num { font-weight: 700; color: #16a34a; }
.choice-item { display: flex; align-items: center; gap: 12px; padding: 16px; background: #fff; border: 2px solid #f5f0e8; border-radius: 12px; cursor: pointer; margin-bottom: 10px; transition: 0.2s; }
.choice-item:hover { border-color: #16a34a; }
.choice-item input { display: none; }
.choice-item input:checked + .choice-radio { background: #16a34a; border-color: #16a34a; }
.choice-radio { width: 20px; height: 20px; border: 2px solid #d6d3d1; border-radius: 50%; }
.question-nav { background: #fff; border: 1px solid #f5f0e8; border-radius: 16px; padding: 24px; margin-bottom: 40px; }
.nav-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.nav-btn { width: 40px; height: 40px; border: 2px solid #e7e5e4; background: #fff; border-radius: 8px; font-weight: 700; cursor: pointer; }
.nav-btn.answered { background: #16a34a; border-color: #16a34a; color: #fff; }
.quiz-actions { display: flex; gap: 12px; justify-content: center; margin-top: 32px; }
.btn-cancel { background: #fff; border: 2px solid #e7e5e4; color: #57534e; padding: 16px 40px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
.btn-cancel:hover { border-color: #dc2626; color: #dc2626; }
.btn-submit { background: #16a34a; color: #fff; border: none; padding: 16px 40px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; }
.btn-submit:hover { background: #15803d; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3); }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; }
.modal-overlay.show { display: flex; }
.modal-box { background: #fff; padding: 32px; border-radius: 20px; text-align: center; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.modal-box h3 { margin: 0 0 12px; font-size: 20px; color: #1c1917; }
.modal-box p { margin: 0 0 24px; color: #78716c; }
.modal-actions { display: flex; gap: 12px; justify-content: center; }
.btn-secondary { background: #fff; border: 2px solid #e7e5e4; color: #57534e; padding: 12px 28px; border-radius: 10px; font-weight: 600; cursor: pointer; }
.btn-secondary:hover { border-color: #16a34a; color: #16a34a; }
.btn-primary { background: #16a34a; color: #fff; border: none; padding: 12px 28px; border-radius: 10px; font-weight: 600; cursor: pointer; }
.btn-primary:hover { background: #15803d; }
</style>

<script>
const timeLimit = <?= $quiz['time_limit'] ?> * 60;
let timeRemaining = timeLimit;
let startTime = Date.now();
let formSubmitted = false;

function startTimer() {
    const timerInterval = setInterval(() => {
        if (formSubmitted) {
            clearInterval(timerInterval);
            return;
        }
        timeRemaining--;
        const mins = Math.floor(timeRemaining / 60);
        const secs = timeRemaining % 60;
        document.getElementById('timerValue').textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            autoSubmit();
        }
    }, 1000);
}

function updateProgress() {
    const answered = document.querySelectorAll('input[type="radio"]:checked');
    const count = answered.length;
    document.getElementById('answeredCount').textContent = count;
    document.getElementById('progressFill').style.width = (count / <?= $totalQuestions ?> * 100) + '%';
    document.querySelectorAll('.nav-btn').forEach((btn, i) => {
        const qCard = document.getElementById('question-' + (i + 1));
        btn.classList.toggle('answered', qCard.querySelector('input:checked'));
    });
}

function scrollToQuestion(num) {
    document.getElementById('question-' + num).scrollIntoView({ behavior: 'smooth' });
}

// Show submit confirmation modal
function showSubmitModal() {
    console.log('showSubmitModal called');
    const answeredCount = document.querySelectorAll('input[type="radio"]:checked').length;
    console.log('Answered questions:', answeredCount);
    document.getElementById('modalAnswered').textContent = answeredCount;
    document.getElementById('submitModal').classList.add('show');
}

function closeModal() {
    console.log('closeModal called');
    document.getElementById('submitModal').classList.remove('show');
}

function doSubmit() {
    console.log('doSubmit called');
    if (formSubmitted) {
        console.log('Already submitted, ignoring');
        return;
    }
    formSubmitted = true;

    // Set time taken
    const timeTaken = Math.floor((Date.now() - startTime) / 1000);
    document.getElementById('timeTaken').value = timeTaken;
    console.log('Time taken set to:', timeTaken);

    // Hide modal
    closeModal();

    // Submit the form directly
    console.log('Submitting form...');
    document.getElementById('quizForm').submit();
}

function autoSubmit() {
    alert('Time is up! Your quiz will be submitted automatically.');
    doSubmit();
}

function confirmExit() {
    if (confirm('Exit quiz? Progress will be lost.')) {
        window.location.href = 'quizzes.php?id=<?= $quiz['subject_offered_id'] ?>';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    startTimer();
    console.log('Quiz initialized. Time limit:', timeLimit, 'seconds');

    // Verify button exists
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        console.log('Submit button found:', submitBtn);
        console.log('Button onclick:', submitBtn.onclick);
    } else {
        console.error('Submit button NOT found!');
    }

    // Verify modal exists
    const modal = document.getElementById('submitModal');
    if (modal) {
        console.log('Modal found:', modal);
    } else {
        console.error('Modal NOT found!');
    }
});
</script>

</body>
</html>