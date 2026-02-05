<?php
/**
 * CIT-LMS Instructor - Analytics Dashboard
 * Comprehensive performance tracking and teaching insights
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('instructor');

$userId = Auth::id();
$pageTitle = 'Analytics';
$currentPage = 'analytics';

// 1. Overview Stats
$stats = db()->fetchOne(
    "SELECT
        (SELECT COUNT(DISTINCT ss.user_student_id) FROM student_subject ss JOIN faculty_subject fs ON ss.subject_offered_id = fs.subject_offered_id WHERE fs.user_teacher_id = ? AND ss.status = 'enrolled') as students,
        (SELECT COUNT(*) FROM lessons WHERE user_teacher_id = ?) as lessons,
        (SELECT COUNT(*) FROM quiz WHERE user_teacher_id = ?) as quizzes,
        (SELECT COUNT(*) FROM student_quiz_attempts sqa JOIN quiz q ON sqa.quiz_id = q.quiz_id WHERE q.user_teacher_id = ? AND sqa.status = 'completed') as attempts,
        (SELECT AVG(sqa.percentage) FROM student_quiz_attempts sqa JOIN quiz q ON sqa.quiz_id = q.quiz_id WHERE q.user_teacher_id = ? AND sqa.status = 'completed') as avg_score,
        (SELECT COUNT(*) FROM student_quiz_attempts sqa JOIN quiz q ON sqa.quiz_id = q.quiz_id WHERE q.user_teacher_id = ? AND sqa.status = 'completed' AND sqa.passed = 1) as passed",
    [$userId, $userId, $userId, $userId, $userId, $userId]
);

$passRate = $stats['attempts'] > 0 ? round(($stats['passed'] / $stats['attempts']) * 100) : 0;

// Enrolled Students List (for expandable card)
$enrolledStudents = db()->fetchAll(
    "SELECT DISTINCT u.users_id, u.student_id, CONCAT(u.first_name, ' ', u.last_name) as name,
        u.email, s.subject_code, s.subject_name,
        (SELECT COUNT(*) FROM student_quiz_attempts sqa2 JOIN quiz q2 ON sqa2.quiz_id = q2.quiz_id WHERE sqa2.user_student_id = u.users_id AND q2.user_teacher_id = ? AND sqa2.status = 'completed') as quiz_attempts,
        (SELECT AVG(sqa2.percentage) FROM student_quiz_attempts sqa2 JOIN quiz q2 ON sqa2.quiz_id = q2.quiz_id WHERE sqa2.user_student_id = u.users_id AND q2.user_teacher_id = ? AND sqa2.status = 'completed') as avg_score
    FROM student_subject ss
    JOIN faculty_subject fs ON ss.subject_offered_id = fs.subject_offered_id
    JOIN users u ON ss.user_student_id = u.users_id
    JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    WHERE fs.user_teacher_id = ? AND ss.status = 'enrolled'
    ORDER BY u.last_name, u.first_name",
    [$userId, $userId, $userId]
);

// 2. Performance by Subject
$bySubject = db()->fetchAll(
    "SELECT s.subject_id, s.subject_code, s.subject_name,
        COUNT(DISTINCT q.quiz_id) as quiz_count,
        COUNT(DISTINCT sqa.attempt_id) as attempt_count,
        COUNT(DISTINCT sqa.user_student_id) as student_count,
        AVG(sqa.percentage) as avg_score,
        SUM(CASE WHEN sqa.passed = 1 THEN 1 ELSE 0 END) as passed_count
    FROM quiz q
    JOIN subject s ON q.subject_id = s.subject_id
    LEFT JOIN student_quiz_attempts sqa ON q.quiz_id = sqa.quiz_id AND sqa.status = 'completed'
    WHERE q.user_teacher_id = ?
    GROUP BY s.subject_id
    ORDER BY s.subject_code",
    [$userId]
);

// 3. Top/Bottom Performing Quizzes
$quizPerformance = db()->fetchAll(
    "SELECT q.quiz_id, q.quiz_title, s.subject_code,
        COUNT(sqa.attempt_id) as attempt_count,
        AVG(sqa.percentage) as avg_score,
        MIN(sqa.percentage) as min_score,
        MAX(sqa.percentage) as max_score,
        SUM(CASE WHEN sqa.passed = 1 THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN sqa.passed = 0 THEN 1 ELSE 0 END) as failed
    FROM quiz q
    JOIN subject s ON q.subject_id = s.subject_id
    LEFT JOIN student_quiz_attempts sqa ON q.quiz_id = sqa.quiz_id AND sqa.status = 'completed'
    WHERE q.user_teacher_id = ?
    GROUP BY q.quiz_id
    HAVING attempt_count > 0
    ORDER BY avg_score DESC
    LIMIT 8",
    [$userId]
);

// 4. Students Needing Attention (lowest average scores)
$atRisk = db()->fetchAll(
    "SELECT u.users_id, CONCAT(u.first_name, ' ', u.last_name) as name,
        u.student_id,
        COUNT(sqa.attempt_id) as attempts,
        AVG(sqa.percentage) as avg_score,
        SUM(CASE WHEN sqa.passed = 0 THEN 1 ELSE 0 END) as failed_count
    FROM student_quiz_attempts sqa
    JOIN quiz q ON sqa.quiz_id = q.quiz_id
    JOIN users u ON sqa.user_student_id = u.users_id
    WHERE q.user_teacher_id = ? AND sqa.status = 'completed'
    GROUP BY u.users_id
    HAVING avg_score < 75
    ORDER BY avg_score ASC
    LIMIT 6",
    [$userId]
);

// 5. Recent Activity
$recent = db()->fetchAll(
    "SELECT sqa.percentage, sqa.passed, sqa.completed_at, sqa.time_spent,
        q.quiz_title, s.subject_code,
        CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM student_quiz_attempts sqa
    JOIN quiz q ON sqa.quiz_id = q.quiz_id
    JOIN subject s ON q.subject_id = s.subject_id
    JOIN users u ON sqa.user_student_id = u.users_id
    WHERE q.user_teacher_id = ? AND sqa.status = 'completed'
    ORDER BY sqa.completed_at DESC
    LIMIT 10",
    [$userId]
);

// 6. Score Distribution
$distribution = db()->fetchOne(
    "SELECT
        SUM(CASE WHEN sqa.percentage >= 90 THEN 1 ELSE 0 END) as excellent,
        SUM(CASE WHEN sqa.percentage >= 75 AND sqa.percentage < 90 THEN 1 ELSE 0 END) as good,
        SUM(CASE WHEN sqa.percentage >= 60 AND sqa.percentage < 75 THEN 1 ELSE 0 END) as average,
        SUM(CASE WHEN sqa.percentage < 60 THEN 1 ELSE 0 END) as needs_improvement,
        COUNT(*) as total
    FROM student_quiz_attempts sqa
    JOIN quiz q ON sqa.quiz_id = q.quiz_id
    WHERE q.user_teacher_id = ? AND sqa.status = 'completed'",
    [$userId]
);

$distTotal = max((int)($distribution['total'] ?? 0), 1);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <!-- Header Banner -->
        <div class="dash-welcome">
            <div class="dash-welcome-left">
                <h1>Analytics & Insights</h1>
                <p>Track student performance and identify areas for improvement</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="dash-stats">
            <div class="dash-stat-card ana-clickable" onclick="toggleStudentList()">
                <div class="dash-stat-icon" style="background: #E8F5E9; color: #1B4D3E;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="dash-stat-content">
                    <span class="dash-stat-number" data-count="<?= $stats['students'] ?>">0</span>
                    <span class="dash-stat-label">Total Students</span>
                </div>
                <svg class="ana-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-icon" style="background: #E3F2FD; color: #1565C0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <div class="dash-stat-content">
                    <span class="dash-stat-number" data-count="<?= $stats['attempts'] ?>">0</span>
                    <span class="dash-stat-label">Quiz Attempts</span>
                </div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-icon" style="background: #FFF3E0; color: #E65100;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                </div>
                <div class="dash-stat-content">
                    <span class="dash-stat-number"><?= round($stats['avg_score'] ?? 0) ?>%</span>
                    <span class="dash-stat-label">Avg Score</span>
                </div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-icon" style="background: #F3E5F5; color: #7B1FA2;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <div class="dash-stat-content">
                    <span class="dash-stat-number"><?= $passRate ?>%</span>
                    <span class="dash-stat-label">Pass Rate</span>
                </div>
            </div>
        </div>

        <!-- Expandable Student List -->
        <div class="ana-student-panel" id="studentListPanel">
            <div class="dash-panel">
                <div class="dash-panel-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Enrolled Students
                        <span class="ana-count-badge"><?= count($enrolledStudents) ?></span>
                    </h3>
                    <button class="ana-close-btn" onclick="toggleStudentList()" title="Close">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <?php if (empty($enrolledStudents)): ?>
                    <div class="dash-panel-body" style="padding:16px 20px;"><div class="dash-empty"><p>No enrolled students found</p></div></div>
                <?php else: ?>
                <div class="ana-table-wrap">
                    <table class="ana-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Subject</th>
                                <th>Quiz Attempts</th>
                                <th>Avg Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrolledStudents as $st):
                                $stAvg = $st['avg_score'] !== null ? round($st['avg_score']) : null;
                            ?>
                            <tr>
                                <td>
                                    <div class="ana-student-cell">
                                        <div class="dash-list-avatar"><?= strtoupper(substr($st['name'], 0, 1)) ?></div>
                                        <div>
                                            <span class="ana-cell-name"><?= e($st['name']) ?></span>
                                            <span class="ana-cell-email"><?= e($st['email']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="ana-cell-muted"><?= e($st['student_id'] ?? '—') ?></span></td>
                                <td><span class="ana-subj-tag"><?= e($st['subject_code']) ?></span></td>
                                <td class="ana-cell-center"><?= $st['quiz_attempts'] ?></td>
                                <td>
                                    <?php if ($stAvg !== null): ?>
                                        <span class="dash-badge <?= $stAvg >= 75 ? 'dash-badge-success' : ($stAvg >= 60 ? 'dash-badge-warning' : 'dash-badge-danger') ?>"><?= $stAvg ?>%</span>
                                    <?php else: ?>
                                        <span class="ana-cell-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($st['quiz_attempts'] == 0): ?>
                                        <span class="dash-badge dash-badge-neutral">No Activity</span>
                                    <?php elseif ($stAvg >= 75): ?>
                                        <span class="dash-badge dash-badge-success">On Track</span>
                                    <?php elseif ($stAvg >= 60): ?>
                                        <span class="dash-badge dash-badge-warning">Needs Help</span>
                                    <?php else: ?>
                                        <span class="dash-badge dash-badge-danger">At Risk</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="dash-grid" style="grid-template-columns: 1.3fr 1fr;">

            <!-- Left Column -->
            <div style="display:flex;flex-direction:column;gap:20px;">

                <!-- Subject Performance -->
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <h3>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                            Performance by Subject
                        </h3>
                    </div>
                    <div class="dash-panel-body" style="padding:12px 20px;">
                        <?php if (empty($bySubject)): ?>
                            <div class="dash-empty"><p>No subjects with quiz data yet</p></div>
                        <?php else: ?>
                            <?php foreach ($bySubject as $i => $s):
                                $subjAvg = round($s['avg_score'] ?? 0);
                                $subjPassRate = $s['attempt_count'] > 0 ? round(($s['passed_count'] / $s['attempt_count']) * 100) : 0;
                            ?>
                            <div class="ana-subj-row <?= $i > 0 ? 'ana-subj-border' : '' ?>">
                                <div class="ana-subj-top">
                                    <div class="ana-subj-left">
                                        <span class="ana-subj-tag"><?= e($s['subject_code']) ?></span>
                                        <span class="ana-subj-name"><?= e($s['subject_name']) ?></span>
                                    </div>
                                    <span class="ana-subj-score <?= $subjAvg >= 75 ? 'score-good' : ($subjAvg >= 60 ? 'score-mid' : 'score-low') ?>"><?= $subjAvg ?>%</span>
                                </div>
                                <div class="ana-progress-track">
                                    <div class="ana-progress-fill <?= $subjAvg >= 75 ? 'fill-good' : ($subjAvg >= 60 ? 'fill-mid' : 'fill-low') ?>" style="width: <?= $subjAvg ?>%"></div>
                                </div>
                                <div class="ana-subj-meta">
                                    <span><?= $s['quiz_count'] ?> quizzes</span>
                                    <span><?= $s['attempt_count'] ?> attempts</span>
                                    <span><?= $s['student_count'] ?> students</span>
                                    <span><?= $subjPassRate ?>% pass rate</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quiz Performance Table -->
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <h3>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                            Quiz Performance
                        </h3>
                    </div>
                    <?php if (empty($quizPerformance)): ?>
                        <div class="dash-panel-body" style="padding:16px 20px;"><div class="dash-empty"><p>No quiz attempts recorded yet</p></div></div>
                    <?php else: ?>
                    <div class="ana-table-wrap">
                        <table class="ana-table">
                            <thead>
                                <tr>
                                    <th>Quiz</th>
                                    <th>Subject</th>
                                    <th>Attempts</th>
                                    <th>Avg</th>
                                    <th>Range</th>
                                    <th>Pass / Fail</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quizPerformance as $qp):
                                    $qAvg = round($qp['avg_score']);
                                ?>
                                <tr>
                                    <td><span class="ana-cell-name"><?= e($qp['quiz_title']) ?></span></td>
                                    <td><span class="ana-subj-tag sm"><?= e($qp['subject_code']) ?></span></td>
                                    <td class="ana-cell-center"><?= $qp['attempt_count'] ?></td>
                                    <td>
                                        <span class="dash-badge <?= $qAvg >= 75 ? 'dash-badge-success' : ($qAvg >= 60 ? 'dash-badge-warning' : 'dash-badge-danger') ?>">
                                            <?= $qAvg ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="ana-cell-muted"><?= round($qp['min_score']) ?>%</span>
                                        <span style="color:#d1d5db;margin:0 2px;">-</span>
                                        <span class="ana-cell-muted"><?= round($qp['max_score']) ?>%</span>
                                    </td>
                                    <td>
                                        <span style="color:#1B4D3E;font-weight:600;"><?= $qp['passed'] ?></span>
                                        <span style="color:#d1d5db;margin:0 3px;">/</span>
                                        <span style="color:#b91c1c;font-weight:600;"><?= $qp['failed'] ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div style="display:flex;flex-direction:column;gap:20px;">

                <!-- Score Distribution -->
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <h3>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                            Score Distribution
                        </h3>
                    </div>
                    <div class="dash-panel-body" style="padding:16px 20px;">
                        <?php
                        $distData = [
                            ['label' => 'Excellent', 'range' => '90 - 100%', 'count' => (int)($distribution['excellent'] ?? 0), 'color' => '#1B4D3E'],
                            ['label' => 'Good', 'range' => '75 - 89%', 'count' => (int)($distribution['good'] ?? 0), 'color' => '#2563eb'],
                            ['label' => 'Average', 'range' => '60 - 74%', 'count' => (int)($distribution['average'] ?? 0), 'color' => '#d97706'],
                            ['label' => 'Needs Work', 'range' => 'Below 60%', 'count' => (int)($distribution['needs_improvement'] ?? 0), 'color' => '#b91c1c'],
                        ];
                        foreach ($distData as $d):
                            $pct = round(($d['count'] / $distTotal) * 100);
                        ?>
                        <div class="ana-dist-row">
                            <div class="ana-dist-info">
                                <div class="ana-dist-dot" style="background: <?= $d['color'] ?>"></div>
                                <div>
                                    <span class="ana-dist-label"><?= $d['label'] ?></span>
                                    <span class="ana-dist-range"><?= $d['range'] ?></span>
                                </div>
                            </div>
                            <div class="ana-dist-bar-area">
                                <div class="ana-dist-track">
                                    <div class="ana-dist-fill" style="width: <?= $pct ?>%; background: <?= $d['color'] ?>"></div>
                                </div>
                                <span class="ana-dist-num"><?= $d['count'] ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="ana-dist-totals">
                            <div class="ana-dist-total-item">
                                <span class="ana-dist-total-val" style="color:#1B4D3E;"><?= $stats['passed'] ?></span>
                                <span class="ana-dist-total-lbl">Passed</span>
                            </div>
                            <div class="ana-dist-total-sep"></div>
                            <div class="ana-dist-total-item">
                                <span class="ana-dist-total-val" style="color:#b91c1c;"><?= $stats['attempts'] - $stats['passed'] ?></span>
                                <span class="ana-dist-total-lbl">Failed</span>
                            </div>
                            <div class="ana-dist-total-sep"></div>
                            <div class="ana-dist-total-item">
                                <span class="ana-dist-total-val"><?= $stats['attempts'] ?></span>
                                <span class="ana-dist-total-lbl">Total</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Students Needing Attention -->
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <h3>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            Students Needing Attention
                        </h3>
                    </div>
                    <div class="dash-panel-body">
                        <?php if (empty($atRisk)): ?>
                            <div class="dash-empty dash-empty-sm">
                                <p>All students are performing well</p>
                            </div>
                        <?php else: ?>
                            <div class="dash-list">
                                <?php foreach ($atRisk as $sr):
                                    $srAvg = round($sr['avg_score']);
                                ?>
                                <div class="dash-list-item">
                                    <div class="dash-list-avatar" style="background:#FEF2F2;color:#b91c1c;"><?= strtoupper(substr($sr['name'], 0, 1)) ?></div>
                                    <div class="dash-list-info">
                                        <span class="dash-list-primary"><?= e($sr['name']) ?></span>
                                        <span class="dash-list-secondary"><?= $sr['attempts'] ?> attempts &middot; <?= $sr['failed_count'] ?> failed</span>
                                    </div>
                                    <span class="dash-badge dash-badge-danger"><?= $srAvg ?>%</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <h3>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            Recent Activity
                        </h3>
                    </div>
                    <div class="dash-panel-body">
                        <?php if (empty($recent)): ?>
                            <div class="dash-empty dash-empty-sm"><p>No activity yet</p></div>
                        <?php else: ?>
                            <div class="dash-list">
                                <?php foreach ($recent as $r): ?>
                                <div class="dash-list-item">
                                    <div class="dash-list-avatar <?= $r['passed'] ? '' : '' ?>" style="<?= $r['passed'] ? '' : 'background:#FEF2F2;color:#b91c1c;' ?>"><?= strtoupper(substr($r['student_name'], 0, 1)) ?></div>
                                    <div class="dash-list-info">
                                        <span class="dash-list-primary"><?= e($r['student_name']) ?></span>
                                        <span class="dash-list-secondary"><?= e($r['subject_code']) ?> &middot; <?= e($r['quiz_title']) ?></span>
                                    </div>
                                    <span class="dash-badge <?= $r['passed'] ? 'dash-badge-success' : 'dash-badge-danger' ?>"><?= round($r['percentage']) ?>%</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>

    </div>
</main>

<style>
/* ============================================
   Analytics Dashboard - Complete Styles
   ============================================ */

/* Header Banner */
.dash-welcome {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding: 28px 32px;
    background: linear-gradient(135deg, #1B4D3E 0%, #2D6A4F 100%);
    border-radius: 14px;
    color: #fff;
}
.dash-welcome h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; letter-spacing: -0.3px; }
.dash-welcome p { margin: 0; opacity: 0.8; font-size: 14px; }

/* Stats Cards */
.dash-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}
.dash-stat-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    transition: border-color 0.2s ease;
}
.dash-stat-card:hover { border-color: #1B4D3E; }
.dash-stat-icon {
    width: 46px; height: 46px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.dash-stat-number { display: block; font-size: 26px; font-weight: 700; color: #1a1a1a; line-height: 1.1; }
.dash-stat-label { font-size: 12px; color: #6b7280; font-weight: 500; text-transform: uppercase; letter-spacing: 0.03em; }

/* Main Grid */
.dash-grid { display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px; }

/* Panels */
.dash-panel { background: #fff; border: 1px solid #e8e8e8; border-radius: 12px; overflow: hidden; }
.dash-panel-header { padding: 16px 20px; border-bottom: 1px solid #f0f0f0; }
.dash-panel-header h3 {
    font-size: 14px; font-weight: 600; color: #1a1a1a; margin: 0;
    display: flex; align-items: center; gap: 8px;
}
.dash-panel-header h3 svg { color: #1B4D3E; }
.dash-panel-body { padding: 4px 0; }

/* Lists */
.dash-list { display: flex; flex-direction: column; }
.dash-list-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 20px; transition: background 0.15s ease;
}
.dash-list-item:hover { background: #fafafa; }
.dash-list-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: #E8F5E9; color: #1B4D3E;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; flex-shrink: 0;
}
.dash-list-info { flex: 1; min-width: 0; }
.dash-list-primary {
    display: block; font-size: 13px; font-weight: 600; color: #1a1a1a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dash-list-secondary {
    display: block; font-size: 12px; color: #9ca3af;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* Badges */
.dash-badge {
    padding: 4px 10px; border-radius: 6px;
    font-size: 12px; font-weight: 600; white-space: nowrap; flex-shrink: 0;
}
.dash-badge-success { background: #E8F5E9; color: #1B4D3E; }
.dash-badge-danger { background: #FEE2E2; color: #b91c1c; }
.dash-badge-warning { background: #FEF3C7; color: #B45309; }
.dash-badge-neutral { background: #F3F4F6; color: #6B7280; }

/* Empty States */
.dash-empty { text-align: center; padding: 32px 20px; color: #9ca3af; }
.dash-empty p { margin: 0; font-size: 13px; }
.dash-empty-sm { padding: 20px; }

/* ============================================
   Analytics-specific styles
   ============================================ */

/* Clickable stat card */
.dash-stat-card.ana-clickable {
    cursor: pointer;
    position: relative;
    padding-right: 40px;
}
.dash-stat-card.ana-clickable:hover {
    border-color: #1B4D3E;
    box-shadow: 0 2px 8px rgba(27, 77, 62, 0.08);
}
.dash-stat-card.ana-clickable.expanded {
    border-color: #1B4D3E;
    background: #f8fdf9;
}
.ana-chevron {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #c4c4c4;
    transition: transform 0.25s ease, color 0.2s;
}
.dash-stat-card.ana-clickable:hover .ana-chevron { color: #1B4D3E; }
.dash-stat-card.ana-clickable.expanded .ana-chevron {
    transform: translateY(-50%) rotate(180deg);
    color: #1B4D3E;
}

/* Student list expandable panel */
.ana-student-panel {
    display: none;
    margin-bottom: 20px;
    animation: anaSlideDown 0.25s ease;
}
.ana-student-panel.show { display: block; }

@keyframes anaSlideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

.ana-close-btn {
    background: none;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 5px;
    cursor: pointer;
    color: #9ca3af;
    display: flex;
    align-items: center;
    transition: all 0.15s;
}
.ana-close-btn:hover {
    background: #fef2f2;
    border-color: #fca5a5;
    color: #b91c1c;
}

.ana-count-badge {
    background: #1B4D3E;
    color: #fff;
    padding: 1px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
    margin-left: 4px;
}

/* Tables */
.ana-table-wrap { overflow-x: auto; }
.ana-table { width: 100%; border-collapse: collapse; }
.ana-table thead th {
    padding: 10px 16px; font-size: 11px; font-weight: 600;
    color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em;
    text-align: left; background: #fafafa; border-bottom: 1px solid #f0f0f0;
    white-space: nowrap;
}
.ana-table tbody td {
    padding: 11px 16px; font-size: 13px; color: #374151;
    border-bottom: 1px solid #f5f5f4;
}
.ana-table tbody tr:hover { background: #fafafa; }

.ana-student-cell { display: flex; align-items: center; gap: 10px; }
.ana-cell-name { display: block; font-weight: 600; color: #1a1a1a; font-size: 13px; }
.ana-cell-email { display: block; font-size: 11px; color: #9ca3af; }
.ana-cell-muted { color: #9ca3af; font-size: 13px; }
.ana-cell-center { text-align: center; }

/* Subject tag */
.ana-subj-tag {
    display: inline-block; background: #E8F5E9; color: #1B4D3E;
    padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 700; white-space: nowrap;
}
.ana-subj-tag.sm { font-size: 11px; padding: 2px 8px; }

/* Subject performance rows */
.ana-subj-row { padding: 14px 0; }
.ana-subj-border { border-top: 1px solid #f3f4f6; }
.ana-subj-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.ana-subj-left { display: flex; align-items: center; gap: 10px; }
.ana-subj-name { color: #374151; font-size: 14px; font-weight: 500; }
.ana-subj-score { font-size: 18px; font-weight: 700; }
.ana-subj-score.score-good { color: #1B4D3E; }
.ana-subj-score.score-mid { color: #b45309; }
.ana-subj-score.score-low { color: #b91c1c; }

/* Progress bars */
.ana-progress-track {
    height: 6px; background: #f3f4f6; border-radius: 3px;
    overflow: hidden; margin-bottom: 8px;
}
.ana-progress-fill { height: 100%; border-radius: 3px; transition: width 0.8s ease; }
.fill-good { background: linear-gradient(90deg, #2D6A4F, #1B4D3E); }
.fill-mid { background: linear-gradient(90deg, #f59e0b, #d97706); }
.fill-low { background: linear-gradient(90deg, #ef4444, #b91c1c); }

.ana-subj-meta { display: flex; gap: 16px; font-size: 12px; color: #9ca3af; }

/* Score distribution */
.ana-dist-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 9px 0; }
.ana-dist-info { display: flex; align-items: center; gap: 10px; min-width: 120px; }
.ana-dist-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ana-dist-label { display: block; font-size: 13px; color: #374151; font-weight: 500; line-height: 1.2; }
.ana-dist-range { display: block; font-size: 11px; color: #9ca3af; }
.ana-dist-bar-area { display: flex; align-items: center; gap: 10px; flex: 1; }
.ana-dist-track { flex: 1; height: 8px; background: #f3f4f6; border-radius: 4px; overflow: hidden; }
.ana-dist-fill { height: 100%; border-radius: 4px; transition: width 0.6s ease; min-width: 2px; }
.ana-dist-num { font-size: 13px; font-weight: 700; color: #374151; min-width: 20px; text-align: right; }

.ana-dist-totals {
    display: flex; align-items: center; justify-content: center;
    margin-top: 16px; padding-top: 16px; border-top: 1px solid #f3f4f6;
}
.ana-dist-total-item { flex: 1; text-align: center; }
.ana-dist-total-val { display: block; font-size: 20px; font-weight: 700; color: #1a1a1a; line-height: 1.2; }
.ana-dist-total-lbl { font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.03em; }
.ana-dist-total-sep { width: 1px; height: 32px; background: #e5e7eb; }

/* Responsive */
@media (max-width: 1024px) {
    .dash-stats { grid-template-columns: repeat(2, 1fr); }
    .dash-grid { grid-template-columns: 1fr !important; }
}
@media (max-width: 768px) {
    .dash-welcome { padding: 24px 20px; }
    .dash-welcome h1 { font-size: 18px; }
    .dash-stats { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .dash-stat-card { padding: 16px; }
    .dash-stat-number { font-size: 22px; }
    .ana-subj-meta { flex-wrap: wrap; gap: 8px; }
    .ana-dist-info { min-width: 90px; }
}
@media (max-width: 480px) {
    .dash-stats { grid-template-columns: 1fr; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Count-up animation for stat numbers
    document.querySelectorAll('.dash-stat-number[data-count]').forEach(function(el) {
        const target = parseInt(el.dataset.count) || 0;
        if (target === 0) { el.textContent = '0'; return; }
        let current = 0;
        const increment = target / 25;
        const timer = setInterval(function() {
            current += increment;
            if (current >= target) {
                el.textContent = target;
                clearInterval(timer);
            } else {
                el.textContent = Math.floor(current);
            }
        }, 30);
    });
});

function toggleStudentList() {
    const panel = document.getElementById('studentListPanel');
    const card = document.querySelector('.dash-stat-card.ana-clickable');

    if (panel.classList.contains('show')) {
        panel.classList.remove('show');
        card.classList.remove('expanded');
    } else {
        panel.classList.add('show');
        card.classList.add('expanded');
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
