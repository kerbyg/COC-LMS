<?php
/**
 * Instructor - Students
 * View enrolled students in assigned classes
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('instructor');

$userId = Auth::id();
$pageTitle = 'Students';
$currentPage = 'students';

$successMessage = '';
$errorMessage = '';

// Handle student removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_student') {
    $studentSubjectId = $_POST['student_subject_id'] ?? 0;

    try {
        // Verify this student belongs to instructor's section
        $verify = db()->fetchOne(
            "SELECT ss.student_subject_id
             FROM student_subject ss
             JOIN section sec ON ss.section_id = sec.section_id
             JOIN faculty_subject fs ON sec.section_id = fs.section_id
             WHERE ss.student_subject_id = ? AND fs.user_teacher_id = ? AND fs.status = 'active'",
            [$studentSubjectId, $userId]
        );

        if ($verify) {
            db()->execute(
                "UPDATE student_subject SET status = 'dropped', updated_at = NOW() WHERE student_subject_id = ?",
                [$studentSubjectId]
            );
            $successMessage = "Student removed from section successfully.";
        } else {
            $errorMessage = "You don't have permission to remove this student.";
        }
    } catch (Exception $e) {
        $errorMessage = "Failed to remove student.";
        error_log("Remove student error: " . $e->getMessage());
    }
}

// Get filter parameters
$offeredId = $_GET['offered_id'] ?? '';
$search = $_GET['search'] ?? '';

// Get instructor's subjects for filter dropdown
$mySubjects = db()->fetchAll(
    "SELECT 
        so.subject_offered_id,
        s.subject_code,
        s.subject_name,
        so.academic_year,
        so.semester
    FROM faculty_subject fs
    JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    WHERE fs.user_teacher_id = ? AND fs.status = 'active'
    ORDER BY so.academic_year DESC, s.subject_code",
    [$userId]
);

// Build students query
$whereClause = "WHERE fs.user_teacher_id = ? AND ss.status = 'enrolled'";
$params = [$userId];

if ($offeredId) {
    $whereClause .= " AND ss.subject_offered_id = ?";
    $params[] = $offeredId;
}

if ($search) {
    $whereClause .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.student_id LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

// Get students (updated to include section info)
$students = db()->fetchAll(
    "SELECT
        u.users_id,
        u.student_id,
        u.first_name,
        u.last_name,
        u.email,
        u.profile_image,
        s.subject_id,
        s.subject_code,
        s.subject_name,
        so.subject_offered_id,
        sec.section_id,
        sec.section_name,
        sec.schedule,
        sec.room,
        sec.enrollment_code,
        ss.student_subject_id,
        ss.enrollment_date,
        ss.final_grade,
        (SELECT COUNT(*) FROM student_progress sp
         WHERE sp.user_student_id = u.users_id AND sp.subject_id = s.subject_id AND sp.status = 'completed') as lessons_completed,
        (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.subject_id AND l.status = 'published') as total_lessons,
        (SELECT COUNT(*) FROM student_quiz_attempts sqa
         JOIN quiz q ON sqa.quiz_id = q.quiz_id
         WHERE sqa.user_student_id = u.users_id AND q.subject_id = s.subject_id AND sqa.status = 'completed') as quizzes_taken,
        (SELECT AVG(sqa.percentage) FROM student_quiz_attempts sqa
         JOIN quiz q ON sqa.quiz_id = q.quiz_id
         WHERE sqa.user_student_id = u.users_id AND q.subject_id = s.subject_id AND sqa.status = 'completed') as avg_quiz_score
    FROM student_subject ss
    JOIN users u ON ss.user_student_id = u.users_id
    JOIN section sec ON ss.section_id = sec.section_id
    JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    JOIN faculty_subject fs ON sec.section_id = fs.section_id
    $whereClause
    ORDER BY s.subject_code, sec.section_name, u.last_name, u.first_name",
    $params
);

// Group students by subject if no filter
$studentsBySubject = [];
foreach ($students as $student) {
    $key = $student['subject_offered_id'];
    if (!isset($studentsBySubject[$key])) {
        $studentsBySubject[$key] = [
            'subject_code' => $student['subject_code'],
            'subject_name' => $student['subject_name'],
            'students' => []
        ];
    }
    $studentsBySubject[$key]['students'][] = $student;
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">

        <?php if ($successMessage): ?>
        <div class="alert alert-success">
            ✓ <?= htmlspecialchars($successMessage) ?>
        </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            ✗ <?= htmlspecialchars($errorMessage) ?>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>My Students</h2>
                <p class="text-muted">View and manage students enrolled in your classes</p>
            </div>
            <div class="header-stats">
                <div class="header-stat">
                    <span class="stat-value"><?= count($students) ?></span>
                    <span class="stat-label">Total Students</span>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group flex-grow">
                    <label>Search Student</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by name, email, or student ID..."
                           value="<?= e($search) ?>">
                </div>
                <div class="filter-group">
                    <label>Subject</label>
                    <select name="offered_id" class="form-control" onchange="this.form.submit()">
                        <option value="">All Subjects</option>
                        <?php foreach ($mySubjects as $subj): ?>
                        <option value="<?= $subj['subject_offered_id'] ?>" <?= $offeredId == $subj['subject_offered_id'] ? 'selected' : '' ?>>
                            <?= e($subj['subject_code']) ?> - <?= e($subj['subject_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-success">🔍 Search</button>
                </div>
                <?php if ($search || $offeredId): ?>
                <div class="filter-group">
                    <a href="students.php" class="btn btn-outline">Clear</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Students List -->
        <?php if (empty($students)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <h3>No Students Found</h3>
                <p>No students match your search criteria.</p>
            </div>
        <?php elseif ($offeredId): ?>
            <!-- Single Subject View -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        📚 <?= e($students[0]['subject_code']) ?> - <?= e($students[0]['subject_name']) ?>
                        <span class="badge badge-primary"><?= count($students) ?> students</span>
                    </h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Section</th>
                                <th>Enrolled</th>
                                <th>Progress</th>
                                <th>Quizzes</th>
                                <th>Avg Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student):
                                $initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));
                                $progress = $student['total_lessons'] > 0
                                    ? round(($student['lessons_completed'] / $student['total_lessons']) * 100)
                                    : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar"><?= $initials ?></div>
                                        <div>
                                            <div class="student-name"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                            <div class="student-email"><?= e($student['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($student['student_id']) ?></td>
                                <td>
                                    <div class="section-info-small">
                                        <strong>Section <?= e($student['section_name']) ?></strong>
                                        <small><?= e($student['schedule'] ?: 'TBA') ?></small>
                                    </div>
                                </td>
                                <td>
                                    <small><?= date('M j, Y', strtotime($student['enrollment_date'])) ?></small>
                                </td>
                                <td>
                                    <div class="progress-mini">
                                        <div class="progress-bar-mini">
                                            <div class="progress-fill-mini" style="width: <?= $progress ?>%"></div>
                                        </div>
                                        <span><?= $student['lessons_completed'] ?>/<?= $student['total_lessons'] ?></span>
                                    </div>
                                </td>
                                <td><?= $student['quizzes_taken'] ?> taken</td>
                                <td>
                                    <?php if ($student['avg_quiz_score']): ?>
                                        <span class="score-badge <?= $student['avg_quiz_score'] >= 70 ? 'passed' : 'failed' ?>">
                                            <?= round($student['avg_quiz_score']) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this student from the section?');">
                                        <input type="hidden" name="action" value="remove_student">
                                        <input type="hidden" name="student_subject_id" value="<?= $student['student_subject_id'] ?>">
                                        <button type="submit" class="btn-remove" title="Remove student">
                                            🗑️
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <!-- Grouped by Subject -->
            <?php foreach ($studentsBySubject as $subjectData): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        📚 <?= e($subjectData['subject_code']) ?> - <?= e($subjectData['subject_name']) ?>
                        <span class="badge badge-primary"><?= count($subjectData['students']) ?> students</span>
                    </h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Section</th>
                                <th>Progress</th>
                                <th>Avg Score</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjectData['students'] as $student):
                                $initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));
                                $progress = $student['total_lessons'] > 0
                                    ? round(($student['lessons_completed'] / $student['total_lessons']) * 100)
                                    : 0;
                            ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar"><?= $initials ?></div>
                                        <div>
                                            <div class="student-name"><?= e($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                            <div class="student-email"><?= e($student['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($student['student_id']) ?></td>
                                <td>
                                    <div class="section-info-small">
                                        <strong>Section <?= e($student['section_name']) ?></strong>
                                        <small><?= e($student['schedule'] ?: 'TBA') ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="progress-mini">
                                        <div class="progress-bar-mini">
                                            <div class="progress-fill-mini" style="width: <?= $progress ?>%"></div>
                                        </div>
                                        <span><?= $progress ?>%</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($student['avg_quiz_score']): ?>
                                        <span class="score-badge <?= $student['avg_quiz_score'] >= 70 ? 'passed' : 'failed' ?>">
                                            <?= round($student['avg_quiz_score']) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this student from the section?');">
                                        <input type="hidden" name="action" value="remove_student">
                                        <input type="hidden" name="student_subject_id" value="<?= $student['student_subject_id'] ?>">
                                        <button type="submit" class="btn-remove" title="Remove student">
                                            🗑️
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
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

.text-muted {
    color: var(--gray-500);
    margin: 0;
}

/* Header Stats */
.header-stats {
    display: flex;
    gap: 24px;
}

.header-stat {
    text-align: center;
    padding: 12px 24px;
    background: var(--cream-light);
    border-radius: var(--border-radius);
}

.stat-value {
    display: block;
    font-size: 28px;
    font-weight: 800;
    color: var(--primary);
}

.stat-label {
    font-size: 12px;
    color: var(--gray-500);
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

.flex-grow {
    flex: 1;
    min-width: 250px;
}

/* Student Info in Table */
.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.student-avatar {
    width: 40px;
    height: 40px;
    background: var(--primary);
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}

.student-name {
    font-weight: 600;
    color: var(--gray-800);
}

.student-email {
    font-size: 12px;
    color: var(--gray-500);
}

/* Mini Progress Bar */
.progress-mini {
    display: flex;
    align-items: center;
    gap: 10px;
}

.progress-bar-mini {
    width: 80px;
    height: 8px;
    background: var(--gray-200);
    border-radius: 8px;
    overflow: hidden;
}

.progress-fill-mini {
    height: 100%;
    background: var(--primary-gradient);
    border-radius: 8px;
}

.progress-mini span {
    font-size: 13px;
    font-weight: 600;
    color: var(--gray-600);
}

/* Card margin */
.mb-3 {
    margin-bottom: 24px;
}

/* Score badge */
.score-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.score-badge.passed {
    background: var(--success-bg);
    color: var(--success);
}

.score-badge.failed {
    background: var(--danger-bg);
    color: var(--danger);
}

/* Section Info Small */
.section-info-small {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.section-info-small strong {
    color: var(--gray-800);
    font-size: 14px;
}

.section-info-small small {
    color: var(--gray-500);
    font-size: 12px;
}

/* Remove Button */
.btn-remove {
    background: transparent;
    border: 1px solid var(--danger);
    color: var(--danger);
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.2s;
}

.btn-remove:hover {
    background: var(--danger);
    color: white;
    transform: scale(1.1);
}

/* Alert Messages */
.alert {
    padding: 16px 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
    font-weight: 600;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border-left: 4px solid #10b981;
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border-left: 4px solid #ef4444;
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>