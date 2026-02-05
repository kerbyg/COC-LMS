<?php
/**
 * My Progress Page - Clean Green Theme
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$pageTitle = 'My Progress';
$currentPage = 'progress';

// 1. Get overall stats
$stats = db()->fetchOne(
    "SELECT
        (SELECT COUNT(*) FROM student_subject WHERE user_student_id = ? AND status = 'enrolled') as total_subjects,
        (SELECT COUNT(*) FROM student_progress WHERE user_student_id = ? AND status = 'completed') as lessons_completed,
        (SELECT COUNT(DISTINCT quiz_id) FROM student_quiz_attempts WHERE user_student_id = ? AND status = 'completed') as quizzes_taken,
        (SELECT ROUND(AVG(percentage), 1) FROM student_quiz_attempts WHERE user_student_id = ? AND status = 'completed') as avg_score
    ",
    [$userId, $userId, $userId, $userId]
);

// 2. Get total lessons and quizzes available
$totalLessons = db()->fetchOne(
    "SELECT COUNT(*) as count FROM lessons l
     JOIN subject_offered so ON l.subject_id = so.subject_id
     JOIN student_subject ss ON ss.subject_offered_id = so.subject_offered_id
     WHERE ss.user_student_id = ? AND ss.status = 'enrolled' AND l.status = 'published'",
    [$userId]
)['count'] ?? 0;

$totalQuizzes = db()->fetchOne(
    "SELECT COUNT(*) as count FROM quiz q
     JOIN subject_offered so ON q.subject_id = so.subject_id
     JOIN student_subject ss ON ss.subject_offered_id = so.subject_offered_id
     WHERE ss.user_student_id = ? AND ss.status = 'enrolled' AND q.status = 'published'",
    [$userId]
)['count'] ?? 0;

// 3. Get quiz performance stats
$quizStats = db()->fetchOne(
    "SELECT
        COUNT(DISTINCT quiz_id) as total_quizzes_attempted,
        SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as quizzes_passed,
        MAX(percentage) as best_score,
        MIN(percentage) as lowest_score
    FROM student_quiz_attempts
    WHERE user_student_id = ? AND status = 'completed'",
    [$userId]
);

// 4. Get progress per subject
$subjectProgress = db()->fetchAll(
    "SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        so.subject_offered_id
    FROM student_subject ss
    JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
    ORDER BY s.subject_code",
    [$userId]
);

// Enrich subject data
foreach ($subjectProgress as &$subj) {
    $sid = $subj['subject_id'];
    $soid = $subj['subject_offered_id'];

    // Instructor
    $instructor = db()->fetchOne(
        "SELECT CONCAT(u.first_name, ' ', u.last_name) as name
         FROM faculty_subject fs JOIN users u ON fs.user_teacher_id = u.users_id
         WHERE fs.subject_offered_id = ? AND fs.status = 'active' LIMIT 1",
        [$soid]
    );
    $subj['instructor_name'] = $instructor['name'] ?? null;

    // Lessons
    $subj['total_lessons'] = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM lessons WHERE subject_id = ? AND status = 'published'", [$sid]
    )['cnt'] ?? 0;
    $subj['completed_lessons'] = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM student_progress sp JOIN lessons l ON sp.lessons_id = l.lessons_id
         WHERE sp.user_student_id = ? AND l.subject_id = ? AND sp.status = 'completed'",
        [$userId, $sid]
    )['cnt'] ?? 0;

    // Quizzes
    $subj['total_quizzes'] = db()->fetchOne(
        "SELECT COUNT(*) as cnt FROM quiz WHERE subject_id = ? AND status = 'published'", [$sid]
    )['cnt'] ?? 0;
    $subj['completed_quizzes'] = db()->fetchOne(
        "SELECT COUNT(DISTINCT qa.quiz_id) as cnt FROM student_quiz_attempts qa JOIN quiz q ON qa.quiz_id = q.quiz_id
         WHERE qa.user_student_id = ? AND q.subject_id = ? AND qa.status = 'completed'",
        [$userId, $sid]
    )['cnt'] ?? 0;

    // Avg Score
    $avgResult = db()->fetchOne(
        "SELECT ROUND(AVG(best_score), 1) as avg FROM (
            SELECT MAX(qa.percentage) as best_score FROM student_quiz_attempts qa JOIN quiz q ON qa.quiz_id = q.quiz_id
            WHERE qa.user_student_id = ? AND q.subject_id = ? AND qa.status = 'completed' GROUP BY qa.quiz_id
        ) as best_scores",
        [$userId, $sid]
    );
    $subj['avg_quiz_score'] = $avgResult['avg'] ?? null;
}
unset($subj);

// 5. Get recent activity
$recentActivity = db()->fetchAll(
    "SELECT * FROM (
        SELECT 'lesson' as type, l.lesson_title as title, sp.completed_at as activity_date,
               s.subject_code, s.subject_name, NULL as score
        FROM student_progress sp
        JOIN lessons l ON sp.lessons_id = l.lessons_id
        JOIN subject s ON l.subject_id = s.subject_id
        WHERE sp.user_student_id = ? AND sp.status = 'completed'
        UNION ALL
        SELECT 'quiz' as type, q.quiz_title as title, qa.completed_at as activity_date,
               s.subject_code, s.subject_name, qa.percentage as score
        FROM student_quiz_attempts qa
        JOIN quiz q ON qa.quiz_id = q.quiz_id
        JOIN subject s ON q.subject_id = s.subject_id
        WHERE qa.user_student_id = ? AND qa.status = 'completed'
    ) as activities ORDER BY activity_date DESC LIMIT 10",
    [$userId, $userId]
);

// 6. Get weekly activity
$weeklyActivity = db()->fetchOne(
    "SELECT
        COUNT(CASE WHEN type = 'lesson' THEN 1 END) as lessons_this_week,
        COUNT(CASE WHEN type = 'quiz' THEN 1 END) as quizzes_this_week
    FROM (
        SELECT 'lesson' as type, completed_at FROM student_progress WHERE user_student_id = ? AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'quiz' as type, completed_at FROM student_quiz_attempts WHERE user_student_id = ? AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ) as weekly",
    [$userId, $userId]
);

// Calculate percentages
$lessonPercent = $totalLessons > 0 ? round(($stats['lessons_completed'] / $totalLessons) * 100) : 0;
$quizPercent = $totalQuizzes > 0 ? round(($stats['quizzes_taken'] / $totalQuizzes) * 100) : 0;
$overallPercent = round(($lessonPercent + $quizPercent) / 2);
$quizPassRate = ($quizStats['total_quizzes_attempted'] ?? 0) > 0
    ? round(($quizStats['quizzes_passed'] / $quizStats['total_quizzes_attempted']) * 100) : 0;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="progress-wrap">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>My Learning Progress</h1>
                <p>Track your academic journey and achievements</p>
            </div>
            <div class="header-date">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <?= date('F d, Y') ?>
            </div>
        </div>

        <!-- Overall Progress Card -->
        <div class="progress-hero">
            <div class="hero-chart">
                <svg viewBox="0 0 100 100" class="circular-progress">
                    <circle cx="50" cy="50" r="45" class="circle-bg"/>
                    <circle cx="50" cy="50" r="45" class="circle-progress"
                            stroke-dasharray="<?= $overallPercent * 2.827 ?> 282.7"/>
                </svg>
                <div class="chart-center">
                    <span class="main-percent"><?= $overallPercent ?>%</span>
                    <span class="percent-label">Overall</span>
                </div>
            </div>

            <div class="hero-stats">
                <div class="hero-stat-card">
                    <div class="stat-icon green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Lessons Completed</span>
                        <span class="stat-value"><?= $stats['lessons_completed'] ?> <small>/ <?= $totalLessons ?></small></span>
                        <div class="mini-bar"><div class="mini-fill" style="width: <?= $lessonPercent ?>%"></div></div>
                    </div>
                </div>

                <div class="hero-stat-card">
                    <div class="stat-icon blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 11l3 3L22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Quizzes Taken</span>
                        <span class="stat-value"><?= $stats['quizzes_taken'] ?> <small>/ <?= $totalQuizzes ?></small></span>
                        <div class="mini-bar blue"><div class="mini-fill" style="width: <?= $quizPercent ?>%"></div></div>
                    </div>
                </div>

                <div class="hero-stat-card">
                    <div class="stat-icon orange">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">This Week</span>
                        <span class="stat-value"><?= ($weeklyActivity['lessons_this_week'] ?? 0) + ($weeklyActivity['quizzes_this_week'] ?? 0) ?> <small>activities</small></span>
                        <span class="stat-sub"><?= $weeklyActivity['lessons_this_week'] ?? 0 ?> lessons, <?= $weeklyActivity['quizzes_this_week'] ?? 0 ?> quizzes</span>
                    </div>
                </div>

                <div class="hero-stat-card">
                    <div class="stat-icon purple">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Average Score</span>
                        <span class="stat-value"><?= $stats['avg_score'] ?? 0 ?>%</span>
                        <span class="stat-sub">Pass Rate: <?= $quizPassRate ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="quick-stat">
                <div class="qs-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                </div>
                <div class="qs-content">
                    <span class="qs-value"><?= $stats['total_subjects'] ?></span>
                    <span class="qs-label">Enrolled Subjects</span>
                </div>
            </div>
            <div class="quick-stat">
                <div class="qs-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="8" r="7"/>
                        <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>
                    </svg>
                </div>
                <div class="qs-content">
                    <span class="qs-value"><?= $quizStats['best_score'] ?? 0 ?>%</span>
                    <span class="qs-label">Best Score</span>
                </div>
            </div>
            <div class="quick-stat">
                <div class="qs-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="qs-content">
                    <span class="qs-value"><?= $quizStats['quizzes_passed'] ?? 0 ?></span>
                    <span class="qs-label">Quizzes Passed</span>
                </div>
            </div>
            <div class="quick-stat">
                <div class="qs-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="20" x2="12" y2="10"/>
                        <line x1="18" y1="20" x2="18" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="16"/>
                    </svg>
                </div>
                <div class="qs-content">
                    <span class="qs-value"><?= $overallPercent ?>%</span>
                    <span class="qs-label">Completion Rate</span>
                </div>
            </div>
        </div>

        <!-- Subject Progress -->
        <div class="section-card">
            <div class="section-header">
                <h2>Subject Progress</h2>
                <span class="section-badge"><?= count($subjectProgress) ?> Subjects</span>
            </div>
            <div class="section-body">
                <?php if (empty($subjectProgress)): ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                    </svg>
                    <h3>No Enrolled Subjects</h3>
                    <p>You haven't enrolled in any subjects yet.</p>
                    <a href="enroll.php" class="btn-primary">Enroll Now</a>
                </div>
                <?php else: ?>
                <div class="subjects-grid">
                    <?php foreach ($subjectProgress as $subj):
                        $lessonProg = $subj['total_lessons'] > 0 ? round(($subj['completed_lessons'] / $subj['total_lessons']) * 100) : 0;
                        $quizProg = $subj['total_quizzes'] > 0 ? round(($subj['completed_quizzes'] / $subj['total_quizzes']) * 100) : 0;
                        $totalProg = round(($lessonProg + $quizProg) / 2);
                    ?>
                    <div class="subject-card">
                        <div class="subj-top">
                            <span class="subj-code"><?= e($subj['subject_code']) ?></span>
                            <div class="subj-ring">
                                <svg viewBox="0 0 36 36">
                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                          fill="none" stroke="#e8e8e8" stroke-width="3"/>
                                    <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                          fill="none" stroke="#1B4D3E" stroke-width="3"
                                          stroke-dasharray="<?= $totalProg ?>, 100"/>
                                </svg>
                                <span class="ring-val"><?= $totalProg ?>%</span>
                            </div>
                        </div>
                        <h3 class="subj-name"><?= e($subj['subject_name']) ?></h3>
                        <?php if ($subj['instructor_name']): ?>
                        <p class="subj-instructor">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <?= e($subj['instructor_name']) ?>
                        </p>
                        <?php endif; ?>
                        <div class="subj-stats">
                            <div class="subj-stat">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                                </svg>
                                <span><?= $subj['completed_lessons'] ?>/<?= $subj['total_lessons'] ?> Lessons</span>
                            </div>
                            <div class="subj-stat">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 11l3 3L22 4"/>
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                                <span><?= $subj['completed_quizzes'] ?>/<?= $subj['total_quizzes'] ?> Quizzes</span>
                            </div>
                            <?php if ($subj['avg_quiz_score']): ?>
                            <div class="subj-stat">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                                <span><?= $subj['avg_quiz_score'] ?>% Avg</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="section-card">
            <div class="section-header">
                <h2>Recent Activity</h2>
                <span class="section-badge">Last 10</span>
            </div>
            <div class="section-body">
                <?php if (empty($recentActivity)): ?>
                <div class="empty-state">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                    <h3>No Activity Yet</h3>
                    <p>Start learning to see your activity here!</p>
                </div>
                <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($recentActivity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?= $activity['type'] ?>">
                            <?php if ($activity['type'] === 'lesson'): ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                            </svg>
                            <?php else: ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"/>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <div class="activity-content">
                            <div class="activity-top">
                                <h4><?= e($activity['title']) ?></h4>
                                <?php if ($activity['score'] !== null): ?>
                                <span class="activity-score <?= $activity['score'] >= 70 ? 'passed' : 'failed' ?>">
                                    <?= $activity['score'] ?>%
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="activity-meta">
                                <span class="meta-code"><?= e($activity['subject_code']) ?></span>
                                <span class="meta-name"><?= e($activity['subject_name']) ?></span>
                                <span class="meta-date"><?= date('M d, Y g:i A', strtotime($activity['activity_date'])) ?></span>
                            </div>
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
/* Progress Page - Green/Cream Theme */
.progress-wrap {
    padding: 24px;
    max-width: 1200px;
    margin: 0 auto;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}
.page-header h1 {
    font-size: 24px;
    font-weight: 700;
    color: #1B4D3E;
    margin: 0 0 4px;
}
.page-header p {
    color: #666;
    margin: 0;
    font-size: 14px;
}
.header-date {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #666;
    padding: 8px 16px;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 8px;
}
.header-date svg { color: #1B4D3E; }

/* Progress Hero */
.progress-hero {
    background: #1B4D3E;
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 24px;
    display: grid;
    grid-template-columns: 160px 1fr;
    gap: 40px;
    align-items: center;
}
.hero-chart {
    position: relative;
    width: 160px;
    height: 160px;
}
.circular-progress {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.circle-bg {
    fill: none;
    stroke: rgba(255,255,255,0.2);
    stroke-width: 8;
}
.circle-progress {
    fill: none;
    stroke: #fff;
    stroke-width: 8;
    stroke-linecap: round;
}
.chart-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}
.main-percent {
    display: block;
    font-size: 36px;
    font-weight: 700;
    color: #fff;
}
.percent-label {
    font-size: 12px;
    color: rgba(255,255,255,0.8);
}

.hero-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}
.hero-stat-card {
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 16px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}
.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.stat-icon.green { background: #E8F5E9; color: #1B4D3E; }
.stat-icon.blue { background: #E3F2FD; color: #1565C0; }
.stat-icon.orange { background: #FFF8E1; color: #F9A825; }
.stat-icon.purple { background: #F3E5F5; color: #7B1FA2; }
.stat-info { flex: 1; }
.stat-label {
    display: block;
    font-size: 11px;
    color: rgba(255,255,255,0.8);
    margin-bottom: 4px;
}
.stat-value {
    display: block;
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    line-height: 1.2;
}
.stat-value small {
    font-size: 14px;
    opacity: 0.7;
}
.stat-sub {
    font-size: 11px;
    color: rgba(255,255,255,0.7);
    margin-top: 4px;
    display: block;
}
.mini-bar {
    height: 5px;
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
    margin-top: 8px;
    overflow: hidden;
}
.mini-bar.blue .mini-fill { background: #1565C0; }
.mini-fill {
    height: 100%;
    background: #fff;
    border-radius: 3px;
}

/* Quick Stats */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.quick-stat {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    gap: 14px;
    align-items: center;
    transition: all 0.2s;
}
.quick-stat:hover {
    border-color: #1B4D3E;
    box-shadow: 0 4px 12px rgba(27, 77, 62, 0.1);
}
.qs-icon {
    width: 44px;
    height: 44px;
    background: #E8F5E9;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1B4D3E;
}
.qs-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #1B4D3E;
}
.qs-label {
    font-size: 12px;
    color: #666;
}

/* Section Card */
.section-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
}
.section-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e8e8e8;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.section-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0;
}
.section-badge {
    background: #E8F5E9;
    color: #1B4D3E;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}
.section-body {
    padding: 24px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 48px 24px;
}
.empty-state svg {
    color: #ccc;
    margin-bottom: 16px;
}
.empty-state h3 {
    font-size: 18px;
    color: #333;
    margin: 0 0 8px;
}
.empty-state p {
    color: #666;
    margin: 0 0 20px;
    font-size: 14px;
}
.btn-primary {
    display: inline-block;
    background: #1B4D3E;
    color: #fff;
    padding: 10px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
}
.btn-primary:hover { background: #2D6A4F; }

/* Subjects Grid */
.subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.subject-card {
    background: #fafafa;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s;
}
.subject-card:hover {
    border-color: #1B4D3E;
    background: #fff;
}
.subj-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}
.subj-code {
    background: #1B4D3E;
    color: #fff;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}
.subj-ring {
    position: relative;
    width: 44px;
    height: 44px;
}
.subj-ring svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.ring-val {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 10px;
    font-weight: 700;
    color: #1B4D3E;
}
.subj-name {
    font-size: 15px;
    font-weight: 600;
    color: #333;
    margin: 0 0 8px;
}
.subj-instructor {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #666;
    margin: 0 0 14px;
}
.subj-instructor svg { color: #1B4D3E; }
.subj-stats {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.subj-stat {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #666;
    padding: 8px 10px;
    background: #fff;
    border-radius: 6px;
}
.subj-stat svg { color: #1B4D3E; }

/* Activity List */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.activity-item {
    display: flex;
    gap: 14px;
    padding: 14px;
    background: #fafafa;
    border-radius: 10px;
    transition: all 0.2s;
}
.activity-item:hover {
    background: #f5f5f5;
}
.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.activity-icon.lesson {
    background: #E8F5E9;
    color: #1B4D3E;
}
.activity-icon.quiz {
    background: #E3F2FD;
    color: #1565C0;
}
.activity-content { flex: 1; }
.activity-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 6px;
}
.activity-top h4 {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin: 0;
}
.activity-score {
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}
.activity-score.passed { background: #E8F5E9; color: #1B4D3E; }
.activity-score.failed { background: #FFEBEE; color: #C62828; }
.activity-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    font-size: 12px;
}
.meta-code {
    background: #1B4D3E;
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
}
.meta-name { color: #666; }
.meta-date { color: #999; }

/* Responsive */
@media (max-width: 1024px) {
    .progress-hero {
        grid-template-columns: 1fr;
        text-align: center;
    }
    .hero-chart { margin: 0 auto; }
    .quick-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .progress-wrap { padding: 16px; }
    .page-header { flex-direction: column; align-items: flex-start; gap: 12px; }
    .hero-stats { grid-template-columns: 1fr; }
    .quick-stats { grid-template-columns: 1fr; }
    .subjects-grid { grid-template-columns: 1fr; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
