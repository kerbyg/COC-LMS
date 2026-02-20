<?php
/**
 * Instructor - Essay & Subjective Answer Grading
 * Grade pending essay/short answer responses from students
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('instructor');

$userId = Auth::id();
$pageTitle = 'Essay Grading';
$currentPage = 'essay-grading';

// Filter: subject_offered_id
$offeredId = $_GET['offered_id'] ?? '';

// Get instructor's subjects
$mySubjects = db()->fetchAll(
    "SELECT so.subject_offered_id, s.subject_code, s.subject_name
     FROM faculty_subject fs
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE fs.user_teacher_id = ? AND fs.status = 'active'
     ORDER BY s.subject_code",
    [$userId]
) ?: [];

// Build WHERE for subject filter
$subjectWhere = '';
$subjectParams = [$userId];
if ($offeredId) {
    $subjectWhere = ' AND fs_filter.subject_offered_id = ?';
    $subjectParams[] = $offeredId;
}

// Get all pending-grading attempts in instructor's subjects
$pendingAttempts = db()->fetchAll(
    "SELECT sqa.attempt_id, sqa.quiz_id, sqa.earned_points, sqa.total_points,
            sqa.percentage, sqa.completed_at,
            q.quiz_title, s.subject_code, s.subject_name,
            u.first_name, u.last_name, u.student_id as student_number,
            (SELECT COUNT(*) FROM student_quiz_answers a
             WHERE a.attempt_id = sqa.attempt_id AND a.grading_status = 'pending') as pending_count,
            (SELECT COUNT(*) FROM student_quiz_answers a
             WHERE a.attempt_id = sqa.attempt_id AND a.grading_status = 'graded') as graded_count
     FROM student_quiz_attempts sqa
     JOIN quiz q ON sqa.quiz_id = q.quiz_id
     JOIN subject s ON q.subject_id = s.subject_id
     JOIN faculty_subject fs ON fs.user_teacher_id = ? AND fs.status = 'active'
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id AND so.subject_id = s.subject_id
     LEFT JOIN faculty_subject fs_filter ON fs_filter.subject_offered_id = so.subject_offered_id
     JOIN users u ON sqa.user_student_id = u.users_id
     WHERE sqa.has_pending_grades = 1" . $subjectWhere . "
     GROUP BY sqa.attempt_id
     ORDER BY sqa.completed_at DESC",
    $subjectParams
) ?: [];

$totalPending = count($pendingAttempts);
$totalAnswers = array_sum(array_column($pendingAttempts, 'pending_count'));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>Essay Grading</h2>
                <p class="text-muted">Review and grade student essay and short answer responses</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon-box pending">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div>
                    <span class="stat-number"><?= $totalPending ?></span>
                    <span class="stat-text">Submissions Pending</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box total">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                </div>
                <div>
                    <span class="stat-number"><?= $totalAnswers ?></span>
                    <span class="stat-text">Answers to Grade</span>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Filter by Subject</label>
                    <select name="offered_id" class="form-control" onchange="this.form.submit()">
                        <option value="">All Subjects</option>
                        <?php foreach ($mySubjects as $subj): ?>
                        <option value="<?= $subj['subject_offered_id'] ?>" <?= $offeredId == $subj['subject_offered_id'] ? 'selected' : '' ?>>
                            <?= e($subj['subject_code']) ?> — <?= e($subj['subject_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($offeredId): ?>
                <div class="filter-group">
                    <a href="essay-grading.php" class="btn btn-outline">Clear</a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Submissions List -->
        <?php if (empty($pendingAttempts)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <h3>All Caught Up!</h3>
            <p>No essay or short answer responses are waiting to be graded.</p>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Pending Submissions <span class="badge badge-warning"><?= $totalPending ?></span></h3>
            </div>
            <div class="submissions-list">
                <?php foreach ($pendingAttempts as $att):
                    $initials = strtoupper(substr($att['first_name'], 0, 1) . substr($att['last_name'], 0, 1));
                    $totalQs = $att['pending_count'] + $att['graded_count'];
                    $progress = $totalQs > 0 ? round(($att['graded_count'] / $totalQs) * 100) : 0;
                ?>
                <div class="submission-row" onclick="openGradingPanel(<?= $att['attempt_id'] ?>)">
                    <div class="student-info">
                        <div class="student-avatar"><?= $initials ?></div>
                        <div>
                            <div class="student-name"><?= e($att['first_name'] . ' ' . $att['last_name']) ?></div>
                            <div class="student-meta"><?= e($att['student_number'] ?? '') ?></div>
                        </div>
                    </div>
                    <div class="quiz-info">
                        <div class="quiz-title"><?= e($att['quiz_title']) ?></div>
                        <div class="subject-badge-sm"><?= e($att['subject_code']) ?></div>
                    </div>
                    <div class="progress-info">
                        <div class="progress-label">
                            <span><?= $att['graded_count'] ?>/<?= $totalQs ?> graded</span>
                            <span class="pending-badge"><?= $att['pending_count'] ?> pending</span>
                        </div>
                        <div class="progress-bar-wrap">
                            <div class="progress-bar-fill" style="width:<?= $progress ?>%"></div>
                        </div>
                    </div>
                    <div class="submission-date">
                        <?= date('M j, Y', strtotime($att['completed_at'])) ?><br>
                        <small><?= date('g:ia', strtotime($att['completed_at'])) ?></small>
                    </div>
                    <div class="submission-action">
                        <button class="btn btn-success btn-sm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Grade
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Grading Panel (Slide-in) -->
<div class="grading-overlay" id="gradingOverlay" onclick="closeGradingPanel(event)">
    <div class="grading-panel" id="gradingPanel">
        <div class="grading-panel-header">
            <div>
                <h3 id="panelTitle">Grade Submission</h3>
                <p id="panelSubtitle" class="text-muted" style="margin:4px 0 0;font-size:13px;"></p>
            </div>
            <button class="modal-close" onclick="closePanel()">&times;</button>
        </div>
        <div class="grading-panel-body" id="panelBody">
            <div class="panel-loading">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#1B4D3E" stroke-width="2" class="spin"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                <p>Loading submission...</p>
            </div>
        </div>
        <div class="grading-panel-footer" id="panelFooter" style="display:none;">
            <button class="btn btn-outline" onclick="closePanel()">Close</button>
            <button class="btn btn-success" id="finalizeBtn" onclick="finalizeGrading()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Finalize &amp; Save
            </button>
        </div>
    </div>
</div>

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
.text-muted { color: var(--gray-500); margin: 0; }

/* Stats */
.stats-row {
    display: flex;
    gap: 14px;
    margin-bottom: 24px;
}
.stat-card {
    flex: 1;
    max-width: 240px;
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.stat-icon-box {
    width: 42px; height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.stat-icon-box.pending { background: #FEF3C7; color: #B45309; }
.stat-icon-box.total   { background: #E8F5E9; color: #1B4D3E; }
.stat-number { display: block; font-size: 22px; font-weight: 700; color: #333; }
.stat-text   { display: block; font-size: 12px; color: #666; font-weight: 500; }

/* Filters */
.filters-card {
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}
.filters-form { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 13px; font-weight: 600; color: var(--gray-700); }

/* Submissions List */
.submissions-list { padding: 0; }
.submission-row {
    display: grid;
    grid-template-columns: 200px 1fr 180px 100px 90px;
    align-items: center;
    gap: 20px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--gray-100);
    cursor: pointer;
    transition: background 0.15s;
}
.submission-row:last-child { border-bottom: none; }
.submission-row:hover { background: #f8fffe; }

/* Student Info */
.student-info { display: flex; align-items: center; gap: 12px; }
.student-avatar {
    width: 40px; height: 40px;
    background: var(--primary);
    color: #fff;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 14px; flex-shrink: 0;
}
.student-name { font-weight: 600; color: var(--gray-800); font-size: 14px; }
.student-meta { font-size: 12px; color: var(--gray-500); }

/* Quiz Info */
.quiz-info {}
.quiz-title { font-size: 13px; font-weight: 600; color: #333; margin-bottom: 4px; }
.subject-badge-sm {
    display: inline-block;
    background: #1B4D3E; color: #fff;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 11px; font-weight: 600;
}

/* Progress */
.progress-info {}
.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: #666;
    margin-bottom: 6px;
}
.pending-badge {
    background: #FEF3C7;
    color: #B45309;
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}
.progress-bar-wrap {
    height: 6px;
    background: #e8e8e8;
    border-radius: 3px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #1B4D3E, #2D6A4F);
    border-radius: 3px;
    transition: width 0.3s;
}

/* Date */
.submission-date { font-size: 13px; color: #555; }
.submission-date small { color: #999; }

/* Action btn */
.btn-sm { padding: 7px 14px; font-size: 12px; }

/* Badges */
.badge-warning { background: #FEF3C7; color: #B45309; }
.card-title .badge {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 2px 9px; border-radius: 12px;
    font-size: 12px; font-weight: 700;
    margin-left: 8px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 24px;
    background: #fafafa;
    border: 1px dashed #ddd;
    border-radius: 12px;
}
.empty-state-icon { color: #1B4D3E; margin-bottom: 16px; }
.empty-state h3 { font-size: 18px; font-weight: 600; color: #333; margin: 0 0 8px; }
.empty-state p { font-size: 14px; color: #666; margin: 0; }

/* Grading Overlay / Panel */
.grading-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 1000;
    display: none;
    justify-content: flex-end;
}
.grading-overlay.active { display: flex; }
.grading-panel {
    background: #fff;
    width: 680px;
    max-width: 100vw;
    height: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: -4px 0 32px rgba(0,0,0,0.15);
    animation: panelSlideIn 0.25s ease-out;
}
@keyframes panelSlideIn {
    from { transform: translateX(40px); opacity: 0; }
    to   { transform: translateX(0);    opacity: 1; }
}
.grading-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 20px 24px;
    border-bottom: 1px solid #e8e8e8;
}
.grading-panel-header h3 { margin: 0; font-size: 17px; font-weight: 700; color: #222; }
.modal-close {
    background: none; border: none;
    font-size: 24px; color: #999;
    cursor: pointer; padding: 0; line-height: 1;
}
.modal-close:hover { color: #333; }
.grading-panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
}
.grading-panel-footer {
    padding: 16px 24px;
    border-top: 1px solid #e8e8e8;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Loading */
.panel-loading {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    height: 200px; color: #888; gap: 12px;
}
.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* Answer blocks */
.attempt-meta {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 20px;
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    font-size: 13px;
    color: #555;
}
.attempt-meta strong { color: #222; display: block; font-size: 14px; margin-bottom: 2px; }

.question-block {
    border: 1px solid #e8e8e8;
    border-radius: 10px;
    margin-bottom: 16px;
    overflow: hidden;
}
.question-header {
    padding: 12px 16px;
    background: #f8f9fa;
    border-bottom: 1px solid #e8e8e8;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.question-num {
    font-size: 12px;
    font-weight: 700;
    color: #1B4D3E;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.question-type-badge {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
    background: #E3F2FD;
    color: #1565C0;
}
.question-type-badge.essay { background: #EDE9FE; color: #7C3AED; }
.question-type-badge.short_answer { background: #FEF3C7; color: #B45309; }

.question-body { padding: 16px; }
.question-text {
    font-size: 14px;
    color: #222;
    font-weight: 600;
    margin-bottom: 12px;
    line-height: 1.5;
}
.student-answer {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    color: #333;
    line-height: 1.6;
    margin-bottom: 14px;
    white-space: pre-wrap;
}
.student-answer.empty { background: #fef9f0; border-color: #fde68a; color: #92400e; font-style: italic; }

.auto-graded-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 10px;
    background: #E8F5E9; color: #1B4D3E;
    border-radius: 6px; font-size: 12px; font-weight: 600;
}

/* Grade inputs */
.grade-inputs {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.grade-inputs .form-group { flex: 0 0 auto; }
.grade-inputs .form-group.grow { flex: 1; }
.field-label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 5px; }
.points-input {
    width: 80px;
    padding: 8px 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    color: #222;
}
.points-input:focus { outline: none; border-color: #1B4D3E; box-shadow: 0 0 0 2px rgba(27,77,62,0.15); }
.points-max { font-size: 12px; color: #888; margin-top: 4px; text-align: center; }
.feedback-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 13px;
    color: #333;
    resize: vertical;
    min-height: 60px;
    font-family: inherit;
}
.feedback-input:focus { outline: none; border-color: #1B4D3E; box-shadow: 0 0 0 2px rgba(27,77,62,0.15); }

.save-answer-btn {
    margin-top: 10px;
    padding: 7px 14px;
    background: #1B4D3E;
    color: #fff;
    border: none;
    border-radius: 7px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    display: flex; align-items: center; gap: 6px;
}
.save-answer-btn:hover { background: #2D6A4F; }
.save-answer-btn.saved { background: #10b981; }
.save-answer-btn:disabled { opacity: 0.6; cursor: not-allowed; }

.graded-label {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12px; color: #1B4D3E; font-weight: 600;
}

/* MC / auto graded question */
.mc-answer-row {
    display: flex; gap: 10px; align-items: center;
    font-size: 13px; color: #555; margin-bottom: 8px;
}
.mc-answer-row .correct-icon { color: #1B4D3E; }
.mc-answer-row .wrong-icon   { color: #b91c1c; }
</style>

<script>var BASE_URL = '<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/app/js/pages/instructor/essay-grading.js"></script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
