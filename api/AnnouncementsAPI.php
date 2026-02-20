<?php
/**
 * CIT-LMS Announcements API
 * CRUD for instructor announcements
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
    case 'instructor-list': getInstructorAnnouncements(); break;
    case 'create': createAnnouncement(); break;
    case 'update': updateAnnouncement(); break;
    case 'delete': deleteAnnouncement(); break;
    case 'student-list': getStudentAnnouncements(); break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getInstructorAnnouncements() {
    $userId = Auth::id();
    $subjectId = $_GET['subject_id'] ?? '';
    $status = $_GET['status'] ?? '';

    try {
        $sql = "SELECT a.*, s.subject_code, s.subject_name
                FROM announcement a
                LEFT JOIN subject_offered so ON a.subject_offered_id = so.subject_offered_id
                LEFT JOIN subject s ON so.subject_id = s.subject_id
                WHERE a.user_id = ?";
        $params = [$userId];

        if ($subjectId) {
            $sql .= " AND so.subject_id = ?";
            $params[] = $subjectId;
        }
        if ($status) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY a.created_at DESC";
        $data = db()->fetchAll($sql, $params);
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

function createAnnouncement() {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = Auth::id();
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $status = $input['status'] ?? 'published';
    $subjectId = $input['subject_id'] ?? null;

    if (!$title || !$content) {
        echo json_encode(['success' => false, 'message' => 'Title and content are required']);
        return;
    }

    try {
        // If subject_id provided, find the subject_offered_id for this instructor
        $subjectOfferedId = null;
        if ($subjectId) {
            $offering = db()->fetchOne(
                "SELECT so.subject_offered_id FROM subject_offered so
                 JOIN section sec ON sec.subject_offered_id = so.subject_offered_id
                 WHERE so.subject_id = ? AND sec.instructor_id = ? AND so.status = 'open'
                 LIMIT 1",
                [$subjectId, $userId]
            );
            if ($offering) $subjectOfferedId = $offering['subject_offered_id'];
        }

        $stmt = pdo()->prepare(
            "INSERT INTO announcement (user_id, subject_offered_id, title, content, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$userId, $subjectOfferedId, $title, $content, $status]);

        echo json_encode(['success' => true, 'message' => 'Announcement created']);
    } catch (PDOException $e) {
        error_log("Announcement create error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create announcement']);
    }
}

function updateAnnouncement() {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = Auth::id();
    $annId = $input['announcement_id'] ?? 0;
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $status = $input['status'] ?? 'published';

    if (!$annId || !$title || !$content) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    try {
        $stmt = pdo()->prepare(
            "UPDATE announcement SET title = ?, content = ?, status = ?, updated_at = NOW()
             WHERE announcement_id = ? AND user_id = ?"
        );
        $stmt->execute([$title, $content, $status, $annId, $userId]);

        echo json_encode(['success' => true, 'message' => 'Announcement updated']);
    } catch (PDOException $e) {
        error_log("Announcement update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update announcement']);
    }
}

function deleteAnnouncement() {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = Auth::id();
    $annId = $input['announcement_id'] ?? 0;

    if (!$annId) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID required']);
        return;
    }

    try {
        $stmt = pdo()->prepare("DELETE FROM announcement WHERE announcement_id = ? AND user_id = ?");
        $stmt->execute([$annId, $userId]);
        echo json_encode(['success' => true, 'message' => 'Announcement deleted']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete announcement']);
    }
}

function getStudentAnnouncements() {
    $userId = Auth::id();
    $subjectId = $_GET['subject_id'] ?? '';

    try {
        $sql = "SELECT a.*, s.subject_id, s.subject_code, s.subject_name,
                    u.first_name as author_first, u.last_name as author_last
                FROM announcement a
                LEFT JOIN subject_offered so ON a.subject_offered_id = so.subject_offered_id
                LEFT JOIN subject s ON so.subject_id = s.subject_id
                JOIN users u ON a.user_id = u.users_id
                WHERE a.status = 'published'
                AND (a.subject_offered_id IS NULL
                     OR a.subject_offered_id IN (
                         SELECT ss.subject_offered_id FROM student_subject ss WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
                     ))";
        $params = [$userId];

        if ($subjectId) {
            $sql .= " AND so.subject_id = ?";
            $params[] = $subjectId;
        }

        $sql .= " ORDER BY a.created_at DESC";
        $data = db()->fetchAll($sql, $params);
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
