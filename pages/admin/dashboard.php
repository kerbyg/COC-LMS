<?php
/**
 * Admin - Dashboard
 * System overview and statistics
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('admin');

$pageTitle = 'Admin Dashboard';
$currentPage = 'dashboard';

// Get statistics
$totalUsers = db()->fetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0;
$totalStudents = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'] ?? 0;
$totalInstructors = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'instructor'")['count'] ?? 0;
$totalDeans = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'dean'")['count'] ?? 0;

$totalDepartments = db()->fetchOne("SELECT COUNT(*) as count FROM department WHERE status = 'active'")['count'] ?? 0;
$totalPrograms = db()->fetchOne("SELECT COUNT(*) as count FROM program WHERE status = 'active'")['count'] ?? 0;
$totalSubjects = db()->fetchOne("SELECT COUNT(*) as count FROM subject WHERE status = 'active'")['count'] ?? 0;
$totalSections = db()->fetchOne("SELECT COUNT(*) as count FROM section")['count'] ?? 0;
$totalOfferings = db()->fetchOne("SELECT COUNT(*) as count FROM subject_offered")['count'] ?? 0;
$totalLessons = db()->fetchOne("SELECT COUNT(*) as count FROM lessons")['count'] ?? 0;
$totalQuizzes = db()->fetchOne("SELECT COUNT(*) as count FROM quiz")['count'] ?? 0;

$activeUsers = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'] ?? 0;

// Quiz stats
$quizAttempts = db()->fetchOne("SELECT COUNT(*) as count FROM student_quiz_attempts WHERE status = 'completed'")['count'] ?? 0;
$avgQuizScore = db()->fetchOne("SELECT AVG(percentage) as avg FROM student_quiz_attempts WHERE status = 'completed'")['avg'] ?? 0;

// Enrollment stats
$totalEnrolled = db()->fetchOne("SELECT COUNT(*) as count FROM student_subject WHERE status = 'enrolled'")['count'] ?? 0;

// Recent users
$recentUsers = db()->fetchAll(
    "SELECT users_id, first_name, last_name, email, role, status, created_at
     FROM users ORDER BY created_at DESC LIMIT 6"
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="dash-wrap">

        <!-- Welcome Banner -->
        <div class="dash-banner">
            <div class="banner-text">
                <h1>Welcome back, <?= e(Auth::name()) ?></h1>
                <p>Here's what's happening across the system today.</p>
            </div>
            <div class="banner-date"><?= date('l, F j, Y') ?></div>
        </div>

        <!-- User Stats Row -->
        <div class="stat-row">
            <div class="stat-card stat-users">
                <div class="stat-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="stat-body">
                    <span class="stat-value"><?= number_format($totalUsers) ?></span>
                    <span class="stat-label">Total Users</span>
                </div>
                <div class="stat-pills">
                    <span class="pill pill-green"><?= $totalStudents ?> Students</span>
                    <span class="pill pill-amber"><?= $totalInstructors ?> Instructors</span>
                    <span class="pill pill-blue"><?= $totalDeans ?> Deans</span>
                </div>
            </div>

            <div class="stat-card stat-academic">
                <div class="stat-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                    </svg>
                </div>
                <div class="stat-body">
                    <span class="stat-value"><?= number_format($totalPrograms) ?></span>
                    <span class="stat-label">Programs</span>
                </div>
                <div class="stat-pills">
                    <span class="pill pill-muted"><?= $totalDepartments ?> Departments</span>
                    <span class="pill pill-muted"><?= $totalSubjects ?> Subjects</span>
                </div>
            </div>

            <div class="stat-card stat-content">
                <div class="stat-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                </div>
                <div class="stat-body">
                    <span class="stat-value"><?= number_format($totalLessons) ?></span>
                    <span class="stat-label">Lessons</span>
                </div>
                <div class="stat-pills">
                    <span class="pill pill-muted"><?= $totalQuizzes ?> Quizzes</span>
                    <span class="pill pill-muted"><?= $totalOfferings ?> Offerings</span>
                </div>
            </div>

            <div class="stat-card stat-performance">
                <div class="stat-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                </div>
                <div class="stat-body">
                    <span class="stat-value"><?= round($avgQuizScore) ?>%</span>
                    <span class="stat-label">Avg Quiz Score</span>
                </div>
                <div class="stat-pills">
                    <span class="pill pill-muted"><?= $quizAttempts ?> Attempts</span>
                    <span class="pill pill-muted"><?= $totalEnrolled ?> Enrolled</span>
                </div>
            </div>
        </div>

        <!-- Bottom Grid -->
        <div class="bottom-grid">

            <!-- Recent Users -->
            <div class="dash-card">
                <div class="card-head">
                    <h3>Recent Users</h3>
                    <a href="users.php" class="link-btn">View All</a>
                </div>
                <div class="card-content">
                    <?php if (empty($recentUsers)): ?>
                        <p class="empty-msg">No users registered yet.</p>
                    <?php else: ?>
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $u): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar"><?= strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1)) ?></div>
                                            <div>
                                                <span class="u-name"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></span>
                                                <span class="u-email"><?= e($u['email']) ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                                    <td><span class="status-dot status-<?= $u['status'] ?>"></span> <?= ucfirst($u['status']) ?></td>
                                    <td class="text-sm text-gray"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions + System Overview -->
            <div class="side-panel">

                <!-- Quick Actions -->
                <div class="dash-card">
                    <div class="card-head">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="card-content actions-list">
                        <a href="users.php?action=create" class="action-item">
                            <div class="action-icon ai-green">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="8.5" cy="7" r="4"/>
                                    <line x1="20" y1="8" x2="20" y2="14"/>
                                    <line x1="23" y1="11" x2="17" y2="11"/>
                                </svg>
                            </div>
                            <span>Add New User</span>
                        </a>
                        <a href="programs.php?action=create" class="action-item">
                            <div class="action-icon ai-blue">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                                    <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                                </svg>
                            </div>
                            <span>Add Program</span>
                        </a>
                        <a href="subjects.php" class="action-item">
                            <div class="action-icon ai-amber">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                </svg>
                            </div>
                            <span>Manage Subjects</span>
                        </a>
                        <a href="subject-offerings.php" class="action-item">
                            <div class="action-icon ai-purple">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                            </div>
                            <span>Subject Offerings</span>
                        </a>
                        <a href="sections.php" class="action-item">
                            <div class="action-icon ai-teal">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                    <line x1="8" y1="21" x2="16" y2="21"/>
                                    <line x1="12" y1="17" x2="12" y2="21"/>
                                </svg>
                            </div>
                            <span>Manage Sections</span>
                        </a>
                    </div>
                </div>

                <!-- System At A Glance -->
                <div class="dash-card">
                    <div class="card-head">
                        <h3>System Overview</h3>
                    </div>
                    <div class="card-content">
                        <div class="overview-list">
                            <div class="ov-item">
                                <span class="ov-label">Active Users</span>
                                <span class="ov-value"><?= $activeUsers ?> / <?= $totalUsers ?></span>
                            </div>
                            <div class="ov-item">
                                <span class="ov-label">Sections</span>
                                <span class="ov-value"><?= $totalSections ?></span>
                            </div>
                            <div class="ov-item">
                                <span class="ov-label">Enrollments</span>
                                <span class="ov-value"><?= $totalEnrolled ?></span>
                            </div>
                            <div class="ov-item">
                                <span class="ov-label">Quiz Completion</span>
                                <span class="ov-value"><?= $quizAttempts ?> attempts</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</main>

<style>
/* Admin Dashboard */
.dash-wrap {
    padding: 24px;
    width: 100%;
}

/* Welcome Banner */
.dash-banner {
    background: linear-gradient(135deg, #1B4D3E 0%, #2D6A4F 100%);
    color: #fff;
    border-radius: 14px;
    padding: 28px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.banner-text h1 {
    font-size: 22px;
    font-weight: 700;
    margin: 0 0 6px;
}
.banner-text p {
    font-size: 14px;
    margin: 0;
    opacity: 0.85;
}
.banner-date {
    font-size: 13px;
    opacity: 0.7;
    white-space: nowrap;
}

/* Stat Row */
.stat-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 24px;
}
.stat-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 22px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.stat-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}
.stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-users .stat-icon { background: #E8F5E9; color: #1B4D3E; }
.stat-academic .stat-icon { background: #E3F2FD; color: #1565C0; }
.stat-content .stat-icon { background: #FFF3E0; color: #E65100; }
.stat-performance .stat-icon { background: #F3E5F5; color: #7B1FA2; }

.stat-body {
    display: flex;
    flex-direction: column;
}
.stat-value {
    font-size: 28px;
    font-weight: 800;
    color: #1a1a1a;
    line-height: 1;
    margin-bottom: 4px;
}
.stat-label {
    font-size: 13px;
    color: #777;
    font-weight: 500;
}
.stat-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
}
.pill {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
}
.pill-green { background: #E8F5E9; color: #1B4D3E; }
.pill-amber { background: #FEF3C7; color: #92400E; }
.pill-blue { background: #DBEAFE; color: #1E40AF; }
.pill-muted { background: #F3F4F6; color: #555; }

/* Bottom Grid */
.bottom-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 20px;
}
.dash-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    overflow: hidden;
}
.card-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 22px;
    border-bottom: 1px solid #f0f0f0;
}
.card-head h3 {
    font-size: 15px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0;
}
.link-btn {
    font-size: 12px;
    color: #1B4D3E;
    font-weight: 600;
    text-decoration: none;
    padding: 4px 10px;
    border: 1px solid #1B4D3E;
    border-radius: 6px;
}
.link-btn:hover { background: #1B4D3E; color: #fff; }
.card-content { padding: 16px 22px; }

/* Table */
.dash-table {
    width: 100%;
    border-collapse: collapse;
}
.dash-table th {
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 8px 10px;
    border-bottom: 1px solid #f0f0f0;
}
.dash-table td {
    padding: 12px 10px;
    font-size: 13px;
    color: #333;
    border-bottom: 1px solid #f8f8f8;
}
.dash-table tr:last-child td { border-bottom: none; }
.dash-table tr:hover td { background: #fafafa; }

.user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-avatar {
    width: 34px;
    height: 34px;
    background: #1B4D3E;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
    flex-shrink: 0;
}
.u-name {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #1a1a1a;
}
.u-email {
    display: block;
    font-size: 11px;
    color: #999;
}
.text-sm { font-size: 12px; }
.text-gray { color: #999; }

/* Badges */
.badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}
.badge-student { background: #E8F5E9; color: #1B4D3E; }
.badge-instructor { background: #FEF3C7; color: #92400E; }
.badge-admin { background: #FEE2E2; color: #991B1B; }
.badge-dean { background: #DBEAFE; color: #1E40AF; }

/* Status Dot */
.status-dot {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    margin-right: 4px;
}
.status-active { background: #10B981; }
.status-inactive { background: #EF4444; }
.status-suspended { background: #F59E0B; }

/* Quick Actions */
.actions-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px 16px !important;
}
.action-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    text-decoration: none;
    color: #333;
    font-size: 13px;
    font-weight: 500;
    transition: background 0.15s;
}
.action-item:hover { background: #f5f5f5; }
.action-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ai-green { background: #E8F5E9; color: #1B4D3E; }
.ai-blue { background: #DBEAFE; color: #1E40AF; }
.ai-amber { background: #FEF3C7; color: #92400E; }
.ai-purple { background: #F3E5F5; color: #7B1FA2; }
.ai-teal { background: #E0F2F1; color: #00695C; }

/* System Overview */
.overview-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.ov-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f5f5f5;
}
.ov-item:last-child { border-bottom: none; }
.ov-label { font-size: 13px; color: #777; }
.ov-value { font-size: 13px; font-weight: 700; color: #1a1a1a; }

/* Side Panel */
.side-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.empty-msg {
    text-align: center;
    color: #aaa;
    padding: 30px 0;
    margin: 0;
}

/* Responsive */
@media (max-width: 1200px) {
    .stat-row { grid-template-columns: repeat(2, 1fr); }
    .bottom-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .dash-wrap { padding: 16px; }
    .dash-banner { flex-direction: column; gap: 8px; text-align: center; }
    .stat-row { grid-template-columns: 1fr; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
