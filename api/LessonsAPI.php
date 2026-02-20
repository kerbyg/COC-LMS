<?php
/**
 * CIT-LMS Lessons API
 * Fixed to match actual database schema: student_subject, user_student_id, lessons
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
    case 'get':
        getLesson();
        break;
    case 'list':
        getLessons();
        break;
    case 'complete':
        markComplete();
        break;
    case 'instructor-lessons':
        getInstructorLessons();
        break;
    case 'create':
        createLesson();
        break;
    case 'update':
        updateLesson();
        break;
    case 'delete':
        deleteLesson();
        break;
    case 'students':
        getStudentsForOffering();
        break;
    case 'subjects':
        getInstructorSubjects();
        break;
    case 'materials':
        getMaterials();
        break;
    case 'add-link':
        addLinkMaterial();
        break;
    case 'upload-material':
        uploadMaterial();
        break;
    case 'delete-material':
        deleteMaterial();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get a single lesson with full details (for student lesson view)
 */
function getLesson() {
    $lessonId = (int)($_GET['lessons_id'] ?? 0);
    $userId = Auth::id();

    if (!$lessonId) {
        echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
        return;
    }

    try {
        // Get lesson with subject info and instructor name
        $lesson = db()->fetchOne(
            "SELECT l.*, s.subject_code, s.subject_name, so.subject_offered_id,
                CONCAT(u.first_name, ' ', u.last_name) as instructor_name
             FROM lessons l
             JOIN subject s ON l.subject_id = s.subject_id
             JOIN subject_offered so ON so.subject_id = s.subject_id
             LEFT JOIN users u ON l.user_teacher_id = u.users_id
             WHERE l.lessons_id = ? AND l.status = 'published' LIMIT 1",
            [$lessonId]
        );

        if (!$lesson) {
            echo json_encode(['success' => false, 'message' => 'Lesson not found']);
            return;
        }

        // Verify enrollment
        $enrollment = db()->fetchOne(
            "SELECT * FROM student_subject WHERE user_student_id = ? AND subject_offered_id = ? AND status = 'enrolled'",
            [$userId, $lesson['subject_offered_id']]
        );
        if (!$enrollment) {
            echo json_encode(['success' => false, 'message' => 'Not enrolled']);
            return;
        }

        // Check prerequisite
        $prerequisiteMet = true;
        $prerequisiteLesson = null;
        if (!empty($lesson['prerequisite_lessons_id'])) {
            $prerequisiteLesson = db()->fetchOne("SELECT lessons_id, lesson_title FROM lessons WHERE lessons_id = ?", [$lesson['prerequisite_lessons_id']]);
            $prereqProgress = db()->fetchOne(
                "SELECT * FROM student_progress WHERE user_student_id = ? AND lessons_id = ? AND status = 'completed'",
                [$userId, $lesson['prerequisite_lessons_id']]
            );
            $prerequisiteMet = !empty($prereqProgress);
        }

        // Get progress
        $progress = db()->fetchOne("SELECT * FROM student_progress WHERE user_student_id = ? AND lessons_id = ?", [$userId, $lessonId]);
        $isCompleted = $progress && $progress['status'] == 'completed';

        // Get all lessons for sidebar + prev/next nav
        $allLessons = db()->fetchAll(
            "SELECT lessons_id, lesson_title as title, lesson_order as order_number,
                (SELECT CASE WHEN status = 'completed' THEN 1 ELSE 0 END FROM student_progress WHERE lessons_id = l.lessons_id AND user_student_id = ?) as is_completed
             FROM lessons l WHERE subject_id = ? AND status = 'published' ORDER BY lesson_order",
            [$userId, $lesson['subject_id']]
        );

        $currentIndex = null;
        foreach ($allLessons as $i => $item) {
            if ($item['lessons_id'] == $lessonId) { $currentIndex = $i; break; }
        }
        $prevLesson = ($currentIndex !== null && $currentIndex > 0) ? $allLessons[$currentIndex - 1] : null;
        $nextLesson = ($currentIndex !== null && $currentIndex < count($allLessons) - 1) ? $allLessons[$currentIndex + 1] : null;

        // Get materials
        $materials = db()->fetchAll("SELECT * FROM lesson_materials WHERE lessons_id = ? ORDER BY uploaded_at", [$lessonId]) ?: [];

        echo json_encode([
            'success' => true,
            'data' => [
                'lesson' => $lesson,
                'is_completed' => $isCompleted,
                'completed_at' => $progress['completed_at'] ?? null,
                'prerequisite_met' => $prerequisiteMet,
                'prerequisite_lesson' => $prerequisiteLesson,
                'all_lessons' => $allLessons,
                'prev_lesson' => $prevLesson,
                'next_lesson' => $nextLesson,
                'materials' => $materials
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get lessons for a subject
 */
function getLessons() {
    $subjectOfferedId = $_GET['subject_id'] ?? 0;
    $userId = Auth::id();

    try {
        $lessons = db()->fetchAll(
            "SELECT
                l.lessons_id,
                l.lesson_title as title,
                l.lesson_description as description,
                l.lesson_order as order_number,
                l.created_at,
                CASE WHEN sp.status = 'completed' THEN 1 ELSE 0 END as is_completed,
                sp.completed_at,
                ql.quiz_id as linked_quiz_id
            FROM lessons l
            LEFT JOIN student_progress sp
                ON l.lessons_id = sp.lessons_id AND sp.user_student_id = ?
            LEFT JOIN quiz_lessons ql ON l.lessons_id = ql.lessons_id
            WHERE l.subject_id = (SELECT subject_id FROM subject_offered WHERE subject_offered_id = ?)
            AND l.status = 'published'
            ORDER BY l.lesson_order",
            [$userId, $subjectOfferedId]
        );

        // Sequential lock: lesson N is locked if lesson N-1 is not completed
        for ($i = 0; $i < count($lessons); $i++) {
            $lessons[$i]['is_locked'] = ($i > 0 && !$lessons[$i - 1]['is_completed']) ? 1 : 0;
        }

        echo json_encode(['success' => true, 'data' => $lessons]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Mark lesson as complete
 */
function markComplete() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $lessonId = $input['lessons_id'] ?? 0;
    $userId = Auth::id();
    
    try {
        // 1. FIXED: Verify student is enrolled using 'student_subject'
        $enrollment = db()->fetchOne(
            "SELECT ss.* FROM student_subject ss
             JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
             JOIN lessons l ON so.subject_id = l.subject_id
             WHERE l.lessons_id = ? AND ss.user_student_id = ? AND ss.status = 'enrolled'",
            [$lessonId, $userId]
        );
        
        if (!$enrollment) {
            echo json_encode(['success' => false, 'message' => 'Lesson not found or not enrolled']);
            return;
        }
        
        // 2. FIXED: Check 'student_progress' using 'user_student_id'
        $existing = db()->fetchOne(
            "SELECT * FROM student_progress WHERE user_student_id = ? AND lessons_id = ?",
            [$userId, $lessonId]
        );
        
        if ($existing && $existing['status'] == 'completed') {
            echo json_encode(['success' => true, 'message' => 'Already completed']);
            return;
        }
        
        // Get subject_id from lesson
        $lesson = db()->fetchOne("SELECT subject_id FROM lessons WHERE lessons_id = ?", [$lessonId]);

        if ($existing) {
            db()->execute(
                "UPDATE student_progress SET status = 'completed', completed_at = NOW()
                 WHERE user_student_id = ? AND lessons_id = ?",
                [$userId, $lessonId]
            );
        } else {
            db()->execute(
                "INSERT INTO student_progress (user_student_id, lessons_id, subject_id, status, completed_at, started_at)
                 VALUES (?, ?, ?, 'completed', NOW(), NOW())",
                [$userId, $lessonId, $lesson['subject_id']]
            );
        }

        echo json_encode(['success' => true, 'message' => 'Lesson marked as complete']);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// ─── Instructor Endpoints ─────────────────────────────────

function getInstructorSubjects() {
    $userId = Auth::id();
    $subjects = db()->fetchAll(
        "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name
         FROM subject_offered so
         JOIN subject s ON so.subject_id = s.subject_id
         WHERE so.user_teacher_id = ?
         ORDER BY s.subject_code",
        [$userId]
    );
    echo json_encode(['success' => true, 'data' => $subjects]);
}

function getInstructorLessons() {
    $userId = Auth::id();
    $subjectId = $_GET['subject_id'] ?? '';

    $where = '';
    $params = [$userId];
    if ($subjectId) {
        $where = 'AND l.subject_id = ?';
        $params[] = $subjectId;
    }

    $lessons = db()->fetchAll(
        "SELECT l.*, s.subject_code, s.subject_name,
            (SELECT COUNT(*) FROM student_progress sp WHERE sp.lessons_id = l.lessons_id AND sp.status = 'completed') as completions
         FROM lessons l
         JOIN subject s ON l.subject_id = s.subject_id
         WHERE s.subject_id IN (
             SELECT DISTINCT so.subject_id FROM subject_offered so
             WHERE so.user_teacher_id = ?
         ) $where
         ORDER BY s.subject_code, l.lesson_order",
        $params
    );
    echo json_encode(['success' => true, 'data' => $lessons]);
}

function createLesson() {
    $data = json_decode(file_get_contents('php://input'), true);
    $subjectId = (int)($data['subject_id'] ?? 0);
    $title = trim($data['lesson_title'] ?? '');
    $description = trim($data['lesson_description'] ?? '');
    $content = $data['lesson_content'] ?? '';
    $status = $data['status'] ?? 'draft';

    if (!$subjectId || !$title) {
        echo json_encode(['success' => false, 'message' => 'Subject and title are required']);
        return;
    }

    // Get next order
    $maxOrder = db()->fetchOne("SELECT MAX(lesson_order) as m FROM lessons WHERE subject_id = ?", [$subjectId])['m'] ?? 0;

    $teacherId = Auth::id();

    try {
        pdo()->prepare(
            "INSERT INTO lessons (subject_id, user_teacher_id, lesson_title, lesson_description, lesson_content, lesson_order, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        )->execute([$subjectId, $teacherId, $title, $description, $content, $maxOrder + 1, $status]);
        echo json_encode(['success' => true, 'message' => 'Lesson created', 'data' => ['id' => pdo()->lastInsertId()]]);
    } catch (Exception $e) {
        error_log('Create lesson: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create lesson']);
    }
}

function updateLesson() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['lessons_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Lesson ID required']); return; }

    $title = trim($data['lesson_title'] ?? '');
    $description = trim($data['lesson_description'] ?? '');
    $content = $data['lesson_content'] ?? '';
    $status = $data['status'] ?? 'draft';

    try {
        pdo()->prepare("UPDATE lessons SET lesson_title=?, lesson_description=?, lesson_content=?, status=?, updated_at=NOW() WHERE lessons_id=?")
            ->execute([$title, $description, $content, $status, $id]);
        echo json_encode(['success' => true, 'message' => 'Lesson updated']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to update lesson']);
    }
}

function deleteLesson() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['lessons_id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Lesson ID required']); return; }

    try {
        pdo()->prepare("DELETE FROM student_progress WHERE lessons_id = ?")->execute([$id]);
        pdo()->prepare("DELETE FROM lessons WHERE lessons_id = ?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Lesson deleted']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete lesson']);
    }
}

function getStudentsForOffering() {
    $offeringId = (int)($_GET['subject_offered_id'] ?? 0);
    if (!$offeringId) { echo json_encode(['success' => true, 'data' => []]); return; }

    $students = db()->fetchAll(
        "SELECT u.users_id, u.first_name, u.last_name, u.email, u.student_id,
            ss.enrollment_date, ss.status as enrollment_status,
            (SELECT COUNT(*) FROM student_progress sp
             JOIN lessons l ON sp.lessons_id = l.lessons_id
             WHERE sp.user_student_id = u.users_id AND l.subject_id = so.subject_id AND sp.status = 'completed') as completed_lessons,
            (SELECT COUNT(*) FROM lessons l WHERE l.subject_id = so.subject_id AND l.status = 'published') as total_lessons,
            (SELECT ROUND(AVG(qa.percentage),1) FROM student_quiz_attempts qa
             JOIN quiz q ON qa.quiz_id = q.quiz_id
             WHERE qa.user_student_id = u.users_id AND q.subject_id = so.subject_id AND qa.status = 'completed') as avg_score
         FROM student_subject ss
         JOIN users u ON ss.user_student_id = u.users_id
         JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
         WHERE ss.subject_offered_id = ? AND ss.status = 'enrolled'
         ORDER BY u.last_name, u.first_name",
        [$offeringId]
    );

    foreach ($students as &$s) {
        $s['progress'] = $s['total_lessons'] > 0 ? round(($s['completed_lessons'] / $s['total_lessons']) * 100) : 0;
    }

    echo json_encode(['success' => true, 'data' => $students]);
}

// ─── Material Management Endpoints ──────────────────────────

/**
 * Verify instructor owns a lesson
 */
function verifyLessonOwner($lessonId) {
    $lesson = db()->fetchOne(
        "SELECT lessons_id, subject_id FROM lessons WHERE lessons_id = ? AND user_teacher_id = ?",
        [$lessonId, Auth::id()]
    );
    return $lesson;
}

/**
 * Get materials for a lesson
 */
function getMaterials() {
    $lessonId = (int)($_GET['lessons_id'] ?? 0);
    if (!$lessonId) {
        echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
        return;
    }

    $materials = db()->fetchAll(
        "SELECT * FROM lesson_materials WHERE lessons_id = ? ORDER BY uploaded_at DESC",
        [$lessonId]
    );
    echo json_encode(['success' => true, 'data' => $materials ?: []]);
}

/**
 * Add a link material (YouTube, Vimeo, external URL)
 */
function addLinkMaterial() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POST required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $lessonId = (int)($data['lessons_id'] ?? 0);
    $url = trim($data['url'] ?? '');
    $title = trim($data['title'] ?? '');

    if (!$lessonId || !$url) {
        echo json_encode(['success' => false, 'message' => 'Lesson ID and URL are required']);
        return;
    }

    if (!verifyLessonOwner($lessonId)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    // Auto-detect title from URL if not provided
    if (!$title) {
        if (preg_match('/youtube\.com|youtu\.be/i', $url)) {
            $title = 'YouTube Video';
        } elseif (preg_match('/vimeo\.com/i', $url)) {
            $title = 'Vimeo Video';
        } else {
            $title = 'External Link';
        }
    }

    // Determine link subtype for display
    $fileType = 'link';
    if (preg_match('/youtube\.com|youtu\.be/i', $url)) {
        $fileType = 'youtube';
    } elseif (preg_match('/vimeo\.com/i', $url)) {
        $fileType = 'vimeo';
    }

    try {
        pdo()->prepare(
            "INSERT INTO lesson_materials (lessons_id, file_name, original_name, file_path, file_type, file_size, material_type, uploaded_at)
             VALUES (?, ?, ?, ?, ?, 0, 'link', NOW())"
        )->execute([$lessonId, $fileType, $title, $url, $fileType]);

        echo json_encode(['success' => true, 'message' => 'Link added', 'data' => ['material_id' => pdo()->lastInsertId()]]);
    } catch (Exception $e) {
        error_log('Add link material: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to add link']);
    }
}

/**
 * Upload a file material (PDF, image, document)
 */
function uploadMaterial() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POST required']);
        return;
    }

    // For file uploads, Content-Type is multipart/form-data, not JSON
    $lessonId = (int)($_POST['lessons_id'] ?? 0);
    if (!$lessonId) {
        echo json_encode(['success' => false, 'message' => 'Lesson ID required']);
        return;
    }

    if (!verifyLessonOwner($lessonId)) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }

    $file = $_FILES['file'];
    $maxFileSize = 10 * 1024 * 1024; // 10MB

    $allowedTypes = [
        'application/pdf' => 'document',
        'image/jpeg' => 'image',
        'image/png' => 'image',
        'image/gif' => 'image',
        'image/webp' => 'image',
        'application/msword' => 'document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
        'application/vnd.ms-powerpoint' => 'document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'document',
        'application/vnd.ms-excel' => 'document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'document',
        'text/plain' => 'document',
        'application/zip' => 'other'
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Upload failed (error code: ' . $file['error'] . ')']);
        return;
    }

    if ($file['size'] > $maxFileSize) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 10MB.']);
        return;
    }

    $mimeType = mime_content_type($file['tmp_name']);
    if (!isset($allowedTypes[$mimeType])) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed: ' . $mimeType]);
        return;
    }

    $materialType = $allowedTypes[$mimeType];
    $uploadDir = __DIR__ . '/../uploads/materials/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'material_' . $lessonId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        return;
    }

    try {
        pdo()->prepare(
            "INSERT INTO lesson_materials (lessons_id, file_name, original_name, file_path, file_type, file_size, material_type, uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        )->execute([$lessonId, $fileName, $file['name'], 'uploads/materials/' . $fileName, $materialType, $file['size'], $materialType]);

        echo json_encode(['success' => true, 'message' => 'File uploaded', 'data' => [
            'material_id' => pdo()->lastInsertId(),
            'original_name' => $file['name'],
            'material_type' => $materialType,
            'file_size' => $file['size']
        ]]);
    } catch (Exception $e) {
        // Clean up file if DB insert fails
        if (file_exists($filePath)) unlink($filePath);
        error_log('Upload material: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to save material record']);
    }
}

/**
 * Delete a material (file + DB record)
 */
function deleteMaterial() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POST required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $materialId = (int)($data['material_id'] ?? 0);
    if (!$materialId) {
        echo json_encode(['success' => false, 'message' => 'Material ID required']);
        return;
    }

    // Get the material and verify ownership
    $material = db()->fetchOne("SELECT * FROM lesson_materials WHERE material_id = ?", [$materialId]);
    if (!$material) {
        echo json_encode(['success' => false, 'message' => 'Material not found']);
        return;
    }

    if (!verifyLessonOwner($material['lessons_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }

    // Delete physical file if it's not a link
    if ($material['material_type'] !== 'link' && !empty($material['file_path'])) {
        $filePath = __DIR__ . '/../' . $material['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    try {
        pdo()->prepare("DELETE FROM lesson_materials WHERE material_id = ?")->execute([$materialId]);
        echo json_encode(['success' => true, 'message' => 'Material deleted']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to delete material']);
    }
}