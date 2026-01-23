<?php
/**
 * CIT-LMS Lessons API
 * Fixed to match actual database schema: student_subject, user_student_id, lessons
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
        getLessons();
        break;
    case 'complete':
        markComplete();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get lessons for a subject
 */
function getLessons() {
    $subjectOfferedId = $_GET['subject_id'] ?? 0;
    $userId = Auth::id();
    
    try {
        // FIXED: Table names changed to 'lessons' and 'student_progress'
        // FIXED: Column changed to 'user_student_id'
        $lessons = db()->fetchAll(
            "SELECT 
                l.lesson_id,
                l.lesson_title as title,
                l.lesson_description as description,
                l.lesson_order as order_number,
                l.created_at,
                CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END as is_completed,
                sp.completed_at
            FROM lessons l
            LEFT JOIN student_progress sp 
                ON l.lesson_id = sp.lesson_id AND sp.user_student_id = ?
            WHERE l.subject_id = (SELECT subject_id FROM subject_offered WHERE subject_offered_id = ?) 
            AND l.status = 'published'
            ORDER BY l.lesson_order",
            [$userId, $subjectOfferedId]
        );
        
        echo json_encode(['success' => true, 'data' => $lessons]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Mark lesson as complete
 */
function markComplete() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $lessonId = $input['lesson_id'] ?? 0;
    $userId = Auth::id();
    
    try {
        // 1. FIXED: Verify student is enrolled using 'student_subject'
        $enrollment = db()->fetchOne(
            "SELECT ss.* FROM student_subject ss
             JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
             JOIN lessons l ON so.subject_id = l.subject_id
             WHERE l.lesson_id = ? AND ss.user_student_id = ? AND ss.status = 'enrolled'",
            [$lessonId, $userId]
        );
        
        if (!$enrollment) {
            echo json_encode(['success' => false, 'message' => 'Lesson not found or not enrolled']);
            return;
        }
        
        // 2. FIXED: Check 'student_progress' using 'user_student_id'
        $existing = db()->fetchOne(
            "SELECT * FROM student_progress WHERE user_student_id = ? AND lesson_id = ?",
            [$userId, $lessonId]
        );
        
        if ($existing && $existing['status'] == 'completed') {
            echo json_encode(['success' => true, 'message' => 'Already completed']);
            return;
        }
        
        // Get subject_id from lesson
        $lesson = db()->fetchOne("SELECT subject_id FROM lessons WHERE lesson_id = ?", [$lessonId]);

        if ($existing) {
            db()->execute(
                "UPDATE student_progress SET status = 'completed', completed_at = NOW()
                 WHERE user_student_id = ? AND lesson_id = ?",
                [$userId, $lessonId]
            );
        } else {
            db()->execute(
                "INSERT INTO student_progress (user_student_id, lesson_id, subject_id, status, completed_at, started_at)
                 VALUES (?, ?, ?, 'completed', NOW(), NOW())",
                [$userId, $lessonId, $lesson['subject_id']]
            );
        }

        // Also update lesson_progress table for pre-test/post-test workflow
        $lessonProgress = db()->fetchOne(
            "SELECT * FROM lesson_progress WHERE user_student_id = ? AND lesson_id = ?",
            [$userId, $lessonId]
        );

        if ($lessonProgress) {
            db()->execute(
                "UPDATE lesson_progress SET completion_percentage = 100, is_completed = 1, completed_at = NOW()
                 WHERE user_student_id = ? AND lesson_id = ?",
                [$userId, $lessonId]
            );
        } else {
            // Get subject_offered_id from enrollment
            db()->execute(
                "INSERT INTO lesson_progress (user_student_id, lesson_id, subject_offered_id, completion_percentage, is_completed, first_accessed, completed_at)
                 VALUES (?, ?, ?, 100, 1, NOW(), NOW())",
                [$userId, $lessonId, $enrollment['subject_offered_id']]
            );
        }

        echo json_encode(['success' => true, 'message' => 'Lesson marked as complete']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}