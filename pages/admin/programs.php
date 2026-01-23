<?php
/**
 * Admin - Programs Management
 * Manage academic programs
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('admin');

$pageTitle = 'Programs';
$currentPage = 'programs';

$action = $_GET['action'] ?? 'list';
$programId = $_GET['id'] ?? '';
$error = '';
$success = '';

// Get departments
$departments = db()->fetchAll("SELECT department_id, department_name FROM department ORDER BY department_name");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create' || $postAction === 'update') {
        $departmentId = $_POST['department_id'] ?? '';
        $programName = trim($_POST['program_name'] ?? '');
        $programCode = trim($_POST['program_code'] ?? '');
        $totalUnits = (int)($_POST['total_units'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        if (empty($departmentId) || empty($programName) || empty($programCode)) {
            $error = 'Please fill in all required fields.';
        } else {
            // Check code uniqueness
            $existing = db()->fetchOne(
                "SELECT program_id FROM program WHERE program_code = ? AND program_id != ?",
                [$programCode, $_POST['program_id'] ?? 0]
            );
            
            if ($existing) {
                $error = 'Program code already exists.';
            } else {
                if ($postAction === 'create') {
                    db()->execute(
                        "INSERT INTO program (department_id, program_name, program_code, total_units, description, status, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$departmentId, $programName, $programCode, $totalUnits, $description, $status]
                    );
                    header("Location: programs.php?success=created");
                    exit;
                } else {
                    db()->execute(
                        "UPDATE program SET department_id = ?, program_name = ?, program_code = ?, total_units = ?, description = ?, status = ?, updated_at = NOW() WHERE program_id = ?",
                        [$departmentId, $programName, $programCode, $totalUnits, $description, $status, $_POST['program_id']]
                    );
                    header("Location: programs.php?success=updated");
                    exit;
                }
            }
        }
    }
    
    if ($postAction === 'delete') {
        $deleteId = (int)$_POST['program_id'];
        // Check if program has users
        $hasUsers = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE program_id = ?", [$deleteId])['count'];
        if ($hasUsers > 0) {
            $error = "Cannot delete program with $hasUsers enrolled users.";
        } else {
            db()->execute("DELETE FROM program WHERE program_id = ?", [$deleteId]);
            header("Location: programs.php?success=deleted");
            exit;
        }
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    $successMessages = ['created' => 'Program created!', 'updated' => 'Program updated!', 'deleted' => 'Program deleted!'];
    $success = $successMessages[$_GET['success']] ?? '';
}

// Get program for editing
$editProgram = null;
if ($action === 'edit' && $programId) {
    $editProgram = db()->fetchOne("SELECT * FROM program WHERE program_id = ?", [$programId]);
    if (!$editProgram) { header("Location: programs.php"); exit; }
}

// Get all programs
$programs = db()->fetchAll(
    "SELECT p.*, d.department_name,
        (SELECT COUNT(*) FROM users u WHERE u.program_id = p.program_id) as student_count
     FROM program p
     LEFT JOIN department d ON p.department_id = d.department_id
     ORDER BY p.program_code"
);

// Add subject_count as 0 for now (or remove from display)
foreach ($programs as &$program) {
    $program['subject_count'] = 0; // Subjects are not directly linked to programs
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <?php if ($action === 'list'): ?>
        <div class="page-header">
            <div>
                <h2>Programs</h2>
                <p class="text-muted">Manage academic programs</p>
            </div>
            <a href="?action=create" class="btn btn-success">+ Add Program</a>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        
        <div class="programs-grid">
            <?php if (empty($programs)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📚</div>
                <h3>No Programs Yet</h3>
                <p>Add your first academic program.</p>
                <a href="?action=create" class="btn btn-success" style="margin-top:16px">+ Add Program</a>
            </div>
            <?php else: ?>
            <?php foreach ($programs as $program): ?>
            <div class="program-card">
                <div class="program-header">
                    <span class="program-code"><?= e($program['program_code']) ?></span>
                    <span class="badge badge-<?= $program['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($program['status']) ?></span>
                </div>
                <h3 class="program-name"><?= e($program['program_name']) ?></h3>
                <p class="program-dept"><?= e($program['department_name'] ?? 'No Department') ?></p>
                
                <div class="program-stats">
                    <div class="pstat"><span class="pstat-num"><?= $program['student_count'] ?></span><span class="pstat-label">Students</span></div>
                    <div class="pstat"><span class="pstat-num"><?= $program['subject_count'] ?></span><span class="pstat-label">Subjects</span></div>
                    <div class="pstat"><span class="pstat-num"><?= $program['total_units'] ?></span><span class="pstat-label">Units</span></div>
                </div>
                
                <div class="program-actions">
                    <a href="?action=edit&id=<?= $program['program_id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this program?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="program_id" value="<?= $program['program_id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- CREATE/EDIT VIEW -->
        <div class="page-header">
            <div>
                <a href="programs.php" class="back-link">← Back to Programs</a>
                <h2><?= $action === 'create' ? 'Add New Program' : 'Edit Program' ?></h2>
            </div>
        </div>
        
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        
        <form method="POST" class="form-card">
            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
            <?php if ($editProgram): ?><input type="hidden" name="program_id" value="<?= $editProgram['program_id'] ?>"><?php endif; ?>
            
            <div class="form-section">
                <h3>📚 Program Details</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Program Code <span class="required">*</span></label>
                        <input type="text" name="program_code" class="form-control" required value="<?= e($editProgram['program_code'] ?? '') ?>" placeholder="e.g., BSIT">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Total Units</label>
                        <input type="number" name="total_units" class="form-control" value="<?= e($editProgram['total_units'] ?? 150) ?>" min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Program Name <span class="required">*</span></label>
                    <input type="text" name="program_name" class="form-control" required value="<?= e($editProgram['program_name'] ?? '') ?>" placeholder="e.g., Bachelor of Science in Information Technology">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Department <span class="required">*</span></label>
                    <select name="department_id" class="form-control" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['department_id'] ?>" <?= ($editProgram['department_id'] ?? '') == $dept['department_id'] ? 'selected' : '' ?>><?= e($dept['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($editProgram['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="status" value="active" <?= ($editProgram['status'] ?? 'active') === 'active' ? 'checked' : '' ?>> Active</label>
                        <label class="radio-label"><input type="radio" name="status" value="inactive" <?= ($editProgram['status'] ?? '') === 'inactive' ? 'checked' : '' ?>> Inactive</label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="programs.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-success">💾 <?= $action === 'create' ? 'Create Program' : 'Save Changes' ?></button>
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

.programs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:24px}
.program-card{background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:1px solid #e5e7eb;border-radius:16px;padding:28px;transition:all 0.3s cubic-bezier(0.4,0,0.2,1);box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.program-card:hover{border-color:#3b82f6;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -2px rgba(0,0,0,0.05);transform:translateY(-4px)}
.program-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.program-code{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);color:#1e3a8a;padding:8px 18px;border-radius:24px;font-size:14px;font-weight:800;box-shadow:0 2px 4px rgba(0,0,0,0.08)}
.program-name{margin:0 0 10px;font-size:20px;color:#111827;font-weight:800;line-height:1.3}
.program-dept{margin:0 0 20px;font-size:15px;color:#6b7280;font-weight:500}
.program-stats{display:flex;gap:24px;padding:20px 0;border-top:2px solid #f3f4f6;border-bottom:2px solid #f3f4f6;margin-bottom:20px}
.pstat{text-align:center;flex:1}
.pstat-num{display:block;font-size:28px;font-weight:800;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.pstat-label{font-size:13px;color:#6b7280;margin-top:4px;font-weight:600}
.program-actions{display:flex;gap:10px}

.form-card{background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05)}
.form-section{padding:28px}
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

@media(max-width:768px){.form-row{grid-template-columns:1fr}.programs-grid{grid-template-columns:1fr}.page-header h2{font-size:22px}}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>