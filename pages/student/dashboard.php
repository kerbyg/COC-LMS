<?php
/**
 * ============================================================
 * CIT-LMS Student Dashboard - Modern UI
 * ============================================================
 */

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- DEBUG: Dashboard PHP is executing -->\n";

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$userName = Auth::user()['first_name'] ?? Auth::user()['name'] ?? 'Student';
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

// Fetch dashboard data
try {
    $subjectCount = db()->fetchOne(
        "SELECT COUNT(*) as count FROM student_subject ss
         JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
         WHERE ss.user_student_id = ? AND ss.status = 'enrolled'",
        [$userId]
    )['count'] ?? 0;

    $lessonsCompleted = db()->fetchOne(
        "SELECT COUNT(*) as count FROM student_progress
         WHERE user_student_id = ? AND status = 'completed'",
        [$userId]
    )['count'] ?? 0;

    $quizzesTaken = db()->fetchOne(
        "SELECT COUNT(DISTINCT quiz_id) as count FROM student_quiz_attempts
         WHERE user_student_id = ?",
        [$userId]
    )['count'] ?? 0;

    $avgScore = db()->fetchOne(
        "SELECT ROUND(AVG(percentage), 0) as avg_score FROM student_quiz_attempts
         WHERE user_student_id = ? AND status = 'completed'",
        [$userId]
    )['avg_score'] ?? 0;

    $enrolledSubjects = db()->fetchAll(
        "SELECT
            s.subject_id, s.subject_code, s.subject_name,
            CONCAT(u.first_name, ' ', u.last_name) as instructor_name,
            so.subject_offered_id,
            (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.subject_id AND l.status = 'published') as total_lessons,
            (SELECT COUNT(*) FROM student_progress sp
             JOIN lessons l ON sp.lesson_id = l.lesson_id
             WHERE sp.user_student_id = ? AND l.subject_id = s.subject_id AND sp.status = 'completed') as completed_lessons
         FROM student_subject ss
         JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
         JOIN subject s ON so.subject_id = s.subject_id
         LEFT JOIN users u ON so.user_teacher_id = u.users_id
         WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
         ORDER BY s.subject_code LIMIT 6",
        [$userId, $userId]
    );

    $announcements = db()->fetchAll(
        "SELECT a.title, a.created_at, s.subject_code
         FROM announcement a
         LEFT JOIN subject_offered so ON a.subject_offered_id = so.subject_offered_id
         LEFT JOIN subject s ON so.subject_id = s.subject_id
         WHERE (a.subject_offered_id IS NULL OR
                EXISTS (SELECT 1 FROM student_subject ss WHERE ss.subject_offered_id = a.subject_offered_id AND ss.user_student_id = ?))
         AND a.status = 'published'
         ORDER BY a.created_at DESC LIMIT 5",
        [$userId]
    );

    $upcomingQuizzes = db()->fetchAll(
        "SELECT q.quiz_title as title, q.created_at, s.subject_code
         FROM quiz q
         JOIN subject s ON q.subject_id = s.subject_id
         JOIN student_subject ss ON ss.subject_offered_id IN (SELECT subject_offered_id FROM subject_offered WHERE subject_id = q.subject_id)
         WHERE ss.user_student_id = ? AND q.status = 'published'
         AND q.quiz_id NOT IN (SELECT quiz_id FROM student_quiz_attempts WHERE user_student_id = ? AND status = 'completed')
         ORDER BY q.created_at DESC LIMIT 5",
        [$userId, $userId]
    );

    // Debug: Queries executed successfully
    echo "<!-- DEBUG: All queries executed successfully -->\n";
    echo "<!-- Subject Count: $subjectCount -->\n";
    echo "<!-- Enrolled Subjects: " . count($enrolledSubjects) . " -->\n";

} catch (Exception $e) {
    // Show error for debugging
    echo "<!DOCTYPE html><html><body>";
    echo "<div style='padding:20px;background:#fee;border:2px solid red;margin:20px;'>";
    echo "<h2 style='color:red;'>🔴 DATABASE ERROR DETECTED:</h2>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<pre style='background:#f5f5f5;padding:10px;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
    echo "</body></html>";
    die();
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <!-- Welcome Hero Section -->
        <div class="welcome-hero">
            <div class="welcome-content">
                <div class="greeting-badge">
                    <span class="wave">👋</span>
                    <span>Welcome back</span>
                </div>
                <h1 class="hero-title"><?= e($userName) ?></h1>
                <p class="hero-subtitle">Track your progress and stay on top of your learning journey</p>
            </div>
            <div class="hero-decoration">
                <div class="floating-shape shape-1"></div>
                <div class="floating-shape shape-2"></div>
                <div class="floating-shape shape-3"></div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="--delay: 0">
                <div class="stat-icon-wrap gradient-emerald">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?= $subjectCount ?></span>
                    <span class="stat-label">Enrolled Subjects</span>
                </div>
                <div class="stat-trend positive">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 13V7M4 10l3-3 3 3"/></svg>
                    Active
                </div>
            </div>
            
            <div class="stat-card" style="--delay: 1">
                <div class="stat-icon-wrap gradient-blue">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?= $lessonsCompleted ?></span>
                    <span class="stat-label">Lessons Completed</span>
                </div>
                <div class="stat-trend positive">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 13V7M4 10l3-3 3 3"/></svg>
                    Progress
                </div>
            </div>
            
            <div class="stat-card" style="--delay: 2">
                <div class="stat-icon-wrap gradient-violet">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?= $quizzesTaken ?></span>
                    <span class="stat-label">Quizzes Taken</span>
                </div>
                <div class="stat-trend neutral">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h8"/></svg>
                    Steady
                </div>
            </div>
            
            <div class="stat-card" style="--delay: 3">
                <div class="stat-icon-wrap gradient-amber">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-value"><?= $avgScore ?: 0 ?><span class="stat-unit">%</span></span>
                    <span class="stat-label">Average Score</span>
                </div>
                <div class="stat-trend <?= $avgScore >= 75 ? 'positive' : ($avgScore >= 50 ? 'neutral' : 'negative') ?>">
                    <?php if ($avgScore >= 75): ?>
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 13V7M4 10l3-3 3 3"/></svg>
                        Great
                    <?php elseif ($avgScore >= 50): ?>
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h8"/></svg>
                        Good
                    <?php else: ?>
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 7v6M4 10l3 3 3-3"/></svg>
                        Keep going
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="dashboard-grid">
            
            <!-- Subjects Section -->
            <section class="dashboard-main">
                <div class="section-card subjects-card">
                    <div class="section-header">
                        <div class="section-title">
                            <div class="title-icon">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                </svg>
                            </div>
                            <h2>My Subjects</h2>
                        </div>
                        <a href="my-subjects.php" class="view-all-btn">
                            View All
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5-5-5-5"/></svg>
                        </a>
                    </div>
                    
                    <div class="section-body">
                        <?php if (empty($enrolledSubjects)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                    </svg>
                                </div>
                                <h3>No Subjects Yet</h3>
                                <p>You haven't enrolled in any subjects. Start your learning journey today!</p>
                            </div>
                        <?php else: ?>
                            <div class="subjects-grid">
                                <?php foreach ($enrolledSubjects as $index => $subj): 
                                    $prog = $subj['total_lessons'] > 0 
                                        ? round(($subj['completed_lessons'] / $subj['total_lessons']) * 100) : 0;
                                    $colors = ['emerald', 'blue', 'violet', 'amber', 'rose', 'cyan'];
                                    $color = $colors[$index % count($colors)];
                                ?>
                                <a href="lessons.php?id=<?= $subj['subject_offered_id'] ?>" class="subject-card" style="--delay: <?= $index ?>; --accent: var(--<?= $color ?>)">
                                    <div class="subject-header">
                                        <span class="subject-code"><?= e($subj['subject_code']) ?></span>
                                        <div class="progress-circle" style="--progress: <?= $prog ?>">
                                            <svg viewBox="0 0 36 36">
                                                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                                <path class="circle-progress" stroke-dasharray="<?= $prog ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                            </svg>
                                            <span class="progress-text"><?= $prog ?>%</span>
                                        </div>
                                    </div>
                                    <h3 class="subject-name"><?= e($subj['subject_name']) ?></h3>
                                    <div class="subject-meta">
                                        <div class="instructor">
                                            <div class="avatar-placeholder">
                                                <?= strtoupper(substr($subj['instructor_name'] ?? 'T', 0, 1)) ?>
                                            </div>
                                            <span><?= e($subj['instructor_name'] ?? 'TBA') ?></span>
                                        </div>
                                    </div>
                                    <div class="subject-footer">
                                        <span class="lesson-count">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16"/><path d="M14 2v6h6"/></svg>
                                            <?= $subj['completed_lessons'] ?>/<?= $subj['total_lessons'] ?> lessons
                                        </span>
                                        <span class="continue-btn">
                                            Continue
                                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5-5-5-5"/></svg>
                                        </span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            
            <!-- Sidebar -->
            <aside class="dashboard-sidebar">
                
                <!-- Announcements -->
                <div class="section-card announcements-card">
                    <div class="section-header compact">
                        <div class="section-title">
                            <div class="title-icon pulse">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                                </svg>
                            </div>
                            <h2>Announcements</h2>
                        </div>
                    </div>
                    <div class="section-body">
                        <?php if (empty($announcements)): ?>
                            <div class="empty-state-sm">
                                <p>No announcements</p>
                            </div>
                        <?php else: ?>
                            <div class="announcement-list">
                                <?php foreach ($announcements as $index => $ann): ?>
                                <div class="announcement-item" style="--delay: <?= $index ?>">
                                    <div class="announcement-badge"><?= e($ann['subject_code']) ?></div>
                                    <h4><?= e($ann['title']) ?></h4>
                                    <time><?= formatDate($ann['created_at'], DATE_FORMAT_SHORT) ?></time>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Upcoming Deadlines -->
                <div class="section-card deadlines-card">
                    <div class="section-header compact">
                        <div class="section-title">
                            <div class="title-icon">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                            </div>
                            <h2>Upcoming</h2>
                        </div>
                    </div>
                    <div class="section-body">
                        <?php if (empty($upcomingQuizzes)): ?>
                            <div class="empty-state-sm">
                                <p>No upcoming deadlines</p>
                            </div>
                        <?php else: ?>
                            <div class="deadline-list">
                                <?php foreach ($upcomingQuizzes as $index => $quiz):
                                    $created = new DateTime($quiz['created_at']);
                                    $now = new DateTime();
                                    $diff = $now->diff($created)->days;
                                    $urgency = 'normal';
                                ?>
                                <div class="deadline-item <?= $urgency ?>" style="--delay: <?= $index ?>">
                                    <div class="deadline-date">
                                        <span class="day"><?= $created->format('d') ?></span>
                                        <span class="month"><?= $created->format('M') ?></span>
                                    </div>
                                    <div class="deadline-info">
                                        <h4><?= e($quiz['title']) ?></h4>
                                        <span class="deadline-subject"><?= e($quiz['subject_code']) ?></span>
                                    </div>
                                    <?php if ($urgency === 'urgent'): ?>
                                    <div class="urgency-badge">
                                        <span class="pulse-dot"></span>
                                        Soon
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </aside>
        </div>
        
    </div>
</main>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

/* CSS Variables */
:root {
    --bg-primary: #f8fafc;
    --bg-card: #ffffff;
    --bg-elevated: #ffffff;
    --text-primary: #0f172a;
    --text-secondary: #64748b;
    --text-muted: #94a3b8;
    --border-light: #e2e8f0;
    --border-focus: #cbd5e1;
    
    --emerald: #10b981;
    --emerald-light: #d1fae5;
    --blue: #3b82f6;
    --blue-light: #dbeafe;
    --violet: #8b5cf6;
    --violet-light: #ede9fe;
    --amber: #f59e0b;
    --amber-light: #fef3c7;
    --rose: #f43f5e;
    --rose-light: #ffe4e6;
    --cyan: #06b6d4;
    --cyan-light: #cffafe;
    
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.05);
    --shadow-lg: 0 8px 24px rgba(0,0,0,0.08);
    --shadow-xl: 0 16px 48px rgba(0,0,0,0.12);
    
    --radius-sm: 8px;
    --radius-md: 12px;
    --radius-lg: 16px;
    --radius-xl: 20px;
}

/* Base Styles */
.page-content {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

/* Welcome Hero */
.welcome-hero {
    position: relative;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: var(--radius-xl);
    padding: 32px 40px;
    margin-bottom: 24px;
    overflow: hidden;
    animation: fadeInUp 0.6s ease-out;
}

.welcome-content {
    position: relative;
    z-index: 2;
}

.greeting-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255,255,255,0.1);
    backdrop-filter: blur(10px);
    padding: 6px 14px;
    border-radius: 100px;
    font-size: 13px;
    color: rgba(255,255,255,0.9);
    margin-bottom: 12px;
}

.wave {
    display: inline-block;
    animation: wave 1.5s ease-in-out infinite;
    transform-origin: 70% 70%;
}

@keyframes wave {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(20deg); }
    50% { transform: rotate(-10deg); }
    75% { transform: rotate(20deg); }
}

.hero-title {
    font-size: 32px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 8px;
    letter-spacing: -0.5px;
}

.hero-subtitle {
    font-size: 15px;
    color: rgba(255,255,255,0.7);
    margin: 0;
}

.hero-decoration {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 50%;
    pointer-events: none;
}

.floating-shape {
    position: absolute;
    border-radius: 50%;
    opacity: 0.1;
}

.shape-1 {
    width: 200px;
    height: 200px;
    background: var(--emerald);
    top: -50px;
    right: 10%;
    animation: float 6s ease-in-out infinite;
}

.shape-2 {
    width: 120px;
    height: 120px;
    background: var(--blue);
    bottom: -30px;
    right: 25%;
    animation: float 8s ease-in-out infinite reverse;
}

.shape-3 {
    width: 80px;
    height: 80px;
    background: var(--violet);
    top: 20%;
    right: 5%;
    animation: float 5s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-20px) scale(1.05); }
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
    animation: fadeInUp 0.5s ease-out backwards;
    animation-delay: calc(var(--delay) * 0.1s);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--border-focus);
}

.stat-icon-wrap {
    width: 52px;
    height: 52px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.gradient-emerald {
    background: linear-gradient(135deg, var(--emerald-light) 0%, #a7f3d0 100%);
    color: var(--emerald);
}

.gradient-blue {
    background: linear-gradient(135deg, var(--blue-light) 0%, #bfdbfe 100%);
    color: var(--blue);
}

.gradient-violet {
    background: linear-gradient(135deg, var(--violet-light) 0%, #ddd6fe 100%);
    color: var(--violet);
}

.gradient-amber {
    background: linear-gradient(135deg, var(--amber-light) 0%, #fde68a 100%);
    color: var(--amber);
}

.stat-info {
    flex: 1;
    min-width: 0;
}

.stat-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.1;
}

.stat-unit {
    font-size: 18px;
    font-weight: 600;
}

.stat-label {
    display: block;
    font-size: 13px;
    color: var(--text-secondary);
    margin-top: 2px;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 100px;
}

.stat-trend.positive {
    background: var(--emerald-light);
    color: var(--emerald);
}

.stat-trend.neutral {
    background: #f1f5f9;
    color: var(--text-secondary);
}

.stat-trend.negative {
    background: var(--rose-light);
    color: var(--rose);
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 24px;
}

/* Section Cards */
.section-card {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
    animation: fadeInUp 0.5s ease-out backwards;
    animation-delay: 0.3s;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-light);
    background: linear-gradient(180deg, #fafafa 0%, var(--bg-card) 100%);
}

.section-header.compact {
    padding: 16px 20px;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.title-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--emerald-light) 0%, #a7f3d0 100%);
    color: var(--emerald);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
}

.title-icon.pulse {
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
}

.section-title h2 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.view-all-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 600;
    color: var(--emerald);
    text-decoration: none;
    padding: 8px 14px;
    border-radius: var(--radius-sm);
    transition: all 0.2s;
}

.view-all-btn:hover {
    background: var(--emerald-light);
}

.section-body {
    padding: 20px 24px;
}

.section-header.compact + .section-body {
    padding: 16px 20px;
}

/* Subjects Grid */
.subjects-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.subject-card {
    --accent: var(--emerald);
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 20px;
    text-decoration: none;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: 12px;
    animation: fadeInUp 0.4s ease-out backwards;
    animation-delay: calc(var(--delay) * 0.08s + 0.3s);
}

.subject-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg);
    border-color: var(--accent);
}

.subject-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.subject-code {
    display: inline-block;
    background: var(--accent);
    color: #fff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.progress-circle {
    width: 44px;
    height: 44px;
    position: relative;
}

.progress-circle svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.circle-bg {
    fill: none;
    stroke: var(--border-light);
    stroke-width: 3;
}

.circle-progress {
    fill: none;
    stroke: var(--accent);
    stroke-width: 3;
    stroke-linecap: round;
    transition: stroke-dasharray 0.6s ease;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 10px;
    font-weight: 700;
    color: var(--text-primary);
}

.subject-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    line-height: 1.4;
}

.subject-meta {
    display: flex;
    align-items: center;
}

.instructor {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--text-secondary);
}

.avatar-placeholder {
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, var(--border-light) 0%, #e2e8f0 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary);
}

.subject-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid var(--border-light);
    margin-top: auto;
}

.lesson-count {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--text-muted);
}

.continue-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    color: var(--accent);
    opacity: 0;
    transform: translateX(-8px);
    transition: all 0.3s;
}

.subject-card:hover .continue-btn {
    opacity: 1;
    transform: translateX(0);
}

/* Sidebar */
.dashboard-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Announcements */
.announcement-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.announcement-item {
    padding: 14px;
    background: #fafafa;
    border-radius: var(--radius-sm);
    border-left: 3px solid var(--emerald);
    animation: fadeInLeft 0.4s ease-out backwards;
    animation-delay: calc(var(--delay) * 0.08s + 0.4s);
    transition: all 0.2s;
}

.announcement-item:hover {
    background: var(--emerald-light);
}

.announcement-badge {
    display: inline-block;
    background: var(--emerald);
    color: #fff;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    margin-bottom: 6px;
}

.announcement-item h4 {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-primary);
    margin: 0 0 4px;
    line-height: 1.4;
}

.announcement-item time {
    font-size: 11px;
    color: var(--text-muted);
}

/* Deadlines */
.deadline-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.deadline-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px;
    background: #fafafa;
    border-radius: var(--radius-sm);
    border-left: 3px solid var(--emerald);
    animation: fadeInLeft 0.4s ease-out backwards;
    animation-delay: calc(var(--delay) * 0.08s + 0.4s);
    transition: all 0.2s;
}

.deadline-item:hover {
    background: #f1f5f9;
}

.deadline-item.urgent {
    border-left-color: var(--rose);
    background: var(--rose-light);
}

.deadline-item.soon {
    border-left-color: var(--amber);
    background: var(--amber-light);
}

.deadline-date {
    text-align: center;
    min-width: 40px;
}

.deadline-date .day {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1;
}

.deadline-date .month {
    display: block;
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    font-weight: 600;
}

.deadline-info {
    flex: 1;
    min-width: 0;
}

.deadline-info h4 {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-primary);
    margin: 0 0 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.deadline-subject {
    font-size: 11px;
    color: var(--text-muted);
}

.urgency-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    color: var(--rose);
    padding: 4px 10px;
    background: rgba(255,255,255,0.6);
    border-radius: 100px;
}

.pulse-dot {
    width: 6px;
    height: 6px;
    background: var(--rose);
    border-radius: 50%;
    animation: pulseDot 1.5s ease-in-out infinite;
}

@keyframes pulseDot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.5); }
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 48px 24px;
}

.empty-icon {
    width: 72px;
    height: 72px;
    background: #f8fafc;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    color: var(--text-muted);
}

.empty-state h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 8px;
}

.empty-state p {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0;
}

.empty-state-sm {
    text-align: center;
    padding: 24px;
}

.empty-state-sm p {
    font-size: 13px;
    color: var(--text-muted);
    margin: 0;
}

/* Animations */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInLeft {
    from {
        opacity: 0;
        transform: translateX(-12px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-sidebar {
        flex-direction: row;
    }
    
    .dashboard-sidebar > * {
        flex: 1;
    }
}

@media (max-width: 768px) {
    .page-content {
        padding: 16px;
    }
    
    .welcome-hero {
        padding: 24px;
    }
    
    .hero-title {
        font-size: 24px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: 16px;
    }
    
    .subjects-grid {
        grid-template-columns: 1fr;
    }
    
    .dashboard-sidebar {
        flex-direction: column;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>