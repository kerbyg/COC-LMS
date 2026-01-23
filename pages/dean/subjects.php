<?php
/**
 * Dean - Subjects
 * View subjects and their offerings
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('dean');

$pageTitle = 'Subjects';
$currentPage = 'subjects';

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($search) {
    $whereClause .= " AND (s.subject_code LIKE ? OR s.subject_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

if ($statusFilter) {
    $whereClause .= " AND s.status = ?";
    $params[] = $statusFilter;
}

// Get subjects with statistics
$subjects = db()->fetchAll(
    "SELECT s.*,
        (SELECT COUNT(*) FROM subject_offered so
         WHERE so.subject_id = s.subject_id AND so.status = 'active') as offerings_count,
        (SELECT COUNT(DISTINCT sec.section_id)
         FROM subject_offered so
         JOIN section sec ON so.subject_offered_id = sec.subject_offered_id
         WHERE so.subject_id = s.subject_id AND sec.status = 'active') as sections_count,
        (SELECT COUNT(*)
         FROM student_subject ss
         JOIN section sec ON ss.section_id = sec.section_id
         JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
         WHERE so.subject_id = s.subject_id AND ss.status = 'enrolled') as enrolled_count
     FROM subject s
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

        <div class="page-header">
            <div>
                <h2>Subjects Management</h2>
                <p class="text-muted">View and monitor subject offerings</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by code or name..." value="<?= e($search) ?>" class="search-input">
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit" class="btn-search">Search</button>
                <?php if ($search || $statusFilter): ?>
                <a href="subjects.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Subjects Table -->
        <?php if (empty($subjects)): ?>
            <div class="empty-state">
                <div class="empty-icon">📚</div>
                <h3>No Subjects Found</h3>
                <p>No subjects match your search criteria.</p>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Subject Name</th>
                                <th class="text-center">Units</th>
                                <th class="text-center">Offerings</th>
                                <th class="text-center">Sections</th>
                                <th class="text-center">Enrolled</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td>
                                    <span class="subject-code"><?= e($subject['subject_code']) ?></span>
                                </td>
                                <td>
                                    <div class="subject-info">
                                        <span class="subject-name"><?= e($subject['subject_name']) ?></span>
                                        <?php if ($subject['description']): ?>
                                        <span class="subject-desc"><?= e(substr($subject['description'], 0, 60)) ?><?= strlen($subject['description']) > 60 ? '...' : '' ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-neutral"><?= $subject['units'] ?> units</span>
                                </td>
                                <td class="text-center">
                                    <span class="count-badge"><?= $subject['offerings_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="count-badge"><?= $subject['sections_count'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="count-badge enrolled"><?= $subject['enrolled_count'] ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $subject['status'] ?>">
                                        <?= ucfirst($subject['status']) ?>
                                    </span>
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
/* Subjects Page Styles */

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

/* Card */
.card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}
.card-body {
    padding: 0;
}

/* Data Table */
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th,
.data-table td {
    padding: 16px 20px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.data-table th {
    background: #f9fafb;
    font-weight: 600;
    font-size: 12px;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.data-table tbody tr {
    transition: background 0.2s;
}
.data-table tbody tr:hover {
    background: #f9fafb;
}
.data-table tbody tr:last-child td {
    border-bottom: none;
}
.text-center {
    text-align: center !important;
}

/* Subject Code */
.subject-code {
    display: inline-block;
    background: #2563eb;
    color: #ffffff;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
}

/* Subject Info */
.subject-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.subject-name {
    font-weight: 600;
    color: #111827;
    font-size: 14px;
}
.subject-desc {
    font-size: 12px;
    color: #6b7280;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
.badge-neutral {
    background: #f3f4f6;
    color: #374151;
}

.count-badge {
    display: inline-block;
    min-width: 32px;
    padding: 6px 12px;
    background: #f3f4f6;
    color: #374151;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    text-align: center;
}
.count-badge.enrolled {
    background: #dcfce7;
    color: #166534;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
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
    .search-form {
        flex-direction: column;
    }
    .filter-select {
        width: 100%;
    }
    .data-table {
        font-size: 12px;
    }
    .data-table th,
    .data-table td {
        padding: 12px;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
