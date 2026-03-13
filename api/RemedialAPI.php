<?php
/**
 * CIT-LMS Remedial API
 * Manage remedial assignments for instructors
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

// RBAC: enforce permission per action
$_remPerms = [
    'instructor-list'   => 'remedials.view',
    'student-list'      => 'remedials.view',
    'students-for-quiz' => 'remedials.view',
    'create'            => 'remedials.create',
    'update'            => 'remedials.edit',
];
if (isset($_remPerms[$action]) && !Auth::can($_remPerms[$action])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => "Permission denied: {$_remPerms[$action]}"]);
    exit;
}

switch ($action) {
    case 'instructor-list':   getInstructorRemedials(); break;
    case 'student-list':      getStudentRemedials();    break;
    case 'create':            createRemedial();         break;
    case 'update':            updateRemedial();         break;
    case 'students-for-quiz': getStudentsForQuiz();     break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getInstructorRemedials() {
    $userId = Auth::id();
    $subjectId = $_GET['subject_id'] ?? '';
    $status = $_GET['status'] ?? '';

    try {
        // Match remedials for quizzes the instructor created OR subjects they teach via subject_offered
        $sql = "SELECT DISTINCT ra.*,
                    u.first_name, u.last_name, u.student_id,
                    q.quiz_title, q.passing_rate,
                    s.subject_code, s.subject_name,
                    ra.created_at as assigned_at
                FROM remedial_assignment ra
                JOIN users u ON ra.user_student_id = u.users_id
                JOIN quiz q ON ra.quiz_id = q.quiz_id
                JOIN subject s ON q.subject_id = s.subject_id
                WHERE (
                    q.user_teacher_id = ?
                    OR s.subject_id IN (
                        SELECT DISTINCT so.subject_id FROM subject_offered so
                        WHERE so.user_teacher_id = ? AND so.status = 'open'
                    )
                )";
        $params = [$userId, $userId];

        if ($subjectId) {
            $sql .= " AND q.subject_id = ?";
            $params[] = $subjectId;
        }
        if ($status) {
            $sql .= " AND ra.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY ra.created_at DESC";
        $data = db()->fetchAll($sql, $params);
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function getStudentRemedials() {
    $userId = Auth::id();

    try {
        $data = db()->fetchAll(
            "SELECT ra.*, q.quiz_title, q.passing_rate, q.quiz_id,
                s.subject_code, s.subject_name,
                (SELECT ql.lessons_id FROM quiz_lessons ql
                 JOIN lessons l ON l.lessons_id = ql.lessons_id
                 WHERE ql.quiz_id = q.quiz_id AND l.subject_id = s.subject_id
                 LIMIT 1) as linked_lessons_id,
                (SELECT l.lesson_title FROM quiz_lessons ql
                 JOIN lessons l ON l.lessons_id = ql.lessons_id
                 WHERE ql.quiz_id = q.quiz_id AND l.subject_id = s.subject_id
                 LIMIT 1) as linked_lesson_title
             FROM remedial_assignment ra
             JOIN quiz q ON ra.quiz_id = q.quiz_id
             JOIN subject s ON q.subject_id = s.subject_id
             WHERE ra.user_student_id = ?
             ORDER BY ra.created_at DESC",
            [$userId]
        );
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function createRemedial() {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = Auth::id();
    $quizId = $input['quiz_id'] ?? 0;
    $studentId = $input['user_student_id'] ?? 0;
    $reason = $input['reason'] ?? '';
    $dueDate = $input['due_date'] ?? null;

    if (!$quizId || !$studentId) {
        echo json_encode(['success' => false, 'message' => 'Quiz and student are required']);
        return;
    }

    try {
        // Verify quiz belongs to this instructor
        $quiz = db()->fetchOne("SELECT quiz_id FROM quiz WHERE quiz_id = ? AND user_teacher_id = ?", [$quizId, $userId]);
        if (!$quiz) {
            echo json_encode(['success' => false, 'message' => 'Quiz not found']);
            return;
        }

        // Check if remedial already exists
        $existing = db()->fetchOne(
            "SELECT remedial_id FROM remedial_assignment WHERE user_student_id = ? AND quiz_id = ? AND status IN ('pending','in_progress')",
            [$studentId, $quizId]
        );
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Active remedial already exists for this student/quiz']);
            return;
        }

        $dueSql = $dueDate ? "?" : "DATE_ADD(NOW(), INTERVAL 7 DAY)";
        $params = [$studentId, $quizId, $userId, $reason];
        if ($dueDate) $params[] = $dueDate;

        $stmt = pdo()->prepare(
            "INSERT INTO remedial_assignment (user_student_id, quiz_id, assigned_by, reason, due_date, status, created_at)
             VALUES (?, ?, ?, ?, $dueSql, 'pending', NOW())"
        );
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Remedial created']);
    } catch (PDOException $e) {
        error_log("Remedial create error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create remedial']);
    }
}

function updateRemedial() {
    $input = json_decode(file_get_contents('php://input'), true);
    $remedialId = $input['remedial_id'] ?? 0;
    $status = $input['status'] ?? '';
    $remarks = $input['remarks'] ?? '';

    if (!$remedialId || !$status) {
        echo json_encode(['success' => false, 'message' => 'Remedial ID and status are required']);
        return;
    }

    try {
        $sets = ["status = ?"];
        $params = [$status];

        if ($remarks) {
            $sets[] = "remarks = CONCAT(IFNULL(remarks, ''), ?)";
            $params[] = "\n[" . date('M d, Y') . "] " . $remarks;
        }
        if ($status === 'completed') {
            $sets[] = "completed_at = NOW()";
        }

        $params[] = $remedialId;
        $stmt = pdo()->prepare("UPDATE remedial_assignment SET " . implode(', ', $sets) . " WHERE remedial_id = ?");
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Remedial updated']);
    } catch (PDOException $e) {
        error_log("Remedial update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update remedial']);
    }
}

// Get all students enrolled in the subject of a given quiz
function getStudentsForQuiz() {
    $quizId = (int)($_GET['quiz_id'] ?? 0);
    if (!$quizId) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }

    $data = db()->fetchAll(
        "SELECT DISTINCT u.users_id, u.first_name, u.last_name, u.student_id
         FROM quiz q
         JOIN subject s ON s.subject_id = q.subject_id
         JOIN subject_offered so ON so.subject_id = s.subject_id
         JOIN student_subject ss ON ss.subject_offered_id = so.subject_offered_id
                                 AND ss.status = 'enrolled'
         JOIN users u ON u.users_id = ss.user_student_id
         WHERE q.quiz_id = ?
         ORDER BY u.last_name, u.first_name",
        [$quizId]
    );
    echo json_encode(['success' => true, 'data' => $data]);
}
