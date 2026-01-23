<?php
/**
 * Admin - Users Management
 * CRUD operations for all user roles
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('admin');

$pageTitle = 'User Management';
$currentPage = 'users';

$action = $_GET['action'] ?? 'list';
$userId = $_GET['id'] ?? '';
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$error = '';
$success = '';

// Get departments and programs for forms
$departments = db()->fetchAll("SELECT department_id, department_name FROM department ORDER BY department_name");
$programs = db()->fetchAll("SELECT program_id, program_code, program_name, department_id FROM program ORDER BY program_code");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create' || $postAction === 'update') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'student';
        $status = $_POST['status'] ?? 'active';
        $departmentId = $_POST['department_id'] ?: null;
        $programId = $_POST['program_id'] ?: null;
        $employeeId = trim($_POST['employee_id'] ?? '') ?: null;
        $studentId = trim($_POST['student_id'] ?? '') ?: null;
        $password = $_POST['password'] ?? '';
        
        if (empty($firstName) || empty($lastName) || empty($email)) {
            $error = 'Please fill in all required fields.';
        } elseif ($postAction === 'create' && empty($password)) {
            $error = 'Password is required for new users.';
        } else {
            // Check email uniqueness
            $existing = db()->fetchOne(
                "SELECT users_id FROM users WHERE email = ? AND users_id != ?",
                [$email, $_POST['user_id'] ?? 0]
            );
            
            if ($existing) {
                $error = 'Email already exists.';
            } else {
                if ($postAction === 'create') {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    db()->execute(
                        "INSERT INTO users (first_name, last_name, email, password, role, status, department_id, program_id, employee_id, student_id, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$firstName, $lastName, $email, $hashedPassword, $role, $status, $departmentId, $programId, $employeeId, $studentId]
                    );
                    header("Location: users.php?success=created");
                    exit;
                } else {
                    $updateQuery = "UPDATE users SET first_name = ?, last_name = ?, email = ?, role = ?, status = ?, department_id = ?, program_id = ?, employee_id = ?, student_id = ?, updated_at = NOW()";
                    $params = [$firstName, $lastName, $email, $role, $status, $departmentId, $programId, $employeeId, $studentId];
                    
                    if (!empty($password)) {
                        $updateQuery .= ", password = ?";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }
                    
                    $updateQuery .= " WHERE users_id = ?";
                    $params[] = $_POST['user_id'];
                    
                    db()->execute($updateQuery, $params);
                    header("Location: users.php?success=updated");
                    exit;
                }
            }
        }
    }
    
    if ($postAction === 'delete') {
        $deleteId = (int)$_POST['user_id'];
        // Don't allow deleting yourself
        if ($deleteId !== Auth::id()) {
            db()->execute("DELETE FROM users WHERE users_id = ?", [$deleteId]);
            header("Location: users.php?success=deleted");
            exit;
        } else {
            $error = 'You cannot delete your own account.';
        }
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    $successMessages = ['created' => 'User created successfully!', 'updated' => 'User updated successfully!', 'deleted' => 'User deleted successfully!'];
    $success = $successMessages[$_GET['success']] ?? '';
}

// Get user for editing
$editUser = null;
if ($action === 'edit' && $userId) {
    $editUser = db()->fetchOne("SELECT * FROM users WHERE users_id = ?", [$userId]);
    if (!$editUser) { header("Location: users.php"); exit; }
}

// Build query for listing
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR employee_id LIKE ? OR student_id LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}
if ($roleFilter) {
    $whereClause .= " AND role = ?";
    $params[] = $roleFilter;
}
if ($statusFilter) {
    $whereClause .= " AND status = ?";
    $params[] = $statusFilter;
}

$users = db()->fetchAll(
    "SELECT u.*, d.department_name, p.program_code 
     FROM users u 
     LEFT JOIN department d ON u.department_id = d.department_id
     LEFT JOIN program p ON u.program_id = p.program_id
     $whereClause 
     ORDER BY u.created_at DESC",
    $params
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <?php if ($action === 'list'): ?>
        <!-- LIST VIEW -->
        <div class="page-header">
            <div>
                <h2>User Management</h2>
                <p class="text-muted">Manage all system users</p>
            </div>
            <a href="?action=create" class="btn btn-success">+ Add User</a>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, email, ID..." value="<?= e($search) ?>">
                </div>
                <div class="filter-group">
                    <label>Role</label>
                    <select name="role" class="form-control">
                        <option value="">All Roles</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="dean" <?= $roleFilter === 'dean' ? 'selected' : '' ?>>Dean</option>
                        <option value="instructor" <?= $roleFilter === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                        <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="users.php" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>ID</th>
                            <th>Role</th>
                            <th>Department/Program</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="7" class="text-center text-muted">No users found</td></tr>
                        <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></div>
                                    <div>
                                        <span class="user-name"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></span>
                                        <span class="user-email"><?= e($user['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($user['employee_id'] ?? $user['student_id'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'dean' ? 'warning' : ($user['role'] === 'instructor' ? 'primary' : 'info')) ?>"><?= ucfirst($user['role']) ?></span></td>
                            <td><?= e($user['department_name'] ?? $user['program_code'] ?? '—') ?></td>
                            <td><span class="badge badge-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= ucfirst($user['status']) ?></span></td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <div class="action-btns">
                                    <a href="?action=edit&id=<?= $user['users_id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                    <?php if ($user['users_id'] !== Auth::id()): ?>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $user['users_id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php else: ?>
        <!-- CREATE/EDIT VIEW -->
        <div class="page-header">
            <div>
                <a href="users.php" class="back-link">← Back to Users</a>
                <h2><?= $action === 'create' ? 'Add New User' : 'Edit User' ?></h2>
            </div>
        </div>
        
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        
        <form method="POST" class="form-card">
            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
            <?php if ($editUser): ?><input type="hidden" name="user_id" value="<?= $editUser['users_id'] ?>"><?php endif; ?>
            
            <div class="form-section">
                <h3>👤 Personal Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-control" required value="<?= e($editUser['first_name'] ?? $_POST['first_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-control" required value="<?= e($editUser['last_name'] ?? $_POST['last_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control" required value="<?= e($editUser['email'] ?? $_POST['email'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Password <?= $action === 'create' ? '<span class="required">*</span>' : '(leave blank to keep current)' ?></label>
                        <input type="password" name="password" class="form-control" <?= $action === 'create' ? 'required' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role <span class="required">*</span></label>
                        <select name="role" class="form-control" required id="roleSelect">
                            <option value="student" <?= ($editUser['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                            <option value="instructor" <?= ($editUser['role'] ?? '') === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                            <option value="dean" <?= ($editUser['role'] ?? '') === 'dean' ? 'selected' : '' ?>>Dean</option>
                            <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3>🏫 Academic Information</h3>
                <div class="form-row">
                    <div class="form-group" id="employeeIdGroup">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="employee_id" class="form-control" value="<?= e($editUser['employee_id'] ?? '') ?>" placeholder="For instructors/admins">
                    </div>
                    <div class="form-group" id="studentIdGroup">
                        <label class="form-label">Student ID</label>
                        <input type="text" name="student_id" class="form-control" value="<?= e($editUser['student_id'] ?? '') ?>" placeholder="For students">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-control">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['department_id'] ?>" <?= ($editUser['department_id'] ?? '') == $dept['department_id'] ? 'selected' : '' ?>><?= e($dept['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Program</label>
                        <select name="program_id" class="form-control">
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $prog): ?>
                            <option value="<?= $prog['program_id'] ?>" <?= ($editUser['program_id'] ?? '') == $prog['program_id'] ? 'selected' : '' ?>><?= e($prog['program_code']) ?> - <?= e($prog['program_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="status" value="active" <?= ($editUser['status'] ?? 'active') === 'active' ? 'checked' : '' ?>> Active</label>
                        <label class="radio-label"><input type="radio" name="status" value="inactive" <?= ($editUser['status'] ?? '') === 'inactive' ? 'checked' : '' ?>> Inactive</label>
                        <label class="radio-label"><input type="radio" name="status" value="pending" <?= ($editUser['status'] ?? '') === 'pending' ? 'checked' : '' ?>> Pending</label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="users.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-success">💾 <?= $action === 'create' ? 'Create User' : 'Save Changes' ?></button>
            </div>
        </form>
        <?php endif; ?>
        
    </div>
</main>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px}
.page-header h2{font-size:28px;font-weight:800;margin:0 0 6px;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.text-muted{color:var(--gray-500);margin:0;font-size:15px}
.back-link{color:var(--primary);font-size:14px;display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;font-weight:600;transition:all 0.2s cubic-bezier(0.4,0,0.2,1)}
.back-link:hover{gap:8px;color:#1e3a8a}

.filters-card{background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:1px solid #e5e7eb;border-radius:16px;padding:24px;margin-bottom:28px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.filters-form{display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap}
.filter-group{display:flex;flex-direction:column;gap:8px}
.filter-group label{font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.025em}

.card{background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:1px solid #e5e7eb;border-radius:16px;overflow:hidden;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05),0 2px 4px -1px rgba(0,0,0,0.03);transition:all 0.3s cubic-bezier(0.4,0,0.2,1)}
.card:hover{box-shadow:0 10px 15px -3px rgba(0,0,0,0.08),0 4px 6px -2px rgba(0,0,0,0.04)}
.table-responsive{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse}
.data-table th,.data-table td{padding:18px 20px;text-align:left;border-bottom:1px solid #f3f4f6}
.data-table th{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);font-weight:700;font-size:13px;color:#1f2937;text-transform:uppercase;letter-spacing:0.05em}
.data-table tbody tr{transition:all 0.2s cubic-bezier(0.4,0,0.2,1)}
.data-table tbody tr:hover{background:linear-gradient(135deg,#fef3c7 0%,rgba(254,243,199,0.3) 100%);transform:scale(1.002)}

.user-cell{display:flex;align-items:center;gap:14px}
.user-avatar{width:44px;height:44px;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;box-shadow:0 4px 6px -1px rgba(30,58,138,0.3)}
.user-name{display:block;font-weight:700;color:#111827;font-size:15px}
.user-email{font-size:13px;color:#6b7280;margin-top:2px}
.action-btns{display:flex;gap:10px}

.form-card{background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05)}
.form-section{padding:28px;border-bottom:1px solid #f3f4f6}
.form-section:last-of-type{border-bottom:none}
.form-section h3{margin:0 0 28px;font-size:20px;color:#111827;font-weight:800;display:flex;align-items:center;gap:10px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:24px}
.form-group{margin-bottom:24px}
.form-label{display:block;font-size:14px;font-weight:700;color:#374151;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.025em;font-size:12px}
.required{color:#dc2626;font-weight:700}
.radio-group{display:flex;gap:28px}
.radio-label{display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:600;color:#374151;transition:color 0.2s}
.radio-label:hover{color:#1e3a8a}
.form-actions{display:flex;justify-content:flex-end;gap:14px;padding:24px 28px;background:linear-gradient(135deg,#f9fafb 0%,#f3f4f6 100%);border-top:1px solid #e5e7eb}

.alert{padding:18px 24px;border-radius:12px;margin-bottom:24px;font-weight:600;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
.alert-success{background:linear-gradient(135deg,#d1fae5 0%,#a7f3d0 100%);color:#065f46;border-left:5px solid #10b981}
.alert-danger{background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%);color:#991b1b;border-left:5px solid #ef4444}

@media(max-width:768px){.form-row{grid-template-columns:1fr}.filters-form{flex-direction:column}.page-header h2{font-size:22px}}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>