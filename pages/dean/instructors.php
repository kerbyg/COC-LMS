<?php
/**
 * Dean - Instructors
 * View and monitor faculty members in dean's department
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('dean');

$pageTitle = 'Instructors';
$currentPage = 'instructors';
$userId = Auth::id();

// Get dean's department
$dean = db()->fetchOne("SELECT department_id FROM users WHERE users_id = ?", [$userId]);
$deptId = $dean['department_id'] ?? 0;

// Get department info
$department = null;
if ($deptId) {
    $department = db()->fetchOne("SELECT department_name, department_code FROM department WHERE department_id = ?", [$deptId]);
}

// Get programs under this department
$programs = [];
if ($deptId) {
    $programs = db()->fetchAll(
        "SELECT p.program_id, p.program_code, p.program_name
         FROM program p
         JOIN department_program dp ON p.program_id = dp.program_id
         WHERE dp.department_id = ? AND p.status = 'active'
         ORDER BY p.program_code",
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
            $existing = db()->fetchOne(
                "SELECT faculty_program_id FROM faculty_program WHERE user_teacher_id = ? AND program_id = ?",
                [$teacherId, $programId]
            );

            if ($existing) {
                db()->execute(
                    "UPDATE faculty_program SET status = 'active', role = ? WHERE faculty_program_id = ?",
                    [$role, $existing['faculty_program_id']]
                );
            } else {
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

// Build query
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

// Get instructors with stats
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

// Add program assignments
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

// Filter by program
if ($programFilter) {
    $instructors = array_filter($instructors, function($inst) use ($programFilter) {
        foreach ($inst['programs'] as $p) {
            if ($p['program_code'] === $programFilter) return true;
        }
        return false;
    });
}

$activeCount = count(array_filter($instructors, fn($i) => $i['status'] === 'active'));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-container">

        <!-- Header -->
        <div class="page-header">
            <div class="header-left">
                <h1>Faculty Members</h1>
                <span class="count-badge"><?= count($instructors) ?> total</span>
                <span class="count-badge active"><?= $activeCount ?> active</span>
            </div>
            <?php if ($department): ?>
            <div class="dept-indicator">
                <span class="dept-label">Department</span>
                <span class="dept-name"><?= e($department['department_name']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters-row">
            <input type="text" name="search" class="search-input" placeholder="Search..." value="<?= e($search) ?>">
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <select name="program" class="filter-select" onchange="this.form.submit()">
                <option value="">All Programs</option>
                <?php foreach ($programs as $p): ?>
                <option value="<?= e($p['program_code']) ?>" <?= $programFilter === $p['program_code'] ? 'selected' : '' ?>><?= e($p['program_code']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-filter">Search</button>
            <?php if ($search || $statusFilter || $programFilter): ?>
            <a href="instructors.php" class="btn-clear">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Instructors Table -->
        <?php if (empty($instructors)): ?>
        <div class="empty-state">
            <h3>No Instructors Found</h3>
            <p>No instructors match your criteria.</p>
        </div>
        <?php else: ?>
        <div class="table-card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Instructor</th>
                        <th>Employee ID</th>
                        <th>Programs</th>
                        <th>Subjects</th>
                        <th>Sections</th>
                        <th>Students</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($instructors as $i): ?>
                    <tr>
                        <td>
                            <div class="instructor-cell">
                                <span class="avatar"><?= strtoupper(substr($i['first_name'], 0, 1)) ?></span>
                                <div class="info">
                                    <span class="name"><?= e($i['first_name'] . ' ' . $i['last_name']) ?></span>
                                    <span class="email"><?= e($i['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= e($i['employee_id'] ?? '-') ?></td>
                        <td>
                            <div class="programs-cell">
                                <?php if (empty($i['programs'])): ?>
                                <span class="no-data">None</span>
                                <?php else: ?>
                                    <?php foreach ($i['programs'] as $p): ?>
                                    <span class="program-tag"><?= e($p['program_code']) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="center"><?= $i['subjects_count'] ?></td>
                        <td class="center"><?= $i['sections_count'] ?></td>
                        <td class="center"><?= $i['students_count'] ?></td>
                        <td>
                            <span class="status-tag status-<?= $i['status'] ?>"><?= ucfirst($i['status']) ?></span>
                        </td>
                        <td>
                            <button type="button" class="btn-action" onclick="openModal(<?= $i['users_id'] ?>, '<?= e($i['first_name'] . ' ' . $i['last_name']) ?>')">
                                Manage Programs
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- Program Modal -->
<div id="programModal" class="modal" style="display:none;">
    <div class="modal-box">
        <div class="modal-head">
            <h3>Manage Program Assignments</h3>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-name" id="modalName"></p>

            <div class="section">
                <h4>Current Assignments</h4>
                <div id="assignments" class="assignments-list"></div>
            </div>

            <div class="section">
                <h4>Add Program</h4>
                <div class="add-row">
                    <select id="newProgram" class="form-select">
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $p): ?>
                        <option value="<?= $p['program_id'] ?>"><?= e($p['program_code']) ?> - <?= e($p['program_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="newRole" class="form-select">
                        <option value="faculty">Faculty</option>
                        <option value="coordinator">Coordinator</option>
                    </select>
                    <button type="button" class="btn-add" onclick="addProgram()">Add</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --coc-green: #1B4D3E;
    --coc-green-light: #2D6A4F;
    --gray-50: #f9fafb;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-400: #9ca3af;
    --gray-600: #4b5563;
    --gray-800: #1f2937;
}

.page-container {
    padding: 20px;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}
.header-left h1 {
    font-size: 22px;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
}
.count-badge {
    background: var(--gray-100);
    color: var(--gray-600);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
}
.count-badge.active {
    background: #dcfce7;
    color: #166534;
}
.dept-indicator {
    display: flex;
    align-items: center;
    gap: 10px;
    background: var(--coc-green);
    padding: 8px 18px;
    border-radius: 6px;
}
.dept-label {
    font-size: 11px;
    font-weight: 500;
    color: rgba(255,255,255,0.7);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.dept-name {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
}

/* Filters */
.filters-row {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.search-input {
    flex: 1;
    min-width: 180px;
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    font-size: 13px;
}
.search-input:focus {
    outline: none;
    border-color: var(--coc-green);
}
.filter-select {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    font-size: 13px;
    min-width: 110px;
}
.filter-select:focus {
    outline: none;
    border-color: var(--coc-green);
}
.btn-filter {
    padding: 8px 16px;
    background: var(--coc-green);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}
.btn-filter:hover {
    background: var(--coc-green-light);
}
.btn-clear {
    padding: 8px 14px;
    background: var(--gray-100);
    color: var(--gray-600);
    border: none;
    border-radius: 6px;
    font-size: 13px;
    text-decoration: none;
}
.btn-clear:hover {
    background: var(--gray-200);
}

/* Table */
.table-card {
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    overflow: hidden;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th {
    text-align: left;
    padding: 10px 14px;
    background: var(--gray-50);
    font-size: 11px;
    font-weight: 600;
    color: var(--gray-400);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--gray-200);
}
.data-table td {
    padding: 10px 14px;
    font-size: 13px;
    color: var(--gray-600);
    border-bottom: 1px solid var(--gray-100);
}
.data-table tr:last-child td {
    border-bottom: none;
}
.data-table tr:hover {
    background: var(--gray-50);
}
.data-table .center {
    text-align: center;
}

/* Instructor Cell */
.instructor-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.avatar {
    width: 32px;
    height: 32px;
    background: var(--coc-green);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}
.instructor-cell .info {
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.instructor-cell .name {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 13px;
}
.instructor-cell .email {
    font-size: 11px;
    color: var(--gray-400);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Programs Cell */
.programs-cell {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
}
.program-tag {
    background: var(--coc-green);
    color: #fff;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
}
.no-data {
    color: var(--gray-400);
    font-size: 12px;
}

/* Status */
.status-tag {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}
.status-active {
    background: #dcfce7;
    color: #166534;
}
.status-inactive {
    background: var(--gray-100);
    color: var(--gray-400);
}

/* Action Button */
.btn-action {
    padding: 5px 10px;
    background: var(--gray-50);
    color: var(--coc-green);
    border: 1px solid var(--gray-200);
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
}
.btn-action:hover {
    background: var(--coc-green);
    color: #fff;
    border-color: var(--coc-green);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: var(--gray-50);
    border: 1px dashed var(--gray-200);
    border-radius: 8px;
}
.empty-state h3 {
    font-size: 16px;
    color: var(--gray-800);
    margin: 0 0 6px;
}
.empty-state p {
    font-size: 13px;
    color: var(--gray-400);
    margin: 0;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-box {
    background: #fff;
    border-radius: 8px;
    width: 90%;
    max-width: 420px;
    max-height: 80vh;
    overflow-y: auto;
}
.modal-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    border-bottom: 1px solid var(--gray-200);
}
.modal-head h3 {
    font-size: 15px;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
}
.modal-close {
    width: 26px;
    height: 26px;
    background: var(--gray-100);
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-close:hover {
    background: var(--gray-200);
}
.modal-body {
    padding: 16px;
}
.modal-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0 0 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--gray-100);
}
.section {
    margin-bottom: 16px;
}
.section h4 {
    font-size: 11px;
    font-weight: 600;
    color: var(--gray-400);
    text-transform: uppercase;
    margin: 0 0 8px;
}
.assignments-list {
    min-height: 30px;
}
.assignment-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 10px;
    background: var(--gray-50);
    border-radius: 4px;
    margin-bottom: 6px;
}
.assignment-info span {
    display: block;
}
.assignment-info .prog-name {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 13px;
}
.assignment-info .prog-role {
    font-size: 11px;
    color: var(--gray-400);
}
.btn-remove {
    padding: 3px 8px;
    background: #fee2e2;
    color: #991b1b;
    border: none;
    border-radius: 3px;
    font-size: 11px;
    cursor: pointer;
}
.btn-remove:hover {
    background: #fecaca;
}
.no-assignments {
    padding: 12px;
    text-align: center;
    color: var(--gray-400);
    font-size: 13px;
}
.add-row {
    display: flex;
    gap: 6px;
}
.form-select {
    flex: 1;
    padding: 8px 10px;
    border: 1px solid var(--gray-200);
    border-radius: 4px;
    font-size: 13px;
}
.form-select:focus {
    outline: none;
    border-color: var(--coc-green);
}
.btn-add {
    padding: 8px 14px;
    background: var(--coc-green);
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}
.btn-add:hover {
    background: var(--coc-green-light);
}

/* Responsive */
@media (max-width: 1024px) {
    .data-table {
        display: block;
        overflow-x: auto;
    }
}
@media (max-width: 768px) {
    .page-container {
        padding: 12px;
    }
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    .header-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .filters-row {
        flex-direction: column;
    }
    .search-input, .filter-select {
        width: 100%;
    }
    .add-row {
        flex-direction: column;
    }
}
</style>

<script>
let currentId = null;

function openModal(id, name) {
    currentId = id;
    document.getElementById('modalName').textContent = name;
    document.getElementById('programModal').style.display = 'flex';
    loadAssignments();
}

function closeModal() {
    document.getElementById('programModal').style.display = 'none';
    currentId = null;
}

function loadAssignments() {
    const el = document.getElementById('assignments');
    el.innerHTML = '<div class="no-assignments">Loading...</div>';

    fetch('instructors.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=get_assignments&teacher_id=' + currentId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.assignments.length > 0) {
            el.innerHTML = data.assignments.map(a => `
                <div class="assignment-row">
                    <div class="assignment-info">
                        <span class="prog-name">${a.program_code} - ${a.program_name}</span>
                        <span class="prog-role">${a.role}</span>
                    </div>
                    <button type="button" class="btn-remove" onclick="removeAssignment(${a.faculty_program_id})">Remove</button>
                </div>
            `).join('');
        } else {
            el.innerHTML = '<div class="no-assignments">No programs assigned</div>';
        }
    });
}

function addProgram() {
    const prog = document.getElementById('newProgram').value;
    const role = document.getElementById('newRole').value;
    if (!prog) { alert('Select a program'); return; }

    fetch('instructors.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=assign_program&teacher_id=${currentId}&program_id=${prog}&role=${role}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadAssignments();
            document.getElementById('newProgram').value = '';
            location.reload();
        }
    });
}

function removeAssignment(id) {
    if (!confirm('Remove this assignment?')) return;

    fetch('instructors.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=remove_assignment&assignment_id=' + id
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            loadAssignments();
            location.reload();
        }
    });
}

document.getElementById('programModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
