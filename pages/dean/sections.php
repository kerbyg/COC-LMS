<?php
/**
 * Dean - Sections Overview (View Only)
 * Monitor class sections for the dean's department
 * Features: View sections, view students, stats - NO create/edit/delete
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('dean');

$pageTitle = 'Sections Overview';
$currentPage = 'sections';
$userId = Auth::id();

// Get dean's department
$dean = db()->fetchOne("SELECT department_id FROM users WHERE users_id = ?", [$userId]);
$deptId = $dean['department_id'] ?? 0;

if (!$deptId) {
    die("Error: Dean is not assigned to a department.");
}

// Get department info
$department = db()->fetchOne("SELECT department_name, department_code FROM department WHERE department_id = ?", [$deptId]);

$action = $_GET['action'] ?? 'list';
$sectionId = $_GET['id'] ?? '';
$offeringFilter = $_GET['offering_id'] ?? '';
$semesterFilter = $_GET['semester'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Get current academic settings
$currentYear = db()->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'academic_year'")['setting_value'] ?? date('Y') . '-' . (date('Y') + 1);

// Get offerings for dean's department only (for filter)
$offerings = db()->fetchAll(
    "SELECT so.subject_offered_id, sem.academic_year, sem.semester_name as semester, s.subject_code, s.subject_name
     FROM subject_offered so
     JOIN subject s ON so.subject_id = s.subject_id
     LEFT JOIN semester sem ON so.semester_id = sem.semester_id
     JOIN department_program dp ON s.program_id = dp.program_id
     WHERE so.status IN ('open', 'active') AND dp.department_id = ?
     ORDER BY sem.academic_year DESC, sem.semester_name, s.subject_code",
    [$deptId]
);

// Get unique semesters for filter
$semesters = db()->fetchAll(
    "SELECT DISTINCT sem.semester_name as semester FROM subject_offered so
     JOIN subject s ON so.subject_id = s.subject_id
     LEFT JOIN semester sem ON so.semester_id = sem.semester_id
     JOIN department_program dp ON s.program_id = dp.program_id
     WHERE dp.department_id = ? AND sem.semester_name IS NOT NULL
     ORDER BY sem.semester_name",
    [$deptId]
);

// View students in section
$viewStudents = null;
$sectionStudents = [];
if ($action === 'students' && $sectionId) {
    $viewStudents = db()->fetchOne(
        "SELECT sec.*, sem.academic_year, sem.semester_name as semester, s.subject_code, s.subject_name,
            (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM faculty_subject fs JOIN users u ON fs.user_teacher_id = u.users_id WHERE fs.section_id = sec.section_id LIMIT 1) as instructor_name
         FROM section sec
         JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
         JOIN subject s ON so.subject_id = s.subject_id
         LEFT JOIN semester sem ON so.semester_id = sem.semester_id
         JOIN department_program dp ON s.program_id = dp.program_id
         WHERE sec.section_id = ? AND dp.department_id = ?",
        [$sectionId, $deptId]
    );

    if ($viewStudents) {
        $sectionStudents = db()->fetchAll(
            "SELECT u.users_id, u.first_name, u.last_name, u.email, u.student_id, ss.status, ss.enrollment_date as enrolled_at
             FROM student_subject ss
             JOIN users u ON ss.user_student_id = u.users_id
             WHERE ss.section_id = ?
             ORDER BY u.last_name, u.first_name",
            [$sectionId]
        );
    } else {
        header("Location: sections.php");
        exit;
    }
}

// Build query - only show sections for dean's department
$whereClause = "WHERE dp.department_id = ?";
$params = [$deptId];

if ($offeringFilter) {
    $whereClause .= " AND sec.subject_offered_id = ?";
    $params[] = $offeringFilter;
}
if ($semesterFilter) {
    $whereClause .= " AND sem.semester_name = ?";
    $params[] = $semesterFilter;
}
if ($statusFilter) {
    $whereClause .= " AND sec.status = ?";
    $params[] = $statusFilter;
}
if ($searchQuery) {
    $whereClause .= " AND (sec.section_name LIKE ? OR s.subject_code LIKE ? OR s.subject_name LIKE ? OR sec.enrollment_code LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$sections = db()->fetchAll(
    "SELECT sec.*, sem.academic_year, sem.semester_name as semester, s.subject_code, s.subject_name,
        (SELECT COUNT(*) FROM student_subject ss WHERE ss.section_id = sec.section_id AND ss.status = 'enrolled') as student_count,
        (SELECT CONCAT(u.first_name, ' ', u.last_name) FROM faculty_subject fs JOIN users u ON fs.user_teacher_id = u.users_id WHERE fs.section_id = sec.section_id LIMIT 1) as instructor_name
     FROM section sec
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     JOIN department_program dp ON s.program_id = dp.program_id
     LEFT JOIN semester sem ON so.semester_id = sem.semester_id
     $whereClause
     ORDER BY sem.academic_year DESC, sem.semester_name DESC, s.subject_code, sec.section_name",
    $params
);

// Quick stats
$stats = [
    'total' => count($sections),
    'active' => count(array_filter($sections, fn($s) => $s['status'] === 'active')),
    'total_students' => array_sum(array_column($sections, 'student_count')),
    'without_instructor' => count(array_filter($sections, fn($s) => !$s['instructor_name']))
];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($action === 'students' && $viewStudents): ?>
        <!-- VIEW STUDENTS -->
        <div class="page-header">
            <div>
                <a href="sections.php" class="back-link">← Back to Sections</a>
                <h2><?= e($viewStudents['section_name']) ?> - Students</h2>
                <p class="text-muted"><?= e($viewStudents['subject_code']) ?> - <?= e($viewStudents['subject_name']) ?></p>
            </div>
        </div>

        <div class="info-card">
            <div class="info-row">
                <span><strong>Enrollment Code:</strong> <?= e($viewStudents['enrollment_code']) ?></span>
                <span><strong>Instructor:</strong> <?= e($viewStudents['instructor_name'] ?: 'Not assigned') ?></span>
                <span><strong>Schedule:</strong> <?= e($viewStudents['schedule'] ?: 'Not set') ?></span>
                <span><strong>Room:</strong> <?= e($viewStudents['room'] ?: 'Not set') ?></span>
                <span><strong>Capacity:</strong> <?= count($sectionStudents) ?>/<?= $viewStudents['max_students'] ?></span>
            </div>
        </div>

        <?php if (empty($sectionStudents)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">👥</div>
            <h3>No Students Enrolled</h3>
            <p>No students have enrolled in this section yet.</p>
        </div>
        <?php else: ?>
        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Enrolled Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sectionStudents as $idx => $student): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><strong><?= e($student['student_id'] ?? 'N/A') ?></strong></td>
                        <td><?= e($student['last_name'] . ', ' . $student['first_name']) ?></td>
                        <td><?= e($student['email']) ?></td>
                        <td><span class="badge badge-<?= $student['status'] === 'enrolled' ? 'success' : 'secondary' ?>"><?= ucfirst($student['status']) ?></span></td>
                        <td><?= $student['enrolled_at'] ? date('M d, Y', strtotime($student['enrolled_at'])) : 'N/A' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- LIST VIEW -->
        <div class="page-header">
            <div>
                <h2>Sections Overview</h2>
                <p class="text-muted"><?= e($department['department_name'] ?? 'Department') ?> - <?= e($currentYear) ?></p>
            </div>
            <div class="header-note">
                <span class="note-icon">ℹ️</span>
                <span>View only - Contact Admin to add/edit sections</span>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">🏫</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['total'] ?></span>
                    <span class="stat-label">Total Sections</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['active'] ?></span>
                    <span class="stat-label">Active Sections</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['total_students'] ?></span>
                    <span class="stat-label">Total Students</span>
                </div>
            </div>
            <div class="stat-card <?= $stats['without_instructor'] > 0 ? 'warning' : '' ?>">
                <div class="stat-icon">⚠️</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['without_instructor'] ?></span>
                    <span class="stat-label">No Instructor</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Section, code, subject..." value="<?= e($searchQuery) ?>">
                </div>
                <div class="filter-group">
                    <label>Subject Offering</label>
                    <select name="offering_id" class="form-control">
                        <option value="">All Offerings</option>
                        <?php foreach ($offerings as $off): ?>
                        <option value="<?= $off['subject_offered_id'] ?>" <?= $offeringFilter == $off['subject_offered_id'] ? 'selected' : '' ?>>
                            <?= e($off['subject_code']) ?> (<?= e($off['semester']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Semester</label>
                    <select name="semester" class="form-control">
                        <option value="">All Semesters</option>
                        <?php foreach ($semesters as $sem): ?>
                        <option value="<?= e($sem['semester']) ?>" <?= $semesterFilter == $sem['semester'] ? 'selected' : '' ?>><?= e($sem['semester']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?= $statusFilter == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $statusFilter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <?php if ($offeringFilter || $semesterFilter || $statusFilter || $searchQuery): ?>
                    <a href="sections.php" class="btn btn-outline">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Sections Grid -->
        <?php if (empty($sections)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🏫</div>
            <h3>No Sections Found</h3>
            <p><?= $searchQuery || $offeringFilter || $semesterFilter || $statusFilter ? 'Try adjusting your filters.' : 'No sections available for your department yet.' ?></p>
        </div>
        <?php else: ?>
        <div class="sections-grid">
            <?php foreach ($sections as $section): ?>
            <div class="section-card <?= $section['status'] !== 'active' ? 'inactive-card' : '' ?>">
                <div class="section-header">
                    <span class="section-subject"><?= e($section['subject_code']) ?></span>
                    <span class="badge badge-<?= $section['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst($section['status']) ?></span>
                </div>
                <h3 class="section-name"><?= e($section['section_name']) ?></h3>
                <p class="section-subject-name"><?= e($section['subject_name']) ?></p>

                <div class="enrollment-code-box">
                    <div class="code-info">
                        <span class="code-label">Enrollment Code</span>
                        <span class="code-value"><?= e($section['enrollment_code'] ?? 'N/A') ?></span>
                    </div>
                    <button class="btn-icon" onclick="copyCode('<?= e($section['enrollment_code']) ?>')" title="Copy code">📋</button>
                </div>

                <div class="section-details">
                    <div class="detail"><span class="detail-icon">📅</span><?= e($section['academic_year'] ?? 'N/A') ?> - <?= e($section['semester'] ?? 'N/A') ?></div>
                    <?php if ($section['schedule']): ?><div class="detail"><span class="detail-icon">🕐</span><?= e($section['schedule']) ?></div><?php endif; ?>
                    <?php if ($section['room']): ?><div class="detail"><span class="detail-icon">🚪</span><?= e($section['room']) ?></div><?php endif; ?>
                    <div class="detail <?= empty($section['instructor_name']) ? 'warning-text' : '' ?>">
                        <span class="detail-icon">👨‍🏫</span>
                        <?= $section['instructor_name'] ? e($section['instructor_name']) : '<em>No instructor assigned</em>' ?>
                    </div>
                </div>

                <div class="section-stats">
                    <div class="enrollment">
                        <div class="enrollment-header">
                            <span class="enrollment-num"><?= $section['student_count'] ?>/<?= $section['max_students'] ?></span>
                            <a href="?action=students&id=<?= $section['section_id'] ?>" class="view-students-link">View Students →</a>
                        </div>
                        <span class="enrollment-label">Students Enrolled</span>
                        <div class="enrollment-bar">
                            <div class="enrollment-fill <?= ($section['student_count'] / max(1, $section['max_students'])) >= 0.9 ? 'full' : '' ?>"
                                 style="width: <?= min(100, ($section['student_count'] / max(1, $section['max_students'])) * 100) ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="section-actions">
                    <a href="?action=students&id=<?= $section['section_id'] ?>" class="btn btn-outline btn-sm">👥 View Students</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</main>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px}
.page-header h2{font-size:28px;font-weight:800;margin:0 0 6px;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.text-muted{color:#6b7280;margin:0;font-size:15px}
.back-link{color:#3b82f6;font-size:14px;display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;font-weight:600;text-decoration:none}
.back-link:hover{gap:8px;color:#1e3a8a}

.header-note{display:flex;align-items:center;gap:8px;padding:10px 16px;background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;font-size:13px;color:#92400e}
.note-icon{font-size:16px}

/* Stats Grid */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:28px}
.stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;display:flex;align-items:center;gap:16px}
.stat-card.warning{border-color:#fbbf24;background:#fffbeb}
.stat-icon{font-size:32px}
.stat-info{display:flex;flex-direction:column}
.stat-value{font-size:28px;font-weight:800;color:#111827}
.stat-label{font-size:13px;color:#6b7280;font-weight:500}

/* Filters */
.filters-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;margin-bottom:28px}
.filters-form{display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap}
.filter-group{display:flex;flex-direction:column;gap:8px;min-width:150px}
.filter-group label{font-size:12px;font-weight:700;color:#374151;text-transform:uppercase}

/* Sections Grid */
.sections-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:24px}
.section-card{background:#fff;border:2px solid #e5e7eb;border-radius:16px;padding:24px;transition:all 0.3s}
.section-card:hover{border-color:#3b82f6;box-shadow:0 8px 20px rgba(59,130,246,0.15);transform:translateY(-4px)}
.section-card.inactive-card{opacity:0.7;background:#f9fafb}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.section-subject{background:linear-gradient(135deg,#fef3c7,#fde68a);color:#1e3a8a;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:800}
.section-name{margin:0 0 4px;font-size:20px;font-weight:800;color:#111827}
.section-subject-name{margin:0 0 16px;font-size:14px;color:#6b7280}

/* Enrollment Code Box */
.enrollment-code-box{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:linear-gradient(135deg,#ecfdf5,#d1fae5);border-radius:10px;margin-bottom:16px;border:1px solid #10b981}
.code-info{display:flex;flex-direction:column}
.code-label{font-size:11px;font-weight:700;color:#065f46;text-transform:uppercase}
.code-value{font-size:18px;font-weight:900;color:#065f46;font-family:monospace;letter-spacing:2px}
.btn-icon{background:#fff;border:1px solid #d1d5db;padding:6px 10px;border-radius:6px;cursor:pointer;font-size:14px;transition:all 0.2s}
.btn-icon:hover{background:#f3f4f6;transform:scale(1.1)}

/* Section Details */
.section-details{display:flex;flex-direction:column;gap:8px;margin-bottom:16px;padding:14px;background:#f9fafb;border-radius:10px}
.detail{display:flex;align-items:center;gap:10px;font-size:13px;color:#374151;font-weight:500}
.detail-icon{font-size:16px;width:22px;text-align:center}
.warning-text{color:#d97706}
.warning-text em{font-style:italic}

/* Enrollment Stats */
.section-stats{padding:16px 0;border-top:1px solid #e5e7eb;margin-bottom:16px}
.enrollment-header{display:flex;justify-content:space-between;align-items:baseline}
.enrollment-num{font-size:24px;font-weight:800;color:#1e3a8a}
.view-students-link{font-size:12px;color:#3b82f6;font-weight:600;text-decoration:none}
.view-students-link:hover{text-decoration:underline}
.enrollment-label{font-size:12px;color:#6b7280;font-weight:600;text-transform:uppercase;display:block;margin:4px 0 8px}
.enrollment-bar{height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden}
.enrollment-fill{height:100%;background:linear-gradient(90deg,#3b82f6,#1e3a8a);border-radius:4px;transition:width 0.5s}
.enrollment-fill.full{background:linear-gradient(90deg,#f59e0b,#d97706)}

/* Section Actions */
.section-actions{display:flex;gap:8px;flex-wrap:wrap}

/* Table */
.table-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden}
.data-table{width:100%;border-collapse:collapse}
.data-table th,.data-table td{padding:14px 20px;text-align:left;border-bottom:1px solid #e5e7eb}
.data-table th{background:#f9fafb;font-size:12px;font-weight:700;text-transform:uppercase;color:#6b7280}
.data-table tbody tr:hover{background:#f9fafb}

/* Info Card */
.info-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;margin-bottom:24px}
.info-row{display:flex;gap:32px;flex-wrap:wrap}
.info-row span{font-size:14px;color:#374151}

/* Empty State */
.empty-state{text-align:center;padding:60px 24px;background:#fff;border:2px dashed #e5e7eb;border-radius:16px}
.empty-state-icon{font-size:56px;margin-bottom:16px;opacity:0.5}
.empty-state h3{margin:0 0 8px;font-size:20px;color:#374151}
.empty-state p{margin:0;color:#6b7280;font-size:14px}

/* Badges */
.badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700}
.badge-success{background:#d1fae5;color:#065f46}
.badge-secondary{background:#e5e7eb;color:#6b7280}

/* Buttons */
.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 20px;border-radius:8px;font-weight:600;font-size:14px;cursor:pointer;text-decoration:none;border:none;transition:all 0.2s}
.btn-primary{background:#3b82f6;color:#fff}
.btn-primary:hover{background:#2563eb}
.btn-outline{background:#fff;border:1px solid #d1d5db;color:#374151}
.btn-outline:hover{border-color:#3b82f6;color:#3b82f6}
.btn-sm{padding:6px 12px;font-size:12px}

@media(max-width:1024px){
    .stats-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:768px){
    .sections-grid{grid-template-columns:1fr}
    .stats-grid{grid-template-columns:1fr}
    .filters-form{flex-direction:column}
    .filter-group{width:100%}
    .page-header{flex-direction:column;align-items:flex-start;gap:16px}
}
</style>

<script>
function copyCode(code) {
    navigator.clipboard.writeText(code).then(function() {
        showToast('Enrollment code copied: ' + code);
    }).catch(function() {
        prompt('Copy this code:', code);
    });
}

function showToast(message) {
    const toast = document.createElement('div');
    toast.textContent = message;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;background:#10b981;color:#fff;padding:14px 24px;border-radius:8px;font-weight:600;z-index:10000;animation:slideIn 0.3s ease;box-shadow:0 4px 12px rgba(0,0,0,0.15)';
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100px); opacity: 0; } }
`;
document.head.appendChild(style);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
