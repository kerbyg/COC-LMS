<?php
/**
 * Admin - Subject Offerings
 * Manage semester offerings
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('admin');

$pageTitle = 'Subject Offerings';
$currentPage = 'subject-offerings';

$action = $_GET['action'] ?? 'list';
$offeringId = $_GET['id'] ?? '';
$yearFilter = $_GET['year'] ?? '';
$semesterFilter = $_GET['semester'] ?? '';
$error = '';
$success = '';

// Get subjects and instructors
$subjects = db()->fetchAll("SELECT subject_id, subject_code, subject_name FROM subject WHERE status = 'active' ORDER BY subject_code");
$instructors = db()->fetchAll("SELECT users_id, first_name, last_name, employee_id FROM users WHERE role = 'instructor' AND status = 'active' ORDER BY last_name");

// Academic years
$currentYear = date('Y');
$academicYears = [];
for ($i = $currentYear - 2; $i <= $currentYear + 1; $i++) {
    $academicYears[] = "$i-" . ($i + 1);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create' || $postAction === 'update') {
        $subjectId = $_POST['subject_id'] ?? '';
        $academicYear = $_POST['academic_year'] ?? '';
        $semester = $_POST['semester'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        if (empty($subjectId) || empty($academicYear) || empty($semester)) {
            $error = 'Please fill in all required fields.';
        } else {
            // Check if already exists
            $existing = db()->fetchOne(
                "SELECT subject_offered_id FROM subject_offered WHERE subject_id = ? AND academic_year = ? AND semester = ? AND subject_offered_id != ?",
                [$subjectId, $academicYear, $semester, $_POST['offering_id'] ?? 0]
            );
            
            if ($existing) {
                $error = 'This subject is already offered for this semester.';
            } else {
                if ($postAction === 'create') {
                    db()->execute(
                        "INSERT INTO subject_offered (subject_id, academic_year, semester, status, created_at, updated_at)
                         VALUES (?, ?, ?, ?, NOW(), NOW())",
                        [$subjectId, $academicYear, $semester, $status]
                    );
                    header("Location: subject-offerings.php?success=created");
                    exit;
                } else {
                    db()->execute(
                        "UPDATE subject_offered SET subject_id = ?, academic_year = ?, semester = ?, status = ?, updated_at = NOW() WHERE subject_offered_id = ?",
                        [$subjectId, $academicYear, $semester, $status, $_POST['offering_id']]
                    );
                    header("Location: subject-offerings.php?success=updated");
                    exit;
                }
            }
        }
    }
    
    if ($postAction === 'delete') {
        $deleteId = (int)$_POST['offering_id'];
        $hasSections = db()->fetchOne("SELECT COUNT(*) as count FROM section WHERE subject_offered_id = ?", [$deleteId])['count'];
        if ($hasSections > 0) {
            $error = "Cannot delete offering with $hasSections sections.";
        } else {
            db()->execute("DELETE FROM faculty_subject WHERE subject_offered_id = ?", [$deleteId]);
            db()->execute("DELETE FROM subject_offered WHERE subject_offered_id = ?", [$deleteId]);
            header("Location: subject-offerings.php?success=deleted");
            exit;
        }
    }
}

if (isset($_GET['success'])) {
    $successMessages = ['created' => 'Offering created!', 'updated' => 'Offering updated!', 'deleted' => 'Offering deleted!'];
    $success = $successMessages[$_GET['success']] ?? '';
}

$editOffering = null;
if ($action === 'edit' && $offeringId) {
    $editOffering = db()->fetchOne("SELECT * FROM subject_offered WHERE subject_offered_id = ?", [$offeringId]);
    if (!$editOffering) { header("Location: subject-offerings.php"); exit; }
}

// Build query
$whereClause = "WHERE 1=1";
$params = [];
if ($yearFilter) { $whereClause .= " AND so.academic_year = ?"; $params[] = $yearFilter; }
if ($semesterFilter) { $whereClause .= " AND so.semester = ?"; $params[] = $semesterFilter; }

$offerings = db()->fetchAll(
    "SELECT so.*, s.subject_code, s.subject_name, s.units,
        (SELECT COUNT(*) FROM section sec WHERE sec.subject_offered_id = so.subject_offered_id) as section_count,
        (SELECT COUNT(*) FROM faculty_subject fs WHERE fs.subject_offered_id = so.subject_offered_id AND fs.status = 'active') as instructor_count,
        (SELECT COUNT(*) FROM student_subject ss JOIN section sec ON ss.section_id = sec.section_id WHERE sec.subject_offered_id = so.subject_offered_id) as student_count
     FROM subject_offered so
     JOIN subject s ON so.subject_id = s.subject_id
     $whereClause
     ORDER BY so.academic_year DESC, so.semester DESC, s.subject_code",
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
                <h2>Subject Offerings</h2>
                <p class="text-muted">Manage semester subject offerings</p>
            </div>
            <a href="?action=create" class="btn btn-success">+ Add Offering</a>
        </div>
        
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Academic Year</label>
                    <select name="year" class="form-control">
                        <option value="">All Years</option>
                        <?php foreach ($academicYears as $year): ?>
                        <option value="<?= $year ?>" <?= $yearFilter === $year ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Semester</label>
                    <select name="semester" class="form-control">
                        <option value="">All Semesters</option>
                        <option value="1st" <?= $semesterFilter === '1st' ? 'selected' : '' ?>>1st Semester</option>
                        <option value="2nd" <?= $semesterFilter === '2nd' ? 'selected' : '' ?>>2nd Semester</option>
                        <option value="summer" <?= $semesterFilter === 'summer' ? 'selected' : '' ?>>Summer</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="subject-offerings.php" class="btn btn-outline">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Offerings Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Units</th>
                            <th>Sections</th>
                            <th>Instructors</th>
                            <th>Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($offerings)): ?>
                        <tr><td colspan="9" class="text-center text-muted">No offerings found</td></tr>
                        <?php else: ?>
                        <?php foreach ($offerings as $offering): ?>
                        <tr>
                            <td>
                                <span class="subject-code"><?= e($offering['subject_code']) ?></span>
                                <span class="subject-name"><?= e($offering['subject_name']) ?></span>
                            </td>
                            <td><?= e($offering['academic_year']) ?></td>
                            <td><span class="badge badge-info"><?= e($offering['semester']) ?></span></td>
                            <td><?= $offering['units'] ?></td>
                            <td><span class="badge badge-primary"><?= $offering['section_count'] ?></span></td>
                            <td><span class="badge badge-success"><?= $offering['instructor_count'] ?></span></td>
                            <td><?= $offering['student_count'] ?></td>
                            <td><span class="badge badge-<?= $offering['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($offering['status']) ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <a href="sections.php?offering_id=<?= $offering['subject_offered_id'] ?>" class="btn btn-outline btn-sm">Sections</a>
                                    <a href="?action=edit&id=<?= $offering['subject_offered_id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this offering?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="offering_id" value="<?= $offering['subject_offered_id'] ?>">
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
                <a href="subject-offerings.php" class="back-link">← Back to Offerings</a>
                <h2><?= $action === 'create' ? 'Add New Offering' : 'Edit Offering' ?></h2>
            </div>
        </div>
        
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        
        <form method="POST" class="form-card">
            <input type="hidden" name="action" value="<?= $action === 'create' ? 'create' : 'update' ?>">
            <?php if ($editOffering): ?><input type="hidden" name="offering_id" value="<?= $editOffering['subject_offered_id'] ?>"><?php endif; ?>
            
            <div class="form-section">
                <h3>📅 Offering Details</h3>
                
                <div class="form-group">
                    <label class="form-label">Subject <span class="required">*</span></label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">-- Select Subject --</option>
                        <?php if (empty($subjects)): ?>
                        <option value="" disabled>No active subjects found. Please add subjects first.</option>
                        <?php else: ?>
                        <?php foreach ($subjects as $subj): ?>
                        <option value="<?= $subj['subject_id'] ?>" <?= ($editOffering['subject_id'] ?? '') == $subj['subject_id'] ? 'selected' : '' ?>>
                            <?= e($subj['subject_code']) ?> - <?= e($subj['subject_name']) ?>
                        </option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($subjects)): ?>
                    <small style="color:#dc2626;margin-top:8px;display:block">⚠️ No subjects available. Please <a href="subjects.php">add subjects</a> first.</small>
                    <?php endif; ?>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Academic Year <span class="required">*</span></label>
                        <select name="academic_year" class="form-control" required>
                            <option value="">Select Year</option>
                            <?php foreach ($academicYears as $year): ?>
                            <option value="<?= $year ?>" <?= ($editOffering['academic_year'] ?? '') === $year ? 'selected' : '' ?>><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Semester <span class="required">*</span></label>
                        <select name="semester" class="form-control" required>
                            <option value="">Select Semester</option>
                            <option value="1st" <?= ($editOffering['semester'] ?? '') === '1st' ? 'selected' : '' ?>>1st Semester</option>
                            <option value="2nd" <?= ($editOffering['semester'] ?? '') === '2nd' ? 'selected' : '' ?>>2nd Semester</option>
                            <option value="summer" <?= ($editOffering['semester'] ?? '') === 'summer' ? 'selected' : '' ?>>Summer</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <div class="radio-group">
                        <label class="radio-label"><input type="radio" name="status" value="active" <?= ($editOffering['status'] ?? 'active') === 'active' ? 'checked' : '' ?>> Active</label>
                        <label class="radio-label"><input type="radio" name="status" value="inactive" <?= ($editOffering['status'] ?? '') === 'inactive' ? 'checked' : '' ?>> Inactive</label>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="subject-offerings.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-success">💾 <?= $action === 'create' ? 'Create Offering' : 'Save Changes' ?></button>
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
.subject-code{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);color:#1e3a8a;padding:6px 14px;border-radius:20px;font-weight:700;font-size:13px;display:inline-block;margin-right:10px;box-shadow:0 2px 4px rgba(0,0,0,0.08)}
.subject-name{display:block;font-size:14px;color:#6b7280;margin-top:6px;font-weight:500}
.action-btns{display:flex;gap:10px}

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

@media(max-width:768px){.form-row{grid-template-columns:1fr}.page-header h2{font-size:22px}}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>