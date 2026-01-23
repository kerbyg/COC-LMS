<?php
/**
 * CIT-LMS Instructor - Quizzes Management
 * View, filter, and manage course assessments with performance tracking
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('instructor');

$userId = Auth::id();
$pageTitle = 'Manage Quizzes';
$currentPage = 'quizzes';
$subjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;

// Handle Quiz Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    
    // Cascading deletion order to respect foreign key constraints
    db()->execute("DELETE FROM student_quiz_answers WHERE attempt_id IN (SELECT attempt_id FROM student_quiz_attempts WHERE quiz_id = ?)", [$id]);
    db()->execute("DELETE FROM student_quiz_attempts WHERE quiz_id = ?", [$id]);
    db()->execute("DELETE FROM question_option WHERE question_id IN (SELECT question_id FROM quiz_questions WHERE quiz_id = ?)", [$id]);
    db()->execute("DELETE FROM quiz_questions WHERE quiz_id = ?", [$id]);
    db()->execute("DELETE FROM quiz WHERE quiz_id = ? AND user_teacher_id = ?", [$id, $userId]);
    
    header("Location: quizzes.php" . ($subjectId ? "?subject_id=$subjectId&" : "?") . "deleted=1"); 
    exit;
}

// 1. Fetch instructor's active subjects for the filter dropdown
$mySubjects = db()->fetchAll(
    "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name 
     FROM faculty_subject fs 
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id 
     JOIN subject s ON so.subject_id = s.subject_id 
     WHERE fs.user_teacher_id = ? AND fs.status = 'active' 
     ORDER BY s.subject_code ASC", 
    [$userId]
);

// 2. Build Query for Quizzes with integrated statistics
$where = "WHERE q.user_teacher_id = ?";
$params = [$userId];
if ($subjectId) { 
    $where .= " AND q.subject_id = ?"; 
    $params[] = $subjectId; 
}

$quizzes = db()->fetchAll(
    "SELECT q.*, s.subject_code, 
    (SELECT COUNT(*) FROM quiz_questions qq WHERE qq.quiz_id = q.quiz_id) as q_count, 
    (SELECT COUNT(*) FROM student_quiz_attempts sqa WHERE sqa.quiz_id = q.quiz_id AND sqa.status = 'completed') as attempts, 
    (SELECT AVG(sqa.percentage) FROM student_quiz_attempts sqa WHERE sqa.quiz_id = q.quiz_id AND sqa.status = 'completed') as avg_score 
    FROM quiz q 
    JOIN subject s ON q.subject_id = s.subject_id 
    $where 
    ORDER BY q.created_at DESC", 
    $params
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">
        
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>📝 Assessment Center</h1>
                <p>Create, manage, and monitor quiz performance across your assigned classes.</p>
            </div>
            <div class="welcome-actions">
                <a href="quiz-ai-generate.php<?= $subjectId ? '?subject_id='.$subjectId : '' ?>" class="btn-ai">🤖 AI Generate</a>
                <a href="quiz-edit.php<?= $subjectId ? '?subject_id='.$subjectId : '' ?>" class="btn-primary">+ Create New Quiz</a>
            </div>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Quiz and all associated data have been permanently removed.</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success">Quiz configuration has been successfully saved.</div>
        <?php endif; ?>

        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Filter by Subject</label>
                    <select name="subject_id" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Subjects</option>
                        <?php foreach ($mySubjects as $s): ?>
                            <option value="<?= $s['subject_id'] ?>" <?= $subjectId == $s['subject_id'] ? 'selected' : '' ?>>
                                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($subjectId): ?>
                    <a href="quizzes.php" class="btn-reset">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($quizzes)): ?>
            <div class="empty-box">
                <span>📝</span>
                <h3>No Quizzes Found</h3>
                <p>You haven't created any assessments for this selection yet.</p>
                <a href="quiz-edit.php" class="class-link" style="margin-top: 15px; display: inline-block;">Create your first quiz →</a>
            </div>
        <?php else: ?>
            <div class="quiz-grid">
                <?php foreach ($quizzes as $q): ?>
                <div class="quiz-card">
                    <div class="quiz-card-header">
                        <span class="subject-tag"><?= e($q['subject_code']) ?></span>
                        <span class="status-badge <?= $q['status'] === 'published' ? 'published' : 'draft' ?>">
                            <?= ucfirst($q['status']) ?>
                        </span>
                    </div>
                    
                    <h3 class="quiz-title"><?= e($q['quiz_title']) ?></h3>
                    
                    <div class="quiz-specs">
                        <div class="spec-item"><span>❓</span> <?= $q['q_count'] ?> Items</div>
                        <div class="spec-item"><span>⏱</span> <?= $q['time_limit'] ?>m</div>
                        <div class="spec-item"><span>🎯</span> <?= (int)$q['passing_rate'] ?>%</div>
                    </div>

                    <?php if ($q['due_date']): ?>
                        <div class="due-date-box">
                            📅 Due: <?= date('M d, Y', strtotime($q['due_date'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="quiz-performance">
                        <div class="perf-stat">
                            <span class="perf-num"><?= $q['attempts'] ?></span>
                            <span class="perf-label">Attempts</span>
                        </div>
                        <div class="perf-stat">
                            <span class="perf-num"><?= $q['avg_score'] ? round($q['avg_score']).'%' : '—' ?></span>
                            <span class="perf-label">Avg. Score</span>
                        </div>
                    </div>

                    <div class="quiz-card-actions">
                        <a href="quiz-questions.php?quiz_id=<?= $q['quiz_id'] ?>" class="btn-action">Questions</a>
                        <a href="quiz-edit.php?id=<?= $q['quiz_id'] ?>" class="btn-action">Edit</a>
                        <form method="POST" class="inline-form" onsubmit="return confirm('Permanently delete this quiz and all student attempts?')">
                            <input type="hidden" name="delete_id" value="<?= $q['quiz_id'] ?>">
                            <button class="btn-delete-icon" title="Delete Quiz">🗑</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
/* Dashboard Aesthetic Framework */
.welcome-section {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 24px; padding: 24px;
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border-radius: 16px; color: #fff;
}
.welcome-text h1 { font-size: 24px; margin: 0 0 4px; }
.welcome-text p { margin: 0; opacity: 0.9; }

.btn-primary {
    padding: 10px 20px; background: #fff; color: #16a34a;
    border-radius: 8px; font-weight: 600; text-decoration: none;
    transition: all 0.2s;
}
.btn-primary:hover { background: #f0fdf4; transform: translateY(-1px); }
.btn-ai {
    padding: 10px 20px; background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: #fff;
    border-radius: 8px; font-weight: 600; text-decoration: none;
    transition: all 0.2s;
}
.btn-ai:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3); }

/* Filters */
.filters-card {
    background: #fff; border: 1px solid #f5f0e8;
    border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;
}
.filters-form { display: flex; align-items: flex-end; gap: 15px; }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 12px; font-weight: 700; color: #78716c; text-transform: uppercase; }
.filter-select {
    padding: 8px 12px; border: 1px solid #e7e5e4;
    border-radius: 8px; min-width: 250px; outline: none;
}
.btn-reset { font-size: 14px; color: #78716c; text-decoration: none; margin-bottom: 10px; }

/* Quiz Grid & Modern Cards */
.quiz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
.quiz-card { 
    background: #fff; border: 1px solid #f5f0e8; border-radius: 16px; 
    padding: 24px; transition: all 0.2s; position: relative;
}
.quiz-card:hover { border-color: #16a34a; box-shadow: 0 10px 25px rgba(22, 163, 74, 0.08); }

.quiz-card-header { display: flex; justify-content: space-between; margin-bottom: 16px; }
.subject-tag { background: #f0fdf4; color: #16a34a; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.status-badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.status-badge.published { background: #dcfce7; color: #15803d; }
.status-badge.draft { background: #fee2e2; color: #b91c1c; }

.quiz-title { font-size: 18px; color: #1c1917; margin: 0 0 12px; line-height: 1.4; font-weight: 700; }

.quiz-specs { display: flex; gap: 15px; margin-bottom: 16px; }
.spec-item { font-size: 13px; color: #57534e; font-weight: 500; display: flex; align-items: center; gap: 4px; }

.due-date-box { 
    background: #fffbeb; color: #92400e; padding: 10px; 
    border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 16px;
    border: 1px solid #fef3c7;
}

.quiz-performance { 
    display: flex; gap: 20px; padding: 16px 0; 
    border-top: 1px solid #f5f0e8; border-bottom: 1px solid #f5f0e8; 
    margin-bottom: 20px;
}
.perf-stat { flex: 1; text-align: center; }
.perf-num { display: block; font-size: 22px; font-weight: 700; color: #16a34a; }
.perf-label { font-size: 11px; color: #78716c; text-transform: uppercase; letter-spacing: 0.5px; }

.quiz-card-actions { display: flex; gap: 8px; align-items: center; }
.btn-action { 
    flex: 1; text-align: center; padding: 8px; border: 1px solid #e7e5e4; 
    border-radius: 8px; font-size: 13px; font-weight: 600; 
    color: #44403c; text-decoration: none; transition: background 0.2s;
}
.btn-action:hover { background: #fafaf9; border-color: #d6d3d1; }
.btn-delete-icon { 
    background: #fee2e2; color: #dc2626; border: none; 
    width: 35px; height: 35px; border-radius: 8px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.2s;
}
.btn-delete-icon:hover { background: #fecaca; }

/* Utilities */ 
.alert { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
.empty-box { text-align: center; padding: 60px; background: #fff; border: 1px solid #f5f0e8; border-radius: 12px; grid-column: 1 / -1; }
.empty-box span { font-size: 40px; display: block; margin-bottom: 10px; opacity: 0.5; }
.inline-form { display: inline; }
.class-link { color: #16a34a; text-decoration: none; font-weight: 500; font-size: 14px; }
.class-link:hover { text-decoration: underline; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>