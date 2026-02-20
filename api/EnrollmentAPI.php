<?php
/**
 * CIT-LMS Enrollment API
 * Student enrollment via section code
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'enroll':      enrollByCode();   break;
    case 'preview':     previewCode();    break;
    case 'my-subjects': getMySubjects();  break;
    case 'drop':        dropSubject();    break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ─── Preview: return section + subjects before confirming enrollment ────────

function previewCode() {
    $input = json_decode(file_get_contents('php://input'), true);
    $code  = strtoupper(trim($input['enrollment_code'] ?? ''));

    if (!$code) {
        echo json_encode(['success' => false, 'message' => 'Enrollment code is required']);
        return;
    }

    $section = db()->fetchOne(
        "SELECT section_id, section_name, max_students,
                (SELECT COUNT(DISTINCT user_student_id) FROM student_subject
                 WHERE section_id = section.section_id AND status = 'enrolled') AS current_enrollment
         FROM section WHERE enrollment_code = ? AND status = 'active'",
        [$code]
    );

    if (!$section) {
        echo json_encode(['success' => false, 'message' => 'Invalid or inactive enrollment code']);
        return;
    }

    $subjects = db()->fetchAll(
        "SELECT ss.subject_offered_id, ss.schedule, ss.room,
                s.subject_code, s.subject_name, s.units,
                CONCAT(u.first_name, ' ', u.last_name) AS instructor_name
         FROM section_subject ss
         JOIN subject_offered so ON so.subject_offered_id = ss.subject_offered_id
         JOIN subject s ON s.subject_id = so.subject_id
         LEFT JOIN users u ON u.users_id = so.user_teacher_id
         WHERE ss.section_id = ? AND ss.status = 'active'
         ORDER BY s.subject_code",
        [$section['section_id']]
    );

    echo json_encode([
        'success' => true,
        'data' => [
            'section_id'   => $section['section_id'],
            'section_name' => $section['section_name'],
            'enrollment_code' => $code,
            'max_students' => $section['max_students'],
            'current_enrollment' => $section['current_enrollment'],
            'subjects'     => $subjects
        ]
    ]);
}

// ─── Enroll student in all subjects of a section ───────────────────────────

function enrollByCode() {
    $input  = json_decode(file_get_contents('php://input'), true);
    $code   = strtoupper(trim($input['enrollment_code'] ?? ''));
    $userId = Auth::id();

    if (!$code) {
        echo json_encode(['success' => false, 'message' => 'Enrollment code is required']);
        return;
    }

    try {
        // Find section
        $section = db()->fetchOne(
            "SELECT section_id, section_name, max_students,
                    (SELECT COUNT(DISTINCT user_student_id) FROM student_subject
                     WHERE section_id = section.section_id AND status = 'enrolled') AS current_enrollment
             FROM section WHERE enrollment_code = ? AND status = 'active'",
            [$code]
        );

        if (!$section) {
            echo json_encode(['success' => false, 'message' => 'Invalid enrollment code']);
            return;
        }

        // Check already enrolled in this section
        $alreadyInSection = db()->fetchOne(
            "SELECT student_subject_id FROM student_subject
             WHERE user_student_id = ? AND section_id = ? AND status = 'enrolled'
             LIMIT 1",
            [$userId, $section['section_id']]
        );
        if ($alreadyInSection) {
            echo json_encode(['success' => false, 'message' => 'Already enrolled in this section']);
            return;
        }

        // Check capacity
        if ($section['current_enrollment'] >= $section['max_students']) {
            echo json_encode(['success' => false, 'message' => 'Section is full']);
            return;
        }

        // Get all subjects in section
        $subjects = db()->fetchAll(
            "SELECT ss.subject_offered_id, so.subject_id, s.subject_code
             FROM section_subject ss
             JOIN subject_offered so ON so.subject_offered_id = ss.subject_offered_id
             JOIN subject s ON s.subject_id = so.subject_id
             WHERE ss.section_id = ? AND ss.status = 'active'",
            [$section['section_id']]
        );

        if (empty($subjects)) {
            echo json_encode(['success' => false, 'message' => 'This section has no subjects yet. Contact your administrator.']);
            return;
        }

        $pdo = pdo();
        $enrolled = 0;
        $skipped  = 0;

        foreach ($subjects as $subj) {
            // Skip if already enrolled in this subject_offered
            $exists = db()->fetchOne(
                "SELECT student_subject_id FROM student_subject
                 WHERE user_student_id = ? AND subject_offered_id = ? AND status = 'enrolled'",
                [$userId, $subj['subject_offered_id']]
            );
            if ($exists) { $skipped++; continue; }

            $pdo->prepare(
                "INSERT INTO student_subject (user_student_id, subject_offered_id, section_id, status, enrollment_date)
                 VALUES (?, ?, ?, 'enrolled', NOW())"
            )->execute([$userId, $subj['subject_offered_id'], $section['section_id']]);
            $enrolled++;
        }

        $msg = "Enrolled in {$section['section_name']} — {$enrolled} subject" . ($enrolled !== 1 ? 's' : '');
        if ($skipped > 0) $msg .= " ({$skipped} already enrolled)";

        echo json_encode(['success' => true, 'message' => $msg, 'enrolled' => $enrolled]);

    } catch (PDOException $e) {
        error_log("Enrollment error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Enrollment failed']);
    }
}

function getMySubjects() {
    $userId = Auth::id();

    try {
        $subjects = db()->fetchAll(
            "SELECT ss.student_subject_id, ss.subject_offered_id, ss.section_id, ss.enrollment_date, ss.status,
                s.subject_id, s.subject_code, s.subject_name, s.units,
                sec.section_name, sec.enrollment_code,
                secsubj.schedule, secsubj.room,
                CONCAT(u2.first_name, ' ', u2.last_name) AS instructor_name,
                (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = s.subject_id AND l.status = 'published') as total_lessons,
                (SELECT COUNT(*) FROM student_progress sp JOIN lessons l2 ON sp.lessons_id = l2.lessons_id
                 WHERE sp.user_student_id = ? AND l2.subject_id = s.subject_id AND sp.status = 'completed') as completed_lessons
             FROM student_subject ss
             JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
             JOIN subject s ON so.subject_id = s.subject_id
             LEFT JOIN users u2 ON u2.users_id = so.user_teacher_id
             LEFT JOIN section sec ON ss.section_id = sec.section_id
             LEFT JOIN section_subject secsubj ON secsubj.section_id = ss.section_id
                                              AND secsubj.subject_offered_id = ss.subject_offered_id
             WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
             ORDER BY s.subject_code",
            [$userId, $userId]
        );

        foreach ($subjects as &$s) {
            $s['progress'] = $s['total_lessons'] > 0 ? round(($s['completed_lessons'] / $s['total_lessons']) * 100) : 0;
        }

        echo json_encode(['success' => true, 'data' => $subjects]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function dropSubject() {
    $input = json_decode(file_get_contents('php://input'), true);
    $ssId = (int)($input['student_subject_id'] ?? 0);
    $userId = Auth::id();

    if (!$ssId) {
        echo json_encode(['success' => false, 'message' => 'Subject enrollment ID required']);
        return;
    }

    try {
        $record = db()->fetchOne(
            "SELECT section_id FROM student_subject WHERE student_subject_id = ? AND user_student_id = ?",
            [$ssId, $userId]
        );
        if (!$record) {
            echo json_encode(['success' => false, 'message' => 'Enrollment not found']);
            return;
        }

        $stmt = pdo()->prepare("UPDATE student_subject SET status = 'dropped' WHERE student_subject_id = ? AND user_student_id = ?");
        $stmt->execute([$ssId, $userId]);

        echo json_encode(['success' => true, 'message' => 'Subject dropped']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to drop subject']);
    }
}
