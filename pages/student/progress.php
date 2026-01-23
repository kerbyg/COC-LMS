<?php
/**
 * CIT-LMS - My Progress Page
 * Modern, detailed progress tracking with analytics
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

// 4. Get progress per subject with detailed stats
$subjectProgress = db()->fetchAll(
    "SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
        (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.subject_id AND l.status = 'published') as total_lessons,
        (SELECT COUNT(*) FROM student_progress sp
         JOIN lessons l ON sp.lesson_id = l.lesson_id
         WHERE sp.user_student_id = ? AND l.subject_id = s.subject_id AND sp.status = 'completed') as completed_lessons,
        (SELECT COUNT(*) FROM quiz q WHERE q.subject_id = s.subject_id AND q.status = 'published') as total_quizzes,
        (SELECT COUNT(DISTINCT qa.quiz_id) FROM student_quiz_attempts qa
         JOIN quiz q ON qa.quiz_id = q.quiz_id
         WHERE qa.user_student_id = ? AND q.subject_id = s.subject_id AND qa.status = 'completed') as completed_quizzes,
        (SELECT ROUND(AVG(best.percentage), 1) FROM (
            SELECT MAX(qa.percentage) as percentage FROM student_quiz_attempts qa
            JOIN quiz q ON qa.quiz_id = q.quiz_id
            WHERE qa.user_student_id = ? AND q.subject_id = s.subject_id AND qa.status = 'completed'
            GROUP BY qa.quiz_id
        ) as best) as avg_quiz_score
    FROM student_subject ss
    JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    LEFT JOIN faculty_subject fs ON so.subject_offered_id = fs.subject_offered_id
    LEFT JOIN users u ON fs.user_teacher_id = u.users_id
    WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
    ORDER BY s.subject_code",
    [$userId, $userId, $userId, $userId]
);

// 5. Get recent activity
$recentActivity = db()->fetchAll(
    "SELECT * FROM (
        SELECT 'lesson' as type, l.lesson_title as title, sp.completed_at as activity_date,
               s.subject_code, s.subject_name, NULL as score
        FROM student_progress sp
        JOIN lessons l ON sp.lesson_id = l.lesson_id
        JOIN subject s ON l.subject_id = s.subject_id
        WHERE sp.user_student_id = ? AND sp.status = 'completed'

        UNION ALL

        SELECT 'quiz' as type, q.quiz_title as title, qa.completed_at as activity_date,
               s.subject_code, s.subject_name, qa.percentage as score
        FROM student_quiz_attempts qa
        JOIN quiz q ON qa.quiz_id = q.quiz_id
        JOIN subject s ON q.subject_id = s.subject_id
        WHERE qa.user_student_id = ? AND qa.status = 'completed'
    ) as activities
    ORDER BY activity_date DESC
    LIMIT 15",
    [$userId, $userId]
);

// 6. Get weekly activity (last 7 days)
$weeklyActivity = db()->fetchOne(
    "SELECT
        COUNT(CASE WHEN type = 'lesson' THEN 1 END) as lessons_this_week,
        COUNT(CASE WHEN type = 'quiz' THEN 1 END) as quizzes_this_week
    FROM (
        SELECT 'lesson' as type, completed_at FROM student_progress
        WHERE user_student_id = ? AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        UNION ALL
        SELECT 'quiz' as type, completed_at FROM student_quiz_attempts
        WHERE user_student_id = ? AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ) as weekly",
    [$userId, $userId]
);

// Calculate percentages
$lessonPercent = $totalLessons > 0 ? round(($stats['lessons_completed'] / $totalLessons) * 100) : 0;
$quizPercent = $totalQuizzes > 0 ? round(($stats['quizzes_taken'] / $totalQuizzes) * 100) : 0;
$overallPercent = round(($lessonPercent + $quizPercent) / 2);
$quizPassRate = ($quizStats['total_quizzes_attempted'] ?? 0) > 0
    ? round(($quizStats['quizzes_passed'] / $quizStats['total_quizzes_attempted']) * 100)
    : 0;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">
        <div class="page-header">
            <div>
                <h1>📊 My Learning Progress</h1>
                <p>Track your academic journey and achievements</p>
            </div>
            <div class="header-date">
                <?= date('F d, Y') ?>
            </div>
        </div>

        <!-- Overall Progress Card -->
        <div class="progress-hero">
            <div class="hero-chart">
                <svg viewBox="0 0 140 140" class="circular-progress">
                    <circle cx="70" cy="70" r="60" class="circle-bg"/>
                    <circle cx="70" cy="70" r="60" class="circle-progress"
                            style="stroke-dasharray: <?= $overallPercent * 3.77 ?>, 377"/>
                </svg>
                <div class="chart-center">
                    <span class="main-percent"><?= $overallPercent ?>%</span>
                    <span class="percent-label">Overall Progress</span>
                </div>
            </div>

            <div class="hero-stats">
                <div class="hero-stat-card">
                    <div class="stat-icon-wrap green">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Lessons Completed</span>
                        <span class="stat-value"><?= $stats['lessons_completed'] ?><span class="stat-total">/ <?= $totalLessons ?></span></span>
                        <div class="mini-progress">
                            <div class="mini-progress-fill" style="width: <?= $lessonPercent ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="hero-stat-card">
                    <div class="stat-icon-wrap blue">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Quizzes Taken</span>
                        <span class="stat-value"><?= $stats['quizzes_taken'] ?><span class="stat-total">/ <?= $totalQuizzes ?></span></span>
                        <div class="mini-progress blue">
                            <div class="mini-progress-fill" style="width: <?= $quizPercent ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="hero-stat-card">
                    <div class="stat-icon-wrap orange">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 6v6l4 2"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">This Week</span>
                        <span class="stat-value"><?= $weeklyActivity['lessons_this_week'] + $weeklyActivity['quizzes_this_week'] ?><span class="stat-total"> activities</span></span>
                        <div class="stat-breakdown">
                            <?= $weeklyActivity['lessons_this_week'] ?> lessons, <?= $weeklyActivity['quizzes_this_week'] ?> quizzes
                        </div>
                    </div>
                </div>

                <div class="hero-stat-card">
                    <div class="stat-icon-wrap purple">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <span class="stat-label">Average Score</span>
                        <span class="stat-value"><?= $stats['avg_score'] ?? 0 ?>%</span>
                        <div class="stat-breakdown">
                            Pass Rate: <?= $quizPassRate ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-icon">📚</span>
                    <span class="metric-title">Enrolled Subjects</span>
                </div>
                <div class="metric-value"><?= $stats['total_subjects'] ?></div>
                <div class="metric-label">Active Courses</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-icon">🎯</span>
                    <span class="metric-title">Best Score</span>
                </div>
                <div class="metric-value"><?= $quizStats['best_score'] ?? 0 ?>%</div>
                <div class="metric-label">Highest Quiz Result</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-icon">✅</span>
                    <span class="metric-title">Quizzes Passed</span>
                </div>
                <div class="metric-value"><?= $quizStats['quizzes_passed'] ?? 0 ?></div>
                <div class="metric-label">Out of <?= $quizStats['total_quizzes_attempted'] ?? 0 ?> Attempts</div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <span class="metric-icon">📈</span>
                    <span class="metric-title">Completion Rate</span>
                </div>
                <div class="metric-value"><?= $overallPercent ?>%</div>
                <div class="metric-label">Overall Progress</div>
            </div>
        </div>

        <!-- Subject Progress Details -->
        <div class="section-card">
            <div class="section-header">
                <h2>📖 Subject Progress Details</h2>
                <span class="section-badge"><?= count($subjectProgress) ?> Subjects</span>
            </div>
            <div class="section-body">
                <?php if (empty($subjectProgress)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">📚</span>
                        <h3>No Enrolled Subjects</h3>
                        <p>You haven't enrolled in any subjects yet.</p>
                        <a href="my-subjects.php" class="btn-primary">Browse Subjects</a>
                    </div>
                <?php else: ?>
                    <div class="subjects-grid">
                        <?php foreach ($subjectProgress as $subj):
                            $lessonProg = $subj['total_lessons'] > 0 ? round(($subj['completed_lessons'] / $subj['total_lessons']) * 100) : 0;
                            $quizProg = $subj['total_quizzes'] > 0 ? round(($subj['completed_quizzes'] / $subj['total_quizzes']) * 100) : 0;
                            $totalProg = round(($lessonProg + $quizProg) / 2);
                        ?>
                        <div class="subject-card">
                            <div class="subject-header">
                                <div class="subject-badge"><?= e($subj['subject_code']) ?></div>
                                <div class="subject-progress-ring">
                                    <svg viewBox="0 0 36 36">
                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                              fill="none" stroke="#e7e5e4" stroke-width="3"/>
                                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                              fill="none" stroke="#16a34a" stroke-width="3"
                                              stroke-dasharray="<?= $totalProg ?>, 100"/>
                                    </svg>
                                    <span class="ring-text"><?= $totalProg ?>%</span>
                                </div>
                            </div>

                            <h3 class="subject-name"><?= e($subj['subject_name']) ?></h3>
                            <?php if ($subj['instructor_name']): ?>
                                <p class="subject-instructor">👨‍🏫 <?= e($subj['instructor_name']) ?></p>
                            <?php endif; ?>

                            <div class="subject-stats">
                                <div class="subject-stat-item">
                                    <span class="stat-icon-sm">📖</span>
                                    <div class="stat-detail">
                                        <span class="stat-number"><?= $subj['completed_lessons'] ?>/<?= $subj['total_lessons'] ?></span>
                                        <span class="stat-text">Lessons</span>
                                    </div>
                                </div>
                                <div class="subject-stat-item">
                                    <span class="stat-icon-sm">📝</span>
                                    <div class="stat-detail">
                                        <span class="stat-number"><?= $subj['completed_quizzes'] ?>/<?= $subj['total_quizzes'] ?></span>
                                        <span class="stat-text">Quizzes</span>
                                    </div>
                                </div>
                                <?php if ($subj['avg_quiz_score']): ?>
                                <div class="subject-stat-item">
                                    <span class="stat-icon-sm">🎯</span>
                                    <div class="stat-detail">
                                        <span class="stat-number"><?= $subj['avg_quiz_score'] ?>%</span>
                                        <span class="stat-text">Avg Score</span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity Timeline -->
        <div class="section-card">
            <div class="section-header">
                <h2>🕐 Recent Activity</h2>
                <span class="section-badge">Last 15 Activities</span>
            </div>
            <div class="section-body">
                <?php if (empty($recentActivity)): ?>
                    <div class="empty-state">
                        <span class="empty-icon">📭</span>
                        <h3>No Activity Yet</h3>
                        <p>Start learning to see your activity here!</p>
                    </div>
                <?php else: ?>
                    <div class="activity-timeline">
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker <?= $activity['type'] ?>">
                                <?= $activity['type'] === 'lesson' ? '📖' : '📝' ?>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <h4 class="timeline-title"><?= e($activity['title']) ?></h4>
                                    <?php if ($activity['score'] !== null): ?>
                                        <span class="timeline-score <?= $activity['score'] >= 70 ? 'passed' : 'failed' ?>">
                                            <?= $activity['score'] ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-meta">
                                    <span class="meta-badge"><?= e($activity['subject_code']) ?></span>
                                    <span class="meta-text"><?= e($activity['subject_name']) ?></span>
                                    <span class="meta-divider">•</span>
                                    <span class="meta-date"><?= date('M d, Y \a\t g:i A', strtotime($activity['activity_date'])) ?></span>
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
* { box-sizing: border-box; }

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
}
.page-header h1 {
    font-size: 28px;
    font-weight: 800;
    color: #1c1917;
    margin: 0 0 4px;
}
.page-header p {
    color: #78716c;
    margin: 0;
    font-size: 15px;
}
.header-date {
    font-size: 14px;
    color: #78716c;
    padding: 8px 16px;
    background: #fdfbf7;
    border-radius: 8px;
    border: 1px solid #f5f0e8;
}

/* Progress Hero Section */
.progress-hero {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border-radius: 20px;
    padding: 40px;
    margin-bottom: 32px;
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 48px;
    align-items: center;
    box-shadow: 0 8px 32px rgba(22, 163, 74, 0.2);
}
.hero-chart {
    position: relative;
    width: 200px;
    height: 200px;
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
    transition: stroke-dasharray 1s ease;
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
    font-size: 48px;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 4px;
}
.percent-label {
    display: block;
    font-size: 13px;
    color: rgba(255,255,255,0.9);
    font-weight: 600;
}

/* Hero Stats Grid */
.hero-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}
.hero-stat-card {
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 16px;
    padding: 20px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.stat-icon-wrap {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.stat-icon-wrap.green { background: #dcfce7; color: #16a34a; }
.stat-icon-wrap.blue { background: #e0f2fe; color: #0891b2; }
.stat-icon-wrap.orange { background: #fed7aa; color: #ea580c; }
.stat-icon-wrap.purple { background: #e9d5ff; color: #9333ea; }
.stat-content {
    flex: 1;
}
.stat-label {
    display: block;
    font-size: 12px;
    color: rgba(255,255,255,0.9);
    margin-bottom: 6px;
    font-weight: 600;
}
.stat-value {
    display: block;
    font-size: 28px;
    font-weight: 800;
    color: #fff;
    line-height: 1;
    margin-bottom: 8px;
}
.stat-total {
    font-size: 16px;
    font-weight: 600;
    opacity: 0.7;
}
.mini-progress {
    height: 6px;
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
    overflow: hidden;
}
.mini-progress.blue .mini-progress-fill { background: #0891b2; }
.mini-progress-fill {
    height: 100%;
    background: #fff;
    border-radius: 3px;
    transition: width 0.5s ease;
}
.stat-breakdown {
    font-size: 12px;
    color: rgba(255,255,255,0.8);
    margin-top: 4px;
}

/* Metrics Grid */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}
.metric-card {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
}
.metric-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.metric-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.metric-icon {
    font-size: 24px;
}
.metric-title {
    font-size: 13px;
    font-weight: 600;
    color: #78716c;
}
.metric-value {
    font-size: 36px;
    font-weight: 800;
    color: #1c1917;
    margin-bottom: 4px;
}
.metric-label {
    font-size: 13px;
    color: #a8a29e;
}

/* Section Card */
.section-card {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 16px;
    margin-bottom: 24px;
    overflow: hidden;
}
.section-header {
    padding: 24px 32px;
    border-bottom: 1px solid #f5f0e8;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.section-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: #1c1917;
    margin: 0;
}
.section-badge {
    background: #fdfbf7;
    color: #78716c;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
.section-body {
    padding: 32px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 48px 24px;
}
.empty-icon {
    font-size: 64px;
    display: block;
    margin-bottom: 16px;
    opacity: 0.5;
}
.empty-state h3 {
    font-size: 18px;
    color: #1c1917;
    margin: 0 0 8px;
}
.empty-state p {
    color: #78716c;
    margin: 0 0 24px;
}
.btn-primary {
    display: inline-block;
    background: #16a34a;
    color: #fff;
    padding: 12px 28px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
}
.btn-primary:hover {
    background: #15803d;
}

/* Subjects Grid */
.subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 24px;
}
.subject-card {
    background: #fdfbf7;
    border: 1px solid #f5f0e8;
    border-radius: 16px;
    padding: 24px;
    transition: all 0.3s ease;
}
.subject-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.subject-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}
.subject-badge {
    background: #16a34a;
    color: #fff;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
}
.subject-progress-ring {
    position: relative;
    width: 48px;
    height: 48px;
}
.subject-progress-ring svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.ring-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 11px;
    font-weight: 700;
    color: #16a34a;
}
.subject-name {
    font-size: 16px;
    font-weight: 700;
    color: #1c1917;
    margin: 0 0 8px;
}
.subject-instructor {
    font-size: 13px;
    color: #78716c;
    margin: 0 0 16px;
}
.subject-stats {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.subject-stat-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    background: #fff;
    border-radius: 8px;
}
.stat-icon-sm {
    font-size: 20px;
}
.stat-detail {
    display: flex;
    flex-direction: column;
}
.stat-number {
    font-size: 14px;
    font-weight: 700;
    color: #1c1917;
}
.stat-text {
    font-size: 11px;
    color: #78716c;
}

/* Activity Timeline */
.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.timeline-item {
    display: flex;
    gap: 20px;
    padding-bottom: 24px;
    position: relative;
}
.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 23px;
    top: 48px;
    width: 2px;
    height: calc(100% - 24px);
    background: #f5f0e8;
}
.timeline-marker {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
    background: #fdfbf7;
    border: 2px solid #f5f0e8;
}
.timeline-marker.lesson {
    background: #dcfce7;
    border-color: #bbf7d0;
}
.timeline-marker.quiz {
    background: #e0f2fe;
    border-color: #bae6fd;
}
.timeline-content {
    flex: 1;
    padding-top: 4px;
}
.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}
.timeline-title {
    font-size: 15px;
    font-weight: 600;
    color: #1c1917;
    margin: 0;
    flex: 1;
}
.timeline-score {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
}
.timeline-score.passed {
    background: #dcfce7;
    color: #16a34a;
}
.timeline-score.failed {
    background: #fee2e2;
    color: #dc2626;
}
.timeline-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.meta-badge {
    background: #16a34a;
    color: #fff;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
}
.meta-text {
    font-size: 13px;
    color: #57534e;
}
.meta-divider {
    color: #d6d3d1;
}
.meta-date {
    font-size: 13px;
    color: #78716c;
}

/* Responsive */
@media (max-width: 1200px) {
    .progress-hero {
        grid-template-columns: 1fr;
        text-align: center;
        justify-items: center;
    }
    .metrics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
    .hero-stats {
        grid-template-columns: 1fr;
    }
    .metrics-grid {
        grid-template-columns: 1fr;
    }
    .subjects-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
