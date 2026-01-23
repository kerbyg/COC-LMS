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

// Get assigned classes with student count
$classes = db()->fetchAll(
    "SELECT 
        s.subject_id,
        s.subject_code,
        s.subject_name,
        so.subject_offered_id,
        so.academic_year,
        so.semester,
        (SELECT COUNT(*) FROM student_subject ss WHERE ss.subject_offered_id = so.subject_offered_id AND ss.status = 'enrolled') as student_count,
        (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.subject_id AND l.user_teacher_id = ? AND l.status = 'published') as lesson_count,
        (SELECT COUNT(*) FROM quiz q WHERE q.subject_id = s.subject_id AND q.user_teacher_id = ? AND q.status = 'published') as quiz_count
    FROM faculty_subject fs
    JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    WHERE fs.user_teacher_id = ? AND fs.status = 'active'
    ORDER BY s.subject_code",
    [$userId, $userId, $userId]
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
    LIMIT 5",
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

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Welcome back, <?= e($userName) ?>! 👋</h1>
                <p>Here's what's happening with your classes today.</p>
            </div>
            <div class="welcome-actions">
                <a href="lesson-edit.php" class="btn-primary">+ New Lesson</a>
                <a href="quiz-edit.php" class="btn-secondary">+ New Quiz</a>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon classes">📚</div>
                <div class="stat-info">
                    <span class="stat-num" data-count="<?= $stats['total_classes'] ?>">0</span>
                    <span class="stat-label">My Classes</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon students">👥</div>
                <div class="stat-info">
                    <span class="stat-num" data-count="<?= $stats['total_students'] ?>">0</span>
                    <span class="stat-label">Total Students</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon lessons">📖</div>
                <div class="stat-info">
                    <span class="stat-num" data-count="<?= $stats['total_lessons'] ?>">0</span>
                    <span class="stat-label">Lessons Created</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon quizzes">📝</div>
                <div class="stat-info">
                    <span class="stat-num" data-count="<?= $stats['total_quizzes'] ?>">0</span>
                    <span class="stat-label">Quizzes Created</span>
                </div>
            </div>
        </div>
        
        <!-- My Classes -->
        <div class="section-header">
            <h2>📚 My Classes</h2>
            <a href="my-classes.php" class="view-all">View All →</a>
        </div>
        
        <div class="classes-grid">
            <?php if (empty($classes)): ?>
                <div class="empty-box">
                    <span>📚</span>
                    <h3>No Classes Assigned</h3>
                    <p>You don't have any classes assigned yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($classes as $class): ?>
                <div class="class-card">
                    <div class="class-header">
                        <span class="class-code"><?= e($class['subject_code']) ?></span>
                        <span class="class-sem"><?= e($class['semester']) ?> <?= e($class['academic_year']) ?></span>
                    </div>
                    <h3 class="class-name"><?= e($class['subject_name']) ?></h3>
                    <div class="class-stats">
                        <div class="class-stat">
                            <span class="cs-num"><?= $class['student_count'] ?></span>
                            <span class="cs-label">Students</span>
                        </div>
                        <div class="class-stat">
                            <span class="cs-num"><?= $class['lesson_count'] ?></span>
                            <span class="cs-label">Lessons</span>
                        </div>
                        <div class="class-stat">
                            <span class="cs-num"><?= $class['quiz_count'] ?></span>
                            <span class="cs-label">Quizzes</span>
                        </div>
                    </div>
                    <a href="lessons.php?subject_id=<?= $class['subject_id'] ?>&offered_id=<?= $class['subject_offered_id'] ?>" class="class-link">
                        Manage Class →
                    </a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Two Column Layout -->
        <div class="grid-2col">
            
            <!-- Recent Quiz Attempts -->
            <div class="panel">
                <div class="panel-head">
                    <h3>📊 Recent Quiz Submissions</h3>
                </div>
                <div class="panel-body">
                    <?php if (empty($recentAttempts)): ?>
                        <p class="empty-msg">No quiz submissions yet</p>
                    <?php else: ?>
                        <div class="attempts-list">
                            <?php foreach ($recentAttempts as $attempt): ?>
                            <div class="attempt-item">
                                <div class="attempt-info">
                                    <span class="attempt-student"><?= e($attempt['student_name']) ?></span>
                                    <span class="attempt-quiz"><?= e($attempt['subject_code']) ?> - <?= e($attempt['quiz_title']) ?></span>
                                </div>
                                <div class="attempt-score <?= $attempt['passed'] ? 'passed' : 'failed' ?>">
                                    <?= round($attempt['percentage']) ?>%
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Upcoming Deadlines -->
            <div class="panel">
                <div class="panel-head">
                    <h3>📅 Upcoming Quiz Deadlines</h3>
                </div>
                <div class="panel-body">
                    <?php if (empty($upcomingDeadlines)): ?>
                        <p class="empty-msg">No upcoming deadlines</p>
                    <?php else: ?>
                        <div class="deadlines-list">
                            <?php foreach ($upcomingDeadlines as $quiz): 
                                $daysLeft = (strtotime($quiz['due_date']) - time()) / 86400;
                                $urgency = $daysLeft <= 2 ? 'urgent' : ($daysLeft <= 5 ? 'soon' : 'normal');
                            ?>
                            <div class="deadline-item">
                                <div class="deadline-info">
                                    <span class="deadline-title"><?= e($quiz['quiz_title']) ?></span>
                                    <span class="deadline-subject"><?= e($quiz['subject_code']) ?> • <?= $quiz['attempts_count'] ?> submissions</span>
                                </div>
                                <div class="deadline-date <?= $urgency ?>">
                                    <?= date('M d', strtotime($quiz['due_date'])) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
        
    </div>
</main>

<style>
/* Instructor Dashboard Styles */

.welcome-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding: 24px;
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border-radius: 16px;
    color: #fff;
}
.welcome-text h1 {
    font-size: 24px;
    margin: 0 0 4px;
}
.welcome-text p {
    margin: 0;
    opacity: 0.9;
}
.welcome-actions {
    display: flex;
    gap: 12px;
}
.btn-primary {
    padding: 12px 20px;
    background: #fff;
    color: #16a34a;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-primary:hover { background: #f0fdf4; }
.btn-secondary {
    padding: 12px 20px;
    background: rgba(255,255,255,0.2);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-secondary:hover { background: rgba(255,255,255,0.3); }

/* Stats */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.stat-icon.classes { background: #dbeafe; }
.stat-icon.students { background: #dcfce7; }
.stat-icon.lessons { background: #fef3c7; }
.stat-icon.quizzes { background: #f3e8ff; }
.stat-num {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: #1c1917;
}
.stat-label {
    font-size: 13px;
    color: #78716c;
}

/* Section Header */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.section-header h2 {
    font-size: 18px;
    color: #1c1917;
    margin: 0;
}
.view-all {
    color: #16a34a;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}
.view-all:hover { text-decoration: underline; }

/* Classes Grid */
.classes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.class-card {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s;
}
.class-card:hover {
    border-color: #16a34a;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);
}
.class-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.class-code {
    background: #16a34a;
    color: #fff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}
.class-sem {
    font-size: 12px;
    color: #78716c;
}
.class-name {
    font-size: 16px;
    color: #1c1917;
    margin: 0 0 16px;
}
.class-stats {
    display: flex;
    gap: 16px;
    padding: 12px 0;
    border-top: 1px solid #f5f0e8;
    border-bottom: 1px solid #f5f0e8;
    margin-bottom: 12px;
}
.class-stat { text-align: center; flex: 1; }
.cs-num {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: #16a34a;
}
.cs-label {
    font-size: 11px;
    color: #78716c;
}
.class-link {
    display: block;
    text-align: center;
    color: #16a34a;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
}
.class-link:hover { text-decoration: underline; }

/* Two Column Grid */
.grid-2col {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

/* Panels */
.panel {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
    overflow: hidden;
}
.panel-head {
    padding: 16px 20px;
    background: #fdfbf7;
    border-bottom: 1px solid #f5f0e8;
}
.panel-head h3 {
    font-size: 15px;
    color: #1c1917;
    margin: 0;
}
.panel-body {
    padding: 16px 20px;
}

/* Attempts List */
.attempts-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.attempt-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 12px;
    border-bottom: 1px solid #f5f0e8;
}
.attempt-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.attempt-student {
    display: block;
    font-weight: 500;
    color: #1c1917;
    font-size: 14px;
}
.attempt-quiz {
    font-size: 12px;
    color: #78716c;
}
.attempt-score {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}
.attempt-score.passed { background: #dcfce7; color: #16a34a; }
.attempt-score.failed { background: #fee2e2; color: #dc2626; }

/* Deadlines List */
.deadlines-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.deadline-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 12px;
    border-bottom: 1px solid #f5f0e8;
}
.deadline-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
.deadline-title {
    display: block;
    font-weight: 500;
    color: #1c1917;
    font-size: 14px;
}
.deadline-subject {
    font-size: 12px;
    color: #78716c;
}
.deadline-date {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}
.deadline-date.urgent { background: #fee2e2; color: #dc2626; }
.deadline-date.soon { background: #fef3c7; color: #b45309; }
.deadline-date.normal { background: #f5f0e8; color: #57534e; }

/* Empty States */
.empty-box {
    text-align: center;
    padding: 40px;
    background: #fdfbf7;
    border-radius: 12px;
    grid-column: 1 / -1;
}
.empty-box span { font-size: 40px; display: block; margin-bottom: 8px; opacity: 0.5; }
.empty-box h3 { font-size: 16px; color: #1c1917; margin: 0 0 4px; }
.empty-box p { color: #78716c; margin: 0; font-size: 14px; }
.empty-msg {
    text-align: center;
    color: #a8a29e;
    padding: 20px;
    margin: 0;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .grid-2col { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .welcome-section { flex-direction: column; text-align: center; gap: 16px; }
    .stats-grid { grid-template-columns: 1fr; }
}
</style>

<script>
// Count-up animation for stats
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.stat-num').forEach(function(el) {
        const target = parseInt(el.dataset.count) || 0;
        let current = 0;
        const increment = target / 30;
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