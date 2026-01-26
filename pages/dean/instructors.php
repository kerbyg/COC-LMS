<?php
/**
 * Dean - Instructors
 * View and monitor faculty members in dean's department (all programs)
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

// Get department info for display
$department = null;
if ($deptId) {
    $department = db()->fetchOne("SELECT department_name, department_code FROM department WHERE department_id = ?", [$deptId]);
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

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
// Note: sections_count and students_count use faculty_subject table (not section.user_instructor_id)
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

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <div class="page-header">
            <div>
                <h2>Faculty Members</h2>
                <p class="text-muted">
                    <?php if ($department): ?>
                        <?= e($department['department_name'] ?? '') ?>
                    <?php else: ?>
                        View and monitor instructor activities
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by name, email, or ID..." value="<?= e($search) ?>" class="search-input">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
                <button type="submit" class="btn-search">Search</button>
                <?php if ($search || $statusFilter): ?>
                <a href="instructors.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Instructors Grid -->
        <?php if (empty($instructors)): ?>
            <div class="empty-state">
                <div class="empty-icon">👨‍🏫</div>
                <h3>No Instructors Found</h3>
                <p>No instructors match your search criteria.</p>
            </div>
        <?php else: ?>
            <div class="instructors-grid">
                <?php foreach ($instructors as $instructor): ?>
                <div class="instructor-card">
                    <div class="instructor-header">
                        <div class="instructor-avatar">
                            <?= strtoupper(substr($instructor['first_name'], 0, 1) . substr($instructor['last_name'], 0, 1)) ?>
                        </div>
                        <div class="instructor-info">
                            <h3 class="instructor-name"><?= e($instructor['first_name'] . ' ' . $instructor['last_name']) ?></h3>
                            <p class="instructor-email"><?= e($instructor['email']) ?></p>
                        </div>
                        <span class="status-badge status-<?= $instructor['status'] ?>"><?= ucfirst($instructor['status']) ?></span>
                    </div>

                    <div class="instructor-meta">
                        <div class="meta-item">
                            <span class="meta-label">Employee ID</span>
                            <span class="meta-value"><?= e($instructor['employee_id'] ?? 'Not Set') ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Joined</span>
                            <span class="meta-value"><?= date('M d, Y', strtotime($instructor['created_at'])) ?></span>
                        </div>
                    </div>

                    <div class="instructor-stats">
                        <div class="stat-item">
                            <div class="stat-icon">📚</div>
                            <div class="stat-info">
                                <span class="stat-value"><?= $instructor['subjects_count'] ?></span>
                                <span class="stat-label">Subjects</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">🏫</div>
                            <div class="stat-info">
                                <span class="stat-value"><?= $instructor['sections_count'] ?></span>
                                <span class="stat-label">Sections</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon">👥</div>
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

<style>
/* Instructors Page Styles */

.page-header {
    margin-bottom: 24px;
}
.page-header h2 {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 4px;
}
.text-muted {
    color: #6b7280;
    font-size: 14px;
    margin: 0;
}

/* Filters Bar */
.filters-bar {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}
.search-form {
    display: flex;
    gap: 12px;
}
.search-input {
    flex: 1;
    padding: 10px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
}
.search-input:focus {
    outline: none;
    border-color: #2563eb;
}
.filter-select {
    padding: 10px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    min-width: 150px;
}
.btn-search, .btn-clear {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}
.btn-search {
    background: #2563eb;
    color: #ffffff;
}
.btn-search:hover {
    background: #1d4ed8;
}
.btn-clear {
    background: #f3f4f6;
    color: #374151;
}
.btn-clear:hover {
    background: #e5e7eb;
}

/* Instructors Grid */
.instructors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

/* Instructor Card */
.instructor-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    transition: all 0.2s;
}
.instructor-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.instructor-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}
.instructor-avatar {
    width: 50px;
    height: 50px;
    background: #2563eb;
    color: #ffffff;
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
.instructor-name {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    margin: 0 0 4px;
}
.instructor-email {
    font-size: 13px;
    color: #6b7280;
    margin: 0;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.status-active {
    background: #dcfce7;
    color: #166534;
}
.status-inactive {
    background: #f3f4f6;
    color: #6b7280;
}
.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.instructor-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}
.meta-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.meta-label {
    font-size: 11px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.meta-value {
    font-size: 14px;
    color: #111827;
    font-weight: 500;
}

.instructor-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}
.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
}
.stat-icon {
    font-size: 20px;
}
.stat-info {
    display: flex;
    flex-direction: column;
}
.stat-value {
    font-size: 18px;
    font-weight: 700;
    color: #111827;
    line-height: 1;
}
.stat-label {
    font-size: 11px;
    color: #6b7280;
    margin-top: 2px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}
.empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}
.empty-state h3 {
    font-size: 20px;
    color: #111827;
    margin: 0 0 8px;
}
.empty-state p {
    color: #6b7280;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .instructors-grid {
        grid-template-columns: 1fr;
    }
    .search-form {
        flex-direction: column;
    }
    .filter-select {
        width: 100%;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
