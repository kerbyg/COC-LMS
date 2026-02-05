<?php
/**
 * Dean - Instructors
 * View and monitor faculty members in dean's department (all programs)
 * Now with program assignment management
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('dean');

$pageTitle = 'Instructors';
$currentPage = 'instructors';
$userId = Auth::id();
$success = '';
$error = '';

// Get dean's department
$dean = db()->fetchOne("SELECT department_id FROM users WHERE users_id = ?", [$userId]);
$deptId = $dean['department_id'] ?? 0;

// Get department info for display
$department = null;
if ($deptId) {
    $department = db()->fetchOne("SELECT department_name, department_code FROM department WHERE department_id = ?", [$deptId]);
}

// Get programs under this department (for assignment modal)
$programs = [];
if ($deptId) {
    $programs = db()->fetchAll(
        "SELECT program_id, program_code, program_name FROM program WHERE department_id = ? AND status = 'active' ORDER BY program_code",
        [$deptId]
    );
}

// Handle AJAX requests for program assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'get_assignments') {
        $teacherId = intval($_POST['teacher_id'] ?? 0);
        $assignments = db()->fetchAll(
            "SELECT fp.faculty_program_id, fp.program_id, fp.role, p.program_code, p.program_name
             FROM faculty_program fp
             JOIN program p ON fp.program_id = p.program_id
             WHERE fp.user_teacher_id = ? AND fp.status = 'active'",
            [$teacherId]
        );
        echo json_encode(['success' => true, 'assignments' => $assignments]);
        exit;
    }

    if ($_POST['action'] === 'assign_program') {
        $teacherId = intval($_POST['teacher_id'] ?? 0);
        $programId = intval($_POST['program_id'] ?? 0);
        $role = $_POST['role'] ?? 'faculty';

        if ($teacherId && $programId) {
            // Check if already exists
            $existing = db()->fetchOne(
                "SELECT faculty_program_id FROM faculty_program WHERE user_teacher_id = ? AND program_id = ?",
                [$teacherId, $programId]
            );

            if ($existing) {
                // Update existing
                db()->execute(
                    "UPDATE faculty_program SET status = 'active', role = ? WHERE faculty_program_id = ?",
                    [$role, $existing['faculty_program_id']]
                );
            } else {
                // Insert new
                db()->execute(
                    "INSERT INTO faculty_program (user_teacher_id, program_id, role, assigned_by) VALUES (?, ?, ?, ?)",
                    [$teacherId, $programId, $role, $userId]
                );
            }
            echo json_encode(['success' => true, 'message' => 'Program assigned successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
        }
        exit;
    }

    if ($_POST['action'] === 'remove_assignment') {
        $assignmentId = intval($_POST['assignment_id'] ?? 0);
        if ($assignmentId) {
            db()->execute("UPDATE faculty_program SET status = 'inactive' WHERE faculty_program_id = ?", [$assignmentId]);
            echo json_encode(['success' => true, 'message' => 'Assignment removed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid assignment']);
        }
        exit;
    }
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$programFilter = $_GET['program'] ?? '';

// Build query - filter by dean's department (all programs under this department)
$whereClause = "WHERE u.role = 'instructor' AND u.department_id = ?";
$params = [$deptId];

if ($search) {
    $whereClause .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($statusFilter) {
    $whereClause .= " AND u.status = ?";
    $params[] = $statusFilter;
}

// Get instructors with their teaching statistics
$instructors = db()->fetchAll(
    "SELECT u.*,
        (SELECT COUNT(DISTINCT fs.faculty_subject_id)
         FROM faculty_subject fs
         WHERE fs.user_teacher_id = u.users_id AND fs.status = 'active') as subjects_count,
        (SELECT COUNT(DISTINCT fs.section_id)
         FROM faculty_subject fs
         WHERE fs.user_teacher_id = u.users_id AND fs.status = 'active' AND fs.section_id IS NOT NULL) as sections_count,
        (SELECT COUNT(*)
         FROM student_subject ss
         JOIN faculty_subject fs ON ss.section_id = fs.section_id
         WHERE fs.user_teacher_id = u.users_id AND ss.status = 'enrolled') as students_count
     FROM users u
     $whereClause
     ORDER BY u.last_name, u.first_name",
    $params
);

// Enrich instructors with program assignments
foreach ($instructors as &$inst) {
    $inst['programs'] = db()->fetchAll(
        "SELECT p.program_code, p.program_name, fp.role
         FROM faculty_program fp
         JOIN program p ON fp.program_id = p.program_id
         WHERE fp.user_teacher_id = ? AND fp.status = 'active'
         ORDER BY p.program_code",
        [$inst['users_id']]
    );
}
unset($inst);

// Filter by program if specified
if ($programFilter) {
    $instructors = array_filter($instructors, function($inst) use ($programFilter) {
        foreach ($inst['programs'] as $p) {
            if ($p['program_code'] === $programFilter) return true;
        }
        return false;
    });
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="instructors-wrap">

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-left">
                <div class="header-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div>
                    <h1>Faculty Members</h1>
                    <p><?= e($department['department_name'] ?? 'View and monitor instructor activities') ?></p>
                </div>
            </div>
            <div class="header-stats">
                <div class="stat-box">
                    <span class="stat-num"><?= count($instructors) ?></span>
                    <span class="stat-label">Total Instructors</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="search-box">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="text" name="search" placeholder="Search by name, email, or ID..." value="<?= e($search) ?>">
                </div>
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <select name="program" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $p): ?>
                    <option value="<?= e($p['program_code']) ?>" <?= $programFilter === $p['program_code'] ? 'selected' : '' ?>>
                        <?= e($p['program_code']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                    Search
                </button>
                <?php if ($search || $statusFilter || $programFilter): ?>
                <a href="instructors.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Instructors Grid -->
        <?php if (empty($instructors)): ?>
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <h3>No Instructors Found</h3>
                <p>No instructors match your search criteria.</p>
            </div>
        <?php else: ?>
            <div class="instructors-grid">
                <?php foreach ($instructors as $instructor): ?>
                <div class="instructor-card">
                    <div class="card-header">
                        <div class="instructor-avatar">
                            <?= strtoupper(substr($instructor['first_name'], 0, 1) . substr($instructor['last_name'], 0, 1)) ?>
                        </div>
                        <div class="instructor-info">
                            <h3><?= e($instructor['first_name'] . ' ' . $instructor['last_name']) ?></h3>
                            <p class="instructor-email"><?= e($instructor['email']) ?></p>
                            <?php if ($instructor['employee_id']): ?>
                            <p class="instructor-id"><?= e($instructor['employee_id']) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="status-badge status-<?= $instructor['status'] ?>"><?= ucfirst($instructor['status']) ?></span>
                    </div>

                    <!-- Program Assignments -->
                    <div class="programs-section">
                        <div class="programs-header">
                            <span class="programs-label">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                                    <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                                </svg>
                                Programs
                            </span>
                            <button type="button" class="btn-manage-programs" onclick="openProgramModal(<?= $instructor['users_id'] ?>, '<?= e($instructor['first_name'] . ' ' . $instructor['last_name']) ?>')">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                                Manage
                            </button>
                        </div>
                        <div class="programs-list">
                            <?php if (empty($instructor['programs'])): ?>
                            <span class="no-programs">No programs assigned</span>
                            <?php else: ?>
                                <?php foreach ($instructor['programs'] as $prog): ?>
                                <span class="program-badge" title="<?= e($prog['program_name']) ?> (<?= e($prog['role']) ?>)">
                                    <?= e($prog['program_code']) ?>
                                </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="instructor-stats">
                        <div class="stat-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                            </svg>
                            <div class="stat-info">
                                <span class="stat-value"><?= $instructor['subjects_count'] ?></span>
                                <span class="stat-label">Subjects</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                <polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            <div class="stat-info">
                                <span class="stat-value"><?= $instructor['sections_count'] ?></span>
                                <span class="stat-label">Sections</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <div class="stat-info">
                                <span class="stat-value"><?= $instructor['students_count'] ?></span>
                                <span class="stat-label">Students</span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- Program Assignment Modal -->
<div id="programModal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                </svg>
                Manage Program Assignments
            </h3>
            <button type="button" class="modal-close" onclick="closeProgramModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-instructor-name" id="modalInstructorName"></p>

            <!-- Current Assignments -->
            <div class="current-assignments">
                <h4>Current Assignments</h4>
                <div id="currentAssignments" class="assignments-list">
                    <div class="loading">Loading...</div>
                </div>
            </div>

            <!-- Add New Assignment -->
            <div class="add-assignment">
                <h4>Add Program</h4>
                <div class="add-form">
                    <select id="newProgramId" class="form-select">
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $p): ?>
                        <option value="<?= $p['program_id'] ?>"><?= e($p['program_code']) ?> - <?= e($p['program_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="newRole" class="form-select">
                        <option value="faculty">Faculty</option>
                        <option value="coordinator">Coordinator</option>
                        <option value="adjunct">Adjunct</option>
                    </select>
                    <button type="button" class="btn-add" onclick="assignProgram()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Instructors Page - Green/Cream Theme */
.instructors-wrap {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.header-icon {
    width: 48px;
    height: 48px;
    background: #E8F5E9;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1B4D3E;
}

.page-header h1 {
    font-size: 24px;
    font-weight: 700;
    color: #1B4D3E;
    margin: 0 0 4px;
}

.page-header p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

.header-stats {
    display: flex;
    gap: 16px;
}

.stat-box {
    background: #1B4D3E;
    color: #fff;
    padding: 16px 24px;
    border-radius: 12px;
    text-align: center;
}

.stat-num {
    display: block;
    font-size: 28px;
    font-weight: 700;
}

.stat-box .stat-label {
    font-size: 12px;
    opacity: 0.9;
}

/* Filters */
.filters-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}

.filters-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.search-box svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.search-box input {
    width: 100%;
    padding: 10px 16px 10px 42px;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.search-box input:focus {
    outline: none;
    border-color: #1B4D3E;
}

.filter-select {
    padding: 10px 16px;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    font-size: 14px;
    min-width: 140px;
    transition: all 0.2s ease;
}

.filter-select:focus {
    outline: none;
    border-color: #1B4D3E;
}

.btn-search {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    background: #1B4D3E;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-search:hover {
    background: #2D6A4F;
}

.btn-clear {
    padding: 10px 20px;
    background: #f5f5f5;
    color: #666;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-clear:hover {
    background: #e8e8e8;
}

/* Instructors Grid */
.instructors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
}

/* Instructor Card */
.instructor-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s ease;
}

.instructor-card:hover {
    border-color: #1B4D3E;
    box-shadow: 0 4px 12px rgba(27, 77, 62, 0.1);
}

.card-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f0f0f0;
}

.instructor-avatar {
    width: 50px;
    height: 50px;
    background: #1B4D3E;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
    flex-shrink: 0;
}

.instructor-info {
    flex: 1;
}

.instructor-info h3 {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0 0 4px;
}

.instructor-email {
    font-size: 13px;
    color: #666;
    margin: 0;
}

.instructor-id {
    font-size: 12px;
    color: #999;
    margin: 4px 0 0;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: #E8F5E9;
    color: #1B4D3E;
}

.status-inactive {
    background: #f5f5f5;
    color: #999;
}

/* Programs Section */
.programs-section {
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f0f0f0;
}

.programs-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.programs-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
}

.programs-label svg {
    color: #1B4D3E;
}

.btn-manage-programs {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: #E8F5E9;
    color: #1B4D3E;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-manage-programs:hover {
    background: #1B4D3E;
    color: #fff;
}

.programs-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.program-badge {
    background: #1B4D3E;
    color: #fff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.no-programs {
    font-size: 13px;
    color: #999;
    font-style: italic;
}

/* Stats */
.instructor-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: #fafafa;
    border-radius: 8px;
}

.stat-item svg {
    color: #1B4D3E;
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 18px;
    font-weight: 700;
    color: #1B4D3E;
    line-height: 1;
}

.stat-label {
    font-size: 11px;
    color: #999;
    margin-top: 2px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 24px;
    background: #fafafa;
    border: 1px dashed #ddd;
    border-radius: 12px;
}

.empty-state svg {
    color: #ccc;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0 0 8px;
}

.empty-state p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-container {
    background: #fff;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #f0f0f0;
}

.modal-header h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 18px;
    font-weight: 600;
    color: #1B4D3E;
    margin: 0;
}

.modal-close {
    width: 32px;
    height: 32px;
    background: #f5f5f5;
    border: none;
    border-radius: 8px;
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: #e8e8e8;
}

.modal-body {
    padding: 20px;
}

.modal-instructor-name {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0 0 20px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f0f0;
}

.current-assignments h4,
.add-assignment h4 {
    font-size: 14px;
    font-weight: 600;
    color: #666;
    margin: 0 0 12px;
}

.assignments-list {
    margin-bottom: 20px;
}

.assignment-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #fafafa;
    border-radius: 8px;
    margin-bottom: 8px;
}

.assignment-info {
    display: flex;
    flex-direction: column;
}

.assignment-program {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.assignment-role {
    font-size: 12px;
    color: #666;
}

.btn-remove {
    padding: 6px 12px;
    background: #fee2e2;
    color: #991b1b;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-remove:hover {
    background: #fecaca;
}

.no-assignments {
    padding: 20px;
    text-align: center;
    color: #999;
    font-style: italic;
}

.add-form {
    display: flex;
    gap: 10px;
}

.form-select {
    flex: 1;
    padding: 10px 12px;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
    font-size: 14px;
}

.form-select:focus {
    outline: none;
    border-color: #1B4D3E;
}

.btn-add {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    background: #1B4D3E;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-add:hover {
    background: #2D6A4F;
}

.loading {
    padding: 20px;
    text-align: center;
    color: #999;
}

/* Responsive */
@media (max-width: 768px) {
    .instructors-wrap {
        padding: 16px;
    }

    .page-header {
        flex-direction: column;
        gap: 16px;
    }

    .instructors-grid {
        grid-template-columns: 1fr;
    }

    .filters-form {
        flex-direction: column;
    }

    .search-box {
        min-width: 100%;
    }

    .filter-select {
        width: 100%;
    }

    .instructor-stats {
        grid-template-columns: 1fr;
    }

    .add-form {
        flex-direction: column;
    }
}
</style>

<script>
let currentTeacherId = null;

function openProgramModal(teacherId, teacherName) {
    currentTeacherId = teacherId;
    document.getElementById('modalInstructorName').textContent = teacherName;
    document.getElementById('programModal').style.display = 'flex';
    loadAssignments();
}

function closeProgramModal() {
    document.getElementById('programModal').style.display = 'none';
    currentTeacherId = null;
}

function loadAssignments() {
    const container = document.getElementById('currentAssignments');
    container.innerHTML = '<div class="loading">Loading...</div>';

    fetch('instructors.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_assignments&teacher_id=' + currentTeacherId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.assignments.length > 0) {
            container.innerHTML = data.assignments.map(a => `
                <div class="assignment-item">
                    <div class="assignment-info">
                        <span class="assignment-program">${a.program_code} - ${a.program_name}</span>
                        <span class="assignment-role">${a.role.charAt(0).toUpperCase() + a.role.slice(1)}</span>
                    </div>
                    <button type="button" class="btn-remove" onclick="removeAssignment(${a.faculty_program_id})">Remove</button>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<div class="no-assignments">No programs assigned yet</div>';
        }
    })
    .catch(() => {
        container.innerHTML = '<div class="no-assignments">Error loading assignments</div>';
    });
}

function assignProgram() {
    const programId = document.getElementById('newProgramId').value;
    const role = document.getElementById('newRole').value;

    if (!programId) {
        alert('Please select a program');
        return;
    }

    fetch('instructors.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=assign_program&teacher_id=${currentTeacherId}&program_id=${programId}&role=${role}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadAssignments();
            document.getElementById('newProgramId').value = '';
        } else {
            alert(data.message || 'Error assigning program');
        }
    });
}

function removeAssignment(assignmentId) {
    if (!confirm('Remove this program assignment?')) return;

    fetch('instructors.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=remove_assignment&assignment_id=' + assignmentId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadAssignments();
        } else {
            alert(data.message || 'Error removing assignment');
        }
    });
}

// Close modal on outside click
document.getElementById('programModal').addEventListener('click', function(e) {
    if (e.target === this) closeProgramModal();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
