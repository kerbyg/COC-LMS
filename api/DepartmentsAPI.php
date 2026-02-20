<?php
/**
 * Departments API - CRUD for department management
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list': handleList(); break;
    case 'get': handleGet(); break;
    case 'create': handleCreate(); break;
    case 'update': handleUpdate(); break;
    case 'delete': handleDelete(); break;
    case 'campuses': handleCampuses(); break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleList() {
    $depts = db()->fetchAll(
        "SELECT d.*, c.campus_name,
            (SELECT COUNT(*) FROM department_program dp WHERE dp.department_id = d.department_id) as program_count
         FROM department d
         LEFT JOIN campus c ON d.campus_id = c.campus_id
         ORDER BY d.department_name"
    );
    echo json_encode(['success' => true, 'data' => $depts]);
}

function handleGet() {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }

    $dept = db()->fetchOne("SELECT * FROM department WHERE department_id = ?", [$id]);
    if (!$dept) { echo json_encode(['success' => false, 'message' => 'Department not found']); return; }
    echo json_encode(['success' => true, 'data' => $dept]);
}

function handleCreate() {
    $data = json_decode(file_get_contents('php://input'), true);

    $campusId = (int)($data['campus_id'] ?? 0);
    $code = trim($data['department_code'] ?? '');
    $name = trim($data['department_name'] ?? '');
    $description = trim($data['description'] ?? '');
    $status = $data['status'] ?? 'active';

    if (!$campusId || !$code || !$name) {
        echo json_encode(['success' => false, 'message' => 'Campus, code, and name are required']);
        return;
    }

    $exists = db()->fetchOne("SELECT department_id FROM department WHERE department_code = ?", [$code]);
    if ($exists) { echo json_encode(['success' => false, 'message' => 'Department code already exists']); return; }

    try {
        $stmt = pdo()->prepare(
            "INSERT INTO department (campus_id, department_code, department_name, description, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$campusId, $code, $name, $description, $status]);
        echo json_encode(['success' => true, 'message' => 'Department created successfully', 'data' => ['id' => pdo()->lastInsertId()]]);
    } catch (Exception $e) {
        error_log('Create department error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create department']);
    }
}

function handleUpdate() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['department_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Department ID required']); return; }

    $campusId = (int)($data['campus_id'] ?? 0);
    $code = trim($data['department_code'] ?? '');
    $name = trim($data['department_name'] ?? '');
    $description = trim($data['description'] ?? '');
    $status = $data['status'] ?? 'active';

    if (!$campusId || !$code || !$name) {
        echo json_encode(['success' => false, 'message' => 'Campus, code, and name are required']);
        return;
    }

    $exists = db()->fetchOne("SELECT department_id FROM department WHERE department_code = ? AND department_id != ?", [$code, $id]);
    if ($exists) { echo json_encode(['success' => false, 'message' => 'Department code already exists']); return; }

    try {
        $stmt = pdo()->prepare(
            "UPDATE department SET campus_id=?, department_code=?, department_name=?, description=?, status=?, updated_at=NOW() WHERE department_id=?"
        );
        $stmt->execute([$campusId, $code, $name, $description, $status, $id]);
        echo json_encode(['success' => true, 'message' => 'Department updated successfully']);
    } catch (Exception $e) {
        error_log('Update department error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update department']);
    }
}

function handleDelete() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['department_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Department ID required']); return; }

    $activePrograms = db()->fetchOne(
        "SELECT COUNT(*) as count FROM department_program dp JOIN program p ON dp.program_id = p.program_id WHERE dp.department_id = ? AND p.status = 'active'",
        [$id]
    )['count'] ?? 0;

    if ($activePrograms > 0) {
        echo json_encode(['success' => false, 'message' => "Cannot delete department with $activePrograms active program(s)"]);
        return;
    }

    try {
        $stmt = pdo()->prepare("UPDATE department SET status = 'inactive', updated_at = NOW() WHERE department_id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Department deactivated successfully']);
    } catch (Exception $e) {
        error_log('Delete department error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate department']);
    }
}

function handleCampuses() {
    $campuses = db()->fetchAll("SELECT campus_id, campus_name FROM campus ORDER BY campus_name");
    echo json_encode(['success' => true, 'data' => $campuses]);
}
