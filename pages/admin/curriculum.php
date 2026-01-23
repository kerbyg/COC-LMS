<?php
/**
 * Admin - Curriculum Management
 * Manage program curricula by year and semester
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('admin');

$pageTitle = 'Curriculum';
$currentPage = 'curriculum';

$error = '';
$success = '';

// Get all programs for filtering
$programs = db()->fetchAll(
    "SELECT program_id, program_name, program_code FROM program WHERE status = 'active' ORDER BY program_code"
);

// Get selected program
$selectedProgramId = $_GET['program_id'] ?? ($programs[0]['program_id'] ?? null);
$selectedProgram = null;

if ($selectedProgramId) {
    $selectedProgram = db()->fetchOne(
        "SELECT * FROM program WHERE program_id = ?",
        [$selectedProgramId]
    );
}

// Get all subjects for the curriculum view
$subjects = [];
if ($selectedProgramId) {
    $subjects = db()->fetchAll(
        "SELECT s.*,
            CASE
                WHEN s.year_level = 1 THEN '1st Year'
                WHEN s.year_level = 2 THEN '2nd Year'
                WHEN s.year_level = 3 THEN '3rd Year'
                WHEN s.year_level = 4 THEN '4th Year'
                ELSE 'General'
            END as year_label,
            CASE
                WHEN s.semester = 1 THEN '1st Semester'
                WHEN s.semester = 2 THEN '2nd Semester'
                WHEN s.semester = 3 THEN 'Summer'
                ELSE 'Any Semester'
            END as semester_label
         FROM subject s
         WHERE s.status = 'active'
         ORDER BY s.year_level, s.semester, s.subject_code"
    );
}

// Organize subjects by year and semester
$curriculum = [];
foreach ($subjects as $subject) {
    $year = $subject['year_level'] ?: 0;
    $semester = $subject['semester'] ?: 0;

    if (!isset($curriculum[$year])) {
        $curriculum[$year] = [];
    }
    if (!isset($curriculum[$year][$semester])) {
        $curriculum[$year][$semester] = [
            'subjects' => [],
            'total_units' => 0
        ];
    }

    $curriculum[$year][$semester]['subjects'][] = $subject;
    $curriculum[$year][$semester]['total_units'] += $subject['units'];
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">

        <div class="page-header">
            <div>
                <h2>Curriculum</h2>
                <p class="text-muted">View program curriculum by year and semester</p>
            </div>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <!-- Program Selection -->
        <div class="filters-card">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Select Program</label>
                    <select name="program_id" class="form-control" onchange="this.form.submit()">
                        <option value="">-- Select Program --</option>
                        <?php foreach ($programs as $program): ?>
                        <option value="<?= $program['program_id'] ?>" <?= $selectedProgramId == $program['program_id'] ? 'selected' : '' ?>>
                            <?= e($program['program_code']) ?> - <?= e($program['program_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($selectedProgram): ?>

        <!-- Program Info -->
        <div class="program-info-card">
            <div class="program-info-header">
                <span class="program-badge"><?= e($selectedProgram['program_code']) ?></span>
                <h3><?= e($selectedProgram['program_name']) ?></h3>
            </div>
            <div class="program-info-meta">
                <span>📚 Total Units: <strong><?= $selectedProgram['total_units'] ?></strong></span>
                <span>📖 Total Subjects: <strong><?= count($subjects) ?></strong></span>
            </div>
        </div>

        <!-- Curriculum Grid -->
        <?php if (!empty($curriculum)): ?>
        <div class="curriculum-container">

            <?php
            $yearLabels = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 0 => 'General'];
            $semesterLabels = [1 => '1st Semester', 2 => '2nd Semester', 3 => 'Summer', 0 => 'Any Semester'];

            foreach ($curriculum as $year => $semesters):
                if (empty($semesters)) continue;
            ?>

            <div class="year-section">
                <h3 class="year-title">🎓 <?= $yearLabels[$year] ?? 'Year ' . $year ?></h3>

                <div class="semester-grid">
                    <?php foreach ($semesters as $semester => $data): ?>
                    <div class="semester-card">
                        <div class="semester-header">
                            <h4><?= $semesterLabels[$semester] ?? 'Semester ' . $semester ?></h4>
                            <span class="semester-units"><?= $data['total_units'] ?> units</span>
                        </div>

                        <div class="subjects-list">
                            <?php foreach ($data['subjects'] as $subject): ?>
                            <div class="subject-item">
                                <div class="subject-info">
                                    <span class="subject-code"><?= e($subject['subject_code']) ?></span>
                                    <span class="subject-name"><?= e($subject['subject_name']) ?></span>
                                </div>
                                <span class="subject-units"><?= $subject['units'] ?> units</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php endforeach; ?>

        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3>No Subjects Found</h3>
            <p>No subjects have been added to this program's curriculum yet.</p>
            <a href="subjects.php" class="btn btn-success" style="margin-top:16px">+ Add Subjects</a>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">📚</div>
            <h3>Select a Program</h3>
            <p>Choose a program above to view its curriculum.</p>
        </div>
        <?php endif; ?>

    </div>
</main>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px}
.page-header h2{font-size:28px;font-weight:800;margin:0 0 6px;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.text-muted{color:var(--gray-500);margin:0;font-size:15px}

.filters-card{background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:1px solid #e5e7eb;border-radius:16px;padding:24px;margin-bottom:28px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.filters-form{display:flex;gap:16px;align-items:flex-end}
.filter-group{display:flex;flex-direction:column;gap:8px;flex:1}
.filter-group label{font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:0.025em}

.program-info-card{background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:1px solid #e5e7eb;border-radius:16px;padding:28px;margin-bottom:32px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.program-info-header{display:flex;align-items:center;gap:16px;margin-bottom:16px}
.program-badge{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);color:#1e3a8a;padding:8px 20px;border-radius:24px;font-size:14px;font-weight:800;box-shadow:0 2px 4px rgba(0,0,0,0.08)}
.program-info-header h3{margin:0;font-size:22px;font-weight:800;color:#111827}
.program-info-meta{display:flex;gap:32px;color:#6b7280;font-size:15px}
.program-info-meta strong{color:#1e3a8a;font-weight:700}

.curriculum-container{display:flex;flex-direction:column;gap:32px}

.year-section{background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:1px solid #e5e7eb;border-radius:16px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.year-title{margin:0 0 24px;font-size:20px;font-weight:800;background:linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

.semester-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px}

.semester-card{background:linear-gradient(135deg,#f9fafb 0%,#ffffff 100%);border:2px solid #e5e7eb;border-radius:12px;overflow:hidden;transition:all 0.3s cubic-bezier(0.4,0,0.2,1)}
.semester-card:hover{border-color:#3b82f6;box-shadow:0 8px 16px rgba(59,130,246,0.15)}

.semester-header{background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);padding:16px 20px;display:flex;justify-content:space-between;align-items:center}
.semester-header h4{margin:0;font-size:16px;font-weight:800;color:#1e3a8a}
.semester-units{background:#ffffff;color:#1e3a8a;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:700;box-shadow:0 2px 4px rgba(0,0,0,0.1)}

.subjects-list{padding:20px;display:flex;flex-direction:column;gap:12px}

.subject-item{display:flex;justify-content:space-between;align-items:center;padding:14px;background:#ffffff;border:1px solid #f3f4f6;border-radius:10px;transition:all 0.2s cubic-bezier(0.4,0,0.2,1)}
.subject-item:hover{background:linear-gradient(135deg,#eff6ff 0%,#dbeafe 100%);border-color:#bfdbfe;transform:translateX(4px)}

.subject-info{display:flex;flex-direction:column;gap:4px}
.subject-code{font-size:14px;font-weight:800;color:#1e3a8a}
.subject-name{font-size:13px;color:#6b7280;font-weight:500}
.subject-units{font-size:13px;font-weight:700;color:#6b7280}

.empty-state{text-align:center;padding:80px 24px;background:linear-gradient(135deg,#ffffff 0%,#f9fafb 100%);border:2px dashed #e5e7eb;border-radius:16px}
.empty-state-icon{font-size:64px;margin-bottom:16px;opacity:0.5}
.empty-state h3{margin:0 0 8px;font-size:22px;color:#374151;font-weight:700}
.empty-state p{margin:0;color:#6b7280;font-size:15px}

.alert{padding:18px 24px;border-radius:12px;margin-bottom:24px;font-weight:600;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
.alert-success{background:linear-gradient(135deg,#d1fae5 0%,#a7f3d0 100%);color:#065f46;border-left:5px solid #10b981}
.alert-danger{background:linear-gradient(135deg,#fee2e2 0%,#fecaca 100%);color:#991b1b;border-left:5px solid #ef4444}

@media(max-width:768px){
    .semester-grid{grid-template-columns:1fr}
    .program-info-meta{flex-direction:column;gap:12px}
    .page-header h2{font-size:22px}
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
