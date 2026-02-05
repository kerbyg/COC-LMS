<?php
/**
 * Student Dashboard - Clean & Simple
 */
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
             JOIN lessons l ON sp.lessons_id = l.lessons_id
             WHERE sp.user_student_id = ? AND l.subject_id = s.subject_id AND sp.status = 'completed') as completed_lessons
         FROM student_subject ss
         JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
         JOIN subject s ON so.subject_id = s.subject_id
         LEFT JOIN users u ON so.user_teacher_id = u.users_id
         WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
         ORDER BY s.subject_code LIMIT 4",
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
         ORDER BY a.created_at DESC LIMIT 4",
        [$userId]
    );

    $upcomingQuizzes = db()->fetchAll(
        "SELECT q.quiz_title as title, q.due_date, q.created_at, s.subject_code
         FROM quiz q
         JOIN subject s ON q.subject_id = s.subject_id
         JOIN student_subject ss ON ss.subject_offered_id IN (SELECT subject_offered_id FROM subject_offered WHERE subject_id = q.subject_id)
         WHERE ss.user_student_id = ? AND q.status = 'published'
         AND q.quiz_id NOT IN (SELECT quiz_id FROM student_quiz_attempts WHERE user_student_id = ? AND status = 'completed')
         ORDER BY q.created_at DESC LIMIT 3",
        [$userId, $userId]
    );

} catch (Exception $e) {
    $subjectCount = $lessonsCompleted = $quizzesTaken = $avgScore = 0;
    $enrolledSubjects = $announcements = $upcomingQuizzes = [];
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="dashboard-wrap">

        <!-- Welcome -->
        <div class="welcome-box">
            <div class="welcome-text">
                <p class="greeting">Welcome back,</p>
                <h1><?= e($userName) ?></h1>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-num"><?= $subjectCount ?></span>
                    <span class="stat-label">Enrolled Subjects</span>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-num"><?= $lessonsCompleted ?></span>
                    <span class="stat-label">Lessons Completed</span>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4"/>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-num"><?= $quizzesTaken ?></span>
                    <span class="stat-label">Quizzes Taken</span>
                </div>
            </div>

            <div class="stat-box">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20V10"/>
                        <path d="M18 20V4"/>
                        <path d="M6 20v-4"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-num"><?= $avgScore ?: 0 ?>%</span>
                    <span class="stat-label">Average Score</span>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="content-grid">

            <!-- Subjects -->
            <div class="card subjects-card">
                <div class="card-header">
                    <h2>My Subjects</h2>
                    <a href="my-subjects.php" class="link-btn">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($enrolledSubjects)): ?>
                    <div class="empty-msg">
                        <p>No subjects enrolled yet.</p>
                        <a href="enroll-section.php" class="btn-primary">Enroll Now</a>
                    </div>
                    <?php else: ?>
                    <div class="subjects-list">
                        <?php foreach ($enrolledSubjects as $subj):
                            $progress = $subj['total_lessons'] > 0
                                ? round(($subj['completed_lessons'] / $subj['total_lessons']) * 100) : 0;
                        ?>
                        <a href="lessons.php?id=<?= $subj['subject_offered_id'] ?>" class="subject-item">
                            <div class="subject-badge"><?= e($subj['subject_code']) ?></div>
                            <div class="subject-details">
                                <h3><?= e($subj['subject_name']) ?></h3>
                                <p><?= e($subj['instructor_name'] ?? 'TBA') ?></p>
                            </div>
                            <div class="subject-progress">
                                <div class="progress-circle">
                                    <svg viewBox="0 0 36 36">
                                        <path class="bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                        <path class="fill" stroke-dasharray="<?= $progress ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                    </svg>
                                    <span><?= $progress ?>%</span>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar-cards">

                <!-- Upcoming -->
                <div class="card">
                    <div class="card-header">
                        <h2>Upcoming Quizzes</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingQuizzes)): ?>
                        <p class="empty-text">No upcoming quizzes</p>
                        <?php else: ?>
                        <div class="list-items">
                            <?php foreach ($upcomingQuizzes as $quiz): ?>
                            <div class="list-item">
                                <span class="item-badge"><?= e($quiz['subject_code']) ?></span>
                                <span class="item-title"><?= e($quiz['title']) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Announcements -->
                <div class="card">
                    <div class="card-header">
                        <h2>Announcements</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($announcements)): ?>
                        <p class="empty-text">No announcements</p>
                        <?php else: ?>
                        <div class="list-items">
                            <?php foreach ($announcements as $ann): ?>
                            <div class="list-item">
                                <span class="item-badge"><?= e($ann['subject_code'] ?? 'General') ?></span>
                                <div class="item-info">
                                    <span class="item-title"><?= e($ann['title']) ?></span>
                                    <span class="item-date"><?= date('M j, Y', strtotime($ann['created_at'])) ?></span>
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
/* Dashboard - Clean Green/Cream Theme */
.dashboard-wrap {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

/* Welcome */
.welcome-box {
    background: linear-gradient(135deg, #1B4D3E 0%, #2D6A4F 100%);
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 24px;
    color: #fff;
}

.greeting {
    font-size: 14px;
    opacity: 0.8;
    margin: 0 0 4px;
}

.welcome-box h1 {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-box {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s ease;
}

.stat-box:hover {
    border-color: #1B4D3E;
    box-shadow: 0 4px 12px rgba(27, 77, 62, 0.1);
    transform: translateY(-2px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    background: #E8F5E9;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1B4D3E;
}

.stat-icon svg {
    width: 24px;
    height: 24px;
}

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-num {
    font-size: 24px;
    font-weight: 700;
    color: #1B4D3E;
}

.stat-label {
    font-size: 13px;
    color: #666;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 24px;
}

/* Card */
.card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #f0f0f0;
}

.card-header h2 {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.link-btn {
    font-size: 13px;
    font-weight: 500;
    color: #1B4D3E;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 6px;
    transition: all 0.2s;
}

.link-btn:hover {
    background: #E8F5E9;
}

.card-body {
    padding: 16px 20px;
}

/* Subjects List */
.subjects-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.subject-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: #fafafa;
    border: 1px solid transparent;
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.2s ease;
}

.subject-item:hover {
    background: #E8F5E9;
    border-color: #1B4D3E;
    transform: translateX(4px);
}

.subject-badge {
    background: #1B4D3E;
    color: #fff;
    padding: 8px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    min-width: 60px;
    text-align: center;
}

.subject-details {
    flex: 1;
    min-width: 0;
}

.subject-details h3 {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin: 0 0 4px;
}

.subject-details p {
    font-size: 13px;
    color: #666;
    margin: 0;
}

.progress-circle {
    width: 48px;
    height: 48px;
    position: relative;
}

.progress-circle svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.progress-circle .bg {
    fill: none;
    stroke: #e8e8e8;
    stroke-width: 3;
}

.progress-circle .fill {
    fill: none;
    stroke: #1B4D3E;
    stroke-width: 3;
    stroke-linecap: round;
}

.progress-circle span {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 11px;
    font-weight: 600;
    color: #1B4D3E;
}

/* Sidebar Cards */
.sidebar-cards {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* List Items */
.list-items {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.list-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    background: #fafafa;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.list-item:hover {
    background: #E8F5E9;
}

.item-badge {
    background: #1B4D3E;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    flex-shrink: 0;
}

.item-info {
    flex: 1;
    min-width: 0;
}

.item-title {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #333;
    line-height: 1.4;
}

.item-date {
    font-size: 11px;
    color: #999;
    margin-top: 2px;
    display: block;
}

/* Empty States */
.empty-msg {
    text-align: center;
    padding: 40px 20px;
}

.empty-msg p {
    color: #666;
    margin: 0 0 16px;
}

.btn-primary {
    display: inline-block;
    background: #1B4D3E;
    color: #fff;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: #2D6A4F;
}

.empty-text {
    text-align: center;
    color: #999;
    font-size: 13px;
    padding: 20px;
    margin: 0;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .content-grid {
        grid-template-columns: 1fr;
    }
    .sidebar-cards {
        flex-direction: row;
    }
    .sidebar-cards .card {
        flex: 1;
    }
}

@media (max-width: 768px) {
    .dashboard-wrap {
        padding: 16px;
    }
    .welcome-box {
        padding: 24px;
    }
    .welcome-box h1 {
        font-size: 24px;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .stat-box {
        padding: 16px;
    }
    .stat-num {
        font-size: 20px;
    }
    .sidebar-cards {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
