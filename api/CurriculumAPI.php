<?php
/**
 * Curriculum API - View and manage curriculum subjects
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? 'view';

// RBAC: enforce permission per action
$_currPerms = [
    'programs'  => 'curriculum.view',
    'view'      => 'curriculum.view',
    'available' => 'curriculum.view',
    'add'       => 'curriculum.edit',
    'update'    => 'curriculum.edit',
    'archive'   => 'curriculum.edit',
];
if (isset($_currPerms[$action]) && !Auth::can($_currPerms[$action])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => "Permission denied: {$_currPerms[$action]}"]);
    exit;
}

switch ($action) {
    case 'programs':    handlePrograms();   break;
    case 'view':        handleView();       break;
    case 'available':   handleAvailable();  break;
    case 'add':         handleAdd();        break;
    case 'update':      handleUpdate();     break;
    case 'archive':     handleArchive();    break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ─── Programs list ─────────────────────────────────────────────────────────

function handlePrograms() {
    $programs = db()->fetchAll("SELECT program_id, program_name, program_code, department_id FROM program WHERE status = 'active' ORDER BY program_code");
    echo json_encode(['success' => true, 'data' => $programs]);
}

// ─── View curriculum for a program ────────────────────────────────────────

function handleView() {
    $programId = (int)($_GET['program_id'] ?? 0);
    if (!$programId) { echo json_encode(['success' => false, 'message' => 'Program ID required']); return; }

    $subjects = db()->fetchAll(
        "SELECT s.subject_id, s.subject_code, s.subject_name, s.units,
                c.year_level, c.semester_id AS semester, s.description, s.lecture_hours,
                s.lab_hours, s.pre_requisite, s.status
         FROM curriculum c
         JOIN subject s ON s.subject_id = c.course_id
         WHERE c.program_id = ? AND c.status = 'active' AND s.status = 'active'
         ORDER BY c.year_level, c.semester_id, s.subject_code",
        [$programId]
    );
    echo json_encode(['success' => true, 'data' => $subjects]);
}

// ─── All active subjects for Add modal ────────────────────────────────────

function handleAvailable() {
    $programId = (int)($_GET['program_id'] ?? 0);
    if (!$programId) { echo json_encode(['success' => false, 'message' => 'Program ID required']); return; }

    // Return ALL active subjects; subjects already in this program's curriculum are marked
    $subjects = db()->fetchAll(
        "SELECT s.subject_id, s.subject_code, s.subject_name, s.units,
                c.year_level, c.semester_id AS semester,
                CASE WHEN c.curriculum_id IS NOT NULL THEN 1 ELSE 0 END AS in_program
         FROM subject s
         LEFT JOIN curriculum c ON c.course_id = s.subject_id
                                AND c.program_id = ?
                                AND c.status = 'active'
         WHERE s.status = 'active'
         ORDER BY s.subject_code",
        [$programId]
    );
    echo json_encode(['success' => true, 'data' => $subjects]);
}

// ─── Add a subject to this program's curriculum ───────────────────────────

function handleAdd() {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $programId = (int)($data['program_id'] ?? 0);
    $subjectId = (int)($data['subject_id'] ?? 0);
    $yearLevel = in_array($data['year_level'] ?? '', ['1','2','3','4']) ? (int)$data['year_level'] : null;
    $semester  = in_array((int)($data['semester'] ?? 0), [1,2,3]) ? (int)$data['semester'] : null;

    if (!$programId || !$subjectId) {
        echo json_encode(['success' => false, 'message' => 'program_id and subject_id are required']);
        return;
    }

    $subj = db()->fetchOne("SELECT subject_code FROM subject WHERE subject_id = ?", [$subjectId]);
    if (!$subj) {
        echo json_encode(['success' => false, 'message' => 'Subject not found']);
        return;
    }

    try {
        $pdo = pdo();

        // Upsert into curriculum table
        $existing = db()->fetchOne(
            "SELECT curriculum_id FROM curriculum WHERE program_id = ? AND course_id = ?",
            [$programId, $subjectId]
        );

        if ($existing) {
            $pdo->prepare(
                "UPDATE curriculum SET year_level = ?, semester_id = ?, status = 'active'
                 WHERE curriculum_id = ?"
            )->execute([$yearLevel, $semester, $existing['curriculum_id']]);
        } else {
            $pdo->prepare(
                "INSERT INTO curriculum (program_id, course_id, course_code, year_level, semester_id, academic_year, status)
                 VALUES (?, ?, ?, ?, ?, '2024-2025', 'active')"
            )->execute([$programId, $subjectId, $subj['subject_code'], $yearLevel, $semester]);
        }

        // Sync placement back to subject for backward-compatibility with other queries
        $pdo->prepare("UPDATE subject SET program_id = ?, year_level = ?, semester = ?, updated_at = NOW() WHERE subject_id = ?")
            ->execute([$programId, $yearLevel, $semester, $subjectId]);

        echo json_encode(['success' => true, 'message' => 'Subject added to curriculum']);
    } catch (Exception $e) {
        error_log('Curriculum add: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add subject']);
    }
}

// ─── Edit a subject's year/semester placement ─────────────────────────────

function handleUpdate() {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $subjectId = (int)($data['subject_id'] ?? 0);
    $programId = (int)($data['program_id'] ?? 0);
    if (!$subjectId) { echo json_encode(['success' => false, 'message' => 'subject_id required']); return; }

    $yearLevel    = in_array($data['year_level'] ?? '', ['1','2','3','4']) ? (int)$data['year_level'] : null;
    $semester     = in_array((int)($data['semester'] ?? 0), [1,2,3]) ? (int)$data['semester'] : null;
    $units        = max(1, (int)($data['units']         ?? 3));
    $lectureHours = max(0, (int)($data['lecture_hours'] ?? 3));
    $labHours     = max(0, (int)($data['lab_hours']     ?? 0));
    $preReq       = trim($data['pre_requisite'] ?? '');

    try {
        $pdo = pdo();

        // Update curriculum table (year/semester placement)
        if ($programId) {
            $curRow = db()->fetchOne(
                "SELECT curriculum_id FROM curriculum WHERE program_id = ? AND course_id = ?",
                [$programId, $subjectId]
            );
            if ($curRow) {
                $pdo->prepare(
                    "UPDATE curriculum SET year_level = ?, semester_id = ? WHERE curriculum_id = ?"
                )->execute([$yearLevel, $semester, $curRow['curriculum_id']]);
            }
        }

        // Update subject (units, hours, prereq; sync year/semester for backward-compat)
        $pdo->prepare(
            "UPDATE subject
             SET year_level = ?, semester = ?, units = ?,
                 lecture_hours = ?, lab_hours = ?, pre_requisite = ?,
                 updated_at = NOW()
             WHERE subject_id = ?"
        )->execute([$yearLevel, $semester, $units, $lectureHours, $labHours, $preReq ?: null, $subjectId]);

        echo json_encode(['success' => true, 'message' => 'Subject updated']);
    } catch (Exception $e) {
        error_log('Curriculum update: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update subject']);
    }
}

// ─── Archive (deactivate) a subject from the curriculum ───────────────────

function handleArchive() {
    $data      = json_decode(file_get_contents('php://input'), true) ?? [];
    $subjectId = (int)($data['subject_id'] ?? 0);
    $programId = (int)($data['program_id'] ?? 0);
    if (!$subjectId) { echo json_encode(['success' => false, 'message' => 'subject_id required']); return; }

    // Check if subject is used in active offerings
    $inUse = db()->fetchOne(
        "SELECT COUNT(*) AS c FROM subject_offered WHERE subject_id = ? AND status != 'cancelled'",
        [$subjectId]
    )['c'] ?? 0;

    if ($inUse > 0) {
        echo json_encode(['success' => false, 'message' => "Cannot archive: subject has {$inUse} active offering(s)"]);
        return;
    }

    try {
        $pdo = pdo();

        // Remove from this program's curriculum
        if ($programId) {
            $pdo->prepare(
                "UPDATE curriculum SET status = 'inactive' WHERE program_id = ? AND course_id = ?"
            )->execute([$programId, $subjectId]);
        }

        // Deactivate the subject itself
        $pdo->prepare("UPDATE subject SET status = 'inactive', updated_at = NOW() WHERE subject_id = ?")
            ->execute([$subjectId]);

        echo json_encode(['success' => true, 'message' => 'Subject archived']);
    } catch (Exception $e) {
        error_log('Curriculum archive: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to archive subject']);
    }
}
