<?php
/**
 * CIT-LMS Instructor - Lessons Management
 * Fixed: Updated database calls to use execute() for deletion
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('instructor');

$userId = Auth::id();
$pageTitle = 'Manage Lessons';
$currentPage = 'lessons';
$subjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;

// Handle Lesson Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    
    // FIXED: Changed query() to execute() to match your Database class
    // We delete progress first to respect foreign key constraints
    db()->execute("DELETE FROM student_progress WHERE lesson_id = ?", [$id]);
    db()->execute("DELETE FROM lessons WHERE lesson_id = ? AND user_teacher_id = ?", [$id, $userId]);
    
    // Redirect with the current filter preserved
    $redirectUrl = "lessons.php?deleted=1" . ($subjectId ? "&subject_id=$subjectId" : "");
    header("Location: $redirectUrl"); 
    exit;
}

// 1. Fetch subjects for the filter dropdown (Using fetchAll from your config)
$mySubjects = db()->fetchAll(
    "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name 
     FROM faculty_subject fs 
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id 
     JOIN subject s ON so.subject_id = s.subject_id 
     WHERE fs.user_teacher_id = ? AND fs.status = 'active' 
     ORDER BY s.subject_code ASC", 
    [$userId]
);

// 2. Build the Lessons Query safely (includes topic count)
$sql = "SELECT l.*, s.subject_code, s.subject_name,
        (SELECT COUNT(*) FROM student_progress sp WHERE sp.lesson_id = l.lesson_id AND sp.status = 'completed') as completions,
        (SELECT COUNT(*) FROM topic t WHERE t.lesson_id = l.lesson_id) as topic_count
        FROM lessons l
        JOIN subject s ON l.subject_id = s.subject_id
        WHERE l.user_teacher_id = ?";

$params = [$userId];

if ($subjectId) { 
    $sql .= " AND l.subject_id = ?"; 
    $params[] = $subjectId; 
}

$sql .= " ORDER BY s.subject_code ASC, l.lesson_order ASC";
$lessons = db()->fetchAll($sql, $params);

// 3. Grouping Logic
$grouped = [];
foreach ($lessons as $l) {
    $sid = $l['subject_id'];
    if (!isset($grouped[$sid])) {
        $grouped[$sid] = [
            'code' => $l['subject_code'],
            'name' => $l['subject_name'],
            'lessons' => []
        ];
    }
    $grouped[$sid]['lessons'][] = $l;
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">
        
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>📖 Course Lessons</h1>
                <p>Organize and manage your instructional materials.</p>
            </div>
            <div class="welcome-actions">
                <a href="lesson-edit.php<?= $subjectId ? '?subject_id='.$subjectId : '' ?>" class="btn-primary">+ Create New Lesson</a>
            </div>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Lesson has been successfully removed.</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success">Lesson changes have been saved.</div>
        <?php endif; ?>

        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Filter by Subject</label>
                    <select name="subject_id" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Assigned Subjects</option>
                        <?php foreach ($mySubjects as $s): ?>
                            <option value="<?= $s['subject_id'] ?>" <?= $subjectId == $s['subject_id'] ? 'selected' : '' ?>>
                                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($subjectId): ?>
                    <a href="lessons.php" class="btn-reset">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($lessons)): ?>
            <div class="empty-box">
                <span>📖</span>
                <h3>No Lessons Found</h3>
                <p>You haven't created any lessons for this criteria yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped as $sid => $data): ?>
                <div class="panel mb-4">
                    <div class="panel-head flex-between">
                        <h3>📚 <?= e($data['code']) ?> - <?= e($data['name']) ?></h3>
                        <span class="badge-count"><?= count($data['lessons']) ?> Lessons</span>
                    </div>
                    <div class="lesson-container">
                        <?php foreach ($data['lessons'] as $l): ?>
                            <div class="lesson-row">
                                <div class="lesson-order"><?= $l['lesson_order'] ?></div>
                                <div class="lesson-main">
                                    <h4><?= e($l['lesson_title']) ?></h4>
                                    <div class="lesson-tags">
                                        <span class="tag-status <?= $l['status'] === 'published' ? 'status-published' : 'status-draft' ?>">
                                            <?= ucfirst($l['status']) ?>
                                        </span>
                                        <span class="tag-topics"><?= $l['topic_count'] ?> topics</span>
                                        <span class="tag-meta">👁 <?= $l['completions'] ?> completions</span>
                                        <span class="tag-meta">📅 Updated <?= date('M d, Y', strtotime($l['updated_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="lesson-btns">
                                    <a href="lesson-edit.php?id=<?= $l['lesson_id'] ?>" class="btn-edit">Edit</a>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Delete this lesson?')">
                                        <input type="hidden" name="delete_id" value="<?= $l['lesson_id'] ?>">
                                        <button class="btn-delete">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<style>
/* Dashboard Theme Styling */
.welcome-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding: 24px; background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); border-radius: 16px; color: #fff; }
.welcome-text h1 { font-size: 24px; margin: 0 0 4px; }
.welcome-text p { margin: 0; opacity: 0.9; }
.btn-primary { padding: 10px 20px; background: #fff; color: #16a34a; border-radius: 8px; font-weight: 600; text-decoration: none; }
.filters-card { background: #fff; border: 1px solid #f5f0e8; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; }
.filters-form { display: flex; align-items: flex-end; gap: 15px; }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 12px; font-weight: 700; color: #78716c; text-transform: uppercase; }
.filter-select { padding: 8px 12px; border: 1px solid #e7e5e4; border-radius: 8px; min-width: 250px; outline: none; }
.btn-reset { font-size: 14px; color: #78716c; text-decoration: none; margin-bottom: 10px; }
.panel { background: #fff; border: 1px solid #f5f0e8; border-radius: 12px; overflow: hidden; }
.panel-head { padding: 16px 24px; background: #fdfbf7; border-bottom: 1px solid #f5f0e8; }
.flex-between { display: flex; justify-content: space-between; align-items: center; }
.badge-count { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.lesson-row { display: flex; align-items: center; padding: 16px 24px; border-bottom: 1px solid #f5f0e8; transition: 0.2s; }
.lesson-row:hover { background: #fafaf9; }
.lesson-order { width: 36px; height: 36px; background: #f5f0e8; color: #57534e; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; margin-right: 20px; }
.lesson-main { flex: 1; }
.lesson-main h4 { margin: 0 0 6px; font-size: 16px; color: #1c1917; }
.lesson-tags { display: flex; gap: 12px; align-items: center; }
.tag-status { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.status-published { background: #dcfce7; color: #15803d; }
.status-draft { background: #fee2e2; color: #b91c1c; }
.tag-meta { font-size: 12px; color: #a8a29e; }
.tag-topics { background: #dbeafe; color: #1d4ed8; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
.lesson-btns { display: flex; gap: 8px; }
.btn-edit { padding: 6px 14px; border: 1px solid #e7e5e4; border-radius: 6px; color: #44403c; font-size: 13px; text-decoration: none; }
.btn-delete { padding: 6px 14px; background: #fff; border: 1px solid #fee2e2; border-radius: 6px; color: #dc2626; font-size: 13px; cursor: pointer; }
.alert { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
.mb-4 { margin-bottom: 24px; }
.empty-box { text-align: center; padding: 60px; background: #fff; border: 1px solid #f5f0e8; border-radius: 12px; }
.inline-form { display: inline; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>