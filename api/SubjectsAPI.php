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

// RBAC: enforce permission per action
$_subjPerms = [
    'enrolled'    => 'subjects.view',
    'details'     => 'subjects.view',
    'all'         => 'subjects.view',
    'by-program'  => 'subjects.view',
    'list'        => 'subjects.view',
    'get'         => 'subjects.view',
    'programs'    => 'subjects.view',
    'departments' => 'subjects.view',
    'semesters'   => 'subjects.view',
    'create'      => 'subjects.create',
    'update'      => 'subjects.edit',
    'delete'      => 'subjects.delete',
];
if (isset($_subjPerms[$action]) && !Auth::can($_subjPerms[$action])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => "Permission denied: {$_subjPerms[$action]}"]);
    exit;
}

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

    case 'departments':
        getDepartments();
        break;

    case 'semesters':
        getSubjectSemesters();
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
    $search = $_GET['search']        ?? '';
    $deptId = (int)($_GET['department_id'] ?? 0);
    $progId = (int)($_GET['program_id']    ?? 0);
    $semId  = (int)($_GET['semester_id']   ?? 0);

    // Resolve active semester for "This Semester" column only — does NOT restrict rows shown
    $activeSemId = $semId;
    if (!$activeSemId) {
        $activeSem = db()->fetchOne("SELECT semester_id FROM semester WHERE status = 'active' LIMIT 1");
        $activeSemId = $activeSem ? (int)$activeSem['semester_id'] : 0;
    }

    $conditions = ["s.status = 'active'"];
    $params     = [];

    if ($search) {
        $conditions[] = "(s.subject_code LIKE ? OR s.subject_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($deptId) {
        $conditions[] = "p.department_id = ?";
        $params[] = $deptId;
    }
    if ($progId) {
        $conditions[] = "p.program_id = ?";
        $params[] = $progId;
    }
    if ($semId) {
        // Filter rows to subjects whose curriculum semester type matches the selected semester
        // sem_type_id (1=1st, 2=2nd, 3=Summer) maps directly to s.semester values
        $conditions[] = "s.semester = (SELECT sem_type_id FROM semester WHERE semester_id = $semId)";
    }
    $where = 'WHERE ' . implode(' AND ', $conditions);

    // Only filter by semester_id if the column actually exists on subject_offered
    $soHasSemId = (db()->fetchOne(
        "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subject_offered' AND COLUMN_NAME = 'semester_id'"
    )['cnt'] ?? 0) > 0;
    // Each subquery uses a different alias — generate a filter per alias
    $sf2 = ($soHasSemId && $activeSemId) ? "AND so2.semester_id = $activeSemId" : '';
    $sf3 = ($soHasSemId && $activeSemId) ? "AND so3.semester_id = $activeSemId" : '';
    $sf4 = ($soHasSemId && $activeSemId) ? "AND so4.semester_id = $activeSemId" : '';

    try {
        $subjects = pdo()->prepare(
            "SELECT s.subject_id, s.subject_code, s.subject_name, s.units,
                    s.year_level, s.semester,
                    s.status, s.description,
                    p.program_id, p.program_code, p.program_name,
                    d.department_id, d.department_code, d.department_name,
                    IF((SELECT so2.subject_offered_id
                        FROM subject_offered so2
                        WHERE so2.subject_id = s.subject_id $sf2 AND so2.status = 'open'
                        LIMIT 1) IS NOT NULL, 1, 0) AS is_offered,
                    (SELECT CONCAT(u.first_name, ' ', u.last_name)
                     FROM subject_offered so3
                     LEFT JOIN users u ON u.users_id = so3.user_teacher_id
                     WHERE so3.subject_id = s.subject_id $sf3 AND so3.status = 'open'
                     LIMIT 1) AS current_instructor,
                    (SELECT COUNT(*)
                     FROM subject_offered so4
                     JOIN section_subject ss ON ss.subject_offered_id = so4.subject_offered_id
                     WHERE so4.subject_id = s.subject_id $sf4
                       AND so4.status = 'open' AND ss.status = 'active') AS current_section_count
             FROM subject s
             JOIN program p  ON p.program_id  = s.program_id
             LEFT JOIN department d ON d.department_id = p.department_id
             $where
             ORDER BY d.department_name, p.program_code, s.year_level, s.semester, s.subject_code"
        );
        $subjects->execute($params);
        $subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $subjects]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'data' => []]);
    }
}

function getSubjectSemesters() {
    $sems = db()->fetchAll(
        "SELECT MIN(sem.semester_id) AS semester_id, sem.semester_name, sem.academic_year,
                MAX(CASE sem.status WHEN 'active' THEN 'active' WHEN 'upcoming' THEN 'upcoming' ELSE 'inactive' END) AS status
         FROM semester sem
         GROUP BY sem.semester_name, sem.academic_year
         ORDER BY sem.academic_year DESC, MIN(sem.semester_id)"
    );
    echo json_encode(['success' => true, 'data' => $sems]);
}

function getDepartments() {
    $depts = db()->fetchAll(
        "SELECT d.department_id, d.department_name, d.department_code
         FROM department d
         WHERE d.status = 'active'
         ORDER BY d.department_name"
    );
    echo json_encode(['success' => true, 'data' => $depts]);
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
    echo json_encode(['success' => true, 'data' => db()->fetchAll(
        "SELECT p.program_id, p.program_code, p.program_name, p.department_id
         FROM program p
         WHERE p.status = 'active'
         ORDER BY p.program_code"
    )]);
}