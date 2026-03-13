<?php
/**
 * ============================================================
 * CIT-LMS Quiz Attempts API
 * Fixed for schema: student_quiz_attempts, quiz_questions, student_quiz_answers
 * ============================================================
 */

// Buffer ALL output — prevents PHP warnings/errors from corrupting JSON
ob_start();

// Convert every PHP error/warning into an exception so we can catch it
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// On shutdown (fatal errors), emit proper JSON
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $err['message']]);
    }
});

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Discard any stray output from includes
ob_clean();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if (!Auth::check()) {
    if ($action === 'submit') {
        header('Location: ' . BASE_URL . '/pages/auth/login.php');
    } else {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    }
    exit;
}

// RBAC: enforce permission per action
$_attemptPerms = [
    'submit'          => 'quizzes.view',
    'get'             => 'quizzes.view',
    'history'         => 'quizzes.view',
    'quiz-scores'     => 'grades.view',
    'pending-grading' => 'quizzes.grade',
    'attempt-answers' => 'quizzes.grade',
    'grade-answer'    => 'quizzes.grade',
    'finalize-grading'=> 'quizzes.grade',
];
if (isset($_attemptPerms[$action]) && !Auth::can($_attemptPerms[$action])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => "Permission denied: {$_attemptPerms[$action]}"]);
    exit;
}

switch ($action) {
    case 'submit':
        submitQuiz();
        break;
    case 'get':
        header('Content-Type: application/json');
        getAttempt();
        break;
    case 'history':
        header('Content-Type: application/json');
        getHistory();
        break;
    case 'quiz-scores':
        header('Content-Type: application/json');
        getQuizScores();
        break;
    case 'pending-grading':
        header('Content-Type: application/json');
        getPendingGrading();
        break;
    case 'attempt-answers':
        header('Content-Type: application/json');
        getAttemptAnswers();
        break;
    case 'grade-answer':
        header('Content-Type: application/json');
        gradeAnswer();
        break;
    case 'finalize-grading':
        header('Content-Type: application/json');
        finalizeGrading();
        break;
    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Submit quiz answers and calculate score
 */
function submitQuiz() {
    header('Content-Type: application/json');
    $userId = Auth::id();

    // Support both JSON body and form POST
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $quizId = (int)($input['quiz_id'] ?? 0);
        $answers = $input['answers'] ?? [];
        $timeTaken = (int)($input['time_taken'] ?? 0);
    } else {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $answers = $_POST['answers'] ?? [];
        $timeTaken = (int)($_POST['time_taken'] ?? 0);
    }

    if (!$quizId) {
        echo json_encode(['success' => false, 'message' => 'Invalid quiz']);
        return;
    }

    try {
        $quiz = db()->fetchOne(
            "SELECT q.*, s.subject_id FROM quiz q
             JOIN subject s ON q.subject_id = s.subject_id
             WHERE q.quiz_id = ? AND q.status = 'published'",
            [$quizId]
        );

        if (!$quiz) {
            echo json_encode(['success' => false, 'message' => 'Quiz not found']);
            return;
        }

        $enrollment = db()->fetchOne(
            "SELECT ss.* FROM student_subject ss
             JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
             WHERE ss.user_student_id = ? AND so.subject_id = ? AND ss.status = 'enrolled'",
            [$userId, $quiz['subject_id']]
        );

        if (!$enrollment) {
            echo json_encode(['success' => false, 'message' => 'Not enrolled in this subject']);
            return;
        }

        $attemptCount = db()->fetchOne(
            "SELECT COUNT(*) as count FROM student_quiz_attempts WHERE quiz_id = ? AND user_student_id = ?",
            [$quizId, $userId]
        )['count'] ?? 0;

        // Unlimited attempts — no attempt limit check

        // Get questions from `questions` table via `quiz_questions` junction
        $questions = db()->fetchAll(
            "SELECT q.questions_id, q.question_text, q.question_type, q.points,
                    (SELECT option_id FROM question_option WHERE quiz_question_id = q.questions_id AND is_correct = 1 LIMIT 1) as correct_option_id
             FROM quiz_questions qq
             JOIN questions q ON qq.questions_id = q.questions_id
             WHERE qq.quiz_id = ?",
            [$quizId]
        );

        $totalPoints = 0;
        $earnedPoints = 0;
        $answerRecords = [];

        $hasPendingGrades = false;

        foreach ($questions as $q) {
            $totalPoints += (int)$q['points'];
            $userAnswer = $answers[$q['questions_id']] ?? null;
            $isCorrect = false;
            $pointsEarned = 0;
            $gradingStatus = 'auto_graded';
            $answerText = null;

            if (in_array($q['question_type'], ['multiple_choice', 'true_false'])) {
                $isCorrect = ($userAnswer !== null && $userAnswer == $q['correct_option_id']);
                if ($isCorrect) {
                    $pointsEarned = (int)$q['points'];
                    $earnedPoints += $pointsEarned;
                }
            } elseif (in_array($q['question_type'], ['essay', 'short_answer'])) {
                $answerText = is_string($userAnswer) ? trim($userAnswer) : '';
                // Get expected/model answer
                $expectedOpt = db()->fetchOne(
                    "SELECT option_text FROM question_option WHERE quiz_question_id = ? AND is_correct = 1 LIMIT 1",
                    [$q['questions_id']]
                );
                $expectedAnswer = $expectedOpt ? trim($expectedOpt['option_text']) : '';

                if (!empty($answerText) && !empty($expectedAnswer)) {
                    // AI grading: compare student answer to expected answer
                    $ai = aiGradeAnswer($q['question_text'], $expectedAnswer, $answerText, (int)$q['points'], $q['question_type']);
                    $pointsEarned = $ai['score'];
                    $earnedPoints += $pointsEarned;
                    $gradingStatus = $ai['status'];
                    $isCorrect = ($pointsEarned >= $q['points']);
                    $q['_ai_feedback'] = $ai['feedback'];
                    if ($gradingStatus === 'pending') $hasPendingGrades = true;
                } elseif (empty($answerText)) {
                    $gradingStatus = 'auto_graded'; // No answer = 0 points, no pending
                } else {
                    // No model answer configured — fall back to manual
                    $gradingStatus = 'pending';
                    $hasPendingGrades = true;
                }
            } elseif ($q['question_type'] === 'fill_blank' || $q['question_type'] === 'fill_in_the_blank') {
                $answerText = is_string($userAnswer) ? trim($userAnswer) : '';
                $correctOpt = db()->fetchOne(
                    "SELECT option_text FROM question_option WHERE quiz_question_id = ? AND is_correct = 1 LIMIT 1",
                    [$q['questions_id']]
                );
                if ($correctOpt && $answerText !== '') {
                    $correct = trim($correctOpt['option_text']);
                    // Exact match (case-insensitive)
                    if (strtolower($answerText) === strtolower($correct)) {
                        $isCorrect = true;
                        $pointsEarned = (int)$q['points'];
                        $earnedPoints += $pointsEarned;
                    } else {
                        // AI fuzzy match for minor typos / paraphrasing
                        $ai = aiGradeAnswer($q['question_text'], $correct, $answerText, (int)$q['points'], 'fill_blank');
                        $pointsEarned = $ai['score'];
                        $earnedPoints += $pointsEarned;
                        $isCorrect = ($pointsEarned >= $q['points']);
                        $gradingStatus = $ai['status'];
                        $q['_ai_feedback'] = $ai['feedback'];
                    }
                }
            }

            $answerRecords[] = [
                'questions_id'     => $q['questions_id'],
                'selected_option_id' => in_array($q['question_type'], ['essay','short_answer','fill_blank','fill_in_the_blank']) ? null : $userAnswer,
                'answer_text'      => $answerText,
                'is_correct'       => $isCorrect ? 1 : 0,
                'points_earned'    => $pointsEarned,
                'grading_status'   => $gradingStatus,
                'grader_feedback'  => $q['_ai_feedback'] ?? null
            ];
        }

        $percentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passed = $hasPendingGrades ? false : ($percentage >= $quiz['passing_rate']);

        // Use pdo() so errors propagate
        $pdo = pdo();

        $stmt = $pdo->prepare(
            "INSERT INTO student_quiz_attempts
             (quiz_id, user_student_id, attempt_number, total_points, earned_points, percentage, passed, time_spent, started_at, completed_at, status, has_pending_grades)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 'completed', ?)"
        );
        $stmt->execute([$quizId, $userId, $attemptCount + 1, $totalPoints, $earnedPoints, round($percentage, 2), $passed ? 1 : 0, $timeTaken, $hasPendingGrades ? 1 : 0]);
        $attemptId = $pdo->lastInsertId();

        // Record in student_scores
        try {
            $pdo->prepare(
                "INSERT INTO student_scores (subject_offered_id, quiz_id, raw_score, user_id, status, remedial_required, remarks)
                 VALUES (?, ?, ?, ?, 'graded', ?, ?)"
            )->execute([
                $enrollment['subject_offered_id'],
                $quizId,
                $earnedPoints,
                $userId,
                $passed ? 0 : 1,
                $passed ? 'Passed' : 'Failed - Remedial required'
            ]);
        } catch (PDOException $e) {
            error_log("student_scores insert: " . $e->getMessage());
        }

        // Get linked lesson from quiz_lessons junction
        $linkedLessonsId = null;
        $linkedLesson = db()->fetchOne(
            "SELECT ql.lessons_id, l.subject_id FROM quiz_lessons ql JOIN lessons l ON ql.lessons_id = l.lessons_id WHERE ql.quiz_id = ? LIMIT 1",
            [$quizId]
        );
        if ($linkedLesson) {
            $linkedLessonsId = $linkedLesson['lessons_id'];
        }

        $remedialId = null;

        if ($passed) {
            // Auto-complete the linked lesson
            if ($linkedLessonsId) {
                $existingProgress = db()->fetchOne(
                    "SELECT progress_id FROM student_progress WHERE user_student_id = ? AND lessons_id = ?",
                    [$userId, $linkedLessonsId]
                );
                try {
                    if ($existingProgress) {
                        $pdo->prepare("UPDATE student_progress SET status='completed', completed_at=NOW() WHERE user_student_id=? AND lessons_id=?")
                            ->execute([$userId, $linkedLessonsId]);
                    } else {
                        $pdo->prepare("INSERT INTO student_progress (user_student_id, lessons_id, subject_id, status, completed_at, started_at) VALUES (?,?,?,'completed',NOW(),NOW())")
                            ->execute([$userId, $linkedLessonsId, $linkedLesson['subject_id']]);
                    }
                } catch (PDOException $e) {
                    error_log("Auto-complete lesson failed: " . $e->getMessage());
                }
            }
            // Close any open remedials for this quiz
            try {
                $pdo->prepare(
                    "UPDATE remedial_assignment SET status='completed', new_score=?, completed_at=NOW(),
                     remarks=CONCAT(IFNULL(remarks,''), ' | Passed on retake with ', ?, '%')
                     WHERE user_student_id=? AND quiz_id=? AND status IN ('pending','in_progress')"
                )->execute([$earnedPoints, round($percentage, 1), $userId, $quizId]);
            } catch (PDOException $e) {
                error_log("Auto-remedial completion failed: " . $e->getMessage());
            }
        }

        // On fail: auto-create remedial (always — even if essay grades still pending)
        if (!$passed || $hasPendingGrades) {
            // Check if student truly failed based on objective points alone when pending grades exist
            $likelyFailed = $hasPendingGrades
                ? ($earnedPoints / max($totalPoints, 1) * 100 < $quiz['passing_rate'])  // failed on obj questions alone
                : !$passed;

            if ($likelyFailed) {
                $existingRemedial = db()->fetchOne(
                    "SELECT remedial_id FROM remedial_assignment
                     WHERE user_student_id = ? AND quiz_id = ? AND status IN ('pending', 'in_progress')",
                    [$userId, $quizId]
                );
                if (!$existingRemedial) {
                    try {
                        $reason = $hasPendingGrades
                            ? 'Scored ' . round($percentage, 1) . '% on objective questions (passing: ' . $quiz['passing_rate'] . '%) — essay answers pending review'
                            : 'Failed quiz with ' . round($percentage, 1) . '% (passing: ' . $quiz['passing_rate'] . '%)';
                        $pdo->prepare(
                            "INSERT INTO remedial_assignment (user_student_id, quiz_id, attempt_id, assigned_by, reason, due_date, status)
                             VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 'pending')"
                        )->execute([
                            $userId, $quizId, $attemptId,
                            $quiz['user_teacher_id'] ?? 0,
                            $reason
                        ]);
                        $remedialId = $pdo->lastInsertId();
                    } catch (PDOException $e) {
                        error_log("Auto-remedial creation failed: " . $e->getMessage());
                    }
                } else {
                    $remedialId = $existingRemedial['remedial_id'];
                }
            }
        }

        // Save individual answers
        foreach ($answerRecords as $record) {
            try {
                $pdo->prepare(
                    "INSERT INTO student_quiz_answers
                     (attempt_id, quiz_id, questions_id, user_student_id, selected_option_id, answer_text, is_correct, points_earned, grading_status, grader_feedback)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $attemptId, $quizId, $record['questions_id'], $userId,
                    $record['selected_option_id'], $record['answer_text'],
                    $record['is_correct'], $record['points_earned'], $record['grading_status'],
                    $record['grader_feedback'] ?? null
                ]);
            } catch (PDOException $e) {
                error_log("Answer save: " . $e->getMessage());
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Quiz submitted',
            'data' => [
                'attempt_id'   => $attemptId,
                'percentage'   => round($percentage, 1),
                'passed'       => $passed,
                'earned_points'=> $earnedPoints,
                'total_points' => $totalPoints,
                'lessons_id'   => $linkedLessonsId,
                'remedial_id'  => $remedialId
            ]
        ]);

    } catch (Exception $e) {
        error_log("Quiz submit error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error submitting quiz: ' . $e->getMessage()]);
    }
}

/**
 * Get attempt details
 */
function getAttempt() {
    $attemptId = $_GET['id'] ?? 0;
    $userId = Auth::id();
    
    try {
        // FIXED: Column 'passing_rate'
        $attempt = db()->fetchOne(
            "SELECT qa.*, q.quiz_title as title, q.passing_rate,
                    s.subject_code, s.subject_name
             FROM student_quiz_attempts qa
             JOIN quiz q ON qa.quiz_id = q.quiz_id
             JOIN subject s ON q.subject_id = s.subject_id
             WHERE qa.attempt_id = ? AND qa.user_student_id = ?",
            [$attemptId, $userId]
        );
        
        if (!$attempt) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Attempt not found']);
            return;
        }
        
        echo json_encode(['success' => true, 'data' => $attempt]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get user's attempt history
 */
function getHistory() {
    $quizId = $_GET['quiz_id'] ?? 0;
    $userId = Auth::id();
    
    try {
        $attempts = db()->fetchAll(
            "SELECT attempt_id, percentage as score, time_spent as time_taken, completed_at as submitted_at
             FROM student_quiz_attempts
             WHERE quiz_id = ? AND user_student_id = ?
             ORDER BY completed_at DESC",
            [$quizId, $userId]
        );
        
        echo json_encode(['success' => true, 'data' => $attempts, 'count' => count($attempts)]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get all scores for a quiz (instructor gradebook)
 */
function getQuizScores() {
    $quizId = $_GET['quiz_id'] ?? 0;

    if (!$quizId) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }

    try {
        $scores = db()->fetchAll(
            "SELECT sqa.attempt_id, sqa.attempt_number, sqa.total_points, sqa.earned_points,
                    sqa.percentage, sqa.passed, sqa.completed_at,
                    u.first_name, u.last_name, u.student_id
             FROM student_quiz_attempts sqa
             JOIN users u ON sqa.user_student_id = u.users_id
             WHERE sqa.quiz_id = ? AND sqa.status = 'completed'
             ORDER BY u.last_name, u.first_name, sqa.attempt_number",
            [$quizId]
        );
        echo json_encode(['success' => true, 'data' => $scores]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

// ─── Essay/Subjective Grading Endpoints ─────────────────────

/**
 * Get attempts with pending essay grades for instructor
 */
function getPendingGrading() {
    Auth::requireRole('instructor');
    $userId = Auth::id();
    $subjectId = $_GET['subject_id'] ?? '';

    $where = '';
    $params = [$userId];
    if ($subjectId) {
        $where = 'AND q.subject_id = ?';
        $params[] = $subjectId;
    }

    $attempts = db()->fetchAll(
        "SELECT sqa.attempt_id, sqa.quiz_id, sqa.earned_points, sqa.total_points, sqa.percentage,
                sqa.completed_at, sqa.has_pending_grades,
                q.quiz_title, s.subject_code, s.subject_name,
                u.first_name, u.last_name, u.student_id,
                (SELECT COUNT(*) FROM student_quiz_answers a
                 JOIN questions q2 ON a.questions_id = q2.questions_id
                 WHERE a.attempt_id = sqa.attempt_id
                 AND q2.question_type IN ('essay','short_answer','fill_blank','fill_in_the_blank')
                 AND a.grading_status = 'pending') as pending_count,
                (SELECT COUNT(*) FROM student_quiz_answers a
                 JOIN questions q2 ON a.questions_id = q2.questions_id
                 WHERE a.attempt_id = sqa.attempt_id
                 AND q2.question_type IN ('essay','short_answer','fill_blank','fill_in_the_blank')
                 AND a.grading_status IN ('graded','auto_graded')) as graded_count
         FROM student_quiz_attempts sqa
         JOIN quiz q ON sqa.quiz_id = q.quiz_id
         JOIN subject s ON q.subject_id = s.subject_id
         JOIN users u ON sqa.user_student_id = u.users_id
         WHERE q.subject_id IN (
             SELECT DISTINCT so.subject_id FROM subject_offered so
             WHERE so.user_teacher_id = ?
         ) $where
         AND sqa.status = 'completed'
         AND (SELECT COUNT(*) FROM student_quiz_answers a
              JOIN questions q2 ON a.questions_id = q2.questions_id
              WHERE a.attempt_id = sqa.attempt_id
              AND q2.question_type IN ('essay','short_answer','fill_blank','fill_in_the_blank')) > 0
         ORDER BY sqa.completed_at DESC",
        $params
    );
    echo json_encode(['success' => true, 'data' => $attempts ?: []]);
}

/**
 * Get full attempt details with all answers for grading
 */
function getAttemptAnswers() {
    Auth::requireRole('instructor');
    $attemptId = (int)($_GET['attempt_id'] ?? 0);
    if (!$attemptId) {
        echo json_encode(['success' => false, 'message' => 'Attempt ID required']);
        return;
    }

    $attempt = db()->fetchOne(
        "SELECT sqa.*, q.quiz_title, q.passing_rate, s.subject_code,
                u.first_name, u.last_name, u.student_id
         FROM student_quiz_attempts sqa
         JOIN quiz q ON sqa.quiz_id = q.quiz_id
         JOIN subject s ON q.subject_id = s.subject_id
         JOIN users u ON sqa.user_student_id = u.users_id
         WHERE sqa.attempt_id = ?",
        [$attemptId]
    );
    if (!$attempt) {
        echo json_encode(['success' => false, 'message' => 'Attempt not found']);
        return;
    }

    $answers = db()->fetchAll(
        "SELECT a.*, a.student_quiz_answer_id AS answer_id,
                q.question_text, q.question_type, q.points as max_points,
                (SELECT option_text FROM question_option WHERE option_id = a.selected_option_id LIMIT 1) as selected_option_text,
                (SELECT option_text FROM question_option WHERE quiz_question_id = q.questions_id AND is_correct = 1 LIMIT 1) as correct_answer_text
         FROM student_quiz_answers a
         JOIN questions q ON a.questions_id = q.questions_id
         WHERE a.attempt_id = ?
         ORDER BY q.question_order, q.questions_id",
        [$attemptId]
    );

    echo json_encode(['success' => true, 'data' => ['attempt' => $attempt, 'answers' => $answers ?: []]]);
}

/**
 * Grade a single answer (essay/short answer)
 */
function gradeAnswer() {
    Auth::requireRole('instructor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POST required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $answerId = (int)($data['answer_id'] ?? 0);
    $pointsEarned = (float)($data['points_earned'] ?? 0);
    $feedback = trim($data['feedback'] ?? '');

    if (!$answerId) {
        echo json_encode(['success' => false, 'message' => 'Answer ID required']);
        return;
    }

    try {
        pdo()->prepare(
            "UPDATE student_quiz_answers
             SET points_earned = ?, grading_status = 'graded', grader_feedback = ?,
                 graded_by = ?, graded_at = NOW()
             WHERE student_quiz_answer_id = ?"
        )->execute([$pointsEarned, $feedback ?: null, Auth::id(), $answerId]);

        echo json_encode(['success' => true, 'message' => 'Answer graded']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to grade answer']);
    }
}

/**
 * Finalize grading: recalculate attempt score after all essays graded
 */
function finalizeGrading() {
    Auth::requireRole('instructor');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POST required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $attemptId = (int)($data['attempt_id'] ?? 0);
    if (!$attemptId) {
        echo json_encode(['success' => false, 'message' => 'Attempt ID required']);
        return;
    }

    try {
        // Recalculate total earned points
        $totals = db()->fetchOne(
            "SELECT SUM(points_earned) as earned FROM student_quiz_answers WHERE attempt_id = ?",
            [$attemptId]
        );
        $earnedPoints = (float)($totals['earned'] ?? 0);

        $attempt = db()->fetchOne(
            "SELECT sqa.*, q.passing_rate, q.user_teacher_id, sqa.user_student_id, sqa.quiz_id
             FROM student_quiz_attempts sqa
             JOIN quiz q ON sqa.quiz_id = q.quiz_id
             WHERE sqa.attempt_id = ?",
            [$attemptId]
        );
        if (!$attempt) {
            echo json_encode(['success' => false, 'message' => 'Attempt not found']);
            return;
        }

        $totalPoints = (int)$attempt['total_points'];
        $percentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passed = $percentage >= $attempt['passing_rate'];

        pdo()->prepare(
            "UPDATE student_quiz_attempts
             SET earned_points = ?, percentage = ?, passed = ?, has_pending_grades = 0
             WHERE attempt_id = ?"
        )->execute([$earnedPoints, round($percentage, 2), $passed ? 1 : 0, $attemptId]);

        // Auto-close remedial if student now passes after manual/AI grading
        if ($passed) {
            try {
                pdo()->prepare(
                    "UPDATE remedial_assignment SET status='completed', new_score=?, completed_at=NOW(),
                     remarks=CONCAT(IFNULL(remarks,''), ' | Passed after grading with ', ?, '%')
                     WHERE user_student_id=? AND quiz_id=? AND status IN ('pending','in_progress')"
                )->execute([$earnedPoints, round($percentage, 1), $attempt['user_student_id'], $attempt['quiz_id']]);
            } catch (PDOException $e) {
                error_log("Auto-remedial close on finalize: " . $e->getMessage());
            }
        }

        // Handle remedial logic now
        if (!$passed) {
            $existingRemedial = db()->fetchOne(
                "SELECT remedial_id FROM remedial_assignment
                 WHERE user_student_id = ? AND quiz_id = ? AND status IN ('pending', 'in_progress')",
                [$attempt['user_student_id'], $attempt['quiz_id']]
            );
            if (!$existingRemedial) {
                try {
                    pdo()->prepare(
                        "INSERT INTO remedial_assignment (user_student_id, quiz_id, attempt_id, assigned_by, reason, due_date, status)
                         VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), 'pending')"
                    )->execute([
                        $attempt['user_student_id'], $attempt['quiz_id'], $attemptId,
                        $attempt['user_teacher_id'] ?? Auth::id(),
                        'Failed quiz with ' . round($percentage, 1) . '% (passing: ' . $attempt['passing_rate'] . '%)'
                    ]);
                } catch (PDOException $e) {
                    error_log("Remedial after grading: " . $e->getMessage());
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Grading finalized',
            'data' => ['percentage' => round($percentage, 1), 'passed' => $passed, 'earned_points' => $earnedPoints]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to finalize grading']);
    }
}

// ── AI-powered grading for essay / short_answer / fill_blank ────────────────

function aiGradeAnswer($questionText, $expectedAnswer, $studentAnswer, $maxPoints, $questionType) {
    $fallback = ['score' => 0, 'feedback' => '', 'status' => 'pending'];

    $keySetting = db()->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'groq_api_key'");
    if (!$keySetting || empty($keySetting['setting_value'])) return $fallback;
    $apiKey = $keySetting['setting_value'];

    // Use a more capable model for grading accuracy
    $model = 'llama-3.3-70b-versatile';
    $modelSetting = db()->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'ai_model'");
    if ($modelSetting && !empty($modelSetting['setting_value'])) {
        $model = $modelSetting['setting_value'];
    }

    $studentText = trim($studentAnswer);

    // Hard rules BEFORE calling AI — saves tokens and ensures reliability
    if (empty($studentText)) {
        return ['score' => 0, 'feedback' => 'No answer was provided.', 'status' => 'auto_graded'];
    }

    // If student answer is suspiciously short compared to question complexity
    $minLengthForEssay = 20; // characters
    if ($questionType === 'essay' && strlen($studentText) < $minLengthForEssay) {
        return ['score' => 0, 'feedback' => 'Answer is too short to receive credit. Please provide a complete response.', 'status' => 'auto_graded'];
    }

    if ($questionType === 'essay') {
        $typeInstruction = "ESSAY question. A valid essay MUST: (1) directly address the question topic, (2) include specific concepts, explanations or examples, (3) be written in complete sentences with substance. Random text, nonsensical phrases, or off-topic answers = 0 points.";
    } elseif ($questionType === 'short_answer') {
        $typeInstruction = "SHORT ANSWER question. Award full points if the core concept is correct. Off-topic, vague, or nonsensical answers = 0 points. Partial credit for partially correct answers.";
    } elseif ($questionType === 'fill_blank' || $questionType === 'fill_in_the_blank') {
        $typeInstruction = "FILL IN THE BLANK. Accept only the correct word/phrase or clear synonyms. Random text = 0 points.";
    } else {
        $typeInstruction = "Grade proportionally. Off-topic or nonsensical answers = 0 points.";
    }

    $expectedSection = !empty($expectedAnswer)
        ? "Model Answer / Key Points: {$expectedAnswer}"
        : "No model answer provided — grade based on whether the student's answer correctly addresses the question topic.";

    $prompt = <<<PROMPT
You are a STRICT and ACCURATE educational grader. Your job is to evaluate ONLY what the student actually wrote — never assume or add content they did not write.

QUESTION: {$questionText}

{$expectedSection}

STUDENT'S EXACT ANSWER (read this carefully word by word):
"{$studentText}"

MAXIMUM POINTS: {$maxPoints}
TYPE: {$typeInstruction}

GRADING STEPS — follow in order:
1. Read the student's answer exactly as written above.
2. Ask: Does this answer actually address the question topic? If NO → score 0.
3. Ask: Does it contain correct, relevant information matching the expected answer?
4. Score proportionally: full points for fully correct, partial for partial, 0 for wrong/irrelevant/gibberish.
5. NEVER award points for vague filler phrases like "make it accurate", "I don't know", random words, or text that does not relate to the question.

Return ONLY valid JSON:
{"score": <number 0 to {$maxPoints}>, "feedback": "<1 direct sentence to the student explaining the score>"}
PROMPT;

    $payload = [
        'model'    => $model,
        'messages' => [
            [
                'role'    => 'system',
                'content' => 'You are a strict educational grader. You must evaluate only what is literally written in the student answer. Never assume content. Never give points for off-topic or gibberish answers. Respond only with valid JSON.'
            ],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens'  => 200,
        'temperature' => 0.0
    ];

    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 20
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return $fallback;

    $data    = json_decode($response, true);
    $content = trim($data['choices'][0]['message']['content'] ?? '');

    // Extract JSON from response (in case there is extra text)
    if (preg_match('/\{[^}]+\}/s', $content, $m)) $content = $m[0];
    $result = json_decode($content, true);

    if (!$result || !array_key_exists('score', $result)) return $fallback;

    $score = max(0, min($maxPoints, round(floatval($result['score']), 1)));
    return [
        'score'    => $score,
        'feedback' => $result['feedback'] ?? '',
        'status'   => 'auto_graded'
    ];
}