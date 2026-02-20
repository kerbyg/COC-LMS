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

    case 'list':
        listSubjects();
        break;

    case 'get':
        getSubject();
        break;

    case 'create':
        createSubject();
        break;

    case 'update':
        updateSubject();
        break;

    case 'delete':
        deleteSubject();
        break;

    case 'programs':
        getPrograms();
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
                so.subject_offered_id,
                so.section_id,
                sec.section_name,
                CONCAT(inst.first_name, ' ', inst.last_name) as instructor_name,
                inst.email as instructor_email,
                e.student_subject_id as enrollment_id,
                e.status as enrollment_status,
                e.enrolled_at,

                -- Lesson progress
                (SELECT COUNT(*)
                 FROM lessons l
                 WHERE l.subject_id = s.subject_id
                 AND l.status = 'published') as total_lessons,

                (SELECT COUNT(*)
                 FROM student_progress sp
                 JOIN lessons l ON sp.lessons_id = l.lessons_id
                 WHERE sp.user_student_id = ?
                 AND l.subject_id = s.subject_id
                 AND sp.status = 'completed') as completed_lessons,

                -- Quiz progress
                (SELECT COUNT(*)
                 FROM quiz q
                 WHERE q.subject_id = s.subject_id
                 AND q.status = 'published') as total_quizzes,

                (SELECT COUNT(DISTINCT qa.quiz_id)
                 FROM student_quiz_attempts qa
                 JOIN quiz q ON qa.quiz_id = q.quiz_id
                 WHERE qa.user_student_id = ?
                 AND q.subject_id = s.subject_id) as completed_quizzes,

                -- Average score
                (SELECT ROUND(AVG(qa.percentage), 1)
                 FROM student_quiz_attempts qa
                 JOIN quiz q ON qa.quiz_id = q.quiz_id
                 WHERE qa.user_student_id = ?
                 AND q.subject_id = s.subject_id
                 AND qa.status = 'completed') as average_score

            FROM student_subject e
            JOIN subject_offered so ON e.subject_offered_id = so.subject_offered_id
            JOIN subject s ON so.subject_id = s.subject_id
            LEFT JOIN faculty_subject fs ON fs.subject_offered_id = so.subject_offered_id
            LEFT JOIN users inst ON fs.user_teacher_id = inst.users_id
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
             WHERE e.user_student_id = ? AND e.subject_offered_id = ? AND e.status = 'enrolled'",
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
                so.subject_offered_id,
                sec.section_name,
                CONCAT(inst.first_name, ' ', inst.last_name) as instructor_name,
                inst.email as instructor_email
            FROM subject_offered so
            JOIN subject s ON so.subject_id = s.subject_id
            LEFT JOIN faculty_subject fs ON fs.subject_offered_id = so.subject_offered_id
            LEFT JOIN users inst ON fs.user_teacher_id = inst.users_id
            LEFT JOIN section sec ON so.section_id = sec.section_id
            WHERE so.subject_offered_id = ?",
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
                l.lessons_id,
                l.lesson_title as title,
                l.lesson_order as order_number,
                l.status,
                l.created_at,
                CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END as is_completed,
                sp.completed_at
            FROM lessons l
            LEFT JOIN student_progress sp
                ON l.lessons_id = sp.lessons_id AND sp.user_student_id = ?
            WHERE l.subject_id = ? AND l.status = 'published'
            ORDER BY l.lesson_order",
            [$userId, $subject['subject_id']]
        );

        // Get quizzes
        $quizzes = db()->fetchAll(
            "SELECT
                q.quiz_id,
                q.quiz_title,
                q.quiz_description,
                q.time_limit,
                q.passing_rate,
                q.max_attempts,
                q.status,
                (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
                (SELECT COUNT(*) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ?) as attempts_used,
                (SELECT MAX(percentage) FROM student_quiz_attempts WHERE quiz_id = q.quiz_id AND user_student_id = ? AND status = 'completed') as best_score
            FROM quiz q
            WHERE q.subject_id = ? AND q.status = 'published'
            ORDER BY q.created_at",
            [$userId, $userId, $subject['subject_id']]
        );

        // Get announcements
        $announcements = db()->fetchAll(
            "SELECT
                announcement_id,
                title,
                content,
                created_at
            FROM announcement
            WHERE subject_offered_id = ? AND status = 'published'
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
            LEFT JOIN department_program dp ON p.program_id = dp.program_id
            LEFT JOIN department d ON dp.department_id = d.department_id
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

// ─── Admin CRUD Functions ─────────────────────────────────

function listSubjects() {
    $search = $_GET['search'] ?? '';
    $where = '';
    $params = [];
    if ($search) {
        $where = "WHERE s.subject_code LIKE ? OR s.subject_name LIKE ?";
        $params = ["%$search%", "%$search%"];
    }
    $subjects = db()->fetchAll(
        "SELECT s.*, p.program_code,
            (SELECT COUNT(*) FROM subject_offered so WHERE so.subject_id = s.subject_id) as offering_count,
            (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.subject_id) as lesson_count
         FROM subject s LEFT JOIN program p ON s.program_id = p.program_id $where ORDER BY s.subject_code",
        $params
    );
    echo json_encode(['success' => true, 'data' => $subjects]);
}

function getSubject() {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }
    $s = db()->fetchOne("SELECT * FROM subject WHERE subject_id = ?", [$id]);
    if (!$s) { echo json_encode(['success' => false, 'message' => 'Subject not found']); return; }
    echo json_encode(['success' => true, 'data' => $s]);
}

function createSubject() {
    $d = json_decode(file_get_contents('php://input'), true);
    $code = trim($d['subject_code'] ?? ''); $name = trim($d['subject_name'] ?? '');
    if (!$code || !$name) { echo json_encode(['success' => false, 'message' => 'Subject code and name are required']); return; }
    if (db()->fetchOne("SELECT subject_id FROM subject WHERE subject_code = ?", [$code])) {
        echo json_encode(['success' => false, 'message' => 'Subject code already exists']); return;
    }
    try {
        pdo()->prepare("INSERT INTO subject (program_id, subject_code, subject_name, units, year_level, semester, description, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())")
            ->execute([$d['program_id'] ?: null, $code, $name, (int)($d['units'] ?? 3), $d['year_level'] ?: null, $d['semester'] ?: null, trim($d['description'] ?? ''), $d['status'] ?? 'active']);
        echo json_encode(['success' => true, 'message' => 'Subject created successfully', 'data' => ['id' => pdo()->lastInsertId()]]);
    } catch (Exception $e) { error_log('Create subject: '.$e->getMessage()); echo json_encode(['success' => false, 'message' => 'Failed to create subject']); }
}

function updateSubject() {
    $d = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['subject_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Subject ID required']); return; }
    $code = trim($d['subject_code'] ?? ''); $name = trim($d['subject_name'] ?? '');
    if (!$code || !$name) { echo json_encode(['success' => false, 'message' => 'Subject code and name are required']); return; }
    if (db()->fetchOne("SELECT subject_id FROM subject WHERE subject_code = ? AND subject_id != ?", [$code, $id])) {
        echo json_encode(['success' => false, 'message' => 'Subject code already exists']); return;
    }
    try {
        pdo()->prepare("UPDATE subject SET program_id=?, subject_code=?, subject_name=?, units=?, year_level=?, semester=?, description=?, status=?, updated_at=NOW() WHERE subject_id=?")
            ->execute([$d['program_id'] ?: null, $code, $name, (int)($d['units'] ?? 3), $d['year_level'] ?: null, $d['semester'] ?: null, trim($d['description'] ?? ''), $d['status'] ?? 'active', $id]);
        echo json_encode(['success' => true, 'message' => 'Subject updated successfully']);
    } catch (Exception $e) { error_log('Update subject: '.$e->getMessage()); echo json_encode(['success' => false, 'message' => 'Failed to update subject']); }
}

function deleteSubject() {
    $d = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['subject_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Subject ID required']); return; }
    $count = db()->fetchOne("SELECT COUNT(*) as c FROM subject_offered WHERE subject_id = ? AND status = 'open'", [$id])['c'] ?? 0;
    if ($count > 0) { echo json_encode(['success' => false, 'message' => "Cannot delete subject with $count active offering(s)"]); return; }
    try {
        pdo()->prepare("UPDATE subject SET status='inactive', updated_at=NOW() WHERE subject_id=?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Subject deactivated successfully']);
    } catch (Exception $e) { error_log('Delete subject: '.$e->getMessage()); echo json_encode(['success' => false, 'message' => 'Failed']); }
}

function getPrograms() {
    echo json_encode(['success' => true, 'data' => db()->fetchAll("SELECT program_id, program_code, program_name FROM program WHERE status='active' ORDER BY program_code")]);
}