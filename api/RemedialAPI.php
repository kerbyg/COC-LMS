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

switch ($action) {
    case 'instructor-list': getInstructorRemedials(); break;
    case 'student-list': getStudentRemedials(); break;
    case 'create': createRemedial(); break;
    case 'update': updateRemedial(); break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getInstructorRemedials() {
    $userId = Auth::id();
    $subjectId = $_GET['subject_id'] ?? '';
    $status = $_GET['status'] ?? '';

    try {
        $sql = "SELECT ra.*,
                    u.first_name, u.last_name, u.student_id,
                    q.quiz_title, q.passing_rate,
                    s.subject_code, s.subject_name
                FROM remedial_assignment ra
                JOIN users u ON ra.user_student_id = u.users_id
                JOIN quiz q ON ra.quiz_id = q.quiz_id
                JOIN subject s ON q.subject_id = s.subject_id
                WHERE q.user_teacher_id = ?";
        $params = [$userId];

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
                ql.lessons_id as linked_lessons_id,
                l.lesson_title as linked_lesson_title
             FROM remedial_assignment ra
             JOIN quiz q ON ra.quiz_id = q.quiz_id
             JOIN subject s ON q.subject_id = s.subject_id
             LEFT JOIN quiz_lessons ql ON ql.quiz_id = q.quiz_id
             LEFT JOIN lessons l ON l.lessons_id = ql.lessons_id
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
