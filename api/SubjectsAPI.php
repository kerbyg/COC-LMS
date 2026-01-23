<?php
/**
 * ============================================================
 * CIT-LMS Subjects API
 * ============================================================
 * Handles subject-related operations
 * 
 * Endpoints:
 *   GET  ?action=enrolled      - Get student's enrolled subjects
 *   GET  ?action=details&id=X  - Get single subject details
 *   GET  ?action=all           - Get all subjects (admin)
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

// Require login
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    
    /**
     * Get enrolled subjects for current student
     */
    case 'enrolled':
        getEnrolledSubjects();
        break;
    
    /**
     * Get single subject details
     */
    case 'details':
        getSubjectDetails();
        break;
    
    /**
     * Get all subjects (admin only)
     */
    case 'all':
        getAllSubjects();
        break;
    
    /**
     * Get subjects by program
     */
    case 'by-program':
        getSubjectsByProgram();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get enrolled subjects for current student
 */
function getEnrolledSubjects() {
    $userId = Auth::id();
    
    try {
        $subjects = db()->fetchAll(
            "SELECT 
                s.subject_id,
                s.subject_code,
                s.subject_name,
                s.description,
                s.units,
                so.subject_offering_id,
                so.section_id,
                sec.section_name,
                CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                u.email as instructor_email,
                e.enrollment_id,
                e.status as enrollment_status,
                e.enrolled_at,
                
                -- Lesson progress
                (SELECT COUNT(*) 
                 FROM lesson l 
                 WHERE l.subject_offering_id = so.subject_offering_id 
                 AND l.status = 'published') as total_lessons,
                 
                (SELECT COUNT(*) 
                 FROM student_lesson_progress slp 
                 JOIN lesson l ON slp.lesson_id = l.lesson_id 
                 WHERE slp.user_student_id = ? 
                 AND l.subject_offering_id = so.subject_offering_id 
                 AND slp.is_completed = 1) as completed_lessons,
                
                -- Quiz progress
                (SELECT COUNT(*) 
                 FROM quiz q 
                 WHERE q.subject_offering_id = so.subject_offering_id 
                 AND q.status = 'published') as total_quizzes,
                 
                (SELECT COUNT(DISTINCT qa.quiz_id) 
                 FROM student_quiz_attempts qa 
                 JOIN quiz q ON qa.quiz_id = q.quiz_id 
                 WHERE qa.user_student_id = ? 
                 AND q.subject_offering_id = so.subject_offering_id) as completed_quizzes,
                
                -- Average score
                (SELECT ROUND(AVG(qa.score), 1) 
                 FROM student_quiz_attempts qa 
                 JOIN quiz q ON qa.quiz_id = q.quiz_id 
                 WHERE qa.user_student_id = ? 
                 AND q.subject_offering_id = so.subject_offering_id) as average_score
                
            FROM student_subject e
            JOIN subject_offering so ON e.subject_offering_id = so.subject_offering_id
            JOIN subject s ON so.subject_id = s.subject_id
            LEFT JOIN users u ON so.user_student_id = u.users_id
            LEFT JOIN section sec ON so.section_id = sec.section_id
            WHERE e.user_student_id = ? AND e.status = 'enrolled'
            ORDER BY s.subject_code",
            [$userId, $userId, $userId, $userId]
        );
        
        // Calculate progress percentage for each subject
        foreach ($subjects as &$subj) {
            $lessonProg = $subj['total_lessons'] > 0 
                ? ($subj['completed_lessons'] / $subj['total_lessons']) * 100 : 0;
            $quizProg = $subj['total_quizzes'] > 0 
                ? ($subj['completed_quizzes'] / $subj['total_quizzes']) * 100 : 0;
            
            $subj['lesson_progress'] = round($lessonProg);
            $subj['quiz_progress'] = round($quizProg);
            $subj['overall_progress'] = round(($lessonProg + $quizProg) / 2);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $subjects,
            'count' => count($subjects)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get single subject details with lessons and quizzes
 */
function getSubjectDetails() {
    $subjectOfferingId = $_GET['id'] ?? 0;
    $userId = Auth::id();
    
    if (!$subjectOfferingId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Subject ID required']);
        return;
    }
    
    try {
        // Verify student is enrolled
        $enrollment = db()->fetchOne(
            "SELECT e.* FROM student_subject e 
             WHERE e.user_student_id = ? AND e.subject_offering_id = ? AND e.status = 'enrolled'",
            [$userId, $subjectOfferingId]
        );
        
        if (!$enrollment && Auth::role() === 'student') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Not enrolled in this subject']);
            return;
        }
        
        // Get subject details
        $subject = db()->fetchOne(
            "SELECT 
                s.subject_id,
                s.subject_code,
                s.subject_name,
                s.description,
                s.units,
                so.subject_offering_id,
                sec.section_name,
                CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
                u.email as instructor_email
            FROM subject_offering so
            JOIN subject s ON so.subject_id = s.subject_id
            LEFT JOIN users u ON so.user_student_id = u.users_id
            LEFT JOIN section sec ON so.section_id = sec.section_id
            WHERE so.subject_offering_id = ?",
            [$subjectOfferingId]
        );
        
        if (!$subject) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Subject not found']);
            return;
        }
        
        // Get lessons
        $lessons = db()->fetchAll(
            "SELECT 
                l.lesson_id,
                l.title,
                l.description,
                l.order_number,
                l.status,
                l.created_at,
                COALESCE(slp.is_completed, 0) as is_completed,
                slp.completed_at
            FROM lesson l
            LEFT JOIN student_lesson_progress slp 
                ON l.lesson_id = slp.lesson_id AND slp.user_student_id = ?
            WHERE l.subject_offering_id = ? AND l.status = 'published'
            ORDER BY l.order_number",
            [$userId, $subjectOfferingId]
        );
        
        // Get quizzes
        $quizzes = db()->fetchAll(
            "SELECT 
                q.quiz_id,
                q.title,
                q.description,
                q.time_limit,
                q.passing_score,
                q.max_attempts,
                q.due_date,
                q.status,
                (SELECT COUNT(*) FROM question WHERE quiz_id = q.quiz_id) as question_count,
                (SELECT COUNT(*) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ?) as attempts_used,
                (SELECT MAX(score) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ?) as best_score
            FROM quiz q
            WHERE q.subject_offering_id = ? AND q.status = 'published'
            ORDER BY q.due_date",
            [$userId, $userId, $subjectOfferingId]
        );
        
        // Get announcements
        $announcements = db()->fetchAll(
            "SELECT 
                announcement_id,
                title,
                content,
                created_at
            FROM announcement
            WHERE subject_offering_id = ? AND status = 'published'
            ORDER BY created_at DESC
            LIMIT 5",
            [$subjectOfferingId]
        );
        
        echo json_encode([
            'success' => true,
            'data' => [
                'subject' => $subject,
                'lessons' => $lessons,
                'quizzes' => $quizzes,
                'announcements' => $announcements,
                'stats' => [
                    'total_lessons' => count($lessons),
                    'completed_lessons' => count(array_filter($lessons, fn($l) => $l['is_completed'])),
                    'total_quizzes' => count($quizzes),
                    'completed_quizzes' => count(array_filter($quizzes, fn($q) => $q['attempts_used'] > 0))
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get all subjects (admin only)
 */
function getAllSubjects() {
    if (!in_array(Auth::role(), ['admin', 'dean'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    try {
        $subjects = db()->fetchAll(
            "SELECT 
                s.*,
                p.program_code,
                p.program_name,
                d.department_code,
                d.department_name
            FROM subject s
            LEFT JOIN program p ON s.program_id = p.program_id
            LEFT JOIN department d ON p.department_id = d.department_id
            ORDER BY s.subject_code"
        );
        
        echo json_encode([
            'success' => true,
            'data' => $subjects,
            'count' => count($subjects)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get subjects by program
 */
function getSubjectsByProgram() {
    $programId = $_GET['program_id'] ?? 0;
    
    if (!$programId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Program ID required']);
        return;
    }
    
    try {
        $subjects = db()->fetchAll(
            "SELECT * FROM subject WHERE program_id = ? ORDER BY subject_code",
            [$programId]
        );
        
        echo json_encode([
            'success' => true,
            'data' => $subjects,
            'count' => count($subjects)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}