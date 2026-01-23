<?php
/**
 * Admin - Subjects Management
 * Manage subjects/courses
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('admin');

$pageTitle = 'Subjects';
$currentPage = 'subjects';

$action = $_GET['action'] ?? 'list';
$subjectId = $_GET['id'] ?? '';
$search = $_GET['search'] ?? '';
$error = '';
$success = '';

// Get programs
$programs = db()->fetchAll("SELECT program_id, program_code, program_name FROM program ORDER BY program_code");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create' || $postAction === 'update') {
        $programId = $_POST['program_id'] ?: null;
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $subjectName = trim($_POST['subject_name'] ?? '');
        $units = (int)($_POST['units'] ?? 3);
        $yearLevel = $_POST['year_level'] ?: null;
        $semester = $_POST['semester'] ?: null;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($subjectCode) || empty($subjectName)) {
            $error = 'Please fill in all required fields.';
        } else {
            $existing = db()->fetchOne(
                "SELECT subject_id FROM subject WHERE subject_code = ? AND subject_id != ?",
                [$subjectCode, $_POST['subject_id'] ?? 0]
            );

            if ($existing) {
                $error = 'Subject code already exists.';
            } else {
                if ($postAction === 'create') {
                    db()->execute(
                        "INSERT INTO subject (program_id, subject_code, subject_name, units, year_level, semester, description, status, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                        [$programId, $subjectCode, $subjectName, $units, $yearLevel, $semester, $description, $status]
                    );
                    header("Location: subjects.php?success=created");
                    exit;
                } else {
                    db()->execute(
                        "UPDATE subject SET program_id = ?, subject_code = ?, subject_name = ?, units = ?, year_level = ?, semester = ?, description = ?, status = ?, updated_at = NOW() WHERE subject_id = ?",
                        [$programId, $subjectCode, $subjectName, $units, $yearLevel, $semester, $description, $status, $_POST['subject_id']]
                    );
                    header("Location: subjects.php?success=updated");
                    exit;
                }
            }
        }
    }
    
    if ($postAction === 'delete') {
        $deleteId = (int)$_POST['subject_id'];
        $hasOfferings = db()->fetchOne("SELECT COUNT(*) as count FROM subject_offered WHERE subject_id = ?", [$deleteId])['count'];
        if ($hasOfferings > 0) {
            $error = "Cannot delete subject with $hasOfferings offerings.";
        } else {
            db()->execute("DELETE FROM subject WHERE subject_id = ?", [$deleteId]);
            header("Location: subjects.php?success=deleted");
            exit;
        }
    }
}

if (isset($_GET['success'])) {
    $successMessages = ['created' => 'Subject created!', 'updated' => 'Subject updated!', 'deleted' => 'Subject deleted!'];
    $success = $successMessages[$_GET['success']] ?? '';
}

$editSubject = null;
if ($action === 'edit' && $subjectId) {
    $editSubject = db()->fetchOne("SELECT * FROM subject WHERE subject_id = ?", [$subjectId]);
    if (!$editSubject) { header("Location: subjects.php"); exit; }
}

// Build query
$whereClause = "WHERE 1=1";
$params = [];
if ($search) {
    $whereClause .= " AND (s.subject_code LIKE ? OR s.subject_name LIKE ?)";
    $params = ["%$search%", "%$search%"];
}

$subjects = db()->fetchAll(
    "SELECT s.*, p.program_code,
        (SELECT COUNT(*) FROM subject_offered so WHERE so.subject_id = s.subject_id) as offering_count,
        (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.subject_id) as lesson_count
     FROM subject s 
     LEFT JOIN program p ON s.program_id = p.program_id
     $whereClause
     ORDER BY s.subject_code",
    $params
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <?php if ($action === 'list'): ?>
        <div class="page-header">
            <div>
                <h2>Subjects</h2>
                <p class="text-muted">Manage course subjects</p>
            </div>
            <a href="?action=create" class="btn btn-success">+ Add Subject</a>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        
        <!-- Search -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group" style="flex:1">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Subject code or name..." value="<?= e($search) ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($search): ?><a href="subjects.php" class="btn btn-outline">Clear</a><?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Subjects Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Subject Name</th>
                            <th>Program</th>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Units</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subjects)): ?>
                        <tr><td colspan="8" class="text-center text-muted">No subjects found</td></tr>
                        <?php else: ?>
                        <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td><span class="subject-code"><?= e($subject['subject_code']) ?></span></td>
                            <td><?= e($subject['subject_name']) ?></td>
                            <td><?= e($subject['program_code'] ?? 'General') ?></td>
                            <td><?= $subject['year_level'] ? $subject['year_level'] . 'Y' : '-' ?></td>
                            <td><?= $subject['semester'] ? ($subject['semester'] == 1 ? '1st' : ($subject['semester'] == 2 ? '2nd' : 'Summer')) : '-' ?></td>
                            <td><?= $subject['units'] ?></td>
                            <td><span class="badge badge-<?= $subject['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($subject['status']) ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <a href="?action=edit&id=<?= $subject['subject_id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this subject?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="subject_id" value="<?= $subject['subject_id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
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
                <a href="subjects.php" class="back-link">← Back to Subjects</a>
                <h2><?= $action === 'create' ? 'Add New Subject' : 'Edit Subject' ?></h2>
            </div>
        </div>
        
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        
        <form method="POST" class="form-card">
            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
            <?php if ($editSubject): ?><input type="hidden" name="subject_id" value="<?= $editSubject['subject_id'] ?>"><?php endif; ?>
            
            <div class="form-section">
                <h3>📖 Subject Details</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Subject Code <span class="required">*</span></label>
                        <input type="text" name="subject_code" class="form-control" required value="<?= e($editSubject['subject_code'] ?? '') ?>" placeholder="e.g., IT101">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Units</label>
                        <input type="number" name="units" class="form-control" value="<?= e($editSubject['units'] ?? 3) ?>" min="1" max="12">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Subject Name <span class="required">*</span></label>
                    <input type="text" name="subject_name" class="form-control" required value="<?= e($editSubject['subject_name'] ?? '') ?>" placeholder="e.g., Introduction to Computing">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Program</label>
                    <select name="program_id" class="form-control">
                        <option value="">General (All Programs)</option>
                        <?php foreach ($programs as $prog): ?>
                        <option value="<?= $prog['program_id'] ?>" <?= ($editSubject['program_id'] ?? '') == $prog['program_id'] ? 'selected' : '' ?>><?= e($prog['program_code']) ?> - <?= e($prog['program_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Year Level</label>
                        <select name="year_level" class="form-control">
                            <option value="">Not Set</option>
                            <option value="1" <?= ($editSubject['year_level'] ?? '') == 1 ? 'selected' : '' ?>>1st Year</option>
                            <option value="2" <?= ($editSubject['year_level'] ?? '') == 2 ? 'selected' : '' ?>>2nd Year</option>
                            <option value="3" <?= ($editSubject['year_level'] ?? '') == 3 ? 'selected' : '' ?>>3rd Year</option>
                            <option value="4" <?= ($editSubject['year_level'] ?? '') == 4 ? 'selected' : '' ?>>4th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Semester</label>
                        <select name="semester" class="form-control">
                            <option value="">Not Set</option>
                            <option value="1" <?= ($editSubject['semester'] ?? '') == 1 ? 'selected' : '' ?>>1st Semester</option>
                            <option value="2" <?= ($editSubject['semester'] ?? '') == 2 ? 'selected' : '' ?>>2nd Semester</option>
                            <option value="3" <?= ($editSubject['semester'] ?? '') == 3 ? 'selected' : '' ?>>Summer</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= e($editSubject['description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="status" value="active" <?= ($editSubject['status'] ?? 'active') === 'active' ? 'checked' : '' ?>> Active</label>
                        <label class="radio-label"><input type="radio" name="status" value="inactive" <?= ($editSubject['status'] ?? '') === 'inactive' ? 'checked' : '' ?>> Inactive</label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="subjects.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-success">💾 <?= $action === 'create' ? 'Create Subject' : 'Save Changes' ?></button>
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
.filters-form{display:flex;gap:16px;align-items:flex-end}
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
.subject-code{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);color:#1e3a8a;padding:6px 14px;border-radius:20px;font-weight:700;font-size:13px;box-shadow:0 2px 4px rgba(0,0,0,0.08)}
.action-btns{display:flex;gap:10px}

.form-card{background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05)}
.form-section{padding:28px}
.form-section h3{margin:0 0 28px;font-size:20px;color:#111827;font-weight:800;display:flex;align-items:center;gap:10px}
.form-row{display:grid;grid-template-columns:2fr 1fr;gap:24px}
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