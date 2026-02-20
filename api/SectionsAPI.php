<?php
/**
 * Sections API - CRUD for section management
 * Each section can hold multiple subjects (via section_subject junction table)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':               handleList();              break;
    case 'create':             handleCreate();            break;
    case 'update':             handleUpdate();            break;
    case 'delete':             handleDelete();            break;
    case 'add-subject':        handleAddSubject();        break;
    case 'remove-subject':     handleRemoveSubject();     break;
    case 'available-subjects': handleAvailableSubjects(); break;
    case 'instructors':        handleInstructors();       break;
    case 'students':           handleStudents();          break;
    case 'unenroll':           handleUnenroll();          break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ─── List all sections with their subjects ─────────────────────────────────

function handleList() {
    $sections = db()->fetchAll(
        "SELECT sec.section_id, sec.section_name, sec.enrollment_code,
                sec.max_students, sec.status, sec.created_at,
                COUNT(DISTINCT ss_stud.student_subject_id) AS student_count
         FROM section sec
         LEFT JOIN student_subject ss_stud ON ss_stud.section_id = sec.section_id
                                          AND ss_stud.status = 'enrolled'
         GROUP BY sec.section_id
         ORDER BY sec.section_name"
    );

    // Attach subjects list to each section
    foreach ($sections as &$sec) {
        $sec['subjects'] = db()->fetchAll(
            "SELECT ss.section_subject_id, ss.subject_offered_id,
                    ss.schedule, ss.room,
                    s.subject_id, s.subject_code, s.subject_name, s.units,
                    CONCAT(u.first_name, ' ', u.last_name) AS instructor_name
             FROM section_subject ss
             JOIN subject_offered so ON so.subject_offered_id = ss.subject_offered_id
             JOIN subject s ON s.subject_id = so.subject_id
             LEFT JOIN users u ON u.users_id = so.user_teacher_id
             WHERE ss.section_id = ? AND ss.status = 'active'
             ORDER BY s.subject_code",
            [$sec['section_id']]
        );
    }

    echo json_encode(['success' => true, 'data' => $sections]);
}

// ─── Generate unique enrollment code ──────────────────────────────────────

function generateEnrollmentCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    do {
        $code = '';
        for ($i = 0; $i < 3; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        $code .= '-' . str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $exists = db()->fetchOne("SELECT section_id FROM section WHERE enrollment_code = ?", [$code]);
    } while ($exists);
    return $code;
}

// ─── Create section ────────────────────────────────────────────────────────

function handleCreate() {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim($data['section_name'] ?? '');
    $maxStudents = max(1, (int)($data['max_students'] ?? 40));

    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Section name is required']);
        return;
    }

    $code = generateEnrollmentCode();

    try {
        $pdo = pdo();
        $pdo->prepare(
            "INSERT INTO section (section_name, enrollment_code, max_students, status)
             VALUES (?, ?, ?, 'active')"
        )->execute([$name, $code, $maxStudents]);

        $sectionId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'message' => 'Section created', 'data' => ['section_id' => $sectionId, 'enrollment_code' => $code]]);
    } catch (Exception $e) {
        error_log('Create section: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create section']);
    }
}

// ─── Update section ────────────────────────────────────────────────────────

function handleUpdate() {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['section_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Section ID required']); return; }

    $name = trim($data['section_name'] ?? '');
    $maxStudents = max(1, (int)($data['max_students'] ?? 40));
    $status = in_array($data['status'] ?? '', ['active','inactive']) ? $data['status'] : 'active';

    try {
        pdo()->prepare("UPDATE section SET section_name=?, max_students=?, status=? WHERE section_id=?")
            ->execute([$name, $maxStudents, $status, $id]);
        echo json_encode(['success' => true, 'message' => 'Section updated']);
    } catch (Exception $e) {
        error_log('Update section: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update section']);
    }
}

// ─── Delete (deactivate) section ───────────────────────────────────────────

function handleDelete() {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['section_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Section ID required']); return; }

    try {
        $pdo = pdo();
        // Remove all student enrollments in this section first
        $pdo->prepare("DELETE FROM student_subject WHERE section_id = ?")->execute([$id]);
        // Then deactivate the section
        $pdo->prepare("UPDATE section SET status = 'inactive' WHERE section_id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Section deleted']);
    } catch (Exception $e) {
        error_log('Delete section: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete section']);
    }
}

// ─── Add a subject to a section ────────────────────────────────────────────

function handleAddSubject() {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $sectionId      = (int)($data['section_id']        ?? 0);
    $offeredId      = (int)($data['subject_offered_id'] ?? 0);
    $schedule       = trim($data['schedule'] ?? '');
    $room           = trim($data['room']     ?? '');

    if (!$sectionId || !$offeredId) {
        echo json_encode(['success' => false, 'message' => 'section_id and subject_offered_id are required']);
        return;
    }

    // Check section exists
    if (!db()->fetchOne("SELECT section_id FROM section WHERE section_id = ?", [$sectionId])) {
        echo json_encode(['success' => false, 'message' => 'Section not found']);
        return;
    }

    // Check not already added
    $exists = db()->fetchOne(
        "SELECT section_subject_id FROM section_subject WHERE section_id = ? AND subject_offered_id = ?",
        [$sectionId, $offeredId]
    );
    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'Subject already added to this section']);
        return;
    }

    try {
        pdo()->prepare(
            "INSERT INTO section_subject (section_id, subject_offered_id, schedule, room, status)
             VALUES (?, ?, ?, ?, 'active')"
        )->execute([$sectionId, $offeredId, $schedule ?: null, $room ?: null]);

        echo json_encode(['success' => true, 'message' => 'Subject added to section']);
    } catch (Exception $e) {
        error_log('Add subject to section: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add subject']);
    }
}

// ─── Remove a subject from a section ──────────────────────────────────────

function handleRemoveSubject() {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $sectionSubjectId = (int)($data['section_subject_id'] ?? 0);

    if (!$sectionSubjectId) {
        echo json_encode(['success' => false, 'message' => 'section_subject_id required']);
        return;
    }

    $row = db()->fetchOne(
        "SELECT section_id, subject_offered_id FROM section_subject WHERE section_subject_id = ?",
        [$sectionSubjectId]
    );
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Not found']);
        return;
    }

    try {
        pdo()->prepare("DELETE FROM section_subject WHERE section_subject_id = ?")->execute([$sectionSubjectId]);
        echo json_encode(['success' => true, 'message' => 'Subject removed from section']);
    } catch (Exception $e) {
        error_log('Remove subject from section: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to remove subject']);
    }
}

// ─── Get subject offerings not yet in this section ─────────────────────────

function handleAvailableSubjects() {
    $sectionId = (int)($_GET['section_id'] ?? 0);
    if (!$sectionId) {
        echo json_encode(['success' => false, 'message' => 'section_id required']);
        return;
    }

    $subjects = db()->fetchAll(
        "SELECT so.subject_offered_id,
                s.subject_code, s.subject_name, s.units,
                CONCAT(u.first_name, ' ', u.last_name) AS instructor_name
         FROM subject_offered so
         JOIN subject s ON s.subject_id = so.subject_id
         LEFT JOIN users u ON u.users_id = so.user_teacher_id
         WHERE so.status = 'open'
           AND so.subject_offered_id NOT IN (
               SELECT subject_offered_id FROM section_subject WHERE section_id = ?
           )
         ORDER BY s.subject_code",
        [$sectionId]
    );

    echo json_encode(['success' => true, 'data' => $subjects]);
}

// ─── List active instructors ───────────────────────────────────────────────

function handleInstructors() {
    $instructors = db()->fetchAll(
        "SELECT users_id, first_name, last_name, employee_id
         FROM users WHERE role = 'instructor' AND status = 'active'
         ORDER BY last_name, first_name"
    );
    echo json_encode(['success' => true, 'data' => $instructors]);
}

// ─── List students enrolled in a section ───────────────────────────────────

function handleStudents() {
    $sectionId = (int)($_GET['section_id'] ?? 0);
    if (!$sectionId) { echo json_encode(['success' => false, 'message' => 'section_id required']); return; }

    $students = db()->fetchAll(
        "SELECT ss.student_subject_id, ss.user_student_id, ss.subject_offered_id, ss.status,
                u.first_name, u.last_name, u.student_id,
                s.subject_code, s.subject_name
         FROM student_subject ss
         JOIN users u ON u.users_id = ss.user_student_id
         JOIN subject_offered so ON so.subject_offered_id = ss.subject_offered_id
         JOIN subject s ON s.subject_id = so.subject_id
         WHERE ss.section_id = ? AND ss.status = 'enrolled'
         ORDER BY u.last_name, u.first_name, s.subject_code",
        [$sectionId]
    );
    echo json_encode(['success' => true, 'data' => $students ?: []]);
}

// ─── Unenroll a student from a subject in a section ────────────────────────

function handleUnenroll() {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $studentSubjectId = (int)($data['student_subject_id'] ?? 0);

    if (!$studentSubjectId) { echo json_encode(['success' => false, 'message' => 'student_subject_id required']); return; }

    try {
        pdo()->prepare("DELETE FROM student_subject WHERE student_subject_id = ?")->execute([$studentSubjectId]);
        echo json_encode(['success' => true, 'message' => 'Student unenrolled']);
    } catch (Exception $e) {
        error_log('Unenroll: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to unenroll']);
    }
}
