<?php
/**
 * Instructor - My Classes
 * View all assigned subjects/classes
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('instructor');

$userId = Auth::id();
$pageTitle = 'My Classes';
$currentPage = 'my-classes';

// Get current academic settings
$settings = db()->fetchOne(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'academic_year'"
);
$currentAY = $settings['setting_value'] ?? '2024-2025';

$semesterSetting = db()->fetchOne(
    "SELECT setting_value FROM system_settings WHERE setting_key = 'current_semester'"
);
$currentSem = $semesterSetting['setting_value'] ?? '1st';

// Get filter parameters
$filterSemId = $_GET['semester_id'] ?? '';

// Build query (using semester table)
$whereClause = "WHERE fs.user_teacher_id = ? AND fs.status = 'active'";
$params = [$userId];

if ($filterSemId) {
    $whereClause .= " AND so.semester_id = ?";
    $params[] = $filterSemId;
}

// Get assigned classes
$classes = db()->fetchAll(
    "SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name,
        s.description,
        s.units,
        so.subject_offered_id,
        sem.academic_year,
        sem.semester_name as semester,
        so.status as offering_status,
        (SELECT COUNT(*) FROM student_subject ss
         WHERE ss.subject_offered_id = so.subject_offered_id AND ss.status = 'enrolled') as student_count,
        (SELECT COUNT(*) FROM lessons l
         WHERE l.subject_id = s.subject_id AND l.user_teacher_id = ?) as lesson_count,
        (SELECT COUNT(*) FROM lessons l
         WHERE l.subject_id = s.subject_id AND l.user_teacher_id = ? AND l.status = 'published') as published_lessons,
        (SELECT COUNT(*) FROM quiz q
         WHERE q.subject_id = s.subject_id AND q.user_teacher_id = ?) as quiz_count,
        (SELECT COUNT(*) FROM quiz q
         WHERE q.subject_id = s.subject_id AND q.user_teacher_id = ? AND q.status = 'published') as published_quizzes
    FROM faculty_subject fs
    JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    LEFT JOIN semester sem ON so.semester_id = sem.semester_id
    $whereClause
    ORDER BY sem.academic_year DESC, s.subject_code",
    array_merge([$userId, $userId, $userId, $userId], $params)
);

// Get available semesters for filter
$semesters = db()->fetchAll(
    "SELECT s.semester_id, s.semester_name, s.academic_year
     FROM semester s
     JOIN sem_type st ON s.sem_type_id = st.sem_type_id
     ORDER BY s.academic_year DESC, st.sem_level"
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>My Classes</h2>
                <p class="text-muted">Manage your assigned subjects and classes</p>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Semester</label>
                    <select name="semester_id" class="form-control" onchange="this.form.submit()">
                        <option value="">All Semesters</option>
                        <?php foreach ($semesters as $sem): ?>
                        <option value="<?= $sem['semester_id'] ?>" <?= $filterSemId == $sem['semester_id'] ? 'selected' : '' ?>>
                            <?= e($sem['semester_name']) ?> (<?= e($sem['academic_year']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <a href="my-classes.php" class="btn btn-outline btn-sm">Reset Filters</a>
                </div>
            </form>
        </div>
        
        <!-- Classes Grid -->
        <div class="classes-grid">
            <?php if (empty($classes)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <h3>No Classes Found</h3>
                    <p>You don't have any classes assigned for the selected filters.</p>
                </div>
            <?php else: ?>
                <?php foreach ($classes as $class): ?>
                <div class="class-card">
                    <div class="class-card-header">
                        <span class="class-code"><?= e($class['subject_code']) ?></span>
                        <h3 class="class-name"><?= e($class['subject_name']) ?></h3>
                        <span class="class-sem"><?= e($class['semester']) ?> Semester • <?= e($class['academic_year']) ?></span>
                    </div>
                    <div class="class-card-body">
                        <p class="class-description"><?= e($class['description'] ?? 'No description available') ?></p>
                        
                        <div class="class-stats">
                            <div class="class-stat">
                                <span class="cs-num"><?= $class['student_count'] ?></span>
                                <span class="cs-label">Students</span>
                            </div>
                            <div class="class-stat">
                                <span class="cs-num"><?= $class['published_lessons'] ?>/<?= $class['lesson_count'] ?></span>
                                <span class="cs-label">Lessons</span>
                            </div>
                            <div class="class-stat">
                                <span class="cs-num"><?= $class['published_quizzes'] ?>/<?= $class['quiz_count'] ?></span>
                                <span class="cs-label">Quizzes</span>
                            </div>
                            <div class="class-stat">
                                <span class="cs-num"><?= $class['units'] ?></span>
                                <span class="cs-label">Units</span>
                            </div>
                        </div>
                        
                        <div class="class-actions">
                            <a href="lessons.php?subject_id=<?= $class['subject_id'] ?>" class="btn btn-success btn-sm">
                                📖 Lessons
                            </a>
                            <a href="quizzes.php?subject_id=<?= $class['subject_id'] ?>" class="btn btn-outline btn-sm">
                                📝 Quizzes
                            </a>
                            <a href="students.php?offered_id=<?= $class['subject_offered_id'] ?>" class="btn btn-outline btn-sm">
                                👥 Students
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    </div>
</main>

<style>
/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.page-header h2 {
    font-size: 24px;
    font-weight: 700;
    color: var(--gray-800);
    margin: 0 0 4px;
}

.page-header .text-muted {
    color: var(--gray-500);
    margin: 0;
}

/* Filters */
.filters-card {
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    padding: 20px;
    margin-bottom: 24px;
}

.filters-form {
    display: flex;
    gap: 16px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-group label {
    font-size: 13px;
    font-weight: 600;
    color: var(--gray-700);
}

.filter-group .form-control {
    min-width: 180px;
}

/* Class Card Enhanced */
.class-card {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    transition: var(--transition);
}

.class-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.class-card-header {
    background: var(--primary-gradient);
    padding: 24px;
    position: relative;
    overflow: hidden;
}

.class-card-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 150px;
    height: 150px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.class-card-header::after {
    content: '';
    position: absolute;
    bottom: -30%;
    right: 10%;
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
}

.class-code {
    display: inline-block;
    background: var(--cream);
    color: var(--primary);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 12px;
    position: relative;
    z-index: 1;
}

.class-name {
    color: var(--white);
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 8px;
    position: relative;
    z-index: 1;
}

.class-sem {
    color: rgba(255, 255, 255, 0.8);
    font-size: 13px;
    position: relative;
    z-index: 1;
}

.class-card-body {
    padding: 24px;
}

.class-description {
    color: var(--gray-600);
    font-size: 14px;
    margin-bottom: 20px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.class-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    padding: 16px 0;
    border-top: 1px solid var(--gray-100);
    border-bottom: 1px solid var(--gray-100);
    margin-bottom: 20px;
}

.class-stat {
    text-align: center;
}

.cs-num {
    display: block;
    font-size: 20px;
    font-weight: 800;
    color: var(--primary);
}

.cs-label {
    font-size: 11px;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.class-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* Responsive */
@media (max-width: 768px) {
    .filters-form {
        flex-direction: column;
    }
    
    .filter-group .form-control {
        min-width: 100%;
    }
    
    .class-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .class-actions {
        flex-direction: column;
    }
    
    .class-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>