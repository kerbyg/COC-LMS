<?php
/**
 * Subject Offerings API - CRUD for subject offerings
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

// RBAC: enforce permission per action
$_soPerms = [
    'list'                => 'subject_offerings.view',
    'instructors'         => 'subject_offerings.view',
    'subjects'            => 'subject_offerings.view',
    'semesters'           => 'subject_offerings.view',
    'departments'         => 'subject_offerings.view',
    'programs'            => 'subject_offerings.view',
    'create'              => 'subject_offerings.create',
    'generate-offerings'  => 'subject_offerings.create',
    'assign'              => 'faculty_assignments.create',
    'bulk-assign'         => 'faculty_assignments.create',
    'update'              => 'subject_offerings.edit',
    'delete'              => 'subject_offerings.delete',
];
if (isset($_soPerms[$action]) && !Auth::can($_soPerms[$action])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => "Permission denied: {$_soPerms[$action]}"]);
    exit;
}

switch ($action) {
    case 'list':            handleList();           break;
    case 'create':          handleCreate();         break;
    case 'update':          handleUpdate();         break;
    case 'delete':          handleDelete();         break;
    case 'assign':          handleAssign();         break;
    case 'bulk-assign':     handleBulkAssign();     break;
    case 'instructors':     handleInstructors();    break;
    case 'generate-offerings': handleGenerateOfferings(); break;
    case 'subjects':        handleSubjects();       break;
    case 'semesters':       handleSemesters();      break;
    case 'departments':     handleDepartments();    break;
    case 'programs':        handlePrograms();       break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function handleList() {
    $semesterId = $_GET['semester_id'] ?? '';
    $programId  = (int)($_GET['program_id']  ?? 0);
    $instrId    = (int)($_GET['instructor_id'] ?? 0);

    // Build the correlated subquery that picks the best matching subject_offered per subject
    // Match by academic_year + sem_type_id instead of exact semester_id to handle duplicate semester rows
    $joinParams  = [];
    $semCondition = '';
    if ($semesterId) {
        $intSemId = (int)$semesterId;
        $semCondition = "AND so2.semester_id IN (
                SELECT s2.semester_id FROM semester s2
                JOIN semester s3 ON s3.semester_id = $intSemId
                WHERE s2.academic_year = s3.academic_year AND s2.semester_name = s3.semester_name
            )";
    }

    $whereConditions = [];
    $whereParams     = [];
    if ($programId) {
        $whereConditions[] = 's.program_id = ?';
        $whereParams[]     = $programId;
    }
    $where  = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    $params = array_merge($joinParams, $whereParams);

    // Start from `subject` (the curriculum) and LEFT JOIN to the best offering
    $offerings = db()->fetchAll(
        "SELECT s.subject_id, s.subject_code, s.subject_name, s.units,
                s.year_level, s.semester AS subject_semester,
                s.program_id, p.program_code, p.program_name,
                d.department_id, d.department_name,
                so.subject_offered_id, so.semester_id, so.user_teacher_id, so.status, so.batch,
                sem.semester_name, sem.academic_year,
                CONCAT(u.first_name, ' ', u.last_name) AS instructor_name,
                (SELECT COUNT(*) FROM section_subject ss2
                 WHERE ss2.subject_offered_id = so.subject_offered_id
                   AND ss2.status = 'active') AS section_count,
                (SELECT COUNT(*) FROM student_subject ss
                 WHERE ss.subject_offered_id = so.subject_offered_id
                   AND ss.status = 'enrolled') AS student_count
         FROM subject s
         LEFT JOIN program p  ON s.program_id    = p.program_id
         LEFT JOIN department d ON p.department_id = d.department_id
         LEFT JOIN subject_offered so ON so.subject_offered_id = (
             SELECT so2.subject_offered_id
             FROM subject_offered so2
             WHERE so2.subject_id = s.subject_id
               $semCondition
               AND so2.status != 'cancelled'
             ORDER BY (so2.user_teacher_id = $instrId) DESC,
                      (so2.user_teacher_id IS NOT NULL) DESC,
                      so2.semester_id DESC
             LIMIT 1
         )
         LEFT JOIN semester sem ON so.semester_id  = sem.semester_id
         LEFT JOIN users u      ON u.users_id       = so.user_teacher_id
         $where
         ORDER BY p.program_code, s.year_level, s.semester, s.subject_code",
        $params
    );
    echo json_encode(['success' => true, 'data' => $offerings]);
}

function handleCreate() {
    $data = json_decode(file_get_contents('php://input'), true);
    $subjectId  = (int)($data['subject_id']  ?? 0);
    $semesterId = (int)($data['semester_id'] ?? 0);
    $status     = $data['status'] ?? 'open';
    $validBatch = ['1st Year','2nd Year','3rd Year','4th Year'];
    $batch      = in_array($data['batch'] ?? '', $validBatch) ? $data['batch'] : null;

    if (!$subjectId || !$semesterId) {
        echo json_encode(['success' => false, 'message' => 'Subject and semester are required']);
        return;
    }

    $exists = db()->fetchOne("SELECT subject_offered_id FROM subject_offered WHERE subject_id = ? AND semester_id = ?", [$subjectId, $semesterId]);
    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'This subject is already offered in this semester']);
        return;
    }

    try {
        pdo()->prepare("INSERT INTO subject_offered (subject_id, semester_id, batch, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())")
            ->execute([$subjectId, $semesterId, $batch, $status]);
        echo json_encode(['success' => true, 'message' => 'Subject offering created', 'data' => ['id' => pdo()->lastInsertId()]]);
    } catch (Exception $e) {
        error_log('Create offering: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create offering']);
    }
}

function handleUpdate() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['subject_offered_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }

    $status     = $data['status'] ?? 'open';
    $validBatch = ['1st Year','2nd Year','3rd Year','4th Year'];
    $batch      = in_array($data['batch'] ?? '', $validBatch) ? $data['batch'] : null;

    try {
        pdo()->prepare("UPDATE subject_offered SET status = ?, batch = ?, updated_at = NOW() WHERE subject_offered_id = ?")
            ->execute([$status, $batch, $id]);
        echo json_encode(['success' => true, 'message' => 'Offering updated']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update']);
    }
}

function handleDelete() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['subject_offered_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID required']); return; }

    $sections = db()->fetchOne("SELECT COUNT(*) as c FROM section_subject WHERE subject_offered_id = ? AND status = 'active'", [$id])['c'] ?? 0;
    if ($sections > 0) {
        echo json_encode(['success' => false, 'message' => "Cannot cancel offering with $sections active section(s)"]);
        return;
    }

    try {
        pdo()->prepare("UPDATE subject_offered SET status = 'cancelled', updated_at = NOW() WHERE subject_offered_id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Offering cancelled']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed']);
    }
}

function handleAssign() {
    $data = json_decode(file_get_contents('php://input'), true);
    $offId  = (int)($data['subject_offered_id'] ?? 0);
    $userId = (int)($data['user_teacher_id']    ?? 0);

    if (!$offId) {
        echo json_encode(['success' => false, 'message' => 'subject_offered_id required']);
        return;
    }

    try {
        pdo()->prepare("UPDATE subject_offered SET user_teacher_id = ?, updated_at = NOW() WHERE subject_offered_id = ?")
            ->execute([$userId ?: null, $offId]);
        echo json_encode(['success' => true, 'message' => 'Instructor assigned']);
    } catch (Exception $e) {
        error_log('Assign instructor: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to assign instructor']);
    }
}

function handleInstructors() {
    $instructors = db()->fetchAll(
        "SELECT u.users_id, u.first_name, u.last_name, u.email, u.employee_id,
                u.department_id, u.program_id,
                d.department_name, d.department_code,
                p.program_name, p.program_code
         FROM users u
         LEFT JOIN department d ON u.department_id = d.department_id
         LEFT JOIN program    p ON u.program_id    = p.program_id
         WHERE u.role = 'instructor' AND u.status = 'active'
         ORDER BY u.last_name, u.first_name"
    );
    echo json_encode(['success' => true, 'data' => $instructors]);
}

function handleSubjects() {
    $programId = (int)($_GET['program_id'] ?? 0);
    if ($programId) {
        $subjects = db()->fetchAll(
            "SELECT subject_id, subject_code, subject_name, units FROM subject WHERE status = 'active' AND program_id = ? ORDER BY subject_code",
            [$programId]
        );
    } else {
        $subjects = db()->fetchAll("SELECT subject_id, subject_code, subject_name, units FROM subject WHERE status = 'active' ORDER BY subject_code");
    }
    echo json_encode(['success' => true, 'data' => $subjects]);
}

function handleDepartments() {
    $depts = db()->fetchAll(
        "SELECT department_id, department_name, department_code FROM department WHERE status = 'active' ORDER BY department_name"
    );
    echo json_encode(['success' => true, 'data' => $depts]);
}

function handlePrograms() {
    $deptId = (int)($_GET['department_id'] ?? 0);
    if ($deptId) {
        $programs = db()->fetchAll(
            "SELECT program_id, program_code, program_name, department_id FROM program WHERE status = 'active' AND department_id = ? ORDER BY program_code",
            [$deptId]
        );
    } else {
        $programs = db()->fetchAll(
            "SELECT program_id, program_code, program_name, department_id FROM program WHERE status = 'active' ORDER BY program_code"
        );
    }
    echo json_encode(['success' => true, 'data' => $programs]);
}

function handleSemesters() {
    $semesters = db()->fetchAll(
        "SELECT MIN(sem.semester_id) AS semester_id, sem.semester_name, sem.academic_year,
                st.sem_level,
                MAX(CASE sem.status WHEN 'active' THEN 'active' ELSE 'inactive' END) AS status
         FROM semester sem LEFT JOIN sem_type st ON sem.sem_type_id = st.sem_type_id
         GROUP BY sem.academic_year, sem.sem_type_id, sem.semester_name, st.sem_level
         ORDER BY sem.academic_year DESC, st.sem_level"
    );
    echo json_encode(['success' => true, 'data' => $semesters]);
}

function handleBulkAssign() {
    $data         = json_decode(file_get_contents('php://input'), true);
    $instructorId = (int)($data['instructor_id'] ?? 0);
    $semesterId   = (int)($data['semester_id']   ?? 0);
    $assignIds    = array_map('intval', $data['assign_subject_ids']   ?? []);
    $unassignIds  = array_map('intval', $data['unassign_subject_ids'] ?? []);

    if (!$instructorId) {
        echo json_encode(['success' => false, 'message' => 'instructor_id required']);
        return;
    }
    if (empty($assignIds) && empty($unassignIds)) {
        echo json_encode(['success' => false, 'message' => 'No changes to apply']);
        return;
    }

    // Resolve which semester to use for new offerings
    if (!$semesterId) {
        $latest = db()->fetchOne(
            "SELECT semester_id FROM semester ORDER BY academic_year DESC, semester_id DESC LIMIT 1"
        );
        $semesterId = $latest ? (int)$latest['semester_id'] : 0;
    }
    if (!$semesterId) {
        echo json_encode(['success' => false, 'message' => 'No semester found']);
        return;
    }

    try {
        $pdo = pdo();
        $pdo->beginTransaction();

        $assigned = 0;
        foreach ($assignIds as $subjectId) {
            // 1. Already have their own offering for this subject+semester? Skip.
            $mine = db()->fetchOne(
                "SELECT subject_offered_id FROM subject_offered
                  WHERE subject_id = ? AND semester_id = ? AND user_teacher_id = ? AND status = 'open' LIMIT 1",
                [$subjectId, $semesterId, $instructorId]
            );
            if ($mine) { $assigned++; continue; }

            // 2. Is there an unassigned offering? Claim it.
            $empty = db()->fetchOne(
                "SELECT subject_offered_id FROM subject_offered
                  WHERE subject_id = ? AND semester_id = ? AND user_teacher_id IS NULL AND status = 'open' LIMIT 1",
                [$subjectId, $semesterId]
            );
            if ($empty) {
                $pdo->prepare(
                    "UPDATE subject_offered SET user_teacher_id = ?, updated_at = NOW()
                      WHERE subject_offered_id = ?"
                )->execute([$instructorId, $empty['subject_offered_id']]);
            } else {
                // Another instructor owns all offerings — create a new one for this instructor
                $pdo->prepare(
                    "INSERT INTO subject_offered (subject_id, semester_id, user_teacher_id, status, created_at, updated_at)
                     VALUES (?, ?, ?, 'open', NOW(), NOW())"
                )->execute([$subjectId, $semesterId, $instructorId]);
            }
            $assigned++;
        }

        $unassigned = 0;
        foreach ($unassignIds as $subjectId) {
            // Only clear if still owned by this instructor (safety)
            $pdo->prepare(
                "UPDATE subject_offered SET user_teacher_id = NULL, updated_at = NOW()
                  WHERE subject_id = ? AND semester_id = ? AND user_teacher_id = ? AND status = 'open'"
            )->execute([$subjectId, $semesterId, $instructorId]);
            $unassigned++;
        }

        $pdo->commit();
        $total = $assigned + $unassigned;
        echo json_encode(['success' => true, 'message' => "Updated $total subject(s)"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Bulk assign: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to apply changes']);
    }
}

/**
 * Auto-create subject_offered rows for every curriculum subject that
 * does not yet have an 'open' offering for the given semester.
 *
 * POST /SubjectOfferingsAPI.php?action=generate-offerings
 * Body: { semester_id: int, program_id?: int }
 */
function handleGenerateOfferings() {
    $data       = json_decode(file_get_contents('php://input'), true);
    $semesterId = (int)($data['semester_id'] ?? 0);
    $programId  = (int)($data['program_id']  ?? 0);

    if (!$semesterId) {
        echo json_encode(['success' => false, 'message' => 'semester_id required']);
        return;
    }

    // Fetch all subjects (optionally filtered by program) that have no open
    // offering for the target semester yet.
    // IMPORTANT: $programId params go into WHERE (before NOT EXISTS),
    // $semesterId goes last (for the NOT EXISTS subquery's ? placeholder).
    $conditions = [];
    $params     = [];
    if ($programId) {
        $conditions[] = 's.program_id = ?';
        $params[]     = $programId;
    }
    $params[] = $semesterId; // used by NOT EXISTS subquery
    // Use "WHERE 1=1" so that optional AND conditions are always valid SQL
    $condStr = $conditions ? 'AND ' . implode(' AND ', $conditions) : '';

    $missing = db()->fetchAll(
        "SELECT s.subject_id
         FROM subject s
         WHERE 1=1 $condStr
           AND NOT EXISTS (
               SELECT 1 FROM subject_offered so
               JOIN semester sx ON so.semester_id = sx.semester_id
               JOIN semester sy ON sy.semester_id = ?
               WHERE so.subject_id = s.subject_id
                 AND sx.academic_year  = sy.academic_year
                 AND sx.semester_name = sy.semester_name
                 AND so.status        = 'open'
           )",
        $params
    );

    if (empty($missing)) {
        echo json_encode(['success' => true, 'created' => 0,
                          'message' => 'All subjects already have offerings for this semester']);
        return;
    }

    try {
        $pdo  = pdo();
        $stmt = $pdo->prepare(
            "INSERT INTO subject_offered (subject_id, semester_id, status, created_at, updated_at)
             VALUES (?, ?, 'open', NOW(), NOW())"
        );
        $pdo->beginTransaction();
        foreach ($missing as $row) {
            $stmt->execute([$row['subject_id'], $semesterId]);
        }
        $pdo->commit();
        $created = count($missing);
        echo json_encode(['success' => true, 'created' => $created,
                          'message' => "Created $created offering(s)"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('generate-offerings: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to generate offerings']);
    }
}
