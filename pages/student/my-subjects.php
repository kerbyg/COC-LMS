<?php
/**
 * CIT-LMS - My Subjects Page
 * Shows all enrolled subjects in a card grid
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$pageTitle = 'My Subjects';
$currentPage = 'my-subjects';

// Fetch enrolled subjects with progress (updated to include section info)
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
             JOIN lessons l ON sp.lesson_id = l.lesson_id
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
    error_log("My Subjects error: " . $e->getMessage());
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <!-- Page Header -->
        <div class="page-head">
            <div>
                <h1>📚 My Subjects</h1>
                <p>You are enrolled in <?= count($subjects) ?> subject<?= count($subjects) != 1 ? 's' : '' ?></p>
            </div>
        </div>
        
        <!-- Subjects Grid -->
        <?php if (empty($subjects)): ?>
            <div class="empty-box">
                <span>📚</span>
                <h3>No Subjects Yet</h3>
                <p>You are not enrolled in any subjects this semester.</p>
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
                    <div class="card-top">
                        <span class="subj-code"><?= e($subj['subject_code']) ?></span>
                        <span class="subj-units"><?= $subj['units'] ?> units</span>
                    </div>
                    
                    <h3 class="subj-name"><?= e($subj['subject_name']) ?></h3>
                    
                    <div class="subj-meta">
                        <p>👨‍🏫 <?= e($subj['instructor_name'] ?? 'TBA') ?></p>
                        <?php if (isset($subj['section_name']) && $subj['section_name']): ?>
                        <p>🏫 <?= e($subj['section_name']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="subj-stats">
                        <div class="stat-item">
                            <span class="stat-num"><?= $subj['completed_lessons'] ?>/<?= $subj['total_lessons'] ?></span>
                            <span class="stat-lbl">Lessons</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-num"><?= $subj['completed_quizzes'] ?>/<?= $subj['total_quizzes'] ?></span>
                            <span class="stat-lbl">Quizzes</span>
                        </div>
                    </div>
                    
                    <div class="progress-section">
                        <div class="progress-header">
                            <span>Progress</span>
                            <span><?= round($overallProg) ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $overallProg ?>%"></div>
                        </div>
                    </div>
                    
                    <a href="learning-hub.php?id=<?= $subj['subject_offered_id'] ?>" class="card-btn">
                        Start Learning →
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    </div>
</main>

<style>
/* My Subjects Page Styles */

.page-head {
    margin-bottom: 24px;
}
.page-head h1 {
    font-size: 22px;
    color: #1c1917;
    margin: 0 0 4px;
}
.page-head p {
    color: #78716c;
    margin: 0;
    font-size: 14px;
}

/* Subjects Grid */
.subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

/* Subject Card */
.subject-card {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s;
}
.subject-card:hover {
    border-color: #16a34a;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.1);
}

.card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.subj-code {
    background: #16a34a;
    color: #fff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}
.subj-units {
    font-size: 12px;
    color: #78716c;
}

.subj-name {
    font-size: 17px;
    color: #1c1917;
    margin: 0 0 12px;
    line-height: 1.4;
}

.subj-meta {
    margin-bottom: 16px;
}
.subj-meta p {
    font-size: 13px;
    color: #57534e;
    margin: 0 0 4px;
}
.subj-meta p:last-child {
    margin: 0;
}

.subj-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 16px;
    padding: 12px;
    background: #fdfbf7;
    border-radius: 8px;
}
.stat-item {
    text-align: center;
    flex: 1;
}
.stat-num {
    display: block;
    font-size: 18px;
    font-weight: 700;
    color: #16a34a;
}
.stat-lbl {
    font-size: 12px;
    color: #78716c;
}

.progress-section {
    margin-bottom: 16px;
}
.progress-header {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 6px;
}
.progress-header span:first-child {
    color: #57534e;
}
.progress-header span:last-child {
    font-weight: 600;
    color: #16a34a;
}
.progress-bar {
    height: 8px;
    background: #e7e5e4;
    border-radius: 4px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background: #16a34a;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.card-btn {
    display: block;
    text-align: center;
    padding: 12px;
    background: #dcfce7;
    color: #16a34a;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
}
.card-btn:hover {
    background: #16a34a;
    color: #fff;
}

/* Empty State */
.empty-box {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
}
.empty-box span {
    font-size: 48px;
    display: block;
    margin-bottom: 12px;
    opacity: 0.5;
}
.empty-box h3 {
    font-size: 18px;
    color: #1c1917;
    margin: 0 0 8px;
}
.empty-box p {
    color: #78716c;
    margin: 0;
    font-size: 14px;
}

/* Responsive */
@media (max-width: 640px) {
    .subjects-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>