<?php
/**
 * Subject Offerings API - CRUD for subject offerings
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':            handleList();           break;
    case 'create':          handleCreate();         break;
    case 'update':          handleUpdate();         break;
    case 'delete':          handleDelete();         break;
    case 'assign':          handleAssign();         break;
    case 'subjects':        handleSubjects();       break;
    case 'semesters':       handleSemesters();      break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleList() {
    $semesterId = $_GET['semester_id'] ?? '';
    $where = '';
    $params = [];
    if ($semesterId) {
        $where = 'WHERE so.semester_id = ?';
        $params[] = $semesterId;
    }

    $offerings = db()->fetchAll(
        "SELECT so.*, s.subject_code, s.subject_name, s.units,
            sem.semester_name, sem.academic_year,
            CONCAT(u.first_name, ' ', u.last_name) AS instructor_name,
            (SELECT COUNT(*) FROM section_subject ss2 WHERE ss2.subject_offered_id = so.subject_offered_id AND ss2.status = 'active') as section_count,
            (SELECT COUNT(DISTINCT fs.user_teacher_id) FROM faculty_subject fs WHERE fs.subject_offered_id = so.subject_offered_id AND fs.status = 'active') as instructor_count,
            (SELECT COUNT(*) FROM student_subject ss WHERE ss.subject_offered_id = so.subject_offered_id AND ss.status = 'enrolled') as student_count
         FROM subject_offered so
         JOIN subject s ON so.subject_id = s.subject_id
         LEFT JOIN semester sem ON so.semester_id = sem.semester_id
         LEFT JOIN users u ON u.users_id = so.user_teacher_id
         $where
         ORDER BY sem.academic_year DESC, s.subject_code",
        $params
    );
    echo json_encode(['success' => true, 'data' => $offerings]);
}

function handleCreate() {
    $data = json_decode(file_get_contents('php://input'), true);
    $subjectId = (int)($data['subject_id'] ?? 0);
    $semesterId = (int)($data['semester_id'] ?? 0);
    $status = $data['status'] ?? 'open';

    if (!$subjectId || !$semesterId) {
        echo json_encode(['success' => false, 'message' => 'Subject and semester are required']);
        return;
    }

    $exists = db()->fetchOne("SELECT subject_offered_id FROM subject_offered WHERE subject_id = ? AND semester_id = ?", [$subjectId, $semesterId]);
    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'This subject is already offered in this semester']);
        return;
    }

    try {
        pdo()->prepare("INSERT INTO subject_offered (subject_id, semester_id, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())")
            ->execute([$subjectId, $semesterId, $status]);
        echo json_encode(['success' => true, 'message' => 'Subject offering created', 'data' => ['id' => pdo()->lastInsertId()]]);
    } catch (Exception $e) {
        error_log('Create offering: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create offering']);
    }
}

function handleUpdate() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['subject_offered_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }

    $status = $data['status'] ?? 'open';
    try {
        pdo()->prepare("UPDATE subject_offered SET status = ?, updated_at = NOW() WHERE subject_offered_id = ?")->execute([$status, $id]);
        echo json_encode(['success' => true, 'message' => 'Offering updated']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update']);
    }
}

function handleDelete() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['subject_offered_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }

    $sections = db()->fetchOne("SELECT COUNT(*) as c FROM section_subject WHERE subject_offered_id = ? AND status = 'active'", [$id])['c'] ?? 0;
    if ($sections > 0) {
        echo json_encode(['success' => false, 'message' => "Cannot cancel offering with $sections active section(s)"]);
        return;
    }

    try {
        pdo()->prepare("UPDATE subject_offered SET status = 'cancelled', updated_at = NOW() WHERE subject_offered_id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Offering cancelled']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed']);
    }
}

function handleAssign() {
    $data = json_decode(file_get_contents('php://input'), true);
    $offId  = (int)($data['subject_offered_id'] ?? 0);
    $userId = (int)($data['user_teacher_id']    ?? 0);

    if (!$offId) {
        echo json_encode(['success' => false, 'message' => 'subject_offered_id required']);
        return;
    }

    try {
        pdo()->prepare("UPDATE subject_offered SET user_teacher_id = ?, updated_at = NOW() WHERE subject_offered_id = ?")
            ->execute([$userId ?: null, $offId]);
        echo json_encode(['success' => true, 'message' => 'Instructor assigned']);
    } catch (Exception $e) {
        error_log('Assign instructor: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to assign instructor']);
    }
}

function handleSubjects() {
    $subjects = db()->fetchAll("SELECT subject_id, subject_code, subject_name, units FROM subject WHERE status = 'active' ORDER BY subject_code");
    echo json_encode(['success' => true, 'data' => $subjects]);
}

function handleSemesters() {
    $semesters = db()->fetchAll(
        "SELECT sem.semester_id, sem.semester_name, sem.academic_year, st.sem_level
         FROM semester sem LEFT JOIN sem_type st ON sem.sem_type_id = st.sem_type_id
         ORDER BY sem.academic_year DESC, st.sem_level"
    );
    echo json_encode(['success' => true, 'data' => $semesters]);
}
