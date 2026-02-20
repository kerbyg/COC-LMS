<?php
/**
 * Instructor - Remedials Management
 * View and manage remedial assignments for students in assigned classes
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('instructor');

$userId = Auth::id();
$pageTitle = 'Remedials';
$currentPage = 'remedials';

$successMessage = '';
$errorMessage = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remedialId = $_POST['remedial_id'] ?? 0;

    // Verify ownership helper
    $verifyOwnership = function($remId) use ($userId) {
        return db()->fetchOne(
            "SELECT ra.remedial_id FROM remedial_assignment ra
             JOIN quiz q ON ra.quiz_id = q.quiz_id
             WHERE ra.remedial_id = ? AND q.user_teacher_id = ?",
            [$remId, $userId]
        );
    };

    if ($action === 'update_status' && $remedialId) {
        $newStatus = $_POST['status'] ?? '';
        $remarks = trim($_POST['remarks'] ?? '');
        $newDueDate = $_POST['due_date'] ?? '';

        if ($verifyOwnership($remedialId) && in_array($newStatus, ['pending', 'in_progress', 'completed'])) {
            $updateFields = "status = ?";
            $updateParams = [$newStatus];

            if ($remarks) {
                $timestamp = date('M j, g:ia');
                $updateFields .= ", remarks = CONCAT(IFNULL(remarks, ''), ?)";
                $updateParams[] = "\n[$timestamp] $remarks";
            }

            if ($newDueDate) {
                $updateFields .= ", due_date = ?";
                $updateParams[] = $newDueDate;
            }

            if ($newStatus === 'completed') {
                $updateFields .= ", completed_at = NOW()";
            }

            $updateParams[] = $remedialId;
            db()->execute(
                "UPDATE remedial_assignment SET $updateFields WHERE remedial_id = ?",
                $updateParams
            );
            $successMessage = "Remedial assignment updated successfully.";
        } else {
            $errorMessage = "Invalid remedial assignment or permission denied.";
        }
    }

    if ($action === 'create_remedial') {
        $studentId = $_POST['student_id'] ?? 0;
        $quizId = $_POST['quiz_id'] ?? 0;
        $reason = trim($_POST['reason'] ?? '');
        $dueDate = $_POST['due_date'] ?? '';

        // Verify quiz belongs to instructor
        $quizCheck = db()->fetchOne(
            "SELECT quiz_id, subject_id FROM quiz WHERE quiz_id = ? AND user_teacher_id = ?",
            [$quizId, $userId]
        );

        // Verify student is enrolled in instructor's subject (use subject_offered_id path, not section_id which can be NULL)
        $studentCheck = db()->fetchOne(
            "SELECT u.users_id FROM users u
             JOIN student_subject ss ON ss.user_student_id = u.users_id
             JOIN section sec ON ss.section_id = sec.section_id
             JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
             JOIN faculty_subject fs ON fs.subject_offered_id = so.subject_offered_id AND fs.user_teacher_id = ?
             WHERE u.users_id = ? AND so.subject_id = (SELECT subject_id FROM quiz WHERE quiz_id = ?) AND ss.status = 'enrolled'",
            [$userId, $studentId, $quizId]
        );

        if ($quizCheck && $studentCheck) {
            // Check no active remedial already exists
            $existing = db()->fetchOne(
                "SELECT remedial_id FROM remedial_assignment
                 WHERE user_student_id = ? AND quiz_id = ? AND status IN ('pending', 'in_progress')",
                [$studentId, $quizId]
            );

            if ($existing) {
                $errorMessage = "An active remedial already exists for this student and quiz.";
            } else {
                // Get the latest failed attempt (may be null for manual assignments before any attempt)
                $lastAttempt = db()->fetchOne(
                    "SELECT attempt_id FROM student_quiz_attempts
                     WHERE quiz_id = ? AND user_student_id = ? AND passed = 0
                     ORDER BY completed_at DESC LIMIT 1",
                    [$quizId, $studentId]
                );
                $attemptId = $lastAttempt['attempt_id'] ?? 0;

                try {
                    $stmt = pdo()->prepare(
                        "INSERT INTO remedial_assignment (user_student_id, quiz_id, attempt_id, assigned_by, reason, due_date, status)
                         VALUES (?, ?, ?, ?, ?, ?, 'pending')"
                    );
                    $stmt->execute([
                        $studentId,
                        $quizId,
                        $attemptId,
                        $userId,
                        $reason ?: 'Manually assigned by instructor',
                        $dueDate ?: date('Y-m-d', strtotime('+7 days'))
                    ]);
                    $successMessage = "Remedial assignment created successfully.";
                } catch (PDOException $e) {
                    $errorMessage = "Failed to create remedial: " . $e->getMessage();
                }
            }
        } else {
            $errorMessage = "Invalid student or quiz selection.";
        }
    }

    if ($action === 'sync_status') {
        // Auto-sync: mark completed any remedials where student has since passed
        $synced = 0;
        $pendingRemedials = db()->fetchAll(
            "SELECT ra.remedial_id, ra.quiz_id, ra.user_student_id, q.passing_rate
             FROM remedial_assignment ra
             JOIN quiz q ON ra.quiz_id = q.quiz_id
             WHERE q.user_teacher_id = ? AND ra.status IN ('pending', 'in_progress')",
            [$userId]
        );

        foreach ($pendingRemedials as $pr) {
            $bestScore = db()->fetchOne(
                "SELECT MAX(percentage) as best FROM student_quiz_attempts
                 WHERE quiz_id = ? AND user_student_id = ? AND status = 'completed'",
                [$pr['quiz_id'], $pr['user_student_id']]
            )['best'] ?? 0;

            if ($bestScore >= $pr['passing_rate']) {
                db()->execute(
                    "UPDATE remedial_assignment SET status = 'completed', new_score = ?, completed_at = NOW(),
                     remarks = CONCAT(IFNULL(remarks, ''), ?)
                     WHERE remedial_id = ?",
                    [$bestScore, "\n[" . date('M j, g:ia') . "] Auto-synced: Student passed with " . round($bestScore, 1) . "%", $pr['remedial_id']]
                );
                $synced++;
            }
        }

        if ($synced > 0) {
            $successMessage = "Synced $synced remedial(s) — students who have since passed were marked completed.";
        } else {
            $successMessage = "All remedials are already in sync. No changes needed.";
        }
    }
}

// Get filter parameters
$offeredId = $_GET['offered_id'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Get instructor's subjects for filter dropdown
$mySubjects = db()->fetchAll(
    "SELECT
        so.subject_offered_id,
        s.subject_code,
        s.subject_name
    FROM faculty_subject fs
    JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
    JOIN subject s ON so.subject_id = s.subject_id
    WHERE fs.user_teacher_id = ? AND fs.status = 'active'
    ORDER BY s.subject_code",
    [$userId]
);

// Build remedials query — use faculty_subject path to avoid duplicates
$whereClause = "WHERE q.user_teacher_id = ?";
$params = [$userId];

if ($offeredId) {
    $whereClause .= " AND fs.subject_offered_id = ?";
    $params[] = $offeredId;
}

if ($statusFilter) {
    $whereClause .= " AND ra.status = ?";
    $params[] = $statusFilter;
}

if ($search) {
    $whereClause .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.student_id LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

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
        q.subject_id,
        s.subject_code,
        s.subject_name,
        fs.subject_offered_id,
        u.users_id as student_id_pk,
        u.student_id,
        u.first_name,
        u.last_name,
        u.email,
        (SELECT COUNT(*) FROM student_quiz_attempts WHERE quiz_id = ra.quiz_id AND user_student_id = ra.user_student_id) as attempts_used,
        (SELECT MAX(percentage) FROM student_quiz_attempts WHERE quiz_id = ra.quiz_id AND user_student_id = ra.user_student_id AND status = 'completed') as best_score
    FROM remedial_assignment ra
    JOIN quiz q ON ra.quiz_id = q.quiz_id
    JOIN subject s ON q.subject_id = s.subject_id
    JOIN faculty_subject fs ON fs.user_teacher_id = q.user_teacher_id AND fs.subject_offered_id IN (
        SELECT so.subject_offered_id FROM subject_offered so WHERE so.subject_id = s.subject_id
    )
    JOIN users u ON ra.user_student_id = u.users_id
    $whereClause
    GROUP BY ra.remedial_id
    ORDER BY
        CASE ra.status
            WHEN 'pending' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'completed' THEN 3
        END,
        ra.due_date ASC",
    $params
) ?: [];

// Detect out-of-sync remedials (student passed but remedial still pending)
$outOfSyncCount = 0;
foreach ($remedials as &$rem) {
    $rem['out_of_sync'] = false;
    if (in_array($rem['status'], ['pending', 'in_progress']) && $rem['best_score'] !== null && $rem['best_score'] >= $rem['passing_rate']) {
        $rem['out_of_sync'] = true;
        $outOfSyncCount++;
    }
}
unset($rem);

// Stats
$totalRemedials = count($remedials);
$pendingCount = count(array_filter($remedials, fn($r) => $r['status'] === 'pending'));
$inProgressCount = count(array_filter($remedials, fn($r) => $r['status'] === 'in_progress'));
$completedCount = count(array_filter($remedials, fn($r) => $r['status'] === 'completed'));
$overdueCount = count(array_filter($remedials, fn($r) =>
    in_array($r['status'], ['pending', 'in_progress']) && $r['due_date'] && strtotime($r['due_date']) < time()
));

// Get quizzes for manual creation (include subject_id for JS filtering)
$myQuizzes = db()->fetchAll(
    "SELECT q.quiz_id, q.quiz_title, q.passing_rate, q.subject_id, s.subject_code, s.subject_name
     FROM quiz q
     JOIN subject s ON q.subject_id = s.subject_id
     WHERE q.user_teacher_id = ? AND q.status = 'published'
     ORDER BY s.subject_code, q.quiz_title",
    [$userId]
) ?: [];

// Get students for manual creation (include subject_id for JS filtering)
$myStudents = db()->fetchAll(
    "SELECT DISTINCT u.users_id, u.student_id, u.first_name, u.last_name, so.subject_id
     FROM users u
     JOIN student_subject ss ON ss.user_student_id = u.users_id
     JOIN section sec ON ss.section_id = sec.section_id
     JOIN subject_offered so ON sec.subject_offered_id = so.subject_offered_id
     JOIN faculty_subject fs ON sec.section_id = fs.section_id
     WHERE fs.user_teacher_id = ? AND ss.status = 'enrolled'
     ORDER BY u.last_name, u.first_name",
    [$userId]
) ?: [];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($successMessage) ?>
        </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>Remedial Assignments</h2>
                <p class="text-muted">Track and manage remedial assignments for students who need improvement</p>
            </div>
            <div class="header-actions">
                <?php if ($outOfSyncCount > 0): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="sync_status">
                    <button type="submit" class="btn btn-warning" title="<?= $outOfSyncCount ?> student(s) have passed but their remedials are still open">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                        Sync (<?= $outOfSyncCount ?>)
                    </button>
                </form>
                <?php endif; ?>
                <button type="button" class="btn btn-success" onclick="openCreateModal()">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Assign Remedial
                </button>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon-box total">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                </div>
                <div>
                    <span class="stat-number"><?= $totalRemedials ?></span>
                    <span class="stat-text">Total</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box pending">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div>
                    <span class="stat-number"><?= $pendingCount ?></span>
                    <span class="stat-text">Pending</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box inprogress">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
                </div>
                <div>
                    <span class="stat-number"><?= $inProgressCount ?></span>
                    <span class="stat-text">In Progress</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-box completed">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div>
                    <span class="stat-number"><?= $completedCount ?></span>
                    <span class="stat-text">Completed</span>
                </div>
            </div>
            <?php if ($overdueCount > 0): ?>
            <div class="stat-card">
                <div class="stat-icon-box overdue">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </div>
                <div>
                    <span class="stat-number"><?= $overdueCount ?></span>
                    <span class="stat-text">Overdue</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group flex-grow">
                    <label>Search Student</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search by name or student ID..."
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
                    <label>Status</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-success">Search</button>
                </div>
                <?php if ($search || $offeredId || $statusFilter): ?>
                <div class="filter-group">
                    <a href="remedials.php" class="btn btn-outline">Clear</a>
                </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Remedials Table -->
        <?php if (empty($remedials)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 15l2 2 4-4"/></svg>
                </div>
                <h3>No Remedial Assignments</h3>
                <p>No remedial assignments found. Remedials are automatically created when students fail quizzes, or you can manually assign one.</p>
                <button type="button" class="btn btn-success" onclick="openCreateModal()" style="margin-top:12px;">Assign Remedial</button>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Remedials <span class="badge badge-primary"><?= $totalRemedials ?></span>
                    </h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Quiz</th>
                                <th>Subject</th>
                                <th>Reason</th>
                                <th>Assigned / Due</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th style="width: 80px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($remedials as $rem):
                                $isOverdue = in_array($rem['status'], ['pending', 'in_progress']) && $rem['due_date'] && strtotime($rem['due_date']) < time();
                                $hasPassed = isset($rem['best_score']) && $rem['best_score'] >= $rem['passing_rate'];
                                $initials = strtoupper(substr($rem['first_name'], 0, 1) . substr($rem['last_name'], 0, 1));
                                $daysLeft = $rem['due_date'] ? round((strtotime($rem['due_date']) - time()) / 86400) : null;
                            ?>
                            <tr class="<?= $isOverdue ? 'row-overdue' : '' ?> <?= $rem['status'] === 'completed' ? 'row-completed' : '' ?> <?= $rem['out_of_sync'] ? 'row-sync' : '' ?>">
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar"><?= $initials ?></div>
                                        <div>
                                            <div class="student-name"><?= e($rem['first_name'] . ' ' . $rem['last_name']) ?></div>
                                            <div class="student-email"><?= e($rem['student_id'] ?? $rem['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="quiz-info-cell">
                                        <strong><?= e($rem['quiz_title']) ?></strong>
                                        <small>Pass: <?= $rem['passing_rate'] ?>% | <?= $rem['attempts_used'] ?>/<?= $rem['max_attempts'] ?? 3 ?> attempts</small>
                                    </div>
                                </td>
                                <td>
                                    <span class="subject-badge-sm"><?= e($rem['subject_code']) ?></span>
                                </td>
                                <td>
                                    <span class="reason-text" title="<?= e($rem['reason']) ?>"><?= e($rem['reason']) ?></span>
                                </td>
                                <td>
                                    <div class="date-cell">
                                        <small class="text-muted"><?= date('M j, Y', strtotime($rem['assigned_at'])) ?></small>
                                        <?php if ($rem['due_date']): ?>
                                            <span class="<?= $isOverdue ? 'text-danger fw-600' : '' ?>">
                                                <?= date('M j, Y', strtotime($rem['due_date'])) ?>
                                            </span>
                                            <?php if ($isOverdue): ?>
                                                <small class="text-danger">Overdue by <?= abs($daysLeft) ?>d</small>
                                            <?php elseif ($daysLeft !== null && $daysLeft <= 3 && $daysLeft >= 0 && $rem['status'] !== 'completed'): ?>
                                                <small class="text-warning"><?= $daysLeft ?>d left</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No deadline</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($rem['best_score'] !== null): ?>
                                        <span class="score-badge <?= $hasPassed ? 'passed' : 'failed' ?>">
                                            <?= round($rem['best_score']) ?>%
                                        </span>
                                        <?php if ($rem['out_of_sync']): ?>
                                            <small class="text-success d-block">Passed!</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No attempt</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($rem['out_of_sync']): ?>
                                        <span class="status-pill sync">
                                            Needs Sync
                                        </span>
                                    <?php elseif ($isOverdue): ?>
                                        <span class="status-pill overdue">Overdue</span>
                                    <?php else: ?>
                                        <span class="status-pill <?= $rem['status'] ?>">
                                            <?php if ($rem['status'] === 'pending'): ?>
                                                Pending
                                            <?php elseif ($rem['status'] === 'in_progress'): ?>
                                                In Progress
                                            <?php else: ?>
                                                Completed
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($rem['status'] === 'completed' && $rem['completed_at']): ?>
                                        <small class="text-muted d-block"><?= date('M j', strtotime($rem['completed_at'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <button class="btn-actions-toggle" onclick="toggleActions(this)" title="Actions">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="3" r="1.5" fill="currentColor"/><circle cx="8" cy="8" r="1.5" fill="currentColor"/><circle cx="8" cy="13" r="1.5" fill="currentColor"/></svg>
                                        </button>
                                        <div class="actions-dropdown">
                                            <?php if ($rem['status'] !== 'completed'): ?>
                                            <button class="action-item" onclick="openStatusModal(<?= $rem['remedial_id'] ?>, <?= htmlspecialchars(json_encode($rem['first_name'] . ' ' . $rem['last_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($rem['quiz_title']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($rem['status']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($rem['due_date'] ?? ''), ENT_QUOTES) ?>)">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                Update Status
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($rem['attempt_id']): ?>
                                            <a href="../student/quiz-result.php?id=<?= $rem['attempt_id'] ?>" class="action-item" target="_blank">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                                View Attempt
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($rem['remarks']): ?>
                                            <button class="action-item" onclick="openRemarksModal(<?= htmlspecialchars(json_encode($rem['first_name'] . ' ' . $rem['last_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($rem['quiz_title']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($rem['remarks'] ?? ''), ENT_QUOTES) ?>)">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                                View Remarks
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Status Update Modal -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Update Remedial Status</h3>
            <button class="modal-close" onclick="closeModal('statusModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="remedial_id" id="modalRemedialId">
            <div class="modal-body">
                <p class="modal-info" id="modalInfo"></p>
                <div class="form-group">
                    <label class="field-label">Status</label>
                    <select name="status" id="modalStatus" class="form-control">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="field-label">Due Date</label>
                    <input type="date" name="due_date" id="modalDueDate" class="form-control">
                    <small class="text-muted">Leave empty to keep current due date</small>
                </div>
                <div class="form-group">
                    <label class="field-label">Remarks (optional)</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Add a note about this update..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('statusModal')">Cancel</button>
                <button type="submit" class="btn btn-success">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Remarks Modal -->
<div class="modal-overlay" id="remarksModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Remarks History</h3>
            <button class="modal-close" onclick="closeModal('remarksModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-info" id="remarksInfo"></p>
            <div id="remarksContent" class="remarks-list"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('remarksModal')">Close</button>
        </div>
    </div>
</div>

<!-- Create Remedial Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-box modal-wide">
        <div class="modal-header">
            <h3>Assign Remedial</h3>
            <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST" id="createRemedialForm">
            <input type="hidden" name="action" value="create_remedial">
            <div class="modal-body">
                <div class="form-group">
                    <label class="field-label">Quiz</label>
                    <select name="quiz_id" id="createQuizSelect" class="form-control" required onchange="onQuizChange()">
                        <option value="" data-subject="">-- Select a quiz first --</option>
                        <?php foreach ($myQuizzes as $qz): ?>
                        <option value="<?= $qz['quiz_id'] ?>" data-subject="<?= $qz['subject_id'] ?>" data-pass="<?= $qz['passing_rate'] ?>">
                            [<?= e($qz['subject_code']) ?>] <?= e($qz['quiz_title']) ?> (pass: <?= $qz['passing_rate'] ?>%)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="field-label">Student</label>
                    <select name="student_id" id="createStudentSelect" class="form-control" required>
                        <option value="">-- Select a quiz first to see students --</option>
                    </select>
                    <small class="text-muted" id="studentHint">Students will be filtered based on the selected quiz's subject</small>
                </div>
                <div class="form-group">
                    <label class="field-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="e.g., Failed quiz, needs to retake after review"></textarea>
                </div>
                <div class="form-group">
                    <label class="field-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn btn-success">Assign Remedial</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Override card/table overflow for dropdowns */
.card { overflow: visible; }
.table-container { overflow: visible; }

/* Fix: hidden topbar dropdowns blocking clicks on page content */
.dropdown-menu {
    pointer-events: none;
}
.dropdown.active .dropdown-menu {
    pointer-events: auto;
}

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

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* Stats Row */
.stats-row {
    display: flex;
    gap: 14px;
    margin-bottom: 24px;
}
.stat-card {
    flex: 1;
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.stat-icon-box {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.stat-icon-box.total { background: #E8F5E9; color: #1B4D3E; }
.stat-icon-box.pending { background: #FEF3C7; color: #B45309; }
.stat-icon-box.inprogress { background: #E3F2FD; color: #1565C0; }
.stat-icon-box.completed { background: #E8F5E9; color: #1B4D3E; }
.stat-icon-box.overdue { background: #FEE2E2; color: #b91c1c; }
.stat-number {
    display: block;
    font-size: 22px;
    font-weight: 700;
    color: #333;
}
.stat-text {
    display: block;
    font-size: 12px;
    color: #666;
    font-weight: 500;
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

/* Table cells */
.quiz-info-cell strong {
    display: block;
    font-size: 13px;
    color: #333;
}
.quiz-info-cell small {
    display: block;
    color: #888;
    font-size: 11px;
    margin-top: 2px;
}

.subject-badge-sm {
    background: #1B4D3E;
    color: #fff;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.reason-text {
    font-size: 12px;
    color: #666;
    max-width: 180px;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: help;
}

.date-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 13px;
}

/* Status Pills */
.status-pill {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.status-pill.pending { background: #FEF3C7; color: #B45309; }
.status-pill.in_progress { background: #E3F2FD; color: #1565C0; }
.status-pill.completed { background: #E8F5E9; color: #1B4D3E; }
.status-pill.overdue { background: #FEE2E2; color: #b91c1c; }
.status-pill.sync { background: #EDE9FE; color: #7C3AED; }

.text-danger { color: #b91c1c; }
.text-success { color: #1B4D3E; }
.text-warning { color: #B45309; }
.fw-600 { font-weight: 600; }
.d-block { display: block; }

/* Row highlights */
.row-overdue { background: #FFF5F5; }
.row-completed { opacity: 0.7; }
.row-sync { background: #F5F3FF; }

/* Score badge */
.score-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}
.score-badge.passed { background: #E8F5E9; color: #1B4D3E; }
.score-badge.failed { background: #FEE2E2; color: #b91c1c; }

/* Student Info */
.student-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.student-avatar {
    width: 40px;
    height: 40px;
    background: var(--primary);
    color: #fff;
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

/* Actions Dropdown */
.actions-cell {
    position: relative;
    display: flex;
    justify-content: center;
}
.btn-actions-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 1px solid var(--gray-200);
    background: var(--white);
    border-radius: 8px;
    cursor: pointer;
    color: var(--gray-500);
    transition: all 0.2s;
}
.btn-actions-toggle:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
    color: var(--gray-700);
}
.btn-actions-toggle.active {
    background: var(--gray-100);
    border-color: var(--primary);
    color: var(--primary);
}
.actions-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06);
    min-width: 180px;
    z-index: 100;
    padding: 6px;
    animation: dropdownFadeIn 0.15s ease-out;
}
.actions-dropdown.show { display: block; }
@keyframes dropdownFadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.action-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    font-size: 13px;
    font-weight: 500;
    color: var(--gray-700);
    text-decoration: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    font-family: inherit;
}
.action-item:hover {
    background: var(--cream-light, #f8f9fa);
    color: var(--gray-800);
}
.action-item svg { flex-shrink: 0; opacity: 0.7; }
.action-item:hover svg { opacity: 1; }

/* Buttons */
.btn-warning {
    padding: 8px 16px;
    background: #FEF3C7;
    color: #B45309;
    border: 1px solid #F59E0B;
    border-radius: 8px;
    font-weight: 600;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-warning:hover { background: #FDE68A; }

/* Modals */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}
.modal-box {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    animation: modalSlideIn 0.2s ease-out;
}
.modal-wide { max-width: 540px; }
@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #e8e8e8;
}
.modal-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}
.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #999;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}
.modal-close:hover { color: #333; }
.modal-body { padding: 24px; }
.modal-info {
    font-size: 13px;
    color: #666;
    margin: 0 0 16px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 24px;
    border-top: 1px solid #e8e8e8;
}

.form-group {
    margin-bottom: 16px;
}
.form-group:last-child { margin-bottom: 0; }
.field-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
}

/* Remarks list */
.remarks-list {
    max-height: 300px;
    overflow-y: auto;
}
.remark-entry {
    padding: 10px 14px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 8px;
    font-size: 13px;
    color: #444;
    line-height: 1.5;
    border-left: 3px solid #1B4D3E;
}
.remark-entry:last-child { margin-bottom: 0; }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 24px;
    background: #fafafa;
    border: 1px dashed #ddd;
    border-radius: 12px;
}
.empty-state-icon { color: #1B4D3E; margin-bottom: 16px; }
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
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
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

<!-- Data script (isolated so encoding issues won't break functions) -->
<script>
var studentsBySubject = <?php
    $studentData = array_reduce($myStudents, function($carry, $st) {
        $subjectId = $st['subject_id'] ?? 0;
        if (!isset($carry[$subjectId])) $carry[$subjectId] = [];
        $exists = false;
        foreach ($carry[$subjectId] as $existing) {
            if ($existing['id'] == $st['users_id']) { $exists = true; break; }
        }
        if (!$exists) {
            $carry[$subjectId][] = [
                'id' => $st['users_id'],
                'student_id' => $st['student_id'] ?? '',
                'name' => ($st['last_name'] ?? '') . ', ' . ($st['first_name'] ?? '')
            ];
        }
        return $carry;
    }, []);
    $encoded = json_encode($studentData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE);
    echo $encoded !== false ? $encoded : '{}';
?>;
</script>

<!-- Main functions script -->
<script>
// Modal helpers — use .active class (matches global style.css opacity/visibility pattern)
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});

// Status Update Modal
function openStatusModal(remedialId, studentName, quizTitle, currentStatus, dueDate) {
    document.getElementById('modalRemedialId').value = remedialId;
    document.getElementById('modalInfo').textContent = studentName + ' \u2014 ' + quizTitle;
    document.getElementById('modalStatus').value = currentStatus;
    document.getElementById('modalDueDate').value = dueDate || '';
    document.getElementById('statusModal').classList.add('active');
}

// Remarks Modal
function openRemarksModal(studentName, quizTitle, remarksText) {
    document.getElementById('remarksInfo').textContent = studentName + ' \u2014 ' + quizTitle;
    var container = document.getElementById('remarksContent');
    container.innerHTML = '';

    var entries = (remarksText || '').split('\n').filter(function(e) { return e.trim(); });
    if (entries.length === 0) {
        container.innerHTML = '<p class="text-muted">No remarks yet.</p>';
    } else {
        entries.forEach(function(entry) {
            var div = document.createElement('div');
            div.className = 'remark-entry';
            div.textContent = entry.replace(/^\s*\|\s*/, '').trim();
            container.appendChild(div);
        });
    }
    document.getElementById('remarksModal').classList.add('active');
}

// Create Remedial Modal
function openCreateModal() {
    document.getElementById('createRemedialForm').reset();
    document.getElementById('createStudentSelect').innerHTML = '<option value="">-- Select a quiz first to see students --</option>';
    document.getElementById('studentHint').textContent = 'Students will be filtered based on the selected quiz\'s subject';
    document.getElementById('createModal').classList.add('active');
}

// Quiz change -> filter students
function onQuizChange() {
    var quizSelect = document.getElementById('createQuizSelect');
    var studentSelect = document.getElementById('createStudentSelect');
    var hint = document.getElementById('studentHint');
    var selectedOption = quizSelect.options[quizSelect.selectedIndex];
    var subjectId = selectedOption.getAttribute('data-subject');

    studentSelect.innerHTML = '';

    if (!subjectId) {
        studentSelect.innerHTML = '<option value="">-- Select a quiz first to see students --</option>';
        hint.textContent = 'Students will be filtered based on the selected quiz\'s subject';
        return;
    }

    var students = (typeof studentsBySubject === 'object' && studentsBySubject !== null) ? (studentsBySubject[subjectId] || []) : [];

    if (students.length === 0) {
        studentSelect.innerHTML = '<option value="">No students enrolled in this subject</option>';
        hint.textContent = 'No students found for this subject';
        return;
    }

    studentSelect.innerHTML = '<option value="">Select a student (' + students.length + ' enrolled)...</option>';
    students.forEach(function(st) {
        var opt = document.createElement('option');
        opt.value = st.id;
        opt.textContent = st.name + ' (' + st.student_id + ')';
        studentSelect.appendChild(opt);
    });
    hint.textContent = students.length + ' student(s) enrolled in this subject';
}

// Actions dropdown
function toggleActions(btn) {
    var dropdown = btn.nextElementSibling;
    var isOpen = dropdown.classList.contains('show');

    document.querySelectorAll('.actions-dropdown.show').forEach(function(d) {
        d.classList.remove('show');
        d.previousElementSibling.classList.remove('active');
    });

    if (!isOpen) {
        dropdown.classList.add('show');
        btn.classList.add('active');
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.actions-cell')) {
        document.querySelectorAll('.actions-dropdown.show').forEach(function(d) {
            d.classList.remove('show');
            d.previousElementSibling.classList.remove('active');
        });
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
