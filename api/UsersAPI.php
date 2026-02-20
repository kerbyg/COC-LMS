<?php
/**
 * Users API - CRUD for user management
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

// Dean can read instructors in their department; everything else is admin-only
$role = Auth::role();
$readOnlyActions = ['list', 'get', 'departments', 'programs'];
if ($role !== 'admin' && !($role === 'dean' && in_array($action, $readOnlyActions))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

switch ($action) {
    case 'list': handleList(); break;
    case 'get': handleGet(); break;
    case 'create': handleCreate(); break;
    case 'update': handleUpdate(); break;
    case 'delete': handleDelete(); break;
    case 'departments': handleDepartments(); break;
    case 'programs': handlePrograms(); break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleList() {
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    $status = $_GET['status'] ?? '';
    $departmentId = $_GET['department_id'] ?? '';
    $programId = $_GET['program_id'] ?? '';

    $where = [];
    $params = [];

    // Dean: scope to their department only
    if (Auth::role() === 'dean') {
        $deanUser = db()->fetchOne("SELECT department_id FROM users WHERE users_id = ?", [Auth::id()]);
        if ($deanUser && $deanUser['department_id']) {
            $where[] = "u.department_id = ?";
            $params[] = $deanUser['department_id'];
        }
    }

    if ($search) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ? OR u.student_id LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params, [$s, $s, $s, $s, $s]);
    }
    if ($role) { $where[] = "u.role = ?"; $params[] = $role; }
    if ($status) { $where[] = "u.status = ?"; $params[] = $status; }
    if ($departmentId) { $where[] = "u.department_id = ?"; $params[] = $departmentId; }
    if ($programId) { $where[] = "u.program_id = ?"; $params[] = $programId; }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $users = db()->fetchAll(
        "SELECT u.users_id, u.first_name, u.last_name, u.email, u.role, u.status,
                u.employee_id, u.student_id, u.department_id, u.program_id,
                u.year_level, u.created_at,
                d.department_name, p.program_code, p.program_name
         FROM users u
         LEFT JOIN department d ON u.department_id = d.department_id
         LEFT JOIN program p ON u.program_id = p.program_id
         $whereSQL
         ORDER BY u.created_at DESC",
        $params
    );

    echo json_encode(['success' => true, 'data' => ['users' => $users, 'total' => count($users)]]);
}

function handleGet() {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }

    $user = db()->fetchOne("SELECT * FROM users WHERE users_id = ?", [$id]);
    if (!$user) { echo json_encode(['success' => false, 'message' => 'User not found']); return; }
    unset($user['password']);
    echo json_encode(['success' => true, 'data' => $user]);
}

function handleCreate() {
    $data = json_decode(file_get_contents('php://input'), true);

    $firstName = trim($data['first_name'] ?? '');
    $lastName = trim($data['last_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? 'student';
    $status = $data['status'] ?? 'active';
    $departmentId = $data['department_id'] ?: null;
    $programId = $data['program_id'] ?: null;
    $employeeId = trim($data['employee_id'] ?? '');
    $studentId = trim($data['student_id'] ?? '');
    $yearLevel = $data['year_level'] ?: null;

    if (!$firstName || !$lastName || !$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'First name, last name, email, and password are required']);
        return;
    }

    // Auto-sync department from program's department
    if ($programId) {
        $progDept = db()->fetchOne(
            "SELECT dp.department_id FROM department_program dp WHERE dp.program_id = ? LIMIT 1",
            [$programId]
        );
        if ($progDept) $departmentId = $progDept['department_id'];
    }

    $exists = db()->fetchOne("SELECT users_id FROM users WHERE email = ?", [$email]);
    if ($exists) { echo json_encode(['success' => false, 'message' => 'Email already exists']); return; }

    try {
        $stmt = pdo()->prepare(
            "INSERT INTO users (first_name, last_name, email, password, role, status, department_id, program_id, employee_id, student_id, year_level, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT), $role, $status, $departmentId, $programId, $employeeId ?: null, $studentId ?: null, $yearLevel]);
        echo json_encode(['success' => true, 'message' => 'User created successfully', 'data' => ['id' => pdo()->lastInsertId()]]);
    } catch (Exception $e) {
        error_log('Create user error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
    }
}

function handleUpdate() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['users_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'User ID required']); return; }

    $firstName = trim($data['first_name'] ?? '');
    $lastName = trim($data['last_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? 'student';
    $status = $data['status'] ?? 'active';
    $departmentId = $data['department_id'] ?: null;
    $programId = $data['program_id'] ?: null;
    $employeeId = trim($data['employee_id'] ?? '');
    $studentId = trim($data['student_id'] ?? '');
    $yearLevel = $data['year_level'] ?: null;

    if (!$firstName || !$lastName || !$email) {
        echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required']);
        return;
    }

    // Auto-sync department from program's department
    if ($programId) {
        $progDept = db()->fetchOne(
            "SELECT dp.department_id FROM department_program dp WHERE dp.program_id = ? LIMIT 1",
            [$programId]
        );
        if ($progDept) $departmentId = $progDept['department_id'];
    }

    $exists = db()->fetchOne("SELECT users_id FROM users WHERE email = ? AND users_id != ?", [$email, $id]);
    if ($exists) { echo json_encode(['success' => false, 'message' => 'Email already exists']); return; }

    try {
        if ($password) {
            $stmt = pdo()->prepare("UPDATE users SET first_name=?, last_name=?, email=?, password=?, role=?, status=?, department_id=?, program_id=?, employee_id=?, student_id=?, year_level=?, updated_at=NOW() WHERE users_id=?");
            $stmt->execute([$firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT), $role, $status, $departmentId, $programId, $employeeId ?: null, $studentId ?: null, $yearLevel, $id]);
        } else {
            $stmt = pdo()->prepare("UPDATE users SET first_name=?, last_name=?, email=?, role=?, status=?, department_id=?, program_id=?, employee_id=?, student_id=?, year_level=?, updated_at=NOW() WHERE users_id=?");
            $stmt->execute([$firstName, $lastName, $email, $role, $status, $departmentId, $programId, $employeeId ?: null, $studentId ?: null, $yearLevel, $id]);
        }
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } catch (Exception $e) {
        error_log('Update user error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }
}

function handleDelete() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['users_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'User ID required']); return; }

    if ($id == Auth::id()) {
        echo json_encode(['success' => false, 'message' => 'Cannot deactivate your own account']);
        return;
    }

    try {
        $stmt = pdo()->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE users_id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'User deactivated successfully']);
    } catch (Exception $e) {
        error_log('Delete user error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to deactivate user']);
    }
}

function handleDepartments() {
    $depts = db()->fetchAll("SELECT department_id, department_name FROM department WHERE status = 'active' ORDER BY department_name");
    echo json_encode(['success' => true, 'data' => $depts]);
}

function handlePrograms() {
    $where = "WHERE p.status = 'active'";
    $params = [];

    // Dean: only programs in their department
    if (Auth::role() === 'dean') {
        $deanUser = db()->fetchOne("SELECT department_id FROM users WHERE users_id = ?", [Auth::id()]);
        if ($deanUser && $deanUser['department_id']) {
            $where .= " AND dp.department_id = ?";
            $params[] = $deanUser['department_id'];
        }
    }

    $programs = db()->fetchAll(
        "SELECT p.program_id, p.program_code, p.program_name, dp.department_id
         FROM program p
         LEFT JOIN department_program dp ON p.program_id = dp.program_id
         $where
         ORDER BY p.program_code",
        $params
    );
    echo json_encode(['success' => true, 'data' => $programs]);
}
