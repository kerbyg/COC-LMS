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
$totalAdmins = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'] ?? 0;

$totalPrograms = db()->fetchOne("SELECT COUNT(*) as count FROM program")['count'] ?? 0;
$totalSubjects = db()->fetchOne("SELECT COUNT(*) as count FROM subject")['count'] ?? 0;
$totalSections = db()->fetchOne("SELECT COUNT(*) as count FROM section")['count'] ?? 0;
$totalLessons = db()->fetchOne("SELECT COUNT(*) as count FROM lessons")['count'] ?? 0;
$totalQuizzes = db()->fetchOne("SELECT COUNT(*) as count FROM quiz")['count'] ?? 0;

$activeUsers = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'] ?? 0;
$pendingUsers = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'pending'")['count'] ?? 0;

// Recent users
$recentUsers = db()->fetchAll(
    "SELECT users_id, first_name, last_name, email, role, status, created_at 
     FROM users ORDER BY created_at DESC LIMIT 5"
);

// Recent activity
$recentActivity = db()->fetchAll(
    "SELECT al.activity_type, al.description, al.created_at, u.first_name, u.last_name
     FROM activity_logs al
     LEFT JOIN users u ON al.users_id = u.users_id
     ORDER BY al.created_at DESC LIMIT 10"
);

// Quiz stats
$quizAttempts = db()->fetchOne("SELECT COUNT(*) as count FROM student_quiz_attempts WHERE status = 'completed'")['count'] ?? 0;
$avgQuizScore = db()->fetchOne("SELECT AVG(percentage) as avg FROM student_quiz_attempts WHERE status = 'completed'")['avg'] ?? 0;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>Dashboard</h2>
                <p class="text-muted">System Overview</p>
            </div>
        </div>

        <!-- Primary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Total Users</span>
                    <span class="stat-value"><?= number_format($totalUsers) ?></span>
                </div>
                <div class="stat-breakdown">
                    <span><?= $totalStudents ?> Students</span>
                    <span><?= $totalInstructors ?> Instructors</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Programs</span>
                    <span class="stat-value"><?= number_format($totalPrograms) ?></span>
                </div>
                <a href="programs.php" class="stat-link">Manage →</a>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Subjects</span>
                    <span class="stat-value"><?= number_format($totalSubjects) ?></span>
                </div>
                <a href="subjects.php" class="stat-link">Manage →</a>
            </div>

            <div class="stat-card">
                <div class="stat-content">
                    <span class="stat-label">Sections</span>
                    <span class="stat-value"><?= number_format($totalSections) ?></span>
                </div>
                <a href="sections.php" class="stat-link">Manage →</a>
            </div>
        </div>

        <!-- Secondary Stats -->
        <div class="stats-row">
            <div class="mini-stat">
                <span class="mini-label">Lessons</span>
                <span class="mini-value"><?= $totalLessons ?></span>
            </div>
            <div class="mini-stat">
                <span class="mini-label">Quizzes</span>
                <span class="mini-value"><?= $totalQuizzes ?></span>
            </div>
            <div class="mini-stat">
                <span class="mini-label">Quiz Attempts</span>
                <span class="mini-value"><?= $quizAttempts ?></span>
            </div>
            <div class="mini-stat">
                <span class="mini-label">Avg Score</span>
                <span class="mini-value"><?= round($avgQuizScore) ?>%</span>
            </div>
            <div class="mini-stat">
                <span class="mini-label">Active</span>
                <span class="mini-value"><?= $activeUsers ?></span>
            </div>
        </div>
        
        <!-- Recent Users -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Users</h3>
                <a href="users.php" class="view-all">View All →</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentUsers)): ?>
                    <p class="empty-text">No users yet</p>
                <?php else: ?>
                    <div class="user-list">
                        <?php foreach ($recentUsers as $user): ?>
                        <div class="user-item">
                            <div class="user-avatar"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></div>
                            <div class="user-info">
                                <span class="user-name"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></span>
                                <span class="user-email"><?= e($user['email']) ?></span>
                            </div>
                            <div class="user-meta">
                                <span class="badge badge-<?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</main>

<style>
/* Clean Professional Dashboard */
.page-header {
    margin-bottom: 32px;
}
.page-header h2 {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 4px;
}
.text-muted {
    color: #6b7280;
    margin: 0;
    font-size: 14px;
}

/* Primary Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}
.stat-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    transition: box-shadow 0.2s;
}
.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.stat-content {
    margin-bottom: 12px;
}
.stat-label {
    display: block;
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-value {
    display: block;
    font-size: 32px;
    font-weight: 700;
    color: #111827;
    line-height: 1;
}
.stat-breakdown {
    display: flex;
    gap: 12px;
    padding-top: 12px;
    border-top: 1px solid #e5e7eb;
    font-size: 13px;
    color: #6b7280;
}
.stat-link {
    display: inline-block;
    font-size: 13px;
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
    padding-top: 12px;
    border-top: 1px solid #e5e7eb;
}
.stat-link:hover {
    color: #1d4ed8;
}

/* Secondary Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}
.mini-stat {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
}
.mini-label {
    display: block;
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.mini-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    line-height: 1;
}

/* Card */
.card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 24px;
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
}
.card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}
.view-all {
    font-size: 13px;
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
}
.view-all:hover {
    color: #1d4ed8;
}
.card-body {
    padding: 24px;
    max-height: 400px;
    overflow-y: auto;
}
.empty-text {
    text-align: center;
    color: #9ca3af;
    margin: 0;
}

/* User List */
.user-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.user-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
    transition: background 0.2s;
}
.user-item:hover {
    background: #f3f4f6;
}
.user-avatar {
    width: 40px;
    height: 40px;
    background: #2563eb;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
}
.user-info {
    flex: 1;
    min-width: 0;
}
.user-name {
    display: block;
    font-weight: 600;
    color: #111827;
    font-size: 14px;
    margin-bottom: 2px;
}
.user-email {
    display: block;
    font-size: 12px;
    color: #6b7280;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.user-meta {
    flex-shrink: 0;
}
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: capitalize;
}
.badge-student {
    background: #dbeafe;
    color: #1e40af;
}
.badge-instructor {
    background: #fef3c7;
    color: #92400e;
}
.badge-admin {
    background: #fee2e2;
    color: #991b1b;
}

/* Scrollbar */
.card-body::-webkit-scrollbar {
    width: 6px;
}
.card-body::-webkit-scrollbar-track {
    background: #f3f4f6;
}
.card-body::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}
.card-body::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 640px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>