<?php
/**
 * ============================================================
 * CIT-LMS Quiz Attempts API
 * Fixed for schema: student_quiz_attempts, quiz_questions, student_quiz_answers
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

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
    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Submit quiz answers and calculate score
 */
function submitQuiz() {
    $userId = Auth::id();
    $quizId = $_POST['quiz_id'] ?? 0;
    $answers = $_POST['answers'] ?? [];
    $timeTaken = $_POST['time_taken'] ?? 0;
    
    if (!$quizId) {
        $_SESSION['error'] = 'Invalid quiz';
        header('Location: ' . BASE_URL . '/pages/student/my-subjects.php');
        exit;
    }
    
    try {
        // 1. FIXED: Get quiz info with column 'passing_rate'
        $quiz = db()->fetchOne(
            "SELECT q.*, s.subject_id FROM quiz q 
             JOIN subject s ON q.subject_id = s.subject_id
             WHERE q.quiz_id = ? AND q.status = 'published'",
            [$quizId]
        );
        
        if (!$quiz) {
            $_SESSION['error'] = 'Quiz not found';
            header('Location: ' . BASE_URL . '/pages/student/my-subjects.php');
            exit;
        }
        
        // 2. FIXED: Check enrollment using 'student_subject'
        $enrollment = db()->fetchOne(
            "SELECT ss.* FROM student_subject ss
             JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
             WHERE ss.user_student_id = ? AND so.subject_id = ? AND ss.status = 'enrolled'",
            [$userId, $quiz['subject_id']]
        );
        
        if (!$enrollment) {
            $_SESSION['error'] = 'Not enrolled in this subject';
            header('Location: ' . BASE_URL . '/pages/student/my-subjects.php');
            exit;
        }
        
        // 3. Check attempts using 'user_student_id'
        $attemptCount = db()->fetchOne(
            "SELECT COUNT(*) as count FROM student_quiz_attempts WHERE quiz_id = ? AND user_student_id = ?",
            [$quizId, $userId]
        )['count'] ?? 0;

        // 4. FIXED: Get questions from 'quiz_questions' table (matches Phase 1 migration structure)
        $questions = db()->fetchAll(
            "SELECT q.questions_id, q.question_type, q.points,
                    (SELECT option_id FROM question_option WHERE quiz_question_id = q.questions_id AND is_correct = 1 LIMIT 1) as correct_option_id
             FROM quiz_questions q
             WHERE q.quiz_id = ?",
            [$quizId]
        );
        
        $totalPoints = 0;
        $earnedPoints = 0;
        $answerRecords = [];
        
        foreach ($questions as $q) {
            $totalPoints += $q['points'];
            $userAnswer = $answers[$q['questions_id']] ?? null;
            $isCorrect = false;
            $pointsEarned = 0;
            
            if ($q['question_type'] === 'multiple_choice' || $q['question_type'] === 'true_false') {
                $isCorrect = ($userAnswer == $q['correct_option_id']);
            }
            
            if ($isCorrect) {
                $pointsEarned = $q['points'];
                $earnedPoints += $pointsEarned;
            }
            
            $answerRecords[] = [
                'questions_id' => $q['questions_id'],
                'selected_option_id' => $userAnswer,
                'is_correct' => $isCorrect ? 1 : 0,
                'points_earned' => $pointsEarned
            ];
        }
        
        // 5. Calculate percentage score against 'passing_rate'
        $percentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passed = $percentage >= $quiz['passing_rate'];

        // 6. FIXED: Insert into 'student_quiz_attempts'
        db()->execute(
            "INSERT INTO student_quiz_attempts
             (quiz_id, user_student_id, attempt_number, total_points, earned_points, percentage, passed, time_spent, started_at, completed_at, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 'completed')",
            [$quizId, $userId, $attemptCount + 1, $totalPoints, $earnedPoints, $percentage, $passed ? 1 : 0, $timeTaken]
        );

        $attemptId = db()->lastInsertId();

        // 7. FIXED: Save to 'student_quiz_answers' (matches Phase 1 migration structure)
        foreach ($answerRecords as $record) {
            db()->execute(
                "INSERT INTO student_quiz_answers
                 (attempt_id, quiz_id, questions_id, user_student_id, selected_option_id, is_correct, points_earned)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$attemptId, $quizId, $record['questions_id'], $userId, $record['selected_option_id'], $record['is_correct'], $record['points_earned']]
            );
        }
        
        header('Location: ' . BASE_URL . '/pages/student/quiz-result.php?id=' . $attemptId);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        header('Location: ' . BASE_URL . '/pages/student/my-subjects.php');
        exit;
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