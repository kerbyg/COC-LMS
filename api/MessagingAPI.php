<?php
/**
 * Messaging API — student ↔ instructor direct messaging
 *
 * Actions:
 *   GET  ?action=contacts      — people I can start a chat with
 *   GET  ?action=threads       — my conversation threads (last msg + unread)
 *   GET  ?action=messages&with=USER_ID   — messages in a thread
 *   GET  ?action=unread_count  — total unread for badge
 *   POST ?action=send          — {receiver_id, content}
 *   POST ?action=mark_read     — {other_user_id}
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? 'threads';

switch ($action) {
    case 'contacts':     handleContacts();    break;
    case 'threads':      handleThreads();     break;
    case 'messages':     handleMessages();    break;
    case 'unread_count': handleUnreadCount(); break;
    case 'send':         handleSend();        break;
    case 'mark_read':    handleMarkRead();    break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

// ─── Contacts: who can I message? ─────────────────────────────────────────

function handleContacts() {
    $me   = Auth::id();
    $role = Auth::role();

    if ($role === 'student') {
        // Instructors teaching subjects in sections the student is enrolled in
        $contacts = db()->fetchAll(
            "SELECT DISTINCT u.users_id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.role,
                    so.subject_code, so.subject_name
             FROM enrollment e
             JOIN section_subject ss ON ss.section_id = e.section_id
             JOIN subject_offered so  ON so.subject_offered_id = ss.subject_offered_id
             JOIN users u             ON u.users_id = so.user_teacher_id
             WHERE e.student_id = ? AND e.status = 'active' AND u.users_id IS NOT NULL
             ORDER BY u.last_name, u.first_name",
            [$me]
        );
    } elseif ($role === 'instructor') {
        // Students enrolled in sections for this instructor's offerings
        $contacts = db()->fetchAll(
            "SELECT DISTINCT u.users_id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.role,
                    s.subject_code, s.subject_name
             FROM subject_offered so
             JOIN section_subject  ss ON ss.subject_offered_id = so.subject_offered_id
             JOIN enrollment        e  ON e.section_id = ss.section_id AND e.status = 'active'
             JOIN users             u  ON u.users_id = e.student_id
             JOIN subject           s  ON s.subject_id = so.subject_id
             WHERE so.user_teacher_id = ?
             ORDER BY u.last_name, u.first_name",
            [$me]
        );
    } else {
        // Admin / Dean can see all users
        $contacts = db()->fetchAll(
            "SELECT users_id, CONCAT(first_name, ' ', last_name) AS name, role,
                    '' AS subject_code, '' AS subject_name
             FROM users WHERE users_id != ? AND status = 'active' ORDER BY last_name, first_name",
            [$me]
        );
    }

    echo json_encode(['success' => true, 'data' => $contacts ?? []]);
}

// ─── Threads: all my conversations ────────────────────────────────────────

function handleThreads() {
    $me = Auth::id();

    $threads = db()->fetchAll(
        "SELECT
             u.users_id AS other_id,
             CONCAT(u.first_name, ' ', u.last_name) AS name,
             u.role,
             (SELECT m2.content
              FROM messages m2
              WHERE (m2.sender_id = ? AND m2.receiver_id = u.users_id)
                 OR (m2.sender_id = u.users_id AND m2.receiver_id = ?)
              ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
             (SELECT m3.created_at
              FROM messages m3
              WHERE (m3.sender_id = ? AND m3.receiver_id = u.users_id)
                 OR (m3.sender_id = u.users_id AND m3.receiver_id = ?)
              ORDER BY m3.created_at DESC LIMIT 1) AS last_at,
             (SELECT COUNT(*)
              FROM messages m4
              WHERE m4.sender_id = u.users_id AND m4.receiver_id = ? AND m4.is_read = 0) AS unread
         FROM users u
         WHERE u.users_id IN (
             SELECT DISTINCT
                 CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
             FROM messages m
             WHERE m.sender_id = ? OR m.receiver_id = ?
         )
         ORDER BY last_at DESC",
        [$me, $me, $me, $me, $me, $me, $me, $me]
    );

    echo json_encode(['success' => true, 'data' => $threads ?? []]);
}

// ─── Messages in a thread ─────────────────────────────────────────────────

function handleMessages() {
    $me    = Auth::id();
    $other = (int)($_GET['with'] ?? 0);

    if (!$other) {
        echo json_encode(['success' => false, 'message' => 'with= required']);
        return;
    }

    $messages = db()->fetchAll(
        "SELECT m.message_id, m.sender_id, m.receiver_id, m.content, m.is_read, m.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS sender_name
         FROM messages m
         JOIN users u ON u.users_id = m.sender_id
         WHERE (m.sender_id = ? AND m.receiver_id = ?)
            OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.created_at ASC",
        [$me, $other, $other, $me]
    );

    echo json_encode(['success' => true, 'data' => $messages ?? []]);
}

// ─── Unread count ──────────────────────────────────────────────────────────

function handleUnreadCount() {
    $me  = Auth::id();
    $row = db()->fetchOne(
        "SELECT COUNT(*) AS c FROM messages WHERE receiver_id = ? AND is_read = 0",
        [$me]
    );
    echo json_encode(['success' => true, 'count' => (int)($row['c'] ?? 0)]);
}

// ─── Send a message ────────────────────────────────────────────────────────

function handleSend() {
    $me   = Auth::id();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $receiverId = (int)($data['receiver_id'] ?? 0);
    $content    = trim($data['content'] ?? '');

    if (!$receiverId || !$content) {
        echo json_encode(['success' => false, 'message' => 'receiver_id and content required']);
        return;
    }

    if ($receiverId === $me) {
        echo json_encode(['success' => false, 'message' => 'Cannot message yourself']);
        return;
    }

    // Verify receiver exists
    $receiver = db()->fetchOne("SELECT users_id FROM users WHERE users_id = ? AND status = 'active'", [$receiverId]);
    if (!$receiver) {
        echo json_encode(['success' => false, 'message' => 'Recipient not found']);
        return;
    }

    try {
        pdo()->prepare(
            "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)"
        )->execute([$me, $receiverId, $content]);

        $msgId = pdo()->lastInsertId();

        // Return the new message so UI can append it immediately
        $msg = db()->fetchOne(
            "SELECT m.message_id, m.sender_id, m.receiver_id, m.content, m.is_read, m.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) AS sender_name
             FROM messages m JOIN users u ON u.users_id = m.sender_id
             WHERE m.message_id = ?",
            [$msgId]
        );

        echo json_encode(['success' => true, 'message' => 'Message sent', 'data' => $msg]);
    } catch (Exception $e) {
        error_log('MessagingAPI send: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

// ─── Mark conversation as read ─────────────────────────────────────────────

function handleMarkRead() {
    $me   = Auth::id();
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    $otherId = (int)($data['other_user_id'] ?? 0);
    if (!$otherId) {
        echo json_encode(['success' => false, 'message' => 'other_user_id required']);
        return;
    }

    pdo()->prepare(
        "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0"
    )->execute([$otherId, $me]);

    echo json_encode(['success' => true]);
}
