<?php
/**
 * Admin - Departments Management
 * Manage academic departments
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('admin');

$pageTitle = 'Departments';
$currentPage = 'departments';

$action = $_GET['action'] ?? 'list';
$departmentId = $_GET['id'] ?? '';
$error = '';
$success = '';

// Get campuses
$campuses = db()->fetchAll("SELECT campus_id, campus_name FROM campus ORDER BY campus_name");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create' || $postAction === 'update') {
        $campusId = $_POST['campus_id'] ?? '';
        $departmentName = trim($_POST['department_name'] ?? '');
        $departmentCode = trim($_POST['department_code'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($campusId) || empty($departmentName) || empty($departmentCode)) {
            $error = 'Please fill in all required fields.';
        } else {
            // Check code uniqueness
            $existing = db()->fetchOne(
                "SELECT department_id FROM department WHERE department_code = ? AND department_id != ?",
                [$departmentCode, $_POST['department_id'] ?? 0]
            );

            if ($existing) {
                $error = 'Department code already exists.';
            } else {
                if ($postAction === 'create') {
                    db()->execute(
                        "INSERT INTO department (campus_id, department_name, department_code, description, status, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$campusId, $departmentName, $departmentCode, $description, $status]
                    );
                    header("Location: departments.php?success=created");
                    exit;
                } else {
                    db()->execute(
                        "UPDATE department SET campus_id = ?, department_name = ?, department_code = ?, description = ?, status = ?, updated_at = NOW()
                         WHERE department_id = ?",
                        [$campusId, $departmentName, $departmentCode, $description, $status, $_POST['department_id']]
                    );
                    header("Location: departments.php?success=updated");
                    exit;
                }
            }
        }
    }

    if ($postAction === 'delete') {
        $deleteId = (int)$_POST['department_id'];
        // Check if department has programs
        $hasPrograms = db()->fetchOne("SELECT COUNT(*) as count FROM program WHERE department_id = ?", [$deleteId])['count'];
        if ($hasPrograms > 0) {
            $error = "Cannot delete department with $hasPrograms programs.";
        } else {
            db()->execute("DELETE FROM department WHERE department_id = ?", [$deleteId]);
            header("Location: departments.php?success=deleted");
            exit;
        }
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    $successMessages = [
        'created' => 'Department created successfully!',
        'updated' => 'Department updated successfully!',
        'deleted' => 'Department deleted successfully!'
    ];
    $success = $successMessages[$_GET['success']] ?? '';
}

// Get department for editing
$editDepartment = null;
if ($action === 'edit' && $departmentId) {
    $editDepartment = db()->fetchOne("SELECT * FROM department WHERE department_id = ?", [$departmentId]);
    if (!$editDepartment) {
        header("Location: departments.php");
        exit;
    }
}

// Get all departments with campus info and program count
$departments = db()->fetchAll(
    "SELECT d.*, c.campus_name,
        (SELECT COUNT(*) FROM program p WHERE p.department_id = d.department_id) as program_count
     FROM department d
     LEFT JOIN campus c ON d.campus_id = c.campus_id
     ORDER BY d.department_name"
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <div class="page-header">
            <div>
                <h2>Departments</h2>
                <p class="text-muted">Manage academic departments and organizational units</p>
            </div>
            <button class="btn btn-success" onclick="openModal()">+ Add Department</button>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <!-- Departments Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Department Code</th>
                                <th>Department Name</th>
                                <th>Campus</th>
                                <th>Programs</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="6" class="text-center empty-message">No departments found</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><span class="badge badge-code"><?= e($dept['department_code']) ?></span></td>
                                <td>
                                    <strong><?= e($dept['department_name']) ?></strong>
                                    <?php if ($dept['description']): ?>
                                    <br><small class="text-muted"><?= e($dept['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($dept['campus_name'] ?? 'N/A') ?></td>
                                <td><span class="badge badge-info"><?= $dept['program_count'] ?> programs</span></td>
                                <td>
                                    <span class="badge badge-<?= $dept['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($dept['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-action btn-edit" onclick='editDepartment(<?= json_encode($dept) ?>)' title="Edit">
                                            ✏️
                                        </button>
                                        <button class="btn-action btn-delete" onclick="confirmDelete(<?= $dept['department_id'] ?>, '<?= e($dept['department_name']) ?>')" title="Delete">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Create/Edit Modal -->
<div class="modal" id="departmentModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Add Department</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="departmentForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="department_id" id="departmentId">

            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="campus_id">Campus <span class="required">*</span></label>
                        <select name="campus_id" id="campus_id" class="form-control" required>
                            <option value="">-- Select Campus --</option>
                            <?php foreach ($campuses as $campus): ?>
                            <option value="<?= $campus['campus_id'] ?>"><?= e($campus['campus_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="department_code">Department Code <span class="required">*</span></label>
                        <input type="text" name="department_code" id="department_code" class="form-control" placeholder="e.g., CIT" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="department_name">Department Name <span class="required">*</span></label>
                    <input type="text" name="department_name" id="department_name" class="form-control" placeholder="e.g., College of Information Technology" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="3" placeholder="Brief description of the department"></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-success">Save Department</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<form method="POST" id="deleteForm">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="department_id" id="deleteDepartmentId">
</form>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px}
.page-header h2{font-size:28px;font-weight:800;margin:0 0 6px;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.text-muted{color:var(--gray-500);margin:0;font-size:15px}

.card{background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,0.05);overflow:hidden}
.card-body{padding:28px}

.data-table{width:100%;border-collapse:collapse}
.data-table thead{background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%)}
.data-table th{padding:16px;text-align:left;font-size:13px;font-weight:800;color:#ffffff;text-transform:uppercase;letter-spacing:0.05em}
.data-table tbody tr{border-bottom:1px solid #f3f4f6;transition:all 0.2s cubic-bezier(0.4,0,0.2,1)}
.data-table tbody tr:hover{background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%)}
.data-table td{padding:16px;font-size:14px;color:#374151}
.empty-message{padding:48px 24px;text-align:center;color:#9ca3af;font-weight:600}

.badge{display:inline-block;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700}
.badge-code{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);color:#1e3a8a}
.badge-info{background:linear-gradient(135deg,#dbeafe 0%,#bfdbfe 100%);color:#1e3a8a}
.badge-success{background:linear-gradient(135deg,#d1fae5 0%,#a7f3d0 100%);color:#065f46}
.badge-secondary{background:#e5e7eb;color:#6b7280}

.action-buttons{display:flex;gap:8px}
.btn-action{background:transparent;border:none;font-size:18px;cursor:pointer;padding:6px;border-radius:6px;transition:all 0.2s}
.btn-action:hover{background:#f3f4f6;transform:scale(1.1)}

.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:#ffffff;border-radius:16px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 40px rgba(0,0,0,0.2)}
.modal-header{display:flex;justify-content:space-between;align-items:center;padding:24px;border-bottom:2px solid #f3f4f6}
.modal-header h3{margin:0;font-size:20px;font-weight:800;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.modal-close{background:transparent;border:none;font-size:28px;color:#9ca3af;cursor:pointer;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px}
.modal-close:hover{background:#f3f4f6}
.modal-body{padding:24px}
.modal-footer{display:flex;gap:12px;justify-content:flex-end;padding:24px;border-top:2px solid #f3f4f6}

.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
.form-group{display:flex;flex-direction:column;gap:8px;margin-bottom:20px}
.form-group label{font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.025em}
.required{color:#ef4444}
.form-control{padding:12px 16px;border:2px solid #e5e7eb;border-radius:10px;font-size:14px;transition:all 0.2s;font-family:inherit}
.form-control:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,0.1)}
textarea.form-control{resize:vertical;min-height:80px}

.btn{padding:12px 24px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.3s cubic-bezier(0.4,0,0.2,1);display:inline-flex;align-items:center;gap:8px}
.btn-success{background:linear-gradient(135deg,#059669 0%,#10b981 100%);color:#ffffff;box-shadow:0 4px 6px rgba(16,185,129,0.25)}
.btn-success:hover{transform:translateY(-2px);box-shadow:0 8px 12px rgba(16,185,129,0.3)}
.btn-secondary{background:#e5e7eb;color:#374151}
.btn-secondary:hover{background:#d1d5db}

.alert{padding:18px 24px;border-radius:12px;margin-bottom:24px;font-weight:600;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
.alert-success{background:linear-gradient(135deg,#d1fae5 0%,#a7f3d0 100%);color:#065f46;border-left:5px solid #10b981}
.alert-danger{background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%);color:#991b1b;border-left:5px solid #ef4444}

@media(max-width:768px){
    .form-grid{grid-template-columns:1fr}
    .page-header{flex-direction:column;align-items:flex-start;gap:16px}
}
</style>

<script>
function openModal() {
    document.getElementById('departmentModal').classList.add('active');
    document.getElementById('modalTitle').textContent = 'Add Department';
    document.getElementById('formAction').value = 'create';
    document.getElementById('departmentForm').reset();
    document.getElementById('departmentId').value = '';
}

function closeModal() {
    document.getElementById('departmentModal').classList.remove('active');
}

function editDepartment(dept) {
    document.getElementById('departmentModal').classList.add('active');
    document.getElementById('modalTitle').textContent = 'Edit Department';
    document.getElementById('formAction').value = 'update';
    document.getElementById('departmentId').value = dept.department_id;
    document.getElementById('campus_id').value = dept.campus_id;
    document.getElementById('department_code').value = dept.department_code;
    document.getElementById('department_name').value = dept.department_name;
    document.getElementById('description').value = dept.description || '';
    document.getElementById('status').value = dept.status;
}

function confirmDelete(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"?`)) {
        document.getElementById('deleteDepartmentId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

window.onclick = function(event) {
    const modal = document.getElementById('departmentModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Open edit modal if editing
<?php if ($editDepartment): ?>
editDepartment(<?= json_encode($editDepartment) ?>);
<?php endif; ?>
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
