<?php
/**
 * Student Remedials Page
 * Shows all remedial assignments for the student with status, quiz info, and actions
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();

// Fetch all remedial assignments for this student
$remedials = db()->fetchAll(
    "SELECT
        ra.remedial_id,
        ra.quiz_id,
        ra.attempt_id,
        ra.reason,
        ra.due_date,
        ra.status,
        ra.new_score,
        ra.completed_at,
        ra.remarks,
        ra.created_at as assigned_at,
        q.quiz_title,
        q.quiz_type,
        q.passing_rate,
        q.max_attempts,
        q.time_limit,
        q.subject_id,
        s.subject_code,
        s.subject_name,
        CONCAT(u.first_name, ' ', u.last_name) as assigned_by_name,
        (SELECT COUNT(*) FROM student_quiz_attempts WHERE quiz_id = ra.quiz_id AND user_student_id = ?) as attempts_used,
        (SELECT MAX(percentage) FROM student_quiz_attempts WHERE quiz_id = ra.quiz_id AND user_student_id = ? AND status = 'completed') as best_score
    FROM remedial_assignment ra
    JOIN quiz q ON ra.quiz_id = q.quiz_id
    JOIN subject s ON q.subject_id = s.subject_id
    LEFT JOIN users u ON ra.assigned_by = u.users_id
    WHERE ra.user_student_id = ?
    ORDER BY
        CASE ra.status
            WHEN 'pending' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'completed' THEN 3
        END,
        ra.due_date ASC",
    [$userId, $userId, $userId]
) ?: [];

// Stats
$totalRemedials = count($remedials);
$pendingCount = count(array_filter($remedials, fn($r) => $r['status'] === 'pending' || $r['status'] === 'in_progress'));
$completedCount = count(array_filter($remedials, fn($r) => $r['status'] === 'completed'));
$overdueCount = count(array_filter($remedials, fn($r) =>
    in_array($r['status'], ['pending', 'in_progress']) && $r['due_date'] && strtotime($r['due_date']) < time()
));

$pageTitle = 'My Remedials';
$currentPage = 'remedials';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="remedials-wrap">

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-text">
                <h1>My Remedials</h1>
                <p>Review and complete your remedial assignments to improve your grades</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon total">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?= $totalRemedials ?></span>
                    <span class="stat-label">Total</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?= $pendingCount ?></span>
                    <span class="stat-label">Pending</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon completed">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?= $completedCount ?></span>
                    <span class="stat-label">Completed</span>
                </div>
            </div>
            <?php if ($overdueCount > 0): ?>
            <div class="stat-card">
                <div class="stat-icon overdue">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <div class="stat-info">
                    <span class="stat-number"><?= $overdueCount ?></span>
                    <span class="stat-label">Overdue</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Remedial Cards -->
        <?php if (empty($remedials)): ?>
            <div class="empty-state">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <h3>No Remedial Assignments</h3>
                <p>You don't have any remedial assignments. Keep up the good work!</p>
            </div>
        <?php else: ?>
            <div class="remedials-list">
                <?php foreach ($remedials as $rem):
                    $isOverdue = in_array($rem['status'], ['pending', 'in_progress']) && $rem['due_date'] && strtotime($rem['due_date']) < time();
                    $attemptsLeft = ($rem['max_attempts'] ?? 3) - $rem['attempts_used'];
                    $hasPassed = isset($rem['best_score']) && $rem['best_score'] >= $rem['passing_rate'];
                    $dueDate = $rem['due_date'] ? date('M j, Y', strtotime($rem['due_date'])) : 'No deadline';
                    $assignedDate = date('M j, Y', strtotime($rem['assigned_at']));

                    // Find subject_offered_id for lesson link
                    $subjectOffering = db()->fetchOne(
                        "SELECT so.subject_offered_id FROM subject_offered so
                         JOIN student_subject ss ON ss.subject_offered_id = so.subject_offered_id
                         WHERE so.subject_id = ? AND ss.user_student_id = ? AND ss.status = 'enrolled'
                         LIMIT 1",
                        [$rem['subject_id'], $userId]
                    );
                    $subjectOfferedId = $subjectOffering['subject_offered_id'] ?? null;
                ?>
                <div class="remedial-card <?= $rem['status'] ?> <?= $isOverdue ? 'overdue' : '' ?>">
                    <div class="card-left">
                        <!-- Status indicator -->
                        <div class="status-indicator <?= $rem['status'] ?> <?= $isOverdue ? 'overdue' : '' ?>">
                            <?php if ($rem['status'] === 'completed'): ?>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M20 6L9 17l-5-5"/>
                                </svg>
                            <?php elseif ($isOverdue): ?>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <line x1="12" y1="8" x2="12" y2="12"/>
                                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                                </svg>
                            <?php else: ?>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-main">
                        <div class="card-top">
                            <div class="card-badges">
                                <span class="subject-badge"><?= e($rem['subject_code']) ?></span>
                                <span class="status-badge <?= $rem['status'] ?> <?= $isOverdue ? 'overdue' : '' ?>">
                                    <?php if ($isOverdue): ?>
                                        Overdue
                                    <?php elseif ($rem['status'] === 'pending'): ?>
                                        Pending
                                    <?php elseif ($rem['status'] === 'in_progress'): ?>
                                        In Progress
                                    <?php else: ?>
                                        Completed
                                    <?php endif; ?>
                                </span>
                            </div>
                            <h3><?= e($rem['quiz_title']) ?></h3>
                            <p class="subject-name"><?= e($rem['subject_name']) ?></p>
                        </div>

                        <div class="card-reason">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M12 16v-4M12 8h.01"/>
                            </svg>
                            <?= e($rem['reason']) ?>
                        </div>

                        <div class="card-meta">
                            <span class="meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                Assigned: <?= $assignedDate ?>
                            </span>
                            <span class="meta-item <?= $isOverdue ? 'overdue-text' : '' ?>">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                Due: <?= $dueDate ?>
                            </span>
                            <span class="meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="8.5" cy="7" r="4"/>
                                </svg>
                                <?= e($rem['assigned_by_name'] ?? 'System') ?>
                            </span>
                            <span class="meta-item">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 11l3 3L22 4"/>
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                                Passing: <?= $rem['passing_rate'] ?>%
                            </span>
                        </div>

                        <?php if ($rem['status'] === 'completed' && $rem['completed_at']): ?>
                        <div class="card-completed-info">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                            Completed on <?= date('M j, Y', strtotime($rem['completed_at'])) ?>
                            <?php if ($rem['new_score']): ?>
                                with score <?= $rem['new_score'] ?> points
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-side">
                        <?php if ($rem['best_score'] !== null): ?>
                        <div class="score-box <?= $hasPassed ? 'passed' : 'failed' ?>">
                            <span class="score-label">Best Score</span>
                            <span class="score-value"><?= round($rem['best_score']) ?>%</span>
                        </div>
                        <?php endif; ?>

                        <div class="attempts-info">
                            <?= $rem['attempts_used'] ?>/<?= $rem['max_attempts'] ?? 3 ?> Attempts
                        </div>

                        <div class="card-actions">
                            <?php if ($rem['status'] !== 'completed'): ?>
                                <?php if ($subjectOfferedId): ?>
                                <a href="lessons.php?id=<?= $subjectOfferedId ?>" class="btn-review">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                                    </svg>
                                    Review Lessons
                                </a>
                                <?php endif; ?>
                                <?php if ($attemptsLeft > 0): ?>
                                <a href="take-quiz.php?id=<?= $rem['quiz_id'] ?>" class="btn-retake">
                                    Retake Quiz
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12h14M12 5l7 7-7 7"/>
                                    </svg>
                                </a>
                                <?php else: ?>
                                <span class="btn-disabled">Max attempts reached</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="btn-completed-tag">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 6L9 17l-5-5"/>
                                    </svg>
                                    Resolved
                                </span>
                            <?php endif; ?>

                            <?php if ($rem['attempt_id']): ?>
                            <a href="quiz-result.php?id=<?= $rem['attempt_id'] ?>" class="btn-view-result">
                                View Original Attempt
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
/* Remedials Page Styles */
.remedials-wrap {
    padding: 24px;
    max-width: 1200px;
    margin: 0 auto;
}

/* Page Header */
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

/* Stats Row */
.stats-row {
    display: flex;
    gap: 16px;
    margin-bottom: 24px;
}
.stat-card {
    flex: 1;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-icon.total { background: #E8F5E9; color: #1B4D3E; }
.stat-icon.pending { background: #FEF3C7; color: #B45309; }
.stat-icon.completed { background: #E8F5E9; color: #1B4D3E; }
.stat-icon.overdue { background: #FEE2E2; color: #b91c1c; }
.stat-number {
    display: block;
    font-size: 22px;
    font-weight: 700;
    color: #333;
}
.stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    font-weight: 500;
}

/* Remedial Cards List */
.remedials-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.remedial-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    display: flex;
    overflow: hidden;
    transition: all 0.2s;
}
.remedial-card:hover {
    border-color: #1B4D3E;
    box-shadow: 0 4px 12px rgba(27, 77, 62, 0.08);
}
.remedial-card.completed {
    opacity: 0.85;
}
.remedial-card.overdue {
    border-left: 4px solid #b91c1c;
}
.remedial-card.pending {
    border-left: 4px solid #B45309;
}
.remedial-card.in_progress {
    border-left: 4px solid #2196F3;
}
.remedial-card.completed:not(.overdue) {
    border-left: 4px solid #1B4D3E;
}

/* Card Left - Status Indicator */
.card-left {
    display: flex;
    align-items: center;
    padding: 20px 0 20px 20px;
}
.status-indicator {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.status-indicator.pending { background: #FEF3C7; color: #B45309; }
.status-indicator.in_progress { background: #E3F2FD; color: #1565C0; }
.status-indicator.completed { background: #E8F5E9; color: #1B4D3E; }
.status-indicator.overdue { background: #FEE2E2; color: #b91c1c; }

/* Card Main */
.card-main {
    flex: 1;
    padding: 20px;
}
.card-top {
    margin-bottom: 10px;
}
.card-badges {
    display: flex;
    gap: 8px;
    margin-bottom: 8px;
}
.subject-badge {
    background: #1B4D3E;
    color: #fff;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
}
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}
.status-badge.pending { background: #FEF3C7; color: #B45309; }
.status-badge.in_progress { background: #E3F2FD; color: #1565C0; }
.status-badge.completed { background: #E8F5E9; color: #1B4D3E; }
.status-badge.overdue { background: #FEE2E2; color: #b91c1c; }

.card-top h3 {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin: 0 0 4px;
}
.subject-name {
    font-size: 13px;
    color: #666;
    margin: 0;
}

.card-reason {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #FFF8E1;
    border-radius: 8px;
    font-size: 12px;
    color: #666;
    margin-bottom: 12px;
    line-height: 1.4;
}
.card-reason svg { color: #B45309; flex-shrink: 0; }

.card-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}
.meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #666;
}
.meta-item svg { color: #1B4D3E; }
.meta-item.overdue-text { color: #b91c1c; font-weight: 600; }
.meta-item.overdue-text svg { color: #b91c1c; }

.card-completed-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    padding: 10px 14px;
    background: #E8F5E9;
    border-radius: 8px;
    font-size: 12px;
    color: #1B4D3E;
    font-weight: 500;
}

/* Card Side */
.card-side {
    width: 200px;
    padding: 20px;
    background: #fafafa;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: center;
    gap: 10px;
    border-left: 1px solid #e8e8e8;
}
.score-box {
    text-align: center;
    padding: 14px;
    border-radius: 8px;
}
.score-box.passed { background: #E8F5E9; }
.score-box.failed { background: #FFEBEE; }
.score-label {
    display: block;
    font-size: 10px;
    color: #666;
    margin-bottom: 4px;
    font-weight: 600;
    text-transform: uppercase;
}
.score-value {
    font-size: 24px;
    font-weight: 700;
    display: block;
    color: #1B4D3E;
}
.score-box.failed .score-value { color: #C62828; }

.attempts-info {
    text-align: center;
    font-size: 11px;
    color: #666;
}

.card-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.btn-review {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 14px;
    background: #E8F5E9;
    color: #1B4D3E;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 12px;
    transition: all 0.2s;
}
.btn-review:hover { background: #c8e6c9; }
.btn-retake {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 16px;
    background: #1B4D3E;
    color: #fff;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 13px;
    transition: all 0.2s;
}
.btn-retake:hover { background: #2D6A4F; }
.btn-disabled {
    display: block;
    padding: 12px 16px;
    background: #e8e8e8;
    color: #999;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
    text-align: center;
    cursor: not-allowed;
}
.btn-completed-tag {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 16px;
    background: #E8F5E9;
    color: #1B4D3E;
    border-radius: 8px;
    font-weight: 600;
    font-size: 12px;
}
.btn-view-result {
    display: block;
    padding: 10px 16px;
    background: #fff;
    border: 1px solid #e8e8e8;
    color: #666;
    border-radius: 8px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    transition: all 0.2s;
}
.btn-view-result:hover {
    border-color: #1B4D3E;
    color: #1B4D3E;
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
    color: #1B4D3E;
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
@media (max-width: 900px) {
    .remedials-wrap { padding: 16px; }
    .stats-row { flex-wrap: wrap; }
    .stat-card { min-width: calc(50% - 8px); }
    .remedial-card { flex-direction: column; }
    .card-left { padding: 16px 16px 0; }
    .card-side {
        width: 100%;
        border-left: none;
        border-top: 1px solid #e8e8e8;
    }
    .card-meta { flex-direction: column; gap: 8px; }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
