<?php
/**
 * Dean - Reports
 * Comprehensive Academic Reports & Analytics for Dean's Department
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('dean');

$pageTitle = 'Reports';
$currentPage = 'reports';
$userId = Auth::id();

// Get dean's department
$dean = db()->fetchOne("SELECT department_id FROM users WHERE users_id = ?", [$userId]);
$deptId = $dean['department_id'] ?? 0;

// Get department info
$department = db()->fetchOne("SELECT department_name, department_code FROM department WHERE department_id = ?", [$deptId]);

// Get filter values
$reportType = $_GET['type'] ?? 'overview';
$selectedYear = $_GET['year'] ?? '';
$selectedSemester = $_GET['semester'] ?? '';

// Get available academic years and semesters for filters
$academicYears = db()->fetchAll(
    "SELECT DISTINCT academic_year FROM subject_offered ORDER BY academic_year DESC"
);
$semesters = db()->fetchAll(
    "SELECT DISTINCT semester FROM subject_offered ORDER BY semester"
);

// Build date filter for queries
$yearFilter = $selectedYear ? "AND so.academic_year = '" . addslashes($selectedYear) . "'" : "";
$semesterFilter = $selectedSemester ? "AND so.semester = '" . addslashes($selectedSemester) . "'" : "";

// ==================== OVERVIEW STATS ====================
// Department-specific counts
$totalStudents = db()->fetchOne(
    "SELECT COUNT(DISTINCT ss.user_student_id) as count
     FROM student_subject ss
     JOIN section sec ON ss.section_id = sec.section_id
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE s.department_id = ? $yearFilter $semesterFilter",
    [$deptId]
)['count'] ?? 0;

$totalInstructors = db()->fetchOne(
    "SELECT COUNT(*) as count FROM users WHERE role = 'instructor' AND department_id = ? AND status = 'active'",
    [$deptId]
)['count'] ?? 0;

$totalSubjects = db()->fetchOne(
    "SELECT COUNT(*) as count FROM subject WHERE department_id = ?",
    [$deptId]
)['count'] ?? 0;

$totalSections = db()->fetchOne(
    "SELECT COUNT(*) as count FROM section sec
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE s.department_id = ? AND sec.status = 'active' $yearFilter $semesterFilter",
    [$deptId]
)['count'] ?? 0;

$totalQuizzes = db()->fetchOne(
    "SELECT COUNT(*) as count FROM quiz q
     JOIN subject s ON q.subject_id = s.subject_id
     WHERE s.department_id = ?",
    [$deptId]
)['count'] ?? 0;

$totalAttempts = db()->fetchOne(
    "SELECT COUNT(*) as count FROM student_quiz_attempts sqa
     JOIN quiz q ON sqa.quiz_id = q.quiz_id
     JOIN subject s ON q.subject_id = s.subject_id
     WHERE s.department_id = ? AND sqa.status = 'completed'",
    [$deptId]
)['count'] ?? 0;

$avgScore = db()->fetchOne(
    "SELECT AVG(sqa.percentage) as avg FROM student_quiz_attempts sqa
     JOIN quiz q ON sqa.quiz_id = q.quiz_id
     JOIN subject s ON q.subject_id = s.subject_id
     WHERE s.department_id = ? AND sqa.status = 'completed'",
    [$deptId]
)['avg'] ?? 0;

$passRate = db()->fetchOne(
    "SELECT
        COUNT(CASE WHEN sqa.percentage >= 75 THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as rate
     FROM student_quiz_attempts sqa
     JOIN quiz q ON sqa.quiz_id = q.quiz_id
     JOIN subject s ON q.subject_id = s.subject_id
     WHERE s.department_id = ? AND sqa.status = 'completed'",
    [$deptId]
)['rate'] ?? 0;

// ==================== TOP PERFORMING STUDENTS ====================
$topStudents = db()->fetchAll(
    "SELECT u.users_id, u.first_name, u.last_name, u.student_id,
        COUNT(sqa.attempt_id) as attempts,
        AVG(sqa.percentage) as avg_score,
        MAX(sqa.percentage) as best_score
     FROM users u
     JOIN student_quiz_attempts sqa ON u.users_id = sqa.student_id
     JOIN quiz q ON sqa.quiz_id = q.quiz_id
     JOIN subject s ON q.subject_id = s.subject_id
     WHERE u.role = 'student' AND sqa.status = 'completed' AND s.department_id = ?
     GROUP BY u.users_id
     HAVING attempts >= 1
     ORDER BY avg_score DESC
     LIMIT 10",
    [$deptId]
);

// ==================== SUBJECT PERFORMANCE ====================
// Get all subjects first, then add quiz data
$subjects = db()->fetchAll(
    "SELECT subject_id, subject_code, subject_name FROM subject WHERE department_id = ? ORDER BY subject_code",
    [$deptId]
);

$subjectPerformance = [];
foreach ($subjects as $subj) {
    // Get quiz stats for this subject
    $stats = db()->fetchOne(
        "SELECT
            COUNT(DISTINCT sqa.student_id) as students,
            COUNT(sqa.attempt_id) as attempts,
            AVG(sqa.percentage) as avg_score,
            SUM(CASE WHEN sqa.percentage >= 75 THEN 1 ELSE 0 END) as passed,
            SUM(CASE WHEN sqa.percentage < 75 THEN 1 ELSE 0 END) as failed
         FROM quiz q
         LEFT JOIN student_quiz_attempts sqa ON q.quiz_id = sqa.quiz_id AND sqa.status = 'completed'
         WHERE q.subject_id = ?",
        [$subj['subject_id']]
    );

    $subjectPerformance[] = [
        'subject_id' => $subj['subject_id'],
        'subject_code' => $subj['subject_code'],
        'subject_name' => $subj['subject_name'],
        'students' => $stats ? ($stats['students'] ?? 0) : 0,
        'attempts' => $stats ? ($stats['attempts'] ?? 0) : 0,
        'avg_score' => $stats ? $stats['avg_score'] : null,
        'passed' => $stats ? ($stats['passed'] ?? 0) : 0,
        'failed' => $stats ? ($stats['failed'] ?? 0) : 0
    ];
}

// ==================== ENROLLMENT ANALYTICS ====================
// Enrollment by Program
$enrollmentByProgram = db()->fetchAll(
    "SELECT p.program_code, p.program_name,
        COUNT(DISTINCT ss.user_student_id) as students,
        COUNT(DISTINCT ss.section_id) as sections
     FROM program p
     LEFT JOIN users u ON p.program_id = u.program_id AND u.role = 'student'
     LEFT JOIN student_subject ss ON u.users_id = ss.user_student_id
     WHERE p.department_id = ?
     GROUP BY p.program_id
     ORDER BY students DESC",
    [$deptId]
);

// Enrollment by Section with Capacity
$enrollmentBySection = db()->fetchAll(
    "SELECT sec.section_id, sec.section_name, sec.max_students,
        s.subject_code, s.subject_name, so.academic_year, so.semester,
        COUNT(ss.student_subject_id) as enrolled,
        ROUND(COUNT(ss.student_subject_id) * 100.0 / NULLIF(sec.max_students, 0), 1) as utilization
     FROM section sec
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     LEFT JOIN student_subject ss ON sec.section_id = ss.section_id AND ss.status = 'enrolled'
     WHERE s.department_id = ? AND sec.status = 'active' $yearFilter $semesterFilter
     GROUP BY sec.section_id
     ORDER BY utilization DESC",
    [$deptId]
);

// Total Capacity Stats
$capacityStats = db()->fetchOne(
    "SELECT
        SUM(sec.max_students) as total_capacity,
        COUNT(DISTINCT ss.student_subject_id) as total_enrolled
     FROM section sec
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     LEFT JOIN student_subject ss ON sec.section_id = ss.section_id AND ss.status = 'enrolled'
     WHERE s.department_id = ? AND sec.status = 'active' $yearFilter $semesterFilter",
    [$deptId]
);

// ==================== INSTRUCTOR PERFORMANCE ====================
// Show ALL instructors in department, even without assignments
// Note: quiz table has user_teacher_id directly, not subject_offered_id
$instructorPerformance = db()->fetchAll(
    "SELECT u.users_id, u.first_name, u.last_name, u.employee_id,
        (SELECT COUNT(DISTINCT fs2.section_id) FROM faculty_subject fs2
         WHERE fs2.user_teacher_id = u.users_id AND fs2.status = 'active') as sections_handled,
        (SELECT COUNT(DISTINCT ss2.user_student_id) FROM student_subject ss2
         JOIN faculty_subject fs2 ON ss2.section_id = fs2.section_id
         WHERE fs2.user_teacher_id = u.users_id AND ss2.status = 'enrolled') as total_students,
        (SELECT COUNT(*) FROM quiz q2
         WHERE q2.user_teacher_id = u.users_id) as quizzes_created,
        (SELECT AVG(sqa2.percentage) FROM student_quiz_attempts sqa2
         JOIN quiz q2 ON sqa2.quiz_id = q2.quiz_id
         WHERE q2.user_teacher_id = u.users_id AND sqa2.status = 'completed') as avg_student_score,
        (SELECT COUNT(CASE WHEN sqa3.percentage >= 75 THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)
         FROM student_quiz_attempts sqa3
         JOIN quiz q3 ON sqa3.quiz_id = q3.quiz_id
         WHERE q3.user_teacher_id = u.users_id AND sqa3.status = 'completed') as pass_rate
     FROM users u
     WHERE u.role = 'instructor' AND u.department_id = ? AND u.status = 'active'
     ORDER BY u.last_name, u.first_name",
    [$deptId]
);

// ==================== QUIZ PERFORMANCE ====================
$quizStats = db()->fetchAll(
    "SELECT q.quiz_id, q.quiz_title, s.subject_code, s.subject_name,
        COUNT(CASE WHEN sqa.status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN sqa.status = 'in_progress' THEN 1 END) as in_progress,
        AVG(CASE WHEN sqa.status = 'completed' THEN sqa.percentage END) as avg_score,
        MIN(CASE WHEN sqa.status = 'completed' THEN sqa.percentage END) as min_score,
        MAX(CASE WHEN sqa.status = 'completed' THEN sqa.percentage END) as max_score
     FROM quiz q
     JOIN subject s ON q.subject_id = s.subject_id
     LEFT JOIN student_quiz_attempts sqa ON q.quiz_id = sqa.quiz_id
     WHERE s.department_id = ?
     GROUP BY q.quiz_id
     ORDER BY completed DESC
     LIMIT 20",
    [$deptId]
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <div class="page-header">
            <div>
                <h2>Academic Reports</h2>
                <p class="text-muted"><?= e($department['department_name'] ?? 'Department') ?> - Performance & Analytics</p>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" class="btn-print">Print Report</button>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <form method="GET" class="filters-form">
                <input type="hidden" name="type" value="<?= e($reportType) ?>">
                <div class="filter-group">
                    <label>Academic Year</label>
                    <select name="year" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Years</option>
                        <?php foreach ($academicYears as $year): ?>
                        <option value="<?= e($year['academic_year']) ?>" <?= $selectedYear === $year['academic_year'] ? 'selected' : '' ?>>
                            <?= e($year['academic_year']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Semester</label>
                    <select name="semester" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Semesters</option>
                        <?php foreach ($semesters as $sem): ?>
                        <option value="<?= e($sem['semester']) ?>" <?= $selectedSemester === $sem['semester'] ? 'selected' : '' ?>>
                            <?= e($sem['semester']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selectedYear || $selectedSemester): ?>
                <a href="?type=<?= e($reportType) ?>" class="btn-clear">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Report Tabs -->
        <div class="report-tabs">
            <a href="?type=overview<?= $selectedYear ? '&year=' . e($selectedYear) : '' ?><?= $selectedSemester ? '&semester=' . e($selectedSemester) : '' ?>"
               class="tab <?= $reportType === 'overview' ? 'active' : '' ?>">Overview</a>
            <a href="?type=enrollment<?= $selectedYear ? '&year=' . e($selectedYear) : '' ?><?= $selectedSemester ? '&semester=' . e($selectedSemester) : '' ?>"
               class="tab <?= $reportType === 'enrollment' ? 'active' : '' ?>">Enrollment Analytics</a>
            <a href="?type=instructors<?= $selectedYear ? '&year=' . e($selectedYear) : '' ?><?= $selectedSemester ? '&semester=' . e($selectedSemester) : '' ?>"
               class="tab <?= $reportType === 'instructors' ? 'active' : '' ?>">Instructor Performance</a>
            <a href="?type=subjects<?= $selectedYear ? '&year=' . e($selectedYear) : '' ?><?= $selectedSemester ? '&semester=' . e($selectedSemester) : '' ?>"
               class="tab <?= $reportType === 'subjects' ? 'active' : '' ?>">Subject Performance</a>
        </div>

        <?php if ($reportType === 'overview'): ?>
        <!-- ==================== OVERVIEW TAB ==================== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">👥</div>
                <div class="stat-info">
                    <span class="stat-value"><?= number_format($totalStudents) ?></span>
                    <span class="stat-label">Enrolled Students</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">👨‍🏫</div>
                <div class="stat-info">
                    <span class="stat-value"><?= number_format($totalInstructors) ?></span>
                    <span class="stat-label">Instructors</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">📚</div>
                <div class="stat-info">
                    <span class="stat-value"><?= number_format($totalSubjects) ?></span>
                    <span class="stat-label">Subjects</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">🏫</div>
                <div class="stat-info">
                    <span class="stat-value"><?= number_format($totalSections) ?></span>
                    <span class="stat-label">Active Sections</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal">📝</div>
                <div class="stat-info">
                    <span class="stat-value"><?= number_format($totalQuizzes) ?></span>
                    <span class="stat-label">Quizzes</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pink">✅</div>
                <div class="stat-info">
                    <span class="stat-value"><?= number_format($totalAttempts) ?></span>
                    <span class="stat-label">Quiz Attempts</span>
                </div>
            </div>
            <div class="stat-card highlight">
                <div class="stat-icon gold">⭐</div>
                <div class="stat-info">
                    <span class="stat-value"><?= round($avgScore) ?>%</span>
                    <span class="stat-label">Avg Quiz Score</span>
                </div>
            </div>
            <div class="stat-card <?= $passRate >= 75 ? 'highlight-success' : 'highlight-warning' ?>">
                <div class="stat-icon <?= $passRate >= 75 ? 'green' : 'orange' ?>">📊</div>
                <div class="stat-info">
                    <span class="stat-value"><?= round($passRate) ?>%</span>
                    <span class="stat-label">Pass Rate</span>
                </div>
            </div>
        </div>

        <div class="report-grid">
            <div class="card">
                <div class="card-header">
                    <h3>🏆 Top Performing Students</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($topStudents)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <p>No quiz data available yet</p>
                    </div>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student</th>
                                <th>Attempts</th>
                                <th>Average</th>
                                <th>Best</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topStudents as $i => $student): ?>
                            <tr>
                                <td><span class="rank rank-<?= $i + 1 ?>"><?= $i + 1 ?></span></td>
                                <td>
                                    <div class="student-info">
                                        <span class="student-name"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></span>
                                        <span class="student-id"><?= e($student['student_id'] ?? 'N/A') ?></span>
                                    </div>
                                </td>
                                <td><?= $student['attempts'] ?></td>
                                <td><span class="score <?= $student['avg_score'] >= 75 ? 'score-pass' : 'score-fail' ?>"><?= round($student['avg_score']) ?>%</span></td>
                                <td><span class="score score-best"><?= round($student['best_score']) ?>%</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>📚 Subject Performance Summary</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($subjectPerformance)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <p>No subject data available</p>
                    </div>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Students</th>
                                <th>Avg Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($subjectPerformance, 0, 8) as $subj): ?>
                            <tr>
                                <td>
                                    <span class="subject-code"><?= e($subj['subject_code']) ?></span>
                                </td>
                                <td><?= $subj['students'] ?? 0 ?></td>
                                <td>
                                    <?php if ($subj['avg_score']): ?>
                                    <div class="progress-bar-mini">
                                        <div class="progress-fill <?= $subj['avg_score'] >= 75 ? 'pass' : 'fail' ?>" style="width: <?= min(100, $subj['avg_score']) ?>%"></div>
                                        <span class="progress-text"><?= round($subj['avg_score']) ?>%</span>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">No data</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php elseif ($reportType === 'enrollment'): ?>
        <!-- ==================== ENROLLMENT ANALYTICS TAB ==================== -->

        <!-- Capacity Overview -->
        <div class="capacity-overview">
            <div class="capacity-card">
                <h4>Overall Capacity Utilization</h4>
                <div class="capacity-stats">
                    <div class="capacity-number">
                        <span class="big-number"><?= number_format($capacityStats['total_enrolled'] ?? 0) ?></span>
                        <span class="separator">/</span>
                        <span class="total"><?= number_format($capacityStats['total_capacity'] ?? 0) ?></span>
                    </div>
                    <div class="capacity-bar">
                        <?php $utilization = ($capacityStats['total_capacity'] > 0) ? ($capacityStats['total_enrolled'] / $capacityStats['total_capacity'] * 100) : 0; ?>
                        <div class="capacity-fill <?= $utilization >= 90 ? 'full' : ($utilization >= 70 ? 'medium' : 'low') ?>" style="width: <?= min(100, $utilization) ?>%"></div>
                    </div>
                    <span class="capacity-percent"><?= round($utilization) ?>% Utilized</span>
                </div>
            </div>
        </div>

        <div class="report-grid">
            <!-- Enrollment by Program -->
            <div class="card">
                <div class="card-header">
                    <h3>📊 Enrollment by Program</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($enrollmentByProgram)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📊</div>
                        <p>No program data available</p>
                    </div>
                    <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Program</th>
                                <th>Students</th>
                                <th>Sections</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollmentByProgram as $prog): ?>
                            <tr>
                                <td>
                                    <div class="program-info">
                                        <span class="program-code"><?= e($prog['program_code']) ?></span>
                                        <span class="program-name"><?= e($prog['program_name']) ?></span>
                                    </div>
                                </td>
                                <td><span class="count-badge blue"><?= $prog['students'] ?></span></td>
                                <td><span class="count-badge gray"><?= $prog['sections'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section Capacity -->
            <div class="card">
                <div class="card-header">
                    <h3>🏫 Section Capacity Utilization</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($enrollmentBySection)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🏫</div>
                        <p>No section data available</p>
                    </div>
                    <?php else: ?>
                    <div class="section-list">
                        <?php foreach (array_slice($enrollmentBySection, 0, 10) as $sec): ?>
                        <div class="section-item">
                            <div class="section-info">
                                <span class="section-name"><?= e($sec['section_name']) ?></span>
                                <span class="section-subject"><?= e($sec['subject_code']) ?></span>
                            </div>
                            <div class="section-capacity">
                                <div class="mini-bar">
                                    <div class="mini-fill <?= $sec['utilization'] >= 90 ? 'full' : ($sec['utilization'] >= 70 ? 'medium' : 'low') ?>"
                                         style="width: <?= min(100, $sec['utilization'] ?? 0) ?>%"></div>
                                </div>
                                <span class="capacity-text"><?= $sec['enrolled'] ?>/<?= $sec['max_students'] ?> (<?= $sec['utilization'] ?? 0 ?>%)</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php elseif ($reportType === 'instructors'): ?>
        <!-- ==================== INSTRUCTOR PERFORMANCE TAB ==================== -->
        <div class="card full-width">
            <div class="card-header">
                <h3>👨‍🏫 Instructor Performance Overview</h3>
            </div>
            <div class="card-body">
                <?php if (empty($instructorPerformance)): ?>
                <div class="empty-state">
                    <div class="empty-icon">👨‍🏫</div>
                    <p>No instructor data available</p>
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Instructor</th>
                            <th>Employee ID</th>
                            <th>Sections</th>
                            <th>Students</th>
                            <th>Quizzes Created</th>
                            <th>Avg Student Score</th>
                            <th>Pass Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($instructorPerformance as $inst): ?>
                        <tr>
                            <td>
                                <div class="instructor-info">
                                    <div class="instructor-avatar"><?= strtoupper(substr($inst['first_name'], 0, 1) . substr($inst['last_name'], 0, 1)) ?></div>
                                    <span class="instructor-name"><?= e($inst['first_name'] . ' ' . $inst['last_name']) ?></span>
                                </div>
                            </td>
                            <td><span class="employee-id"><?= e($inst['employee_id'] ?? 'N/A') ?></span></td>
                            <td><span class="count-badge purple"><?= $inst['sections_handled'] ?></span></td>
                            <td><span class="count-badge blue"><?= $inst['total_students'] ?></span></td>
                            <td><span class="count-badge teal"><?= $inst['quizzes_created'] ?? 0 ?></span></td>
                            <td>
                                <?php if ($inst['avg_student_score']): ?>
                                <span class="score <?= $inst['avg_student_score'] >= 75 ? 'score-pass' : 'score-fail' ?>">
                                    <?= round($inst['avg_student_score']) ?>%
                                </span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($inst['pass_rate'] !== null): ?>
                                <div class="pass-rate-bar">
                                    <div class="pass-rate-fill <?= $inst['pass_rate'] >= 75 ? 'good' : ($inst['pass_rate'] >= 50 ? 'medium' : 'low') ?>"
                                         style="width: <?= min(100, $inst['pass_rate']) ?>%"></div>
                                    <span class="pass-rate-text"><?= round($inst['pass_rate']) ?>%</span>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <?php elseif ($reportType === 'subjects'): ?>
        <!-- ==================== SUBJECT PERFORMANCE TAB ==================== -->
        <div class="card full-width">
            <div class="card-header">
                <h3>📚 Subject Performance Details</h3>
            </div>
            <div class="card-body">
                <?php if (empty($subjectPerformance)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📚</div>
                    <p>No subject data available</p>
                </div>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Students</th>
                            <th>Attempts</th>
                            <th>Passed</th>
                            <th>Failed</th>
                            <th>Pass Rate</th>
                            <th>Avg Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subjectPerformance as $subj): ?>
                        <?php
                            $total = ($subj['passed'] ?? 0) + ($subj['failed'] ?? 0);
                            $subjectPassRate = $total > 0 ? ($subj['passed'] / $total * 100) : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="subject-info">
                                    <span class="subject-code-lg"><?= e($subj['subject_code']) ?></span>
                                    <span class="subject-name-sm"><?= e($subj['subject_name']) ?></span>
                                </div>
                            </td>
                            <td><span class="count-badge blue"><?= $subj['students'] ?? 0 ?></span></td>
                            <td><?= $subj['attempts'] ?? 0 ?></td>
                            <td><span class="count-badge green"><?= $subj['passed'] ?? 0 ?></span></td>
                            <td><span class="count-badge red"><?= $subj['failed'] ?? 0 ?></span></td>
                            <td>
                                <?php if ($total > 0): ?>
                                <div class="pass-rate-bar">
                                    <div class="pass-rate-fill <?= $subjectPassRate >= 75 ? 'good' : ($subjectPassRate >= 50 ? 'medium' : 'low') ?>"
                                         style="width: <?= $subjectPassRate ?>%"></div>
                                    <span class="pass-rate-text"><?= round($subjectPassRate) ?>%</span>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($subj['avg_score']): ?>
                                <span class="score-lg <?= $subj['avg_score'] >= 75 ? 'score-pass' : 'score-fail' ?>">
                                    <?= round($subj['avg_score']) ?>%
                                </span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Most Difficult Subjects -->
        <div class="card full-width" style="margin-top: 24px;">
            <div class="card-header">
                <h3>⚠️ Subjects Needing Attention (Lowest Scores)</h3>
            </div>
            <div class="card-body">
                <?php
                $difficultSubjects = array_filter($subjectPerformance, fn($s) => $s['avg_score'] !== null && $s['avg_score'] < 75);
                usort($difficultSubjects, fn($a, $b) => ($a['avg_score'] ?? 100) - ($b['avg_score'] ?? 100));
                $difficultSubjects = array_slice($difficultSubjects, 0, 5);
                ?>
                <?php if (empty($difficultSubjects)): ?>
                <div class="success-state">
                    <div class="success-icon">🎉</div>
                    <p>All subjects are performing well (above 75% average)</p>
                </div>
                <?php else: ?>
                <div class="difficult-subjects">
                    <?php foreach ($difficultSubjects as $subj): ?>
                    <div class="difficult-card">
                        <div class="difficult-header">
                            <span class="subject-code-lg"><?= e($subj['subject_code']) ?></span>
                            <span class="score-lg score-fail"><?= round($subj['avg_score']) ?>%</span>
                        </div>
                        <p class="difficult-name"><?= e($subj['subject_name']) ?></p>
                        <div class="difficult-stats">
                            <span><?= $subj['students'] ?? 0 ?> students</span>
                            <span><?= $subj['failed'] ?? 0 ?> failed</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
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

/* Filters Bar */
.filters-bar {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
}
.filters-form {
    display: flex;
    gap: 16px;
    align-items: flex-end;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
}
.filter-select {
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-size: 14px;
    min-width: 150px;
}
.btn-clear {
    padding: 8px 16px;
    background: #f3f4f6;
    color: #374151;
    border-radius: 6px;
    font-size: 14px;
    text-decoration: none;
    font-weight: 500;
}
.btn-clear:hover {
    background: #e5e7eb;
}

/* Report Tabs */
.report-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 28px;
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
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 28px;
}
.stat-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s;
}
.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}
.stat-card.highlight {
    border-color: #fbbf24;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
}
.stat-card.highlight-success {
    border-color: #10b981;
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
}
.stat-card.highlight-warning {
    border-color: #f59e0b;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.stat-icon.blue { background: #dbeafe; }
.stat-icon.green { background: #dcfce7; }
.stat-icon.purple { background: #f3e8ff; }
.stat-icon.orange { background: #ffedd5; }
.stat-icon.teal { background: #ccfbf1; }
.stat-icon.pink { background: #fce7f3; }
.stat-icon.gold { background: #fef3c7; }
.stat-info {
    display: flex;
    flex-direction: column;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    line-height: 1;
}
.stat-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
    margin-top: 4px;
}

/* Report Grid */
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
.card.full-width {
    grid-column: 1 / -1;
}
.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}
.card-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #111827;
}
.card-body {
    padding: 20px 24px;
}

/* Data Tables */
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th,
.data-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.data-table th {
    font-weight: 600;
    font-size: 11px;
    color: #6b7280;
    background: #f9fafb;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.data-table tbody tr:hover {
    background: #f9fafb;
}
.data-table tbody tr:last-child td {
    border-bottom: none;
}

/* Ranks */
.rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-weight: 700;
    font-size: 12px;
    background: #f3f4f6;
    color: #6b7280;
}
.rank-1 { background: #fef3c7; color: #92400e; }
.rank-2 { background: #e5e7eb; color: #374151; }
.rank-3 { background: #ffedd5; color: #9a3412; }

/* Student/Instructor Info */
.student-info, .instructor-info, .subject-info, .program-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.student-name, .instructor-name, .subject-code-lg, .program-code {
    font-weight: 600;
    color: #111827;
}
.student-id, .employee-id, .subject-name-sm, .program-name {
    font-size: 12px;
    color: #6b7280;
}
.instructor-avatar {
    width: 36px;
    height: 36px;
    background: #2563eb;
    color: white;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 12px;
    margin-right: 10px;
}
.instructor-info {
    display: flex;
    align-items: center;
    flex-direction: row;
}

/* Scores */
.score {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}
.score-pass { background: #dcfce7; color: #166534; }
.score-fail { background: #fee2e2; color: #991b1b; }
.score-best { background: #fef3c7; color: #92400e; }
.score-lg {
    font-size: 16px;
    padding: 6px 12px;
}

/* Count Badges */
.count-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
.count-badge.blue { background: #dbeafe; color: #1e40af; }
.count-badge.green { background: #dcfce7; color: #166534; }
.count-badge.red { background: #fee2e2; color: #991b1b; }
.count-badge.purple { background: #f3e8ff; color: #7c3aed; }
.count-badge.teal { background: #ccfbf1; color: #0d9488; }
.count-badge.gray { background: #f3f4f6; color: #374151; }

/* Subject Code Badge */
.subject-code {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}

/* Progress Bars */
.progress-bar-mini {
    position: relative;
    height: 20px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    min-width: 100px;
}
.progress-bar-mini .progress-fill {
    height: 100%;
    border-radius: 10px;
}
.progress-bar-mini .progress-fill.pass { background: linear-gradient(90deg, #10b981, #059669); }
.progress-bar-mini .progress-fill.fail { background: linear-gradient(90deg, #ef4444, #dc2626); }
.progress-bar-mini .progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 11px;
    font-weight: 600;
    color: #111827;
}

/* Pass Rate Bar */
.pass-rate-bar {
    position: relative;
    height: 24px;
    background: #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    min-width: 120px;
}
.pass-rate-fill {
    height: 100%;
    border-radius: 12px;
    transition: width 0.4s ease;
}
.pass-rate-fill.good { background: linear-gradient(90deg, #10b981, #059669); }
.pass-rate-fill.medium { background: linear-gradient(90deg, #f59e0b, #d97706); }
.pass-rate-fill.low { background: linear-gradient(90deg, #ef4444, #dc2626); }
.pass-rate-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 12px;
    font-weight: 600;
    color: #111827;
}

/* Capacity Overview */
.capacity-overview {
    margin-bottom: 24px;
}
.capacity-card {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    border-radius: 16px;
    padding: 28px;
    color: white;
}
.capacity-card h4 {
    margin: 0 0 20px;
    font-size: 16px;
    font-weight: 600;
    opacity: 0.9;
}
.capacity-stats {
    display: flex;
    align-items: center;
    gap: 24px;
}
.capacity-number {
    display: flex;
    align-items: baseline;
    gap: 8px;
}
.capacity-number .big-number {
    font-size: 48px;
    font-weight: 800;
}
.capacity-number .separator {
    font-size: 32px;
    opacity: 0.5;
}
.capacity-number .total {
    font-size: 24px;
    opacity: 0.7;
}
.capacity-bar {
    flex: 1;
    height: 16px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    overflow: hidden;
}
.capacity-fill {
    height: 100%;
    border-radius: 8px;
    transition: width 0.5s ease;
}
.capacity-fill.low { background: #10b981; }
.capacity-fill.medium { background: #f59e0b; }
.capacity-fill.full { background: #ef4444; }
.capacity-percent {
    font-size: 18px;
    font-weight: 600;
    min-width: 120px;
    text-align: right;
}

/* Section List */
.section-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.section-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f9fafb;
    border-radius: 8px;
}
.section-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.section-name {
    font-weight: 600;
    color: #111827;
}
.section-subject {
    font-size: 12px;
    color: #6b7280;
}
.section-capacity {
    display: flex;
    align-items: center;
    gap: 12px;
}
.mini-bar {
    width: 80px;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}
.mini-fill {
    height: 100%;
    border-radius: 4px;
}
.mini-fill.low { background: #10b981; }
.mini-fill.medium { background: #f59e0b; }
.mini-fill.full { background: #ef4444; }
.capacity-text {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
    min-width: 100px;
    text-align: right;
}

/* Difficult Subjects */
.difficult-subjects {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
}
.difficult-card {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    padding: 16px;
}
.difficult-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.difficult-name {
    font-size: 13px;
    color: #6b7280;
    margin: 0 0 12px;
}
.difficult-stats {
    display: flex;
    gap: 16px;
    font-size: 12px;
    color: #991b1b;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #9ca3af;
}
.empty-icon, .success-icon {
    font-size: 48px;
    margin-bottom: 12px;
    opacity: 0.5;
}
.empty-state p, .success-state p {
    margin: 0;
    font-size: 14px;
}
.success-state {
    text-align: center;
    padding: 40px 20px;
    color: #059669;
    background: #ecfdf5;
    border-radius: 12px;
}
.success-state .success-icon {
    opacity: 1;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 1024px) {
    .report-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    .capacity-stats {
        flex-direction: column;
        align-items: flex-start;
    }
    .capacity-bar {
        width: 100%;
    }
}

/* Print Styles */
@media print {
    .report-tabs, .header-actions, .filters-bar {
        display: none;
    }
    .card {
        page-break-inside: avoid;
        border: 1px solid #ccc;
    }
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
