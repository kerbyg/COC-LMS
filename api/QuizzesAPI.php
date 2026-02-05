<?php
/**
 * ============================================================
 * CIT-LMS Quizzes API
 * ============================================================
 * Endpoints:
 *   GET ?action=list&subject_id=X  - Get quizzes for subject
 *   GET ?action=get&id=X           - Get single quiz details
 *   GET ?action=questions&id=X     - Get quiz questions (for taking)
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        getQuizzes();
        break;
    case 'get':
        getQuiz();
        break;
    case 'questions':
        getQuestions();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get quizzes for a subject
 */
function getQuizzes() {
    $subjectOfferingId = $_GET['subject_id'] ?? 0;
    $userId = Auth::id();
    
    if (!$subjectOfferingId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Subject ID required']);
        return;
    }
    
    try {
        $quizzes = db()->fetchAll(
            "SELECT
                q.quiz_id,
                q.quiz_title,
                q.quiz_description,
                q.time_limit,
                q.passing_rate,
                q.max_attempts,
                q.due_date,
                q.created_at,
                (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
                (SELECT COUNT(*) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ?) as attempts_used,
                (SELECT MAX(score) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ?) as best_score,
                (SELECT attempt_id FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ? ORDER BY score DESC LIMIT 1) as best_attempt_id
            FROM quiz q
            WHERE q.subject_id = ? AND q.status = 'published'
            ORDER BY q.due_date ASC, q.created_at ASC",
            [$userId, $userId, $userId, $subjectOfferingId]
        );
        
        // Add status to each quiz
        foreach ($quizzes as &$quiz) {
            $now = time();
            $dueDate = $quiz['due_date'] ? strtotime($quiz['due_date']) : null;
            $isOverdue = $dueDate && $now > $dueDate;
            $hasPassed = $quiz['best_score'] >= $quiz['passing_rate'];
            
            if ($hasPassed) {
                $quiz['status'] = 'passed';
            } elseif ($quiz['attempts_used'] > 0) {
                $quiz['status'] = 'attempted';
            } elseif ($isOverdue) {
                $quiz['status'] = 'overdue';
            } else {
                $quiz['status'] = 'available';
            }
            
            $quiz['can_take'] = !$isOverdue && ($quiz['max_attempts'] - $quiz['attempts_used']) > 0;
            $quiz['attempts_remaining'] = $quiz['max_attempts'] - $quiz['attempts_used'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $quizzes,
            'count' => count($quizzes)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get single quiz details
 */
function getQuiz() {
    $quizId = $_GET['id'] ?? 0;
    $userId = Auth::id();
    
    if (!$quizId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Quiz ID required']);
        return;
    }
    
    try {
        $quiz = db()->fetchOne(
            "SELECT
                q.*,
                s.subject_code,
                s.subject_name,
                (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
                (SELECT SUM(points) FROM quiz_questions WHERE quiz_id = q.quiz_id) as total_points,
                (SELECT COUNT(*) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ?) as attempts_used,
                (SELECT MAX(score) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ?) as best_score
            FROM quiz q
            JOIN subject s ON q.subject_id = s.subject_id
            WHERE q.quiz_id = ? AND q.status = 'published'",
            [$userId, $userId, $quizId]
        );
        
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Quiz not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $quiz
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get quiz questions for taking (without correct answers)
 */
function getQuestions() {
    $quizId = $_GET['id'] ?? 0;
    $userId = Auth::id();
    
    if (!$quizId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Quiz ID required']);
        return;
    }
    
    try {
        // Verify quiz exists and student is enrolled
        $quiz = db()->fetchOne(
            "SELECT q.*, e.enrollment_id
             FROM quiz q
             JOIN student_subject e ON e.subject_id = q.subject_id
             WHERE q.quiz_id = ? AND e.user_student_id = ? AND e.status = 'enrolled' AND q.status = 'published'",
            [$quizId, $userId]
        );
        
        if (!$quiz) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Quiz not found or not enrolled']);
            return;
        }
        
        // Check attempts
        $attemptCount = db()->fetchOne(
            "SELECT COUNT(*) as count FROM student_quiz_attempts WHERE quiz_id = ? AND user_student_id = ?",
            [$quizId, $userId]
        )['count'] ?? 0;
        
        if ($attemptCount >= $quiz['max_attempts']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No attempts remaining']);
            return;
        }
        
        // Get questions (shuffle if enabled)
        $orderBy = !empty($quiz['is_randomized']) ? "RAND()" : "order_number";
        $questions = db()->fetchAll(
            "SELECT questions_id, question_text, question_type, points
             FROM quiz_questions
             WHERE quiz_id = ?
             ORDER BY $orderBy",
            [$quizId]
        );
        
        // Get choices (without is_correct flag)
        foreach ($questions as &$q) {
            $choiceOrder = !empty($quiz['is_randomized']) ? "RAND()" : "option_order";
            $q['choices'] = db()->fetchAll(
                "SELECT option_id, option_text
                 FROM question_option
                 WHERE questions_id = ?
                 ORDER BY $choiceOrder",
                [$q['questions_id']]
            );
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'quiz' => [
                    'quiz_id' => $quiz['quiz_id'],
                    'title' => $quiz['quiz_title'],
                    'time_limit' => $quiz['time_limit'],
                    'passing_rate' => $quiz['passing_rate']
                ],
                'questions' => $questions
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}