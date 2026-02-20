<?php
/**
 * Curriculum API - Read-only curriculum view
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

switch ($action) {
    case 'programs':
        $programs = db()->fetchAll("SELECT program_id, program_name, program_code FROM program WHERE status = 'active' ORDER BY program_code");
        echo json_encode(['success' => true, 'data' => $programs]);
        break;

    case 'view':
        $programId = (int)($_GET['program_id'] ?? 0);
        if (!$programId) {
            echo json_encode(['success' => false, 'message' => 'Program ID required']);
            break;
        }
        $subjects = db()->fetchAll(
            "SELECT s.subject_id, s.subject_code, s.subject_name, s.units, s.year_level, s.semester, s.description, s.lecture_hours, s.lab_hours, s.pre_requisite
             FROM subject s
             WHERE s.program_id = ? AND s.status = 'active'
             ORDER BY s.year_level, FIELD(s.semester, '1st', '2nd', 'summer'), s.subject_code",
            [$programId]
        );
        echo json_encode(['success' => true, 'data' => $subjects]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
