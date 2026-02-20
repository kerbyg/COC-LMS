<?php
/**
 * Dean - Subject Offerings Overview (View Only)
 * Monitor subject offerings - NO create/edit/delete
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('dean');

$pageTitle = 'Subject Offerings Overview';
$currentPage = 'subject-offerings';
$userId = Auth::id();

// Get dean's department
$dean = db()->fetchOne("SELECT department_id FROM users WHERE users_id = ?", [$userId]);
$deptId = $dean['department_id'] ?? 0;

if (!$deptId) {
    die("Error: Dean is not assigned to a department.");
}

// Get department info
$department = db()->fetchOne("SELECT department_name FROM department WHERE department_id = ?", [$deptId]);

$semesterFilter = $_GET['semester'] ?? '';

// Get semesters for filter
$semesters = db()->fetchAll("SELECT semester_id, semester_name, academic_year FROM semester ORDER BY academic_year DESC, semester_name");

// Build query - only show offerings for dean's department
$whereClause = "WHERE dp.department_id = ?";
$params = [$deptId];

if ($semesterFilter) {
    $whereClause .= " AND so.semester_id = ?";
    $params[] = $semesterFilter;
}

$offerings = db()->fetchAll(
    "SELECT so.*, s.subject_code, s.subject_name, s.units, sem.academic_year, sem.semester_name,
        (SELECT COUNT(*) FROM section sec WHERE sec.subject_offered_id = so.subject_offered_id) as section_count,
        (SELECT COUNT(*) FROM faculty_subject fs WHERE fs.subject_offered_id = so.subject_offered_id AND fs.status = 'active') as instructor_count,
        (SELECT COUNT(*) FROM student_subject ss JOIN section sec ON ss.section_id = sec.section_id WHERE sec.subject_offered_id = so.subject_offered_id) as student_count
     FROM subject_offered so
     JOIN subject s ON so.subject_id = s.subject_id
     JOIN department_program dp ON s.program_id = dp.program_id
     LEFT JOIN semester sem ON so.semester_id = sem.semester_id
     $whereClause
     ORDER BY sem.academic_year DESC, s.subject_code",
    $params
);

// Quick stats
$stats = [
    'total' => count($offerings),
    'active' => count(array_filter($offerings, fn($o) => $o['status'] === 'active' || $o['status'] === 'open')),
    'total_sections' => array_sum(array_column($offerings, 'section_count')),
    'total_students' => array_sum(array_column($offerings, 'student_count'))
];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <div class="page-header">
            <div>
                <h2>Subject Offerings Overview</h2>
                <p class="text-muted"><?= e($department['department_name'] ?? 'Department') ?> - Monitoring View</p>
            </div>
            <div class="header-note">
                <span class="note-icon">ℹ️</span>
                <span>View only - Contact Admin to add/edit offerings</span>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['total'] ?></span>
                    <span class="stat-label">Total Offerings</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['active'] ?></span>
                    <span class="stat-label">Active Offerings</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏫</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['total_sections'] ?></span>
                    <span class="stat-label">Total Sections</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['total_students'] ?></span>
                    <span class="stat-label">Total Enrollments</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Semester</label>
                    <select name="semester" class="form-control">
                        <option value="">All Semesters</option>
                        <?php foreach ($semesters as $sem): ?>
                        <option value="<?= $sem['semester_id'] ?>" <?= $semesterFilter == $sem['semester_id'] ? 'selected' : '' ?>>
                            <?= e($sem['semester_name']) ?> (<?= e($sem['academic_year']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <?php if ($semesterFilter): ?>
                    <a href="subject-offerings.php" class="btn btn-outline">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Offerings Table -->
        <?php if (empty($offerings)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📚</div>
            <h3>No Subject Offerings Found</h3>
            <p><?= $semesterFilter ? 'Try adjusting your filters.' : 'No offerings available for your department yet.' ?></p>
        </div>
        <?php else: ?>
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
                        <?php foreach ($offerings as $offering): ?>
                        <tr>
                            <td>
                                <span class="subject-code"><?= e($offering['subject_code']) ?></span>
                                <span class="subject-name"><?= e($offering['subject_name']) ?></span>
                            </td>
                            <td><?= e($offering['academic_year'] ?? 'N/A') ?></td>
                            <td><span class="badge badge-info"><?= e($offering['semester_name'] ?? 'N/A') ?></span></td>
                            <td><?= $offering['units'] ?></td>
                            <td><span class="badge badge-primary"><?= $offering['section_count'] ?></span></td>
                            <td><span class="badge badge-success"><?= $offering['instructor_count'] ?></span></td>
                            <td><?= $offering['student_count'] ?></td>
                            <td><span class="badge badge-<?= ($offering['status'] === 'active' || $offering['status'] === 'open') ? 'success' : 'secondary' ?>"><?= ucfirst($offering['status']) ?></span></td>
                            <td>
                                <a href="sections.php?offering_id=<?= $offering['subject_offered_id'] ?>" class="btn btn-outline btn-sm">View Sections</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px}
.page-header h2{font-size:28px;font-weight:800;margin:0 0 6px;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.text-muted{color:#6b7280;margin:0;font-size:15px}

.header-note{display:flex;align-items:center;gap:8px;padding:10px 16px;background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;font-size:13px;color:#92400e}
.note-icon{font-size:16px}

/* Stats Grid */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:28px}
.stat-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;display:flex;align-items:center;gap:16px}
.stat-icon{font-size:32px}
.stat-info{display:flex;flex-direction:column}
.stat-value{font-size:28px;font-weight:800;color:#111827}
.stat-label{font-size:13px;color:#6b7280;font-weight:500}

.filters-card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;margin-bottom:28px}
.filters-form{display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap}
.filter-group{display:flex;flex-direction:column;gap:8px}
.filter-group label{font-size:12px;font-weight:700;color:#374151;text-transform:uppercase}

.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden}
.table-responsive{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse}
.data-table th,.data-table td{padding:16px 20px;text-align:left;border-bottom:1px solid #e5e7eb}
.data-table th{background:#f9fafb;font-weight:700;font-size:12px;color:#6b7280;text-transform:uppercase}
.data-table tbody tr:hover{background:#f9fafb}
.subject-code{background:linear-gradient(135deg,#fef3c7,#fde68a);color:#1e3a8a;padding:6px 14px;border-radius:20px;font-weight:700;font-size:12px;display:inline-block;margin-right:10px}
.subject-name{display:block;font-size:13px;color:#6b7280;margin-top:6px}

.empty-state{text-align:center;padding:60px 24px;background:#fff;border:2px dashed #e5e7eb;border-radius:16px}
.empty-state-icon{font-size:56px;margin-bottom:16px;opacity:0.5}
.empty-state h3{margin:0 0 8px;font-size:20px;color:#374151}
.empty-state p{margin:0;color:#6b7280;font-size:14px}

.badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700}
.badge-success{background:#d1fae5;color:#065f46}
.badge-primary{background:#dbeafe;color:#1e40af}
.badge-info{background:#e0f2fe;color:#0369a1}
.badge-secondary{background:#e5e7eb;color:#6b7280}

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
    .stats-grid{grid-template-columns:1fr}
    .page-header{flex-direction:column;align-items:flex-start;gap:16px}
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
