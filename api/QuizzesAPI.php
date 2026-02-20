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
    case 'instructor-list':
        getInstructorQuizzes();
        break;
    case 'create':
        createQuiz();
        break;
    case 'update':
        updateQuiz();
        break;
    case 'delete':
        deleteQuiz();
        break;
    case 'list-questions':
        listQuestionsForInstructor();
        break;
    case 'add-question':
        addQuestion();
        break;
    case 'update-question':
        updateQuestion();
        break;
    case 'delete-question':
        deleteQuestion();
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
                (SELECT SUM(qs.points) FROM quiz_questions qq2 JOIN questions qs ON qq2.questions_id = qs.questions_id WHERE qq2.quiz_id = q.quiz_id) as total_points,
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
            "SELECT q.*, ss.student_subject_id
             FROM quiz q
             JOIN subject_offered so ON so.subject_id = q.subject_id
             JOIN student_subject ss ON ss.subject_offered_id = so.subject_offered_id
             WHERE q.quiz_id = ? AND ss.user_student_id = ? AND ss.status = 'enrolled' AND q.status = 'published'",
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
        $orderBy = !empty($quiz['is_randomized']) ? "RAND()" : "q.question_order";
        $questions = db()->fetchAll(
            "SELECT q.questions_id, q.question_text, q.question_type, q.points
             FROM quiz_questions qq
             JOIN questions q ON qq.questions_id = q.questions_id
             WHERE qq.quiz_id = ?
             ORDER BY $orderBy",
            [$quizId]
        );

        // Get choices (without is_correct flag)
        foreach ($questions as &$q) {
            $choiceOrder = !empty($quiz['is_randomized']) ? "RAND()" : "order_number";
            $q['choices'] = db()->fetchAll(
                "SELECT option_id, option_text
                 FROM question_option
                 WHERE quiz_question_id = ?
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

// ─── Instructor Quiz CRUD ─────────────────────────────────

function getInstructorQuizzes() {
    $userId = Auth::id();
    $subjectId = $_GET['subject_id'] ?? '';
    $where = ''; $params = [$userId];
    if ($subjectId) { $where = 'AND q.subject_id = ?'; $params[] = $subjectId; }

    $quizzes = db()->fetchAll(
        "SELECT q.*, s.subject_code, s.subject_name,
            (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) as question_count,
            (SELECT COUNT(*) FROM student_quiz_attempts qa WHERE qa.quiz_id = q.quiz_id AND qa.status = 'completed') as attempt_count,
            (SELECT ROUND(AVG(qa.percentage),1) FROM student_quiz_attempts qa WHERE qa.quiz_id = q.quiz_id AND qa.status = 'completed') as avg_score
         FROM quiz q JOIN subject s ON q.subject_id = s.subject_id
         WHERE q.user_teacher_id = ? $where ORDER BY q.created_at DESC",
        $params
    );
    echo json_encode(['success' => true, 'data' => $quizzes]);
}

function createQuiz() {
    $d = json_decode(file_get_contents('php://input'), true);
    $subjectId = (int)($d['subject_id'] ?? 0); $title = trim($d['quiz_title'] ?? '');
    if (!$subjectId || !$title) { echo json_encode(['success' => false, 'message' => 'Subject and title required']); return; }
    try {
        pdo()->prepare("INSERT INTO quiz (subject_id, user_teacher_id, quiz_title, quiz_description, time_limit, passing_rate, max_attempts, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())")
            ->execute([$subjectId, Auth::id(), $title, trim($d['quiz_description']??''), (int)($d['time_limit']??30), (int)($d['passing_rate']??60), (int)($d['max_attempts']??3), $d['status']??'draft']);
        echo json_encode(['success' => true, 'message' => 'Quiz created', 'data' => ['id' => pdo()->lastInsertId()]]);
    } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Failed to create quiz']); }
}

function updateQuiz() {
    $d = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['quiz_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Quiz ID required']); return; }
    try {
        pdo()->prepare("UPDATE quiz SET quiz_title=?, quiz_description=?, time_limit=?, passing_rate=?, max_attempts=?, status=?, updated_at=NOW() WHERE quiz_id=? AND user_teacher_id=?")
            ->execute([trim($d['quiz_title']??''), trim($d['quiz_description']??''), (int)($d['time_limit']??30), (int)($d['passing_rate']??60), (int)($d['max_attempts']??3), $d['status']??'draft', $id, Auth::id()]);
        echo json_encode(['success' => true, 'message' => 'Quiz updated']);
    } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Failed']); }
}

function deleteQuiz() {
    $d = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['quiz_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Quiz ID required']); return; }
    $attempts = db()->fetchOne("SELECT COUNT(*) as c FROM student_quiz_attempts WHERE quiz_id = ?", [$id])['c'] ?? 0;
    if ($attempts > 0) { echo json_encode(['success' => false, 'message' => "Cannot delete quiz with $attempts attempt(s). Set to draft instead."]); return; }
    try {
        pdo()->prepare("DELETE FROM question_option WHERE quiz_question_id IN (SELECT questions_id FROM quiz_questions WHERE quiz_id = ?)")->execute([$id]);
        pdo()->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?")->execute([$id]);
        pdo()->prepare("DELETE FROM quiz WHERE quiz_id = ? AND user_teacher_id = ?")->execute([$id, Auth::id()]);
        echo json_encode(['success' => true, 'message' => 'Quiz deleted']);
    } catch (Exception $e) { echo json_encode(['success' => false, 'message' => 'Failed']); }
}

// ─── Instructor Question CRUD ────────────────────────────

function listQuestionsForInstructor() {
    $quizId = (int)($_GET['quiz_id'] ?? 0);
    if (!$quizId) { echo json_encode(['success' => false, 'message' => 'Quiz ID required']); return; }

    $quiz = db()->fetchOne("SELECT * FROM quiz WHERE quiz_id = ? AND user_teacher_id = ?", [$quizId, Auth::id()]);
    if (!$quiz) { echo json_encode(['success' => false, 'message' => 'Quiz not found']); return; }

    $questions = db()->fetchAll(
        "SELECT q.questions_id, q.question_text, q.question_type, q.points, q.question_order
         FROM quiz_questions qq
         JOIN questions q ON qq.questions_id = q.questions_id
         WHERE qq.quiz_id = ?
         ORDER BY q.question_order",
        [$quizId]
    );

    foreach ($questions as &$q) {
        $q['options'] = db()->fetchAll(
            "SELECT option_id, option_text, is_correct, order_number
             FROM question_option WHERE quiz_question_id = ? ORDER BY order_number",
            [$q['questions_id']]
        );
    }

    echo json_encode(['success' => true, 'data' => ['quiz' => $quiz, 'questions' => $questions]]);
}

function addQuestion() {
    $d = json_decode(file_get_contents('php://input'), true);
    $quizId = (int)($d['quiz_id'] ?? 0);
    $text = trim($d['question_text'] ?? '');
    $type = $d['question_type'] ?? 'multiple_choice';
    $points = (int)($d['points'] ?? 1);
    $options = $d['options'] ?? [];

    if (!$quizId || !$text) { echo json_encode(['success' => false, 'message' => 'Quiz ID and question text required']); return; }

    $quiz = db()->fetchOne("SELECT * FROM quiz WHERE quiz_id = ? AND user_teacher_id = ?", [$quizId, Auth::id()]);
    if (!$quiz) { echo json_encode(['success' => false, 'message' => 'Quiz not found']); return; }

    if (in_array($type, ['multiple_choice', 'true_false'])) {
        $hasCorrect = false;
        foreach ($options as $opt) { if (!empty($opt['is_correct'])) $hasCorrect = true; }
        if (!$hasCorrect) { echo json_encode(['success' => false, 'message' => 'Select at least one correct answer']); return; }
    }

    try {
        $pdo = pdo();
        $pdo->beginTransaction();

        $maxOrder = db()->fetchOne("SELECT MAX(q.question_order) as m FROM quiz_questions qq JOIN questions q ON qq.questions_id = q.questions_id WHERE qq.quiz_id = ?", [$quizId])['m'] ?? 0;

        $stmt = $pdo->prepare("INSERT INTO questions (question_text, question_type, points, question_order, users_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$text, $type, $points, $maxOrder + 1, Auth::id()]);
        $questionId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, questions_id) VALUES (?, ?)");
        $stmt->execute([$quizId, $questionId]);

        foreach ($options as $i => $opt) {
            $optText = trim($opt['option_text'] ?? '');
            if ($optText === '') continue;
            $pdo->prepare("INSERT INTO question_option (quiz_question_id, option_text, is_correct, order_number) VALUES (?, ?, ?, ?)")
                ->execute([$questionId, $optText, !empty($opt['is_correct']) ? 1 : 0, $i + 1]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Question added', 'data' => ['questions_id' => $questionId]]);
    } catch (Exception $e) {
        pdo()->rollBack();
        error_log('Add question: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add question']);
    }
}

function updateQuestion() {
    $d = json_decode(file_get_contents('php://input'), true);
    $questionId = (int)($d['questions_id'] ?? 0);
    $text = trim($d['question_text'] ?? '');
    $type = $d['question_type'] ?? 'multiple_choice';
    $points = (int)($d['points'] ?? 1);
    $options = $d['options'] ?? [];

    if (!$questionId || !$text) { echo json_encode(['success' => false, 'message' => 'Question ID and text required']); return; }

    $quiz = db()->fetchOne("SELECT q.quiz_id FROM quiz q JOIN quiz_questions qq ON q.quiz_id = qq.quiz_id WHERE qq.questions_id = ? AND q.user_teacher_id = ?", [$questionId, Auth::id()]);
    if (!$quiz) { echo json_encode(['success' => false, 'message' => 'Question not found']); return; }

    if (in_array($type, ['multiple_choice', 'true_false'])) {
        $hasCorrect = false;
        foreach ($options as $opt) { if (!empty($opt['is_correct'])) $hasCorrect = true; }
        if (!$hasCorrect) { echo json_encode(['success' => false, 'message' => 'Select at least one correct answer']); return; }
    }

    try {
        $pdo = pdo();
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE questions SET question_text=?, question_type=?, points=? WHERE questions_id=?")
            ->execute([$text, $type, $points, $questionId]);

        $pdo->prepare("DELETE FROM question_option WHERE quiz_question_id = ?")->execute([$questionId]);
        foreach ($options as $i => $opt) {
            $optText = trim($opt['option_text'] ?? '');
            if ($optText === '') continue;
            $pdo->prepare("INSERT INTO question_option (quiz_question_id, option_text, is_correct, order_number) VALUES (?, ?, ?, ?)")
                ->execute([$questionId, $optText, !empty($opt['is_correct']) ? 1 : 0, $i + 1]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Question updated']);
    } catch (Exception $e) {
        pdo()->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update question']);
    }
}

function deleteQuestion() {
    $d = json_decode(file_get_contents('php://input'), true);
    $questionId = (int)($d['questions_id'] ?? 0);
    if (!$questionId) { echo json_encode(['success' => false, 'message' => 'Question ID required']); return; }

    $quiz = db()->fetchOne("SELECT q.quiz_id FROM quiz q JOIN quiz_questions qq ON q.quiz_id = qq.quiz_id WHERE qq.questions_id = ? AND q.user_teacher_id = ?", [$questionId, Auth::id()]);
    if (!$quiz) { echo json_encode(['success' => false, 'message' => 'Question not found']); return; }

    try {
        $pdo = pdo();
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM question_option WHERE quiz_question_id = ?")->execute([$questionId]);
        $pdo->prepare("DELETE FROM quiz_questions WHERE questions_id = ?")->execute([$questionId]);
        $pdo->prepare("DELETE FROM questions WHERE questions_id = ?")->execute([$questionId]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Question deleted']);
    } catch (Exception $e) {
        pdo()->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete question']);
    }
}