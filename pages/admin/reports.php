<?php
/**
 * Admin - Reports
 * System reports and analytics
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('admin');

$pageTitle = 'Reports';
$currentPage = 'reports';

$reportType = $_GET['type'] ?? 'overview';

// Overview Stats
$totalUsers = db()->fetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0;
$totalStudents = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'] ?? 0;
$totalInstructors = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'instructor'")['count'] ?? 0;
$totalSubjects = db()->fetchOne("SELECT COUNT(*) as count FROM subject")['count'] ?? 0;
$totalQuizzes = db()->fetchOne("SELECT COUNT(*) as count FROM quiz")['count'] ?? 0;
$totalAttempts = db()->fetchOne("SELECT COUNT(*) as count FROM student_quiz_attempts WHERE status = 'completed'")['count'] ?? 0;
$avgScore = db()->fetchOne("SELECT AVG(percentage) as avg FROM student_quiz_attempts WHERE status = 'completed'")['avg'] ?? 0;

// User Registration Trend (last 6 months)
$userTrend = db()->fetchAll(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, role
     FROM users 
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m'), role
     ORDER BY month"
);

// Top Performing Students
$topStudents = db()->fetchAll(
    "SELECT u.users_id, u.first_name, u.last_name, u.student_id,
        COUNT(sqa.attempt_id) as attempts,
        AVG(sqa.percentage) as avg_score,
        MAX(sqa.percentage) as best_score
     FROM users u
     JOIN student_quiz_attempts sqa ON u.users_id = sqa.student_id
     WHERE u.role = 'student' AND sqa.status = 'completed'
     GROUP BY u.users_id
     HAVING attempts >= 3
     ORDER BY avg_score DESC
     LIMIT 10"
);

// Subject Performance
$subjectPerformance = db()->fetchAll(
    "SELECT s.subject_code, s.subject_name,
        COUNT(DISTINCT sqa.student_id) as students,
        COUNT(sqa.attempt_id) as attempts,
        AVG(sqa.percentage) as avg_score
     FROM subject s
     JOIN quiz q ON s.subject_id = q.subject_id
     JOIN student_quiz_attempts sqa ON q.quiz_id = sqa.quiz_id
     WHERE sqa.status = 'completed'
     GROUP BY s.subject_id
     ORDER BY avg_score DESC"
);

// Recent Activity
$recentActivity = db()->fetchAll(
    "SELECT al.activity_type, al.activity_description, al.created_at, u.first_name, u.last_name, u.role
     FROM activity_logs al
     LEFT JOIN users u ON al.users_id = u.users_id
     ORDER BY al.created_at DESC
     LIMIT 50"
);

// Quiz Completion Rate
$quizStats = db()->fetchAll(
    "SELECT q.quiz_id, q.quiz_title, s.subject_code,
        (SELECT COUNT(*) FROM student_quiz_attempts sqa WHERE sqa.quiz_id = q.quiz_id AND sqa.status = 'completed') as completed,
        (SELECT COUNT(*) FROM student_quiz_attempts sqa WHERE sqa.quiz_id = q.quiz_id AND sqa.status = 'in_progress') as in_progress,
        (SELECT AVG(sqa.percentage) FROM student_quiz_attempts sqa WHERE sqa.quiz_id = q.quiz_id AND sqa.status = 'completed') as avg_score
     FROM quiz q
     JOIN subject s ON q.subject_id = s.subject_id
     ORDER BY completed DESC
     LIMIT 15"
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <div class="page-header">
            <div>
                <h2>Reports & Analytics</h2>
                <p class="text-muted">System Performance Insights</p>
            </div>
            <button onclick="window.print()" class="btn-print">Print Report</button>
        </div>

        <!-- Report Tabs -->
        <div class="report-tabs">
            <a href="?type=overview" class="tab <?= $reportType === 'overview' ? 'active' : '' ?>">Overview</a>
            <a href="?type=users" class="tab <?= $reportType === 'users' ? 'active' : '' ?>">Users</a>
            <a href="?type=performance" class="tab <?= $reportType === 'performance' ? 'active' : '' ?>">Performance</a>
            <a href="?type=activity" class="tab <?= $reportType === 'activity' ? 'active' : '' ?>">Activity Log</a>
        </div>
        
        <?php if ($reportType === 'overview'): ?>
        <!-- OVERVIEW REPORT -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-label">Total Users</span>
                <span class="stat-value"><?= number_format($totalUsers) ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Students</span>
                <span class="stat-value"><?= number_format($totalStudents) ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Instructors</span>
                <span class="stat-value"><?= number_format($totalInstructors) ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Subjects</span>
                <span class="stat-value"><?= number_format($totalSubjects) ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Quizzes</span>
                <span class="stat-value"><?= number_format($totalQuizzes) ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Quiz Attempts</span>
                <span class="stat-value"><?= number_format($totalAttempts) ?></span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Avg Quiz Score</span>
                <span class="stat-value"><?= round($avgScore) ?>%</span>
            </div>
        </div>

        <div class="report-grid">
            <div class="card">
                <div class="card-header"><h3>Top Performing Students</h3></div>
                <div class="card-body">
                    <?php if (empty($topStudents)): ?>
                    <p class="empty-text">No data available</p>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Attempts</th>
                                <th>Average</th>
                                <th>Best Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topStudents as $i => $student): ?>
                            <tr>
                                <td><span class="rank"><?= $i + 1 ?></span></td>
                                <td class="font-medium"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                <td><?= e($student['student_id'] ?? '—') ?></td>
                                <td><?= $student['attempts'] ?></td>
                                <td><span class="badge <?= $student['avg_score'] >= 75 ? 'badge-success' : 'badge-danger' ?>"><?= round($student['avg_score']) ?>%</span></td>
                                <td><?= round($student['best_score']) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3>Subject Performance</h3></div>
                <div class="card-body">
                    <?php if (empty($subjectPerformance)): ?>
                    <p class="empty-text">No data available</p>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Students</th>
                                <th>Attempts</th>
                                <th>Average Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjectPerformance as $subj): ?>
                            <tr>
                                <td class="font-medium"><?= e($subj['subject_code']) ?></td>
                                <td><?= $subj['students'] ?></td>
                                <td><?= $subj['attempts'] ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= min(100, $subj['avg_score']) ?>%"></div>
                                        <span class="progress-text"><?= round($subj['avg_score']) ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php elseif ($reportType === 'users'): ?>
        <!-- USERS REPORT -->
        <div class="card">
            <div class="card-header"><h3>User Statistics</h3></div>
            <div class="card-body">
                <div class="user-stats">
                    <div class="user-stat">
                        <div class="user-stat-chart">
                            <div class="pie-segment students" style="--value:<?= $totalUsers > 0 ? ($totalStudents / $totalUsers * 100) : 0 ?>%"></div>
                        </div>
                        <div class="user-stat-info">
                            <span class="user-stat-value"><?= $totalStudents ?></span>
                            <span class="user-stat-label">Students (<?= $totalUsers > 0 ? round($totalStudents / $totalUsers * 100) : 0 ?>%)</span>
                        </div>
                    </div>
                    <div class="user-stat">
                        <div class="user-stat-chart">
                            <div class="pie-segment instructors" style="--value:<?= $totalUsers > 0 ? ($totalInstructors / $totalUsers * 100) : 0 ?>%"></div>
                        </div>
                        <div class="user-stat-info">
                            <span class="user-stat-value"><?= $totalInstructors ?></span>
                            <span class="user-stat-label">Instructors (<?= $totalUsers > 0 ? round($totalInstructors / $totalUsers * 100) : 0 ?>%)</span>
                        </div>
                    </div>
                </div>
                
                <h4 style="margin-top:32px">Registration by Status</h4>
                <?php
                $statusCounts = db()->fetchAll("SELECT status, COUNT(*) as count FROM users GROUP BY status");
                ?>
                <table class="data-table" style="margin-top:16px">
                    <thead><tr><th>Status</th><th>Count</th><th>Percentage</th></tr></thead>
                    <tbody>
                        <?php foreach ($statusCounts as $st): ?>
                        <tr>
                            <td><span class="badge badge-<?= $st['status'] === 'active' ? 'success' : ($st['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= ucfirst($st['status']) ?></span></td>
                            <td><?= $st['count'] ?></td>
                            <td><?= $totalUsers > 0 ? round($st['count'] / $totalUsers * 100) : 0 ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($reportType === 'performance'): ?>
        <!-- PERFORMANCE REPORT -->
        <div class="card">
            <div class="card-header"><h3>Quiz Performance</h3></div>
            <div class="card-body">
                <table class="data-table">
                    <thead><tr><th>Quiz</th><th>Subject</th><th>Completed</th><th>In Progress</th><th>Avg Score</th></tr></thead>
                    <tbody>
                        <?php if (empty($quizStats)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No quiz data available</td></tr>
                        <?php else: ?>
                        <?php foreach ($quizStats as $quiz): ?>
                        <tr>
                            <td><?= e($quiz['quiz_title']) ?></td>
                            <td><span class="subject-code"><?= e($quiz['subject_code']) ?></span></td>
                            <td><span class="badge badge-success"><?= $quiz['completed'] ?></span></td>
                            <td><span class="badge badge-warning"><?= $quiz['in_progress'] ?></span></td>
                            <td>
                                <?php if ($quiz['avg_score']): ?>
                                <span class="score <?= $quiz['avg_score'] >= 75 ? 'score-pass' : 'score-fail' ?>"><?= round($quiz['avg_score']) ?>%</span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($reportType === 'activity'): ?>
        <!-- ACTIVITY LOG -->
        <div class="card">
            <div class="card-header"><h3>Recent Activity Log</h3></div>
            <div class="card-body">
                <table class="data-table">
                    <thead><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Details</th></tr></thead>
                    <tbody>
                        <?php if (empty($recentActivity)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No activity logged</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentActivity as $activity): ?>
                        <tr>
                            <td><?= date('M d, g:i A', strtotime($activity['created_at'])) ?></td>
                            <td><?= $activity['first_name'] ? e($activity['first_name'] . ' ' . $activity['last_name']) : '<em>System</em>' ?></td>
                            <td><?php if ($activity['role']): ?><span class="badge badge-<?= $activity['role'] === 'admin' ? 'danger' : ($activity['role'] === 'instructor' ? 'primary' : 'info') ?>"><?= ucfirst($activity['role']) ?></span><?php endif; ?></td>
                            <td><?= e($activity['activity_description'] ?? $activity['activity_type'] ?? 'Activity') ?></td>
                            <td><small class="text-muted"><?= e($activity['activity_type'] ?? '') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</main>

<style>
/* Clean Professional Reports Page */

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
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
.header-actions {
    display: flex;
    gap: 12px;
}
.btn-print {
    padding: 10px 20px;
    background: #ffffff;
    color: #374151;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-print:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

/* Report Tabs */
.report-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 32px;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 0;
}
.tab {
    padding: 12px 24px;
    text-decoration: none;
    color: #6b7280;
    font-weight: 500;
    font-size: 14px;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
    border-radius: 6px 6px 0 0;
}
.tab:hover {
    color: #2563eb;
    background: #f9fafb;
}
.tab.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
    background: #ffffff;
    font-weight: 600;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}
.stat-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: box-shadow 0.2s;
}
.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
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

/* Report Grid Layout */
.report-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

/* Cards */
.card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}
.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
}
.card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}
.card-body {
    padding: 24px;
}

/* Data Tables */
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th,
.data-table td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.data-table th {
    font-weight: 600;
    font-size: 12px;
    color: #374151;
    background: #f9fafb;
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

/* Rank Numbers */
.rank {
    display: inline-block;
    min-width: 24px;
    text-align: center;
    font-weight: 600;
    color: #6b7280;
}

/* Score Badges */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.badge-success {
    background: #dcfce7;
    color: #166534;
}
.badge-danger {
    background: #fee2e2;
    color: #991b1b;
}

/* Progress Bars */
.progress-bar {
    position: relative;
    height: 24px;
    background: #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    border-radius: 8px;
    transition: width 0.4s ease;
}
.progress-fill.pass {
    background: #10b981;
}
.progress-fill.fail {
    background: #ef4444;
}
.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 12px;
    font-weight: 600;
    color: #111827;
}

/* Subject Code Badge */
.subject-code {
    background: #f3f4f6;
    color: #374151;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

/* User Stats Display */
.user-stats {
    display: flex;
    gap: 48px;
    justify-content: center;
    padding: 20px 0;
}
.user-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.user-stat-value {
    display: block;
    font-size: 32px;
    font-weight: 700;
    color: #111827;
    line-height: 1;
}
.user-stat-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Empty State */
.empty-text {
    text-align: center;
    color: #9ca3af;
    padding: 40px 0;
    font-size: 14px;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .report-grid {
        grid-template-columns: 1fr;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .user-stats {
        flex-direction: column;
        gap: 24px;
    }
}

/* Print Styles */
@media print {
    .report-tabs,
    .header-actions {
        display: none;
    }
    .card {
        page-break-inside: avoid;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>