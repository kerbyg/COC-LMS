<?php
/**
 * My Subjects Page - Clean Green Theme
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$pageTitle = 'My Subjects';
$currentPage = 'my-subjects';

try {
    $subjects = db()->fetchAll(
        "SELECT
            s.subject_id,
            s.subject_code,
            s.subject_name,
            s.description,
            s.units,
            so.subject_offered_id,
            sec.section_id,
            sec.section_name,
            sec.schedule,
            sec.room,
            (SELECT CONCAT(u.first_name, ' ', u.last_name)
             FROM faculty_subject fs
             JOIN users u ON fs.user_teacher_id = u.users_id
             WHERE fs.section_id = sec.section_id AND fs.status = 'active'
             LIMIT 1) as instructor_name,
            (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.subject_id AND l.status = 'published') as total_lessons,
            (SELECT COUNT(*) FROM student_progress sp
             JOIN lessons l ON sp.lessons_id = l.lessons_id
             WHERE sp.user_student_id = ? AND l.subject_id = s.subject_id AND sp.status = 'completed') as completed_lessons,
            (SELECT COUNT(*) FROM quiz q WHERE q.subject_id = s.subject_id AND q.status = 'published') as total_quizzes,
            (SELECT COUNT(DISTINCT qa.quiz_id) FROM student_quiz_attempts qa
             JOIN quiz q ON qa.quiz_id = q.quiz_id
             WHERE qa.user_student_id = ? AND q.subject_id = s.subject_id AND qa.status = 'completed') as completed_quizzes
         FROM student_subject ss
         JOIN section sec ON ss.section_id = sec.section_id
         JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
         JOIN subject s ON so.subject_id = s.subject_id
         WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
         ORDER BY s.subject_code",
        [$userId, $userId, $userId]
    );
} catch (Exception $e) {
    $subjects = [];
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="subjects-wrap">

        <!-- Header -->
        <div class="page-header">
            <h1>My Subjects</h1>
            <p><?= count($subjects) ?> subject<?= count($subjects) != 1 ? 's' : '' ?> enrolled</p>
        </div>

        <!-- Subjects Grid -->
        <?php if (empty($subjects)): ?>
        <div class="empty-state">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
            </svg>
            <h3>No Subjects Yet</h3>
            <p>You haven't enrolled in any subjects yet</p>
            <a href="enroll.php" class="btn-enroll">Enroll Now</a>
        </div>
        <?php else: ?>
        <div class="subjects-grid">
            <?php foreach ($subjects as $subj):
                $lessonProg = $subj['total_lessons'] > 0
                    ? round(($subj['completed_lessons'] / $subj['total_lessons']) * 100) : 0;
                $quizProg = $subj['total_quizzes'] > 0
                    ? round(($subj['completed_quizzes'] / $subj['total_quizzes']) * 100) : 0;
                $overallProg = ($lessonProg + $quizProg) / 2;
            ?>
            <div class="subject-card">
                <div class="card-header">
                    <span class="subject-code"><?= e($subj['subject_code']) ?></span>
                    <span class="subject-units"><?= $subj['units'] ?> units</span>
                </div>

                <h3 class="subject-name"><?= e($subj['subject_name']) ?></h3>

                <div class="subject-info">
                    <div class="info-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span><?= e($subj['instructor_name'] ?? 'TBA') ?></span>
                    </div>
                    <?php if ($subj['section_name']): ?>
                    <div class="info-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        <span><?= e($subj['section_name']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="subject-stats">
                    <div class="stat">
                        <span class="stat-value"><?= $subj['completed_lessons'] ?>/<?= $subj['total_lessons'] ?></span>
                        <span class="stat-label">Lessons</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value"><?= $subj['completed_quizzes'] ?>/<?= $subj['total_quizzes'] ?></span>
                        <span class="stat-label">Quizzes</span>
                    </div>
                </div>

                <div class="progress-section">
                    <div class="progress-info">
                        <span>Progress</span>
                        <span class="progress-percent"><?= round($overallProg) ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $overallProg ?>%"></div>
                    </div>
                </div>

                <a href="learning-hub.php?id=<?= $subj['subject_offered_id'] ?>" class="btn-learn">
                    Continue Learning
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<style>
/* My Subjects - Green/Cream Theme */
.subjects-wrap {
    padding: 24px;
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
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

/* Grid */
.subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

/* Card */
.subject-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s ease;
}

.subject-card:hover {
    border-color: #1B4D3E;
    box-shadow: 0 4px 12px rgba(27, 77, 62, 0.1);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.subject-code {
    background: #1B4D3E;
    color: #fff;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.subject-units {
    font-size: 12px;
    color: #999;
}

.subject-name {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0 0 14px;
    line-height: 1.4;
}

/* Info */
.subject-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 16px;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #666;
}

.info-item svg {
    color: #1B4D3E;
}

/* Stats */
.subject-stats {
    display: flex;
    gap: 16px;
    padding: 14px;
    background: #fafafa;
    border-radius: 8px;
    margin-bottom: 16px;
}

.stat {
    flex: 1;
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #1B4D3E;
}

.stat-label {
    font-size: 12px;
    color: #999;
}

/* Progress */
.progress-section {
    margin-bottom: 16px;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 6px;
}

.progress-info span:first-child {
    color: #666;
}

.progress-percent {
    font-weight: 600;
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
    transition: width 0.3s ease;
}

/* Button */
.btn-learn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 12px;
    background: #E8F5E9;
    color: #1B4D3E;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-learn:hover {
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
    margin: 0 0 20px;
}

.btn-enroll {
    display: inline-block;
    padding: 10px 24px;
    background: #1B4D3E;
    color: #fff;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-enroll:hover {
    background: #2D6A4F;
}

/* Responsive */
@media (max-width: 768px) {
    .subjects-wrap {
        padding: 16px;
    }

    .subjects-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
