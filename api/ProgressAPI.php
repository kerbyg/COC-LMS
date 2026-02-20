<?php
/**
 * CIT-LMS Progress API
 * Student progress, grades, and quiz history
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
    case 'grades': getGrades(); break;
    case 'subject-progress': getSubjectProgress(); break;
    case 'student-quizzes': getStudentQuizzes(); break;
    case 'quiz-result': getQuizResult(); break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get grades overview - all quiz scores grouped by subject
 */
function getGrades() {
    $userId = Auth::id();

    try {
        // Get enrolled subjects
        $subjects = db()->fetchAll(
            "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name
             FROM student_subject ss
             JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
             JOIN subject s ON so.subject_id = s.subject_id
             WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
             ORDER BY s.subject_code",
            [$userId]
        );

        $result = [];
        foreach ($subjects as $subj) {
            $quizzes = db()->fetchAll(
                "SELECT q.quiz_id, q.quiz_title, q.quiz_type, q.passing_rate,
                    (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) as question_count,
                    (SELECT MAX(sqa.percentage) FROM student_quiz_attempts sqa
                     WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed') as best_score,
                    (SELECT sqa.passed FROM student_quiz_attempts sqa
                     WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed'
                     ORDER BY sqa.percentage DESC LIMIT 1) as passed,
                    (SELECT COUNT(*) FROM student_quiz_attempts sqa
                     WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed') as attempts
                 FROM quiz q
                 WHERE q.subject_id = ? AND q.status = 'published'
                 ORDER BY q.created_at",
                [$userId, $userId, $userId, $subj['subject_id']]
            );

            $subj['quizzes'] = $quizzes;
            $scores = array_filter(array_column($quizzes, 'best_score'), fn($s) => $s !== null);
            $subj['avg_score'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : null;
            $result[] = $subj;
        }

        echo json_encode(['success' => true, 'data' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get detailed progress for a single subject (lessons + quizzes)
 */
function getSubjectProgress() {
    $userId = Auth::id();
    $subjectId = $_GET['subject_id'] ?? 0;

    if (!$subjectId) {
        echo json_encode(['success' => false, 'message' => 'Subject ID required']);
        return;
    }

    try {
        // Subject info
        $subject = db()->fetchOne(
            "SELECT s.subject_id, s.subject_code, s.subject_name
             FROM subject s WHERE s.subject_id = ?",
            [$subjectId]
        );

        // Lessons with completion
        $lessons = db()->fetchAll(
            "SELECT l.lessons_id, l.lesson_title, l.lesson_order, l.lesson_description,
                CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END as is_completed,
                sp.completed_at
             FROM lessons l
             LEFT JOIN student_progress sp ON l.lessons_id = sp.lessons_id AND sp.user_student_id = ?
             WHERE l.subject_id = ? AND l.status = 'published'
             ORDER BY l.lesson_order",
            [$userId, $subjectId]
        );

        // Quizzes with best scores + attempt count + earned/total points
        $quizzes = db()->fetchAll(
            "SELECT q.quiz_id, q.quiz_title, q.quiz_type, q.passing_rate, q.max_attempts, q.time_limit,
                (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) as question_count,
                (SELECT MAX(sqa.percentage) FROM student_quiz_attempts sqa
                 WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed') as best_score,
                (SELECT sqa.earned_points FROM student_quiz_attempts sqa
                 WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed'
                 ORDER BY sqa.percentage DESC LIMIT 1) as best_earned,
                (SELECT sqa.total_points FROM student_quiz_attempts sqa
                 WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed'
                 ORDER BY sqa.percentage DESC LIMIT 1) as best_total,
                (SELECT COUNT(*) FROM student_quiz_attempts sqa
                 WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed') as attempts_used,
                (SELECT sqa.completed_at FROM student_quiz_attempts sqa
                 WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed'
                 ORDER BY sqa.percentage DESC LIMIT 1) as best_attempt_date
             FROM quiz q
             WHERE q.subject_id = ? AND q.status = 'published'
             ORDER BY q.quiz_type, q.created_at",
            [$userId, $userId, $userId, $userId, $userId, $subjectId]
        );

        $completedLessons = count(array_filter($lessons, fn($l) => $l['is_completed']));
        $totalLessons = count($lessons);
        $quizzesAttempted = count(array_filter($quizzes, fn($q) => $q['best_score'] !== null));
        $quizzesPassed = count(array_filter($quizzes, fn($q) => $q['best_score'] !== null && (float)$q['best_score'] >= (float)$q['passing_rate']));
        $scores = array_filter(array_column($quizzes, 'best_score'), fn($s) => $s !== null);
        $avgScore = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : null;

        // Overall progress: weight lessons 50%, quizzes 50%
        $lessonPct = $totalLessons > 0 ? ($completedLessons / $totalLessons) * 100 : 0;
        $quizPct = count($quizzes) > 0 ? ($quizzesPassed / count($quizzes)) * 100 : 0;
        $overallProgress = $totalLessons > 0 && count($quizzes) > 0
            ? round(($lessonPct + $quizPct) / 2)
            : round($lessonPct > 0 ? $lessonPct : $quizPct);

        echo json_encode([
            'success' => true,
            'data' => [
                'subject' => $subject,
                'lessons' => $lessons,
                'quizzes' => $quizzes,
                'completed_lessons' => $completedLessons,
                'total_lessons' => $totalLessons,
                'quizzes_attempted' => $quizzesAttempted,
                'quizzes_passed' => $quizzesPassed,
                'total_quizzes' => count($quizzes),
                'avg_score' => $avgScore,
                'lesson_progress' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0,
                'progress' => $overallProgress
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get all quizzes for a student across all enrolled subjects
 */
function getStudentQuizzes() {
    $userId = Auth::id();
    $subjectId = $_GET['subject_id'] ?? '';

    try {
        $where = '';
        $params = [$userId, $userId, $userId, $userId];
        if ($subjectId) {
            $where = 'AND q.subject_id = ?';
            $params[] = $subjectId;
        }

        $quizzes = db()->fetchAll(
            "SELECT q.quiz_id, q.quiz_title, q.quiz_description, q.quiz_type, q.time_limit,
                q.passing_rate, q.max_attempts, q.subject_id,
                s.subject_code, s.subject_name,
                (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) as question_count,
                (SELECT COUNT(*) FROM student_quiz_attempts sqa
                 WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed') as attempts_used,
                (SELECT MAX(sqa.percentage) FROM student_quiz_attempts sqa
                 WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed') as best_score,
                (SELECT sqa.passed FROM student_quiz_attempts sqa
                 WHERE sqa.quiz_id = q.quiz_id AND sqa.user_student_id = ? AND sqa.status = 'completed'
                 ORDER BY sqa.percentage DESC LIMIT 1) as passed
             FROM quiz q
             JOIN subject s ON q.subject_id = s.subject_id
             JOIN subject_offered so ON so.subject_id = s.subject_id
             JOIN student_subject ss ON ss.subject_offered_id = so.subject_offered_id
             WHERE ss.user_student_id = ? AND ss.status = 'enrolled' AND q.status = 'published'
             $where
             GROUP BY q.quiz_id
             ORDER BY s.subject_code, q.quiz_type, q.created_at",
            $params
        );

        // Compute status
        foreach ($quizzes as &$quiz) {
            $quiz['attempts_remaining'] = max(0, $quiz['max_attempts'] - $quiz['attempts_used']);
            if ($quiz['passed']) $quiz['quiz_status'] = 'passed';
            elseif ($quiz['attempts_used'] > 0) $quiz['quiz_status'] = 'attempted';
            else $quiz['quiz_status'] = 'available';
            $quiz['can_take'] = $quiz['attempts_remaining'] > 0;
        }

        echo json_encode(['success' => true, 'data' => $quizzes]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get detailed quiz result with answers
 */
function getQuizResult() {
    $attemptId = $_GET['attempt_id'] ?? 0;
    $userId = Auth::id();

    if (!$attemptId) {
        echo json_encode(['success' => false, 'message' => 'Attempt ID required']);
        return;
    }

    try {
        $attempt = db()->fetchOne(
            "SELECT sqa.*, q.quiz_title, q.passing_rate, q.show_answers, q.quiz_type,
                s.subject_code, s.subject_name
             FROM student_quiz_attempts sqa
             JOIN quiz q ON sqa.quiz_id = q.quiz_id
             JOIN subject s ON q.subject_id = s.subject_id
             WHERE sqa.attempt_id = ? AND sqa.user_student_id = ?",
            [$attemptId, $userId]
        );

        if (!$attempt) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Attempt not found']);
            return;
        }

        // Get answers with correct option info if show_answers is enabled
        $answers = [];
        if (!empty($attempt['show_answers'])) {
            $answers = db()->fetchAll(
                "SELECT sa.questions_id, sa.selected_option_id, sa.is_correct, sa.points_earned,
                    qq.question_text, qq.question_type, qq.points,
                    (SELECT option_id FROM question_option WHERE questions_id = qq.questions_id AND is_correct = 1 LIMIT 1) as correct_option_id
                 FROM student_quiz_answers sa
                 JOIN quiz_questions qq ON sa.questions_id = qq.questions_id
                 WHERE sa.attempt_id = ? AND sa.user_student_id = ?
                 ORDER BY qq.order_number",
                [$attemptId, $userId]
            );

            foreach ($answers as &$a) {
                $a['options'] = db()->fetchAll(
                    "SELECT option_id, option_text, is_correct FROM question_option WHERE questions_id = ? ORDER BY option_order",
                    [$a['questions_id']]
                );
            }
        }

        // Get linked lesson (via quiz_lessons) and open remedial (if failed)
        $linkedLessonsId = null;
        $linkedLessonTitle = null;
        $remedialId = null;

        $linkedLesson = db()->fetchOne(
            "SELECT ql.lessons_id, l.lesson_title FROM quiz_lessons ql JOIN lessons l ON ql.lessons_id = l.lessons_id WHERE ql.quiz_id = ? LIMIT 1",
            [$attempt['quiz_id']]
        );
        if ($linkedLesson) {
            $linkedLessonsId = $linkedLesson['lessons_id'];
            $linkedLessonTitle = $linkedLesson['lesson_title'];
        }

        if (!$attempt['passed']) {
            $remedial = db()->fetchOne(
                "SELECT remedial_id FROM remedial_assignment WHERE user_student_id = ? AND quiz_id = ? AND status IN ('pending','in_progress') LIMIT 1",
                [$userId, $attempt['quiz_id']]
            );
            if ($remedial) {
                $remedialId = $remedial['remedial_id'];
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'attempt'       => $attempt,
                'answers'       => $answers,
                'lessons_id'    => $linkedLessonsId,
                'lesson_title'  => $linkedLessonTitle,
                'remedial_id'   => $remedialId
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
