<?php
/**
 * CIT-LMS Instructor Dashboard
 * Main landing page for instructors
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('instructor');

$userId = Auth::id();
$userName = Auth::user()['first_name'];
$pageTitle = 'Instructor Dashboard';
$currentPage = 'dashboard';

// Get instructor stats
$stats = db()->fetchOne(
    "SELECT
        (SELECT COUNT(*) FROM faculty_subject WHERE user_teacher_id = ? AND status = 'active') as total_classes,
        (SELECT COUNT(*) FROM lessons WHERE user_teacher_id = ? AND status = 'published') as total_lessons,
        (SELECT COUNT(*) FROM quiz WHERE user_teacher_id = ? AND status = 'published') as total_quizzes,
        (SELECT COUNT(DISTINCT ss.user_student_id)
         FROM student_subject ss
         JOIN faculty_subject fs ON ss.subject_offered_id = fs.subject_offered_id
         WHERE fs.user_teacher_id = ? AND ss.status = 'enrolled') as total_students
    ",
    [$userId, $userId, $userId, $userId]
);

// Get recent quiz attempts from students
$recentAttempts = db()->fetchAll(
    "SELECT
        sqa.attempt_id,
        sqa.percentage,
        sqa.passed,
        sqa.completed_at,
        q.quiz_title,
        s.subject_code,
        CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM student_quiz_attempts sqa
    JOIN quiz q ON sqa.quiz_id = q.quiz_id
    JOIN subject s ON q.subject_id = s.subject_id
    JOIN users u ON sqa.user_student_id = u.users_id
    WHERE q.user_teacher_id = ? AND sqa.status = 'completed'
    ORDER BY sqa.completed_at DESC
    LIMIT 6",
    [$userId]
);

// Get pending quizzes (due soon)
$upcomingDeadlines = db()->fetchAll(
    "SELECT
        q.quiz_id,
        q.quiz_title,
        q.due_date,
        s.subject_code,
        (SELECT COUNT(*) FROM student_quiz_attempts sqa WHERE sqa.quiz_id = q.quiz_id AND sqa.status = 'completed') as attempts_count
    FROM quiz q
    JOIN subject s ON q.subject_id = s.subject_id
    WHERE q.user_teacher_id = ? AND q.status = 'published' AND q.due_date >= CURDATE()
    ORDER BY q.due_date ASC
    LIMIT 5",
    [$userId]
);

// Get recent activity (lessons created/updated recently)
$recentLessons = db()->fetchAll(
    "SELECT
        l.lessons_id,
        l.lesson_title,
        l.status,
        l.updated_at,
        s.subject_code
    FROM lessons l
    JOIN subject s ON l.subject_id = s.subject_id
    WHERE l.user_teacher_id = ?
    ORDER BY l.updated_at DESC
    LIMIT 4",
    [$userId]
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <!-- Welcome Banner -->
        <div class="dash-welcome">
            <div class="dash-welcome-left">
                <h1>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e($userName) ?></h1>
                <p>Here's an overview of your teaching activity.</p>
            </div>
            <div class="dash-welcome-actions">
                <a href="lesson-edit.php" class="dash-btn dash-btn-light">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    New Lesson
                </a>
                <a href="quiz-edit.php" class="dash-btn dash-btn-outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                    New Quiz
                </a>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="dash-stats">
            <div class="dash-stat-card">
                <div class="dash-stat-icon" style="background: #E8F5E9; color: #1B4D3E;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg>
                </div>
                <div class="dash-stat-content">
                    <span class="dash-stat-number" data-count="<?= $stats['total_classes'] ?>">0</span>
                    <span class="dash-stat-label">Classes</span>
                </div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-icon" style="background: #E3F2FD; color: #1565C0;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <div class="dash-stat-content">
                    <span class="dash-stat-number" data-count="<?= $stats['total_students'] ?>">0</span>
                    <span class="dash-stat-label">Students</span>
                </div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-icon" style="background: #FFF3E0; color: #E65100;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </div>
                <div class="dash-stat-content">
                    <span class="dash-stat-number" data-count="<?= $stats['total_lessons'] ?>">0</span>
                    <span class="dash-stat-label">Lessons</span>
                </div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-icon" style="background: #F3E5F5; color: #7B1FA2;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <div class="dash-stat-content">
                    <span class="dash-stat-number" data-count="<?= $stats['total_quizzes'] ?>">0</span>
                    <span class="dash-stat-label">Quizzes</span>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="dash-grid">

            <!-- Recent Submissions -->
            <div class="dash-panel">
                <div class="dash-panel-header">
                    <h3>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                        Recent Submissions
                    </h3>
                </div>
                <div class="dash-panel-body">
                    <?php if (empty($recentAttempts)): ?>
                        <div class="dash-empty">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
                            <p>No submissions yet</p>
                        </div>
                    <?php else: ?>
                        <div class="dash-list">
                            <?php foreach ($recentAttempts as $attempt): ?>
                            <div class="dash-list-item">
                                <div class="dash-list-avatar">
                                    <?= strtoupper(substr($attempt['student_name'], 0, 1)) ?>
                                </div>
                                <div class="dash-list-info">
                                    <span class="dash-list-primary"><?= e($attempt['student_name']) ?></span>
                                    <span class="dash-list-secondary"><?= e($attempt['subject_code']) ?> - <?= e($attempt['quiz_title']) ?></span>
                                </div>
                                <div class="dash-badge <?= $attempt['passed'] ? 'dash-badge-success' : 'dash-badge-danger' ?>">
                                    <?= round($attempt['percentage']) ?>%
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div class="dash-right-col">

                <!-- Upcoming Deadlines -->
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <h3>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                            Upcoming Deadlines
                        </h3>
                    </div>
                    <div class="dash-panel-body">
                        <?php if (empty($upcomingDeadlines)): ?>
                            <div class="dash-empty dash-empty-sm">
                                <p>No upcoming deadlines</p>
                            </div>
                        <?php else: ?>
                            <div class="dash-list dash-list-compact">
                                <?php foreach ($upcomingDeadlines as $quiz):
                                    $daysLeft = (strtotime($quiz['due_date']) - time()) / 86400;
                                    $urgency = $daysLeft <= 2 ? 'dash-badge-danger' : ($daysLeft <= 5 ? 'dash-badge-warning' : 'dash-badge-neutral');
                                ?>
                                <div class="dash-list-item">
                                    <div class="dash-list-info">
                                        <span class="dash-list-primary"><?= e($quiz['quiz_title']) ?></span>
                                        <span class="dash-list-secondary"><?= e($quiz['subject_code']) ?> &middot; <?= $quiz['attempts_count'] ?> submitted</span>
                                    </div>
                                    <div class="dash-badge <?= $urgency ?>">
                                        <?= date('M d', strtotime($quiz['due_date'])) ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Lessons -->
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <h3>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            Recent Lessons
                        </h3>
                    </div>
                    <div class="dash-panel-body">
                        <?php if (empty($recentLessons)): ?>
                            <div class="dash-empty dash-empty-sm">
                                <p>No lessons created yet</p>
                            </div>
                        <?php else: ?>
                            <div class="dash-list dash-list-compact">
                                <?php foreach ($recentLessons as $lesson): ?>
                                <div class="dash-list-item">
                                    <div class="dash-list-info">
                                        <span class="dash-list-primary"><?= e($lesson['lesson_title']) ?></span>
                                        <span class="dash-list-secondary"><?= e($lesson['subject_code']) ?> &middot; <?= date('M d', strtotime($lesson['updated_at'])) ?></span>
                                    </div>
                                    <div class="dash-badge <?= $lesson['status'] === 'published' ? 'dash-badge-success' : 'dash-badge-neutral' ?>">
                                        <?= ucfirst($lesson['status']) ?>
                                    </div>
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
   Instructor Dashboard - Clean Professional
   ============================================ */

/* Welcome Banner */
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
.dash-welcome h1 {
    font-size: 22px;
    font-weight: 700;
    margin: 0 0 4px;
    letter-spacing: -0.3px;
}
.dash-welcome p {
    margin: 0;
    opacity: 0.8;
    font-size: 14px;
}
.dash-welcome-actions {
    display: flex;
    gap: 10px;
}
.dash-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.dash-btn-light {
    background: #fff;
    color: #1B4D3E;
}
.dash-btn-light:hover { background: #E8F5E9; }
.dash-btn-outline {
    background: rgba(255,255,255,0.12);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.25);
}
.dash-btn-outline:hover { background: rgba(255,255,255,0.22); }

/* Stats */
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
.dash-stat-card:hover {
    border-color: #1B4D3E;
}
.dash-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.dash-stat-number {
    display: block;
    font-size: 26px;
    font-weight: 700;
    color: #1a1a1a;
    line-height: 1.1;
}
.dash-stat-label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* Main Grid */
.dash-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 20px;
}
.dash-right-col {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Panels */
.dash-panel {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    overflow: hidden;
}
.dash-panel-header {
    padding: 16px 20px;
    border-bottom: 1px solid #f0f0f0;
}
.dash-panel-header h3 {
    font-size: 14px;
    font-weight: 600;
    color: #1a1a1a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dash-panel-header h3 svg {
    color: #1B4D3E;
}
.dash-panel-body {
    padding: 4px 0;
}

/* Lists */
.dash-list {
    display: flex;
    flex-direction: column;
}
.dash-list-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    transition: background 0.15s ease;
}
.dash-list-item:hover {
    background: #fafafa;
}
.dash-list-compact .dash-list-item {
    padding: 10px 20px;
}
.dash-list-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #E8F5E9;
    color: #1B4D3E;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    flex-shrink: 0;
}
.dash-list-info {
    flex: 1;
    min-width: 0;
}
.dash-list-primary {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #1a1a1a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dash-list-secondary {
    display: block;
    font-size: 12px;
    color: #9ca3af;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Badges */
.dash-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
}
.dash-badge-success { background: #E8F5E9; color: #1B4D3E; }
.dash-badge-danger { background: #FEE2E2; color: #DC2626; }
.dash-badge-warning { background: #FEF3C7; color: #B45309; }
.dash-badge-neutral { background: #F3F4F6; color: #6B7280; }

/* Empty States */
.dash-empty {
    text-align: center;
    padding: 32px 20px;
    color: #9ca3af;
}
.dash-empty svg {
    margin-bottom: 8px;
    opacity: 0.4;
}
.dash-empty p {
    margin: 0;
    font-size: 13px;
}
.dash-empty-sm {
    padding: 20px;
}

/* Responsive */
@media (max-width: 1024px) {
    .dash-stats { grid-template-columns: repeat(2, 1fr); }
    .dash-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .dash-welcome { flex-direction: column; text-align: center; gap: 16px; padding: 24px 20px; }
    .dash-welcome h1 { font-size: 18px; }
    .dash-stats { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .dash-stat-card { padding: 16px; }
    .dash-stat-number { font-size: 22px; }
}
@media (max-width: 480px) {
    .dash-stats { grid-template-columns: 1fr; }
    .dash-welcome-actions { flex-direction: column; width: 100%; }
    .dash-btn { justify-content: center; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.dash-stat-number').forEach(function(el) {
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
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
