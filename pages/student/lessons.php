<?php
/**
 * CIT-LMS - Lessons Page
 * Shows all lessons for a specific subject with real-time progress
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$subjectOfferingId = $_GET['id'] ?? null;

// If no subject ID provided, show all lessons from all enrolled subjects
if (!$subjectOfferingId) {
    // Get all lessons from all enrolled subjects
    $lessons = db()->fetchAll(
        "SELECT
            l.lesson_id,
            l.lesson_title as title,
            l.lesson_description as description,
            l.lesson_order as order_number,
            l.created_at,
            s.subject_code,
            s.subject_name,
            so.subject_offered_id,
            CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END as is_completed,
            sp.completed_at
        FROM lessons l
        JOIN subject s ON l.subject_id = s.subject_id
        JOIN subject_offered so ON so.subject_id = s.subject_id
        JOIN student_subject ss ON ss.subject_offered_id = so.subject_offered_id
        LEFT JOIN student_progress sp
            ON l.lesson_id = sp.lesson_id AND sp.user_student_id = ?
        WHERE ss.user_student_id = ? AND ss.status = 'enrolled' AND l.status = 'published'
        ORDER BY s.subject_code, l.lesson_order ASC",
        [$userId, $userId]
    );

    $pageTitle = 'All Lessons';
    $currentPage = 'lessons';
    $subject = null;
    $progress = 0;
} else {
    // Show lessons for specific subject
    $enrollment = db()->fetchOne(
        "SELECT ss.* FROM student_subject ss
         WHERE ss.user_student_id = ? AND ss.subject_offered_id = ? AND ss.status = 'enrolled'",
        [$userId, $subjectOfferingId]
    );

    if (!$enrollment) {
        header('Location: my-subjects.php');
        exit;
    }

    $subject = db()->fetchOne(
        "SELECT
            s.subject_id, s.subject_code, s.subject_name, s.description,
            CONCAT(u.first_name, ' ', u.last_name) as instructor_name
        FROM subject_offered so
        JOIN subject s ON so.subject_id = s.subject_id
        LEFT JOIN faculty_subject fs ON so.subject_offered_id = fs.subject_offered_id
        LEFT JOIN users u ON fs.user_teacher_id = u.users_id
        WHERE so.subject_offered_id = ?",
        [$subjectOfferingId]
    );

    if (!$subject) {
        header('Location: my-subjects.php');
        exit;
    }

    $lessons = db()->fetchAll(
        "SELECT
            l.lesson_id,
            l.lesson_title as title,
            l.lesson_description as description,
            l.lesson_order as order_number,
            l.created_at,
            CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END as is_completed,
            sp.completed_at
        FROM lessons l
        LEFT JOIN student_progress sp
            ON l.lesson_id = sp.lesson_id AND sp.user_student_id = ?
        WHERE l.subject_id = ? AND l.status = 'published'
        ORDER BY l.lesson_order ASC",
        [$userId, $subject['subject_id']]
    );

    // Calculate real progress percentage for the progress bar
    $totalLessons = count($lessons);
    $completedLessons = count(array_filter($lessons, fn($l) => $l['is_completed']));
    $progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

    $pageTitle = $subject['subject_code'] . ' - Lessons';
    $currentPage = 'lessons';
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($subject): ?>
            <!-- Back Link -->
            <div class="page-top">
                <a href="my-subjects.php" class="back-link">← Back to Subjects</a>
            </div>

            <!-- Subject Header with Progress -->
            <div class="subject-header">
                <div class="header-left">
                    <span class="subj-code"><?= e($subject['subject_code']) ?></span>
                    <h1 class="subject-title"><?= e($subject['subject_name']) ?></h1>
                    <p class="instructor-info">👨‍🏫 Instructor: <?= e($subject['instructor_name'] ?? 'TBA') ?></p>
                </div>
                <div class="header-right">
                    <span class="modules-completed"><?= $completedLessons ?>/<?= $totalLessons ?> Modules Completed</span>
                    <span class="completion-percent"><?= $progress ?>%</span>
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar-fill" style="width: <?= $progress ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <a href="learning-hub.php?id=<?= $subjectOfferingId ?>" class="nav-tab">
                    <span class="tab-icon">🎯</span>
                    <span class="tab-text">Learning Hub</span>
                </a>
                <a href="lessons.php?id=<?= $subjectOfferingId ?>" class="nav-tab active">
                    <span class="tab-icon">📖</span>
                    <span class="tab-text">Lessons</span>
                </a>
                <a href="quizzes.php?id=<?= $subjectOfferingId ?>" class="nav-tab">
                    <span class="tab-icon">📝</span>
                    <span class="tab-text">Quizzes</span>
                </a>
                <a href="announcements.php?id=<?= $subjectOfferingId ?>" class="nav-tab">
                    <span class="tab-icon">📢</span>
                    <span class="tab-text">Announcements</span>
                </a>
            </div>
        <?php else: ?>
            <!-- All Lessons View -->
            <div class="page-head">
                <h1>📖 All Lessons</h1>
                <p>View all lessons from your enrolled subjects</p>
            </div>
        <?php endif; ?>

        <!-- Lessons Content -->
        <div class="lessons-container">
            <?php if (empty($lessons)): ?>
                <div class="empty-state">
                    <span class="empty-icon">📖</span>
                    <h3>No Lessons Available</h3>
                    <p>Your instructors haven't published any lessons yet.</p>
                </div>
            <?php else: ?>
                <div class="lessons-grid">
                    <?php
                    $lessonNumber = 1;
                    foreach ($lessons as $lesson):
                        $isCompleted = $lesson['is_completed'];
                    ?>
                    <div class="lesson-module <?= $isCompleted ? 'completed' : '' ?>">
                        <div class="module-status">
                            <?php if ($isCompleted): ?>
                                <span class="status-icon completed">✓</span>
                            <?php else: ?>
                                <span class="status-number"><?= $lessonNumber ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="module-info">
                            <h3 class="module-title"><?= e($lesson['title']) ?></h3>
                            <p class="module-desc"><?= e($lesson['description'] ?: 'Learn computer basics') ?></p>

                            <div class="module-footer">
                                <?php if ($isCompleted): ?>
                                    <span class="completion-badge">✓ Finished on <?= date('M d, Y', strtotime($lesson['completed_at'])) ?></span>
                                <?php else: ?>
                                    <span class="availability-badge">Module Available</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="module-action">
                            <a href="lesson-view.php?id=<?= $lesson['lesson_id'] ?>"
                               class="action-btn <?= $isCompleted ? 'review-btn' : 'start-btn' ?>">
                                <?= $isCompleted ? 'Review Content' : 'Start Lesson →' ?>
                            </a>
                        </div>
                    </div>
                    <?php
                        $lessonNumber++;
                    endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
/* Modern Lessons Page Styles */

/* Back Link */
.page-top {
    margin-bottom: 20px;
}
.back-link {
    color: #16a34a;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: color 0.2s;
}
.back-link:hover {
    color: #15803d;
}

/* Subject Header */
.subject-header {
    background: linear-gradient(135deg, #fdfbf7 0%, #f5f0e8 100%);
    border: 1px solid #f5f0e8;
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 32px;
}

.header-left {
    flex: 1;
}

.subj-code {
    background: #16a34a;
    color: #fff;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
    margin-bottom: 12px;
    text-transform: uppercase;
}

.subject-title {
    font-size: 26px;
    color: #1c1917;
    margin: 0 0 8px;
    font-weight: 700;
}

.instructor-info {
    color: #57534e;
    margin: 0;
    font-size: 14px;
}

.header-right {
    text-align: right;
    min-width: 200px;
}

.modules-completed {
    display: block;
    font-size: 13px;
    color: #78716c;
    margin-bottom: 4px;
}

.completion-percent {
    display: block;
    font-size: 24px;
    font-weight: 800;
    color: #16a34a;
    margin-bottom: 8px;
}

.progress-bar-wrapper {
    height: 10px;
    background: #e7e5e4;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #16a34a 0%, #22c55e 100%);
    border-radius: 10px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Navigation Tabs */
.nav-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 28px;
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
    padding: 6px;
}

.nav-tab {
    flex: 1;
    padding: 14px 20px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    color: #57534e;
    text-align: center;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.nav-tab:hover {
    background: #fdfbf7;
    color: #16a34a;
}

.nav-tab.active {
    background: #16a34a;
    color: #fff;
}

.tab-icon {
    font-size: 16px;
}

.tab-text {
    font-size: 14px;
}

/* Lessons Grid */
.lessons-container {
    margin-top: 24px;
}

.lessons-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.lesson-module {
    background: #fff;
    border: 2px solid #f5f0e8;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: flex-start;
    gap: 20px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.lesson-module:hover {
    border-color: #16a34a;
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(22, 163, 74, 0.12);
}

.lesson-module.completed {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-color: #bbf7d0;
}

/* Module Status (Left Circle) */
.module-status {
    flex-shrink: 0;
}

.status-icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
}

.status-icon.completed {
    background: #16a34a;
    color: #fff;
}

.status-number {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #f5f5f4;
    color: #78716c;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 700;
}

/* Module Info (Middle) */
.module-info {
    flex: 1;
}

.module-title {
    font-size: 18px;
    color: #1c1917;
    margin: 0 0 8px;
    font-weight: 700;
}

.module-desc {
    color: #78716c;
    margin: 0 0 12px;
    font-size: 14px;
    line-height: 1.5;
}

.module-footer {
    margin-top: 12px;
}

.completion-badge {
    display: inline-block;
    padding: 6px 12px;
    background: #dcfce7;
    color: #16a34a;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
}

.availability-badge {
    display: inline-block;
    padding: 6px 12px;
    background: #f5f5f4;
    color: #78716c;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
}

/* Module Action (Right Button) */
.module-action {
    flex-shrink: 0;
}

.action-btn {
    display: inline-block;
    padding: 14px 24px;
    border-radius: 10px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    transition: all 0.2s;
    white-space: nowrap;
}

.start-btn {
    background: #16a34a;
    color: #fff;
}

.start-btn:hover {
    background: #15803d;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
}

.review-btn {
    background: #fff;
    color: #16a34a;
    border: 2px solid #16a34a;
}

.review-btn:hover {
    background: #16a34a;
    color: #fff;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 80px 40px;
    background: #fff;
    border: 2px dashed #e7e5e4;
    border-radius: 16px;
}

.empty-icon {
    font-size: 64px;
    display: block;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 20px;
    color: #1c1917;
    margin: 0 0 8px;
}

.empty-state p {
    color: #78716c;
    margin: 0;
    font-size: 14px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .subject-header {
        flex-direction: column;
        gap: 20px;
    }

    .header-right {
        width: 100%;
        text-align: left;
    }

    .lesson-module {
        flex-direction: column;
        align-items: stretch;
    }

    .module-action {
        width: 100%;
    }

    .action-btn {
        display: block;
        text-align: center;
        width: 100%;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>