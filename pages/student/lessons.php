<?php
/**
 * Lessons Page - Clean Green Theme
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$subjectOfferingId = $_GET['id'] ?? null;

if (!$subjectOfferingId) {
    $lessons = db()->fetchAll(
        "SELECT
            l.lessons_id,
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
        LEFT JOIN student_progress sp ON l.lessons_id = sp.lessons_id AND sp.user_student_id = ?
        WHERE ss.user_student_id = ? AND ss.status = 'enrolled' AND l.status = 'published'
        ORDER BY s.subject_code, l.lesson_order ASC",
        [$userId, $userId]
    );

    $pageTitle = 'All Lessons';
    $currentPage = 'lessons';
    $subject = null;
    $progress = 0;
} else {
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
            l.lessons_id,
            l.lesson_title as title,
            l.lesson_description as description,
            l.lesson_order as order_number,
            l.created_at,
            CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END as is_completed,
            sp.completed_at
        FROM lessons l
        LEFT JOIN student_progress sp ON l.lessons_id = sp.lessons_id AND sp.user_student_id = ?
        WHERE l.subject_id = ? AND l.status = 'published'
        ORDER BY l.lesson_order ASC",
        [$userId, $subject['subject_id']]
    );

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

    <div class="lessons-wrap">

        <?php if ($subject): ?>
        <!-- Back Link -->
        <a href="my-subjects.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
            Back to Subjects
        </a>

        <!-- Subject Header -->
        <div class="subject-header">
            <div class="header-info">
                <span class="subject-code"><?= e($subject['subject_code']) ?></span>
                <h1><?= e($subject['subject_name']) ?></h1>
                <div class="instructor">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <?= e($subject['instructor_name'] ?? 'TBA') ?>
                </div>
            </div>
            <div class="header-progress">
                <div class="progress-stats">
                    <span class="progress-text"><?= $completedLessons ?>/<?= $totalLessons ?> Completed</span>
                    <span class="progress-percent"><?= $progress ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <a href="learning-hub.php?id=<?= $subjectOfferingId ?>" class="tab">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polygon points="10 8 16 12 10 16 10 8"/>
                </svg>
                Learning Hub
            </a>
            <a href="lessons.php?id=<?= $subjectOfferingId ?>" class="tab active">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                </svg>
                Lessons
            </a>
            <a href="quizzes.php?id=<?= $subjectOfferingId ?>" class="tab">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11l3 3L22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                Quizzes
            </a>
            <a href="announcements.php?id=<?= $subjectOfferingId ?>" class="tab">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                Announcements
            </a>
        </div>
        <?php else: ?>
        <!-- All Lessons Header -->
        <div class="page-header">
            <h1>All Lessons</h1>
            <p>Lessons from all your enrolled subjects</p>
        </div>
        <?php endif; ?>

        <!-- Lessons List -->
        <?php if (empty($lessons)): ?>
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
            </svg>
            <h3>No Lessons Available</h3>
            <p>Your instructor hasn't published any lessons yet</p>
        </div>
        <?php else: ?>
        <div class="lessons-list">
            <?php $num = 1; foreach ($lessons as $lesson): ?>
            <div class="lesson-card <?= $lesson['is_completed'] ? 'completed' : '' ?>">
                <div class="lesson-number">
                    <?php if ($lesson['is_completed']): ?>
                    <div class="check-circle">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <path d="M20 6L9 17l-5-5"/>
                        </svg>
                    </div>
                    <?php else: ?>
                    <div class="num-circle"><?= $num ?></div>
                    <?php endif; ?>
                </div>

                <div class="lesson-content">
                    <h3><?= e($lesson['title']) ?></h3>
                    <?php if ($lesson['description']): ?>
                    <p><?= e($lesson['description']) ?></p>
                    <?php endif; ?>
                    <div class="lesson-meta">
                        <?php if ($lesson['is_completed']): ?>
                        <span class="badge completed">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6L9 17l-5-5"/>
                            </svg>
                            Completed <?= date('M j, Y', strtotime($lesson['completed_at'])) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge available">Available</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lesson-action">
                    <a href="lesson-view.php?id=<?= $lesson['lessons_id'] ?>" class="btn-lesson <?= $lesson['is_completed'] ? 'review' : 'start' ?>">
                        <?= $lesson['is_completed'] ? 'Review' : 'Start' ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
            <?php $num++; endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<style>
/* Lessons Page - Green/Cream Theme */
.lessons-wrap {
    padding: 24px;
    max-width: 1000px;
    margin: 0 auto;
}

/* Back Link */
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #1B4D3E;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 20px;
    transition: opacity 0.2s;
}

.back-link:hover {
    opacity: 0.7;
}

/* Subject Header */
.subject-header {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 24px;
}

.header-info .subject-code {
    display: inline-block;
    background: #1B4D3E;
    color: #fff;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    margin-bottom: 10px;
}

.header-info h1 {
    font-size: 22px;
    font-weight: 700;
    color: #333;
    margin: 0 0 10px;
}

.instructor {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #666;
}

.instructor svg {
    color: #1B4D3E;
}

.header-progress {
    min-width: 200px;
    text-align: right;
}

.progress-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.progress-text {
    font-size: 13px;
    color: #666;
}

.progress-percent {
    font-size: 18px;
    font-weight: 700;
    color: #1B4D3E;
}

.progress-bar {
    height: 6px;
    background: #e8e8e8;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: #1B4D3E;
    border-radius: 3px;
    transition: width 0.3s;
}

/* Navigation Tabs */
.nav-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 10px;
    padding: 6px;
}

.tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    color: #666;
    transition: all 0.2s;
}

.tab:hover {
    background: #fafafa;
    color: #1B4D3E;
}

.tab.active {
    background: #1B4D3E;
    color: #fff;
}

.tab svg {
    flex-shrink: 0;
}

/* Page Header (All Lessons) */
.page-header {
    margin-bottom: 24px;
}

.page-header h1 {
    font-size: 24px;
    font-weight: 700;
    color: #1B4D3E;
    margin: 0 0 4px;
}

.page-header p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Lessons List */
.lessons-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.lesson-card {
    display: flex;
    align-items: center;
    gap: 16px;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s ease;
}

.lesson-card:hover {
    border-color: #1B4D3E;
    box-shadow: 0 4px 12px rgba(27, 77, 62, 0.1);
}

.lesson-card.completed {
    background: #f8fdf9;
    border-color: #c8e6c9;
}

/* Lesson Number */
.lesson-number {
    flex-shrink: 0;
}

.num-circle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #f5f5f5;
    color: #999;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    font-weight: 700;
}

.check-circle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #1B4D3E;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Lesson Content */
.lesson-content {
    flex: 1;
    min-width: 0;
}

.lesson-content h3 {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0 0 6px;
}

.lesson-content p {
    font-size: 14px;
    color: #666;
    margin: 0 0 10px;
    line-height: 1.4;
}

.lesson-meta {
    display: flex;
    align-items: center;
    gap: 8px;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.badge.completed {
    background: #E8F5E9;
    color: #1B4D3E;
}

.badge.available {
    background: #f5f5f5;
    color: #666;
}

/* Lesson Action */
.lesson-action {
    flex-shrink: 0;
}

.btn-lesson {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.btn-lesson.start {
    background: #1B4D3E;
    color: #fff;
}

.btn-lesson.start:hover {
    background: #2D6A4F;
}

.btn-lesson.review {
    background: #E8F5E9;
    color: #1B4D3E;
}

.btn-lesson.review:hover {
    background: #1B4D3E;
    color: #fff;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 24px;
    background: #fafafa;
    border: 1px dashed #ddd;
    border-radius: 12px;
}

.empty-state svg {
    color: #ccc;
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0 0 8px;
}

.empty-state p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .lessons-wrap {
        padding: 16px;
    }

    .subject-header {
        flex-direction: column;
    }

    .header-progress {
        width: 100%;
        text-align: left;
    }

    .nav-tabs {
        flex-wrap: wrap;
    }

    .tab {
        flex: 1 1 45%;
    }

    .lesson-card {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }

    .lesson-number {
        display: flex;
        justify-content: center;
    }

    .lesson-action {
        width: 100%;
    }

    .btn-lesson {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
