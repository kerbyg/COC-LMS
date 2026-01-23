<?php
/**
 * Dean - Dashboard
 * Academic oversight and department statistics
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('dean');

$pageTitle = 'Dean Dashboard';
$currentPage = 'dashboard';

// Get academic statistics
$totalInstructors = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'instructor' AND status = 'active'")['count'] ?? 0;
$totalStudents = db()->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'active'")['count'] ?? 0;
$totalSubjects = db()->fetchOne("SELECT COUNT(*) as count FROM subject WHERE status = 'active'")['count'] ?? 0;
$totalSections = db()->fetchOne("SELECT COUNT(*) as count FROM section WHERE status = 'active'")['count'] ?? 0;

// Subject offerings for current year
$currentYear = date('Y');
$academicYear = "$currentYear-" . ($currentYear + 1);
$currentSemester = (date('n') >= 6 && date('n') <= 10) ? '1st' : '2nd';

$activeOfferings = db()->fetchOne(
    "SELECT COUNT(*) as count FROM subject_offered
     WHERE academic_year = ? AND semester = ? AND status = 'active'",
    [$academicYear, $currentSemester]
)['count'] ?? 0;

// Enrollment stats
$totalEnrollments = db()->fetchOne("SELECT COUNT(*) as count FROM student_subject WHERE status = 'enrolled'")['count'] ?? 0;
$avgEnrollmentPerSection = $totalSections > 0 ? round($totalEnrollments / $totalSections, 1) : 0;

// Faculty workload
$facultyWorkload = db()->fetchAll(
    "SELECT u.users_id, u.first_name, u.last_name, u.employee_id,
        COUNT(DISTINCT fs.faculty_subject_id) as subjects_count,
        COUNT(DISTINCT sec.section_id) as sections_count,
        (SELECT COUNT(*) FROM student_subject ss
         JOIN section sec2 ON ss.section_id = sec2.section_id
         WHERE sec2.user_instructor_id = u.users_id AND ss.status = 'enrolled') as students_count
     FROM users u
     LEFT JOIN faculty_subject fs ON u.users_id = fs.user_teacher_id AND fs.status = 'active'
     LEFT JOIN section sec ON u.users_id = sec.user_instructor_id AND sec.status = 'active'
     WHERE u.role = 'instructor' AND u.status = 'active'
     GROUP BY u.users_id
     ORDER BY subjects_count DESC, students_count DESC
     LIMIT 10"
);

// Subject performance overview
$subjectPerformance = db()->fetchAll(
    "SELECT s.subject_code, s.subject_name,
        COUNT(DISTINCT ss.user_student_id) as enrolled_students,
        COUNT(DISTINCT sec.section_id) as section_count,
        (SELECT AVG(sqa.percentage)
         FROM student_quiz_attempts sqa
         JOIN quiz q ON sqa.quiz_id = q.quiz_id
         WHERE q.subject_id = s.subject_id AND sqa.status = 'completed') as avg_quiz_score
     FROM subject s
     LEFT JOIN subject_offered so ON s.subject_id = so.subject_id
     LEFT JOIN section sec ON so.subject_offered_id = sec.subject_offered_id AND sec.status = 'active'
     LEFT JOIN student_subject ss ON sec.section_id = ss.section_id AND ss.status = 'enrolled'
     WHERE s.status = 'active'
     GROUP BY s.subject_id
     HAVING section_count > 0
     ORDER BY enrolled_students DESC
     LIMIT 8"
);

// Recent activity (academic focused)
$recentActivity = db()->fetchAll(
    "SELECT al.activity_type, al.activity_description, al.created_at,
        u.first_name, u.last_name, u.role
     FROM activity_logs al
     LEFT JOIN users u ON al.users_id = u.users_id
     WHERE al.activity_type IN ('enrollment', 'grade_posted', 'quiz_completed', 'lesson_created', 'announcement_posted')
     ORDER BY al.created_at DESC
     LIMIT 15"
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>Academic Dashboard</h2>
                <p class="text-muted">Department Overview & Performance</p>
            </div>
            <div class="semester-badge">
                <?= $academicYear ?> - <?= $currentSemester ?> Semester
            </div>
        </div>

        <!-- Primary Stats -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-content">
                    <span class="stat-label">Active Instructors</span>
                    <span class="stat-value"><?= number_format($totalInstructors) ?></span>
                </div>
                <a href="instructors.php" class="stat-link">View All →</a>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <span class="stat-label">Enrolled Students</span>
                    <span class="stat-value"><?= number_format($totalStudents) ?></span>
                </div>
                <div class="stat-footer">
                    <?= number_format($totalEnrollments) ?> total enrollments
                </div>
            </div>

            <div class="stat-card purple">
                <div class="stat-icon">📚</div>
                <div class="stat-content">
                    <span class="stat-label">Active Subjects</span>
                    <span class="stat-value"><?= number_format($totalSubjects) ?></span>
                </div>
                <div class="stat-footer">
                    <?= $activeOfferings ?> offered this semester
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">🏫</div>
                <div class="stat-content">
                    <span class="stat-label">Sections</span>
                    <span class="stat-value"><?= number_format($totalSections) ?></span>
                </div>
                <div class="stat-footer">
                    Avg <?= $avgEnrollmentPerSection ?> students/section
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="dashboard-grid">

            <!-- Faculty Workload -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Faculty Workload</h3>
                    <a href="instructors.php" class="view-all">View All →</a>
                </div>
                <div class="card-body">
                    <?php if (empty($facultyWorkload)): ?>
                        <p class="empty-text">No faculty data available</p>
                    <?php else: ?>
                        <div class="faculty-list">
                            <?php foreach ($facultyWorkload as $faculty): ?>
                            <div class="faculty-item">
                                <div class="faculty-info">
                                    <div class="faculty-avatar">
                                        <?= strtoupper(substr($faculty['first_name'], 0, 1) . substr($faculty['last_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="faculty-name"><?= e($faculty['first_name'] . ' ' . $faculty['last_name']) ?></div>
                                        <div class="faculty-id"><?= e($faculty['employee_id'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                                <div class="faculty-stats">
                                    <span class="faculty-stat" title="Subjects">
                                        📚 <?= $faculty['subjects_count'] ?>
                                    </span>
                                    <span class="faculty-stat" title="Sections">
                                        🏫 <?= $faculty['sections_count'] ?>
                                    </span>
                                    <span class="faculty-stat" title="Students">
                                        👥 <?= $faculty['students_count'] ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Subject Performance -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Subject Enrollment</h3>
                    <a href="subjects.php" class="view-all">View All →</a>
                </div>
                <div class="card-body">
                    <?php if (empty($subjectPerformance)): ?>
                        <p class="empty-text">No subject data available</p>
                    <?php else: ?>
                        <div class="subject-list">
                            <?php foreach ($subjectPerformance as $subject): ?>
                            <div class="subject-item">
                                <div class="subject-info">
                                    <span class="subject-code"><?= e($subject['subject_code']) ?></span>
                                    <span class="subject-name"><?= e($subject['subject_name']) ?></span>
                                </div>
                                <div class="subject-stats">
                                    <span class="subject-count"><?= $subject['enrolled_students'] ?> students</span>
                                    <span class="subject-sections"><?= $subject['section_count'] ?> sections</span>
                                    <?php if ($subject['avg_quiz_score']): ?>
                                    <span class="subject-score <?= $subject['avg_quiz_score'] >= 75 ? 'pass' : 'fail' ?>">
                                        <?= round($subject['avg_quiz_score']) ?>% avg
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Academic Activity</h3>
            </div>
            <div class="card-body">
                <?php if (empty($recentActivity)): ?>
                    <p class="empty-text">No recent activity</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-dot"></div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?= $activity['first_name'] ? e($activity['first_name'] . ' ' . $activity['last_name']) : 'System' ?></strong>
                                    <span class="badge badge-<?= $activity['role'] ?? 'secondary' ?>"><?= ucfirst($activity['role'] ?? 'System') ?></span>
                                    - <?= e($activity['activity_description'] ?? $activity['activity_type']) ?>
                                </div>
                                <div class="activity-time"><?= date('M d, g:i A', strtotime($activity['created_at'])) ?></div>
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
/* Dean Dashboard Styles */

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
    font-size: 14px;
    margin: 0;
}
.semester-badge {
    background: #2563eb;
    color: #ffffff;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}
.stat-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    transition: all 0.2s;
}
.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}
.stat-card.blue { border-left: 4px solid #2563eb; }
.stat-card.green { border-left: 4px solid #10b981; }
.stat-card.purple { border-left: 4px solid #8b5cf6; }
.stat-card.orange { border-left: 4px solid #f59e0b; }

.stat-icon {
    font-size: 32px;
    margin-bottom: 12px;
}
.stat-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
}
.stat-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #111827;
    line-height: 1;
}
.stat-footer {
    font-size: 12px;
    color: #6b7280;
    padding-top: 12px;
    border-top: 1px solid #e5e7eb;
}
.stat-link {
    display: inline-block;
    color: #2563eb;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    margin-top: 8px;
}
.stat-link:hover {
    color: #1d4ed8;
}

/* Dashboard Grid */
.dashboard-grid {
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
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}
.card-body {
    padding: 24px;
}
.view-all {
    color: #2563eb;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
}
.view-all:hover {
    color: #1d4ed8;
}

/* Faculty List */
.faculty-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.faculty-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
}
.faculty-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.faculty-avatar {
    width: 40px;
    height: 40px;
    background: #2563eb;
    color: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
}
.faculty-name {
    font-weight: 600;
    color: #111827;
}
.faculty-id {
    font-size: 12px;
    color: #6b7280;
}
.faculty-stats {
    display: flex;
    gap: 12px;
}
.faculty-stat {
    font-size: 13px;
    color: #374151;
    padding: 4px 8px;
    background: #ffffff;
    border-radius: 6px;
}

/* Subject List */
.subject-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.subject-item {
    padding: 12px;
    background: #f9fafb;
    border-radius: 8px;
}
.subject-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}
.subject-code {
    background: #2563eb;
    color: #ffffff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}
.subject-name {
    font-weight: 500;
    color: #111827;
}
.subject-stats {
    display: flex;
    gap: 12px;
    font-size: 12px;
    color: #6b7280;
}
.subject-count, .subject-sections {
    color: #374151;
}
.subject-score {
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
}
.subject-score.pass {
    background: #dcfce7;
    color: #166534;
}
.subject-score.fail {
    background: #fee2e2;
    color: #991b1b;
}

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.activity-item {
    display: flex;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #e5e7eb;
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-dot {
    width: 8px;
    height: 8px;
    background: #2563eb;
    border-radius: 50%;
    margin-top: 6px;
    flex-shrink: 0;
}
.activity-content {
    flex: 1;
}
.activity-text {
    font-size: 14px;
    color: #374151;
    margin-bottom: 4px;
}
.activity-time {
    font-size: 12px;
    color: #6b7280;
}
.badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin: 0 4px;
}
.badge-admin { background: #fee2e2; color: #991b1b; }
.badge-instructor { background: #dbeafe; color: #1e40af; }
.badge-student { background: #dcfce7; color: #166534; }
.badge-secondary { background: #e5e7eb; color: #374151; }

.empty-text {
    text-align: center;
    color: #9ca3af;
    padding: 40px 0;
    font-size: 14px;
}

/* Responsive */
@media (max-width: 1024px) {
    .dashboard-grid {
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
    .faculty-stats {
        flex-direction: column;
        gap: 4px;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
