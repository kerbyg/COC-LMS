<?php
/**
 * CIT-LMS Instructor - Enhanced Lesson Editor
 * Features: Topics, Learning Objectives, Prerequisites, Video Support, File Uploads
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
Auth::requireRole('instructor');

$userId = Auth::id();
$lessonId = !empty($_GET['id']) ? (int)$_GET['id'] : null;
$subjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;
$isEdit = !empty($lessonId);
$pageTitle = $isEdit ? 'Edit Lesson' : 'Create Lesson';
$currentPage = 'lessons';
$error = '';
$success = '';

// Check which columns exist
$lessonCols = array_column(db()->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'lessons'") ?: [], 'column_name');
$topicCols = array_column(db()->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'topic'") ?: [], 'column_name');

$hasObjectives = in_array('learning_objectives', $lessonCols);
$hasPrerequisite = in_array('prerequisite_lessons_id', $lessonCols);
$hasDifficulty = in_array('difficulty', $lessonCols);
$hasVideoUrl = in_array('video_url', $topicCols);

// Initialize lesson defaults
$lesson = [
    'subject_id' => $subjectId, 'lesson_title' => '', 'lesson_description' => '',
    'lesson_content' => '', 'lesson_order' => 1, 'estimated_time' => 30,
    'status' => 'published', 'learning_objectives' => '', 'prerequisite_lessons_id' => null,
    'difficulty' => 'beginner'
];

// Fetch instructor's subjects
$mySubjects = db()->fetchAll(
    "SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name FROM faculty_subject fs
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE fs.user_teacher_id = ? AND fs.status = 'active' ORDER BY s.subject_code", [$userId]
);

// If editing, load existing lesson
if ($isEdit) {
    $lessonData = db()->fetchOne("SELECT * FROM lessons WHERE lessons_id = ? AND user_teacher_id = ?", [$lessonId, $userId]);
    if (!$lessonData) { header('Location: lessons.php'); exit; }
    $lesson = array_merge($lesson, $lessonData);
    $subjectId = $lesson['subject_id'];
}

// Get other lessons for prerequisite dropdown (excluding current)
$otherLessons = $subjectId ? db()->fetchAll(
    "SELECT lessons_id, lesson_title, lesson_order FROM lessons WHERE subject_id = ? AND user_teacher_id = ?" . ($isEdit ? " AND lessons_id != ?" : "") . " ORDER BY lesson_order",
    $isEdit ? [$subjectId, $userId, $lessonId] : [$subjectId, $userId]
) ?: [] : [];

// Get next order for new lessons
if ($subjectId && !$isEdit) {
    $maxOrder = db()->fetchOne("SELECT MAX(lesson_order) as m FROM lessons WHERE subject_id = ? AND user_teacher_id = ?", [$subjectId, $userId]);
    $lesson['lesson_order'] = ($maxOrder['m'] ?? 0) + 1;
}

// Fetch topics
$topics = $isEdit ? (db()->fetchAll("SELECT * FROM topic WHERE lessons_id = ? ORDER BY topic_order", [$lessonId]) ?: []) : [];

// Allowed file types for upload
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
$maxFileSize = 10 * 1024 * 1024; // 10MB

/**
 * Handle file upload - saves to lesson_materials table
 */
function uploadMaterialFile($file, $lessonId, $topicId = null) {
    global $allowedTypes, $maxFileSize;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload failed. Error code: ' . $file['error']];
    }

    if ($file['size'] > $maxFileSize) {
        return ['error' => 'File too large. Maximum size is 10MB.'];
    }

    $mimeType = mime_content_type($file['tmp_name']);
    if (!isset($allowedTypes[$mimeType])) {
        return ['error' => 'File type not allowed: ' . $mimeType];
    }

    $uploadDir = __DIR__ . '/../../uploads/materials/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'material_' . $lessonId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return [
            'success' => true,
            'file_name' => $fileName,
            'original_name' => $file['name'],
            'file_type' => $allowedTypes[$mimeType],
            'file_size' => $file['size'],
            'file_path' => 'uploads/materials/' . $fileName
        ];
    }

    return ['error' => 'Failed to save file.'];
}

// Handle Topic Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isEdit && isset($_POST['topic_action'])) {
    $action = $_POST['topic_action'];

    if ($action === 'add_topic') {
        $title = trim($_POST['new_topic_title'] ?? '');
        $content = $_POST['new_topic_content'] ?? '';
        $videoUrl = trim($_POST['new_video_url'] ?? '');
        $order = count($topics) + 1;

        if ($title) {
            $cols = ['lessons_id', 'topic_title', 'topic_content', 'topic_order', 'created_at', 'updated_at'];
            $vals = [$lessonId, $title, $content, $order, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')];
            $placeholders = ['?', '?', '?', '?', '?', '?'];

            if ($hasVideoUrl && $videoUrl) {
                $cols[] = 'video_url';
                $vals[] = $videoUrl;
                $placeholders[] = '?';
            }

            db()->execute("INSERT INTO topic (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")", $vals);
            $newTopicId = db()->lastInsertId();

            // Handle file uploads for new topic
            if (!empty($_FILES['new_topic_files']['name'][0])) {
                foreach ($_FILES['new_topic_files']['name'] as $idx => $name) {
                    if (empty($name)) continue;
                    $file = [
                        'name' => $_FILES['new_topic_files']['name'][$idx],
                        'type' => $_FILES['new_topic_files']['type'][$idx],
                        'tmp_name' => $_FILES['new_topic_files']['tmp_name'][$idx],
                        'error' => $_FILES['new_topic_files']['error'][$idx],
                        'size' => $_FILES['new_topic_files']['size'][$idx]
                    ];
                    $result = uploadMaterialFile($file, $lessonId, $newTopicId);
                    if (!empty($result['success'])) {
                        db()->execute(
                            "INSERT INTO lesson_materials (lessons_id, topic_id, file_name, original_name, file_path, file_type, file_size, material_type, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                            [$lessonId, $newTopicId, $result['file_name'], $result['original_name'], $result['file_path'], $result['file_type'], $result['file_size'], $result['file_type']]
                        );
                    }
                }
            }

            $success = 'Topic added!';
        }
    } elseif ($action === 'upload_file') {
        // Handle adding files to existing topic
        $topicId = (int)($_POST['topic_id'] ?? 0);
        if ($topicId && !empty($_FILES['topic_files']['name'][0])) {
            $uploadCount = 0;
            foreach ($_FILES['topic_files']['name'] as $idx => $name) {
                if (empty($name)) continue;
                $file = [
                    'name' => $_FILES['topic_files']['name'][$idx],
                    'type' => $_FILES['topic_files']['type'][$idx],
                    'tmp_name' => $_FILES['topic_files']['tmp_name'][$idx],
                    'error' => $_FILES['topic_files']['error'][$idx],
                    'size' => $_FILES['topic_files']['size'][$idx]
                ];
                $result = uploadMaterialFile($file, $lessonId, $topicId);
                if (!empty($result['success'])) {
                    db()->execute(
                        "INSERT INTO lesson_materials (lessons_id, topic_id, file_name, original_name, file_path, file_type, file_size, material_type, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [$lessonId, $topicId, $result['file_name'], $result['original_name'], $result['file_path'], $result['file_type'], $result['file_size'], $result['file_type']]
                    );
                    $uploadCount++;
                } else {
                    $error = $result['error'] ?? 'Upload failed';
                }
            }
            if ($uploadCount > 0) $success = "$uploadCount file(s) uploaded!";
        }
    } elseif ($action === 'delete_file') {
        // Handle file deletion
        $materialId = (int)($_POST['material_id'] ?? 0);
        if ($materialId) {
            $material = db()->fetchOne("SELECT * FROM lesson_materials WHERE material_id = ? AND lessons_id = ?", [$materialId, $lessonId]);
            if ($material) {
                $filePath = __DIR__ . '/../../' . $material['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                db()->execute("DELETE FROM lesson_materials WHERE material_id = ?", [$materialId]);
                $success = 'File deleted!';
            }
        }
    } elseif ($action === 'update_topic') {
        $topicId = (int)($_POST['topic_id'] ?? 0);
        $title = trim($_POST['topic_title'] ?? '');
        $content = $_POST['topic_content'] ?? '';
        $videoUrl = trim($_POST['video_url'] ?? '');

        if ($topicId && $title) {
            $sql = "UPDATE topic SET topic_title=?, topic_content=?, updated_at=NOW()";
            $params = [$title, $content];
            if ($hasVideoUrl) { $sql .= ", video_url=?"; $params[] = $videoUrl; }
            $sql .= " WHERE topic_id=? AND lessons_id=?";
            $params[] = $topicId; $params[] = $lessonId;
            db()->execute($sql, $params);
            $success = 'Topic updated!';
        }
    } elseif ($action === 'delete_topic') {
        $topicId = (int)($_POST['topic_id'] ?? 0);
        if ($topicId) {
            // Delete files associated with this topic
            $topicFiles = db()->fetchAll("SELECT * FROM lesson_materials WHERE topic_id = ?", [$topicId]);
            foreach ($topicFiles as $tf) {
                $filePath = __DIR__ . '/../../' . $tf['file_path'];
                if (file_exists($filePath)) unlink($filePath);
            }
            db()->execute("DELETE FROM lesson_materials WHERE topic_id = ?", [$topicId]);
            db()->execute("DELETE FROM topic WHERE topic_id=? AND lessons_id=?", [$topicId, $lessonId]);
            $success = 'Topic deleted!';
        }
    }
    $topics = db()->fetchAll("SELECT * FROM topic WHERE lessons_id = ? ORDER BY topic_order", [$lessonId]) ?: [];
}

// Handle Lesson Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lesson'])) {
    $lesson['subject_id'] = $_POST['subject_id'] ?? '';
    $lesson['lesson_title'] = trim($_POST['lesson_title'] ?? '');
    $lesson['lesson_description'] = trim($_POST['lesson_description'] ?? '');
    $lesson['lesson_content'] = $_POST['lesson_content'] ?? '';
    $lesson['lesson_order'] = (int)($_POST['lesson_order'] ?? 1);
    $lesson['estimated_time'] = (int)($_POST['estimated_time'] ?? 30);
    $lesson['status'] = $_POST['status'] ?? 'draft';
    $lesson['learning_objectives'] = trim($_POST['learning_objectives'] ?? '');
    $lesson['prerequisite_lessons_id'] = !empty($_POST['prerequisite_lessons_id']) ? (int)$_POST['prerequisite_lessons_id'] : null;
    $lesson['difficulty'] = $_POST['difficulty'] ?? 'beginner';

    if (empty($lesson['subject_id']) || empty($lesson['lesson_title'])) {
        $error = 'Subject and title are required.';
    } else {
        // Build dynamic query
        $cols = ['subject_id', 'lesson_title', 'lesson_description', 'lesson_content', 'lesson_order', 'estimated_time', 'status'];
        $vals = [$lesson['subject_id'], $lesson['lesson_title'], $lesson['lesson_description'], $lesson['lesson_content'], $lesson['lesson_order'], $lesson['estimated_time'], $lesson['status']];

        if ($hasObjectives) { $cols[] = 'learning_objectives'; $vals[] = $lesson['learning_objectives']; }
        if ($hasPrerequisite) { $cols[] = 'prerequisite_lessons_id'; $vals[] = $lesson['prerequisite_lessons_id']; }
        if ($hasDifficulty) { $cols[] = 'difficulty'; $vals[] = $lesson['difficulty']; }

        if ($isEdit) {
            $set = implode('=?, ', $cols) . '=?, updated_at=NOW()';
            $vals[] = $lessonId; $vals[] = $userId;
            $saveSuccess = db()->execute("UPDATE lessons SET $set WHERE lessons_id=? AND user_teacher_id=?", $vals);
        } else {
            $cols[] = 'user_teacher_id'; $vals[] = $userId;
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $saveSuccess = db()->execute("INSERT INTO lessons (" . implode(',', $cols) . ", created_at, updated_at) VALUES ($placeholders, NOW(), NOW())", $vals);
            if ($saveSuccess) {
                header("Location: lesson-edit.php?id=" . db()->lastInsertId() . "&created=1");
                exit;
            }
        }
        $success = $saveSuccess ? 'Lesson saved!' : '';
        $error = !$saveSuccess ? 'Failed to save.' : '';
    }
}

if (isset($_GET['created'])) $success = 'Lesson created! Add topics below.';

// Get lesson-level materials (not attached to any topic)
$lessonMaterials = $isEdit ? (db()->fetchAll("SELECT * FROM lesson_materials WHERE lessons_id = ? AND topic_id IS NULL ORDER BY uploaded_at", [$lessonId]) ?: []) : [];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    <div class="page-content">
        <div class="page-header">
            <a href="lessons.php<?= $subjectId ? '?subject_id='.$subjectId : '' ?>" class="back-link">← Back to Lessons</a>
            <h2><?= $pageTitle ?></h2>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

        <form method="POST" class="card">
            <input type="hidden" name="save_lesson" value="1">

            <div class="card-section">
                <h3>📚 Basic Information</h3>
                <div class="grid-3">
                    <div class="field">
                        <label>Subject <span class="req">*</span></label>
                        <select name="subject_id" required <?= $isEdit ? 'disabled' : 'onchange="location=\'lesson-edit.php?subject_id=\'+this.value"' ?>>
                            <option value="">Select Subject</option>
                            <?php foreach ($mySubjects as $s): ?>
                            <option value="<?= $s['subject_id'] ?>" <?= $lesson['subject_id']==$s['subject_id']?'selected':'' ?>><?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isEdit): ?><input type="hidden" name="subject_id" value="<?= $lesson['subject_id'] ?>"><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Order</label>
                        <input type="number" name="lesson_order" value="<?= $lesson['lesson_order'] ?>" min="1">
                    </div>
                    <div class="field">
                        <label>Est. Time (mins)</label>
                        <input type="number" name="estimated_time" value="<?= $lesson['estimated_time'] ?? 30 ?>" min="5">
                    </div>
                </div>

                <div class="field">
                    <label>Title <span class="req">*</span></label>
                    <input type="text" name="lesson_title" required value="<?= e($lesson['lesson_title']) ?>" placeholder="e.g., Introduction to Variables">
                </div>

                <div class="field">
                    <label>Description</label>
                    <textarea name="lesson_description" rows="2" placeholder="Brief overview..."><?= e($lesson['lesson_description'] ?? '') ?></textarea>
                </div>
            </div>

            <?php if ($hasObjectives || $hasPrerequisite || $hasDifficulty): ?>
            <div class="card-section">
                <h3>🎯 Learning Settings</h3>
                <div class="grid-2">
                    <?php if ($hasDifficulty): ?>
                    <div class="field">
                        <label>Difficulty Level</label>
                        <select name="difficulty">
                            <option value="beginner" <?= ($lesson['difficulty']??'')=='beginner'?'selected':'' ?>>🟢 Beginner</option>
                            <option value="intermediate" <?= ($lesson['difficulty']??'')=='intermediate'?'selected':'' ?>>🟡 Intermediate</option>
                            <option value="advanced" <?= ($lesson['difficulty']??'')=='advanced'?'selected':'' ?>>🔴 Advanced</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasPrerequisite): ?>
                    <div class="field">
                        <label>Prerequisite Lesson</label>
                        <select name="prerequisite_lessons_id">
                            <option value="">None - Available immediately</option>
                            <?php foreach ($otherLessons as $ol): ?>
                            <option value="<?= $ol['lessons_id'] ?>" <?= ($lesson['prerequisite_lessons_id']??'')==$ol['lessons_id']?'selected':'' ?>>
                                Module <?= $ol['lesson_order'] ?>: <?= e($ol['lesson_title']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Students must complete this lesson first</small>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($hasObjectives): ?>
                <div class="field">
                    <label>Learning Objectives</label>
                    <textarea name="learning_objectives" rows="3" placeholder="By the end of this lesson, students will be able to:&#10;• Understand variables&#10;• Declare and use variables&#10;• Identify data types"><?= e($lesson['learning_objectives'] ?? '') ?></textarea>
                    <small>One objective per line (use • or - for bullets)</small>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="card-section">
                <h3>📝 Lesson Content</h3>
                <div class="field">
                    <textarea name="lesson_content" rows="8" placeholder="Main lesson content (HTML supported)..."><?= e($lesson['lesson_content']) ?></textarea>
                </div>
                <div class="field">
                    <label>Status</label>
                    <div class="radio-row">
                        <label><input type="radio" name="status" value="draft" <?= $lesson['status']==='draft'?'checked':'' ?>> Draft</label>
                        <label><input type="radio" name="status" value="published" <?= $lesson['status']==='published'?'checked':'' ?>> Published</label>
                    </div>
                </div>
            </div>

            <div class="card-footer">
                <a href="lessons.php" class="btn-outline">Cancel</a>
                <button type="submit" class="btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Lesson' ?></button>
            </div>
        </form>

        <?php if ($isEdit): ?>
        <!-- TOPICS SECTION -->
        <div class="card mt-4">
            <div class="card-section">
                <h3>📖 Topics (<?= count($topics) ?>)</h3>
                <p class="hint">Break your lesson into smaller topics for better learning.</p>

                <?php if ($topics): ?>
                <div class="topics-list">
                    <?php foreach ($topics as $t):
                        // Get materials for this topic from lesson_materials table
                        $topicMaterials = db()->fetchAll("SELECT * FROM lesson_materials WHERE topic_id = ? ORDER BY uploaded_at", [$t['topic_id']]) ?: [];
                    ?>
                    <div class="topic-card" id="topic-<?= $t['topic_id'] ?>">
                        <div class="topic-header">
                            <span class="topic-num"><?= $t['topic_order'] ?></span>
                            <span class="topic-name"><?= e($t['topic_title']) ?></span>
                            <?php if ($hasVideoUrl && !empty($t['video_url'])): ?><span class="tag-video">🎬 Video</span><?php endif; ?>
                            <?php if (count($topicMaterials) > 0): ?><span class="tag-files">📎 <?= count($topicMaterials) ?> file(s)</span><?php endif; ?>
                            <div class="topic-btns">
                                <button type="button" class="btn-sm" onclick="toggleEdit(<?= $t['topic_id'] ?>)">Edit</button>
                                <button type="button" class="btn-sm btn-files" onclick="toggleFiles(<?= $t['topic_id'] ?>)">📁 Files</button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete topic and all its files?')">
                                    <input type="hidden" name="topic_action" value="delete_topic">
                                    <input type="hidden" name="topic_id" value="<?= $t['topic_id'] ?>">
                                    <button class="btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                        <div class="topic-preview"><?= mb_strimwidth(strip_tags($t['topic_content']??''), 0, 100, '...') ?: 'No content' ?></div>

                        <!-- Files Section -->
                        <div class="topic-files" id="files-<?= $t['topic_id'] ?>" style="display:none">
                            <div class="files-header">
                                <h5>📁 Attachments</h5>
                            </div>

                            <?php if ($topicMaterials): ?>
                            <div class="files-list">
                                <?php foreach ($topicMaterials as $mat):
                                    $icon = match($mat['material_type'] ?? $mat['file_type'] ?? 'other') {
                                        'document' => '📄',
                                        'image' => '🖼️',
                                        'video' => '🎬',
                                        'audio' => '🎵',
                                        default => '📎'
                                    };
                                    $sizeKB = round(($mat['file_size'] ?? 0) / 1024, 1);
                                ?>
                                <div class="file-item">
                                    <span class="file-icon"><?= $icon ?></span>
                                    <div class="file-info">
                                        <a href="../../<?= e($mat['file_path']) ?>" target="_blank" class="file-name"><?= e($mat['original_name']) ?></a>
                                        <span class="file-size"><?= $sizeKB ?> KB</span>
                                    </div>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this file?')">
                                        <input type="hidden" name="topic_action" value="delete_file">
                                        <input type="hidden" name="material_id" value="<?= $mat['material_id'] ?>">
                                        <button class="btn-sm btn-danger" title="Delete file">🗑️</button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="no-files">No files attached yet.</p>
                            <?php endif; ?>

                            <!-- Upload new files -->
                            <form method="POST" enctype="multipart/form-data" class="upload-form">
                                <input type="hidden" name="topic_action" value="upload_file">
                                <input type="hidden" name="topic_id" value="<?= $t['topic_id'] ?>">
                                <div class="upload-area">
                                    <input type="file" name="topic_files[]" id="files-input-<?= $t['topic_id'] ?>" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip">
                                    <label for="files-input-<?= $t['topic_id'] ?>" class="upload-label">
                                        <span class="upload-icon">📤</span>
                                        <span>Choose files or drag here</span>
                                        <small>PDF, Images, Documents (Max 10MB each)</small>
                                    </label>
                                </div>
                                <button type="submit" class="btn-primary btn-sm">Upload Files</button>
                            </form>
                        </div>

                        <div class="topic-edit" id="edit-<?= $t['topic_id'] ?>" style="display:none">
                            <form method="POST">
                                <input type="hidden" name="topic_action" value="update_topic">
                                <input type="hidden" name="topic_id" value="<?= $t['topic_id'] ?>">
                                <div class="field"><label>Title</label><input type="text" name="topic_title" value="<?= e($t['topic_title']) ?>" required></div>
                                <?php if ($hasVideoUrl): ?>
                                <div class="field"><label>Video URL (YouTube/Vimeo)</label><input type="url" name="video_url" value="<?= e($t['video_url']??'') ?>" placeholder="https://youtube.com/watch?v=..."></div>
                                <?php endif; ?>
                                <div class="field"><label>Content</label><textarea name="topic_content" rows="5"><?= e($t['topic_content']??'') ?></textarea></div>
                                <div class="topic-edit-btns">
                                    <button type="button" class="btn-outline btn-sm" onclick="toggleEdit(<?= $t['topic_id'] ?>)">Cancel</button>
                                    <button class="btn-primary btn-sm">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-box">No topics yet. Add one below!</div>
                <?php endif; ?>

                <!-- Add Topic Form -->
                <div class="add-topic">
                    <h4>➕ Add Topic</h4>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="topic_action" value="add_topic">
                        <div class="grid-2">
                            <div class="field"><label>Title <span class="req">*</span></label><input type="text" name="new_topic_title" required placeholder="e.g., Understanding Variables"></div>
                            <?php if ($hasVideoUrl): ?>
                            <div class="field"><label>Video URL</label><input type="url" name="new_video_url" placeholder="https://youtube.com/watch?v=..."></div>
                            <?php endif; ?>
                        </div>
                        <div class="field"><label>Content</label><textarea name="new_topic_content" rows="4" placeholder="Topic content..."></textarea></div>

                        <div class="field">
                            <label>📎 Attach Files (Optional)</label>
                            <div class="upload-area">
                                <input type="file" name="new_topic_files[]" id="new-topic-files" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip">
                                <label for="new-topic-files" class="upload-label">
                                    <span class="upload-icon">📤</span>
                                    <span>Choose files or drag here</span>
                                    <small>PDF, Images, Documents (Max 10MB each)</small>
                                </label>
                            </div>
                        </div>

                        <button class="btn-primary">Add Topic</button>
                    </form>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="info-box mt-4">💡 Save the lesson first to add topics.</div>
        <?php endif; ?>
    </div>
</main>

<style>
.back-link{color:#16a34a;text-decoration:none;font-size:14px;display:block;margin-bottom:8px}
.page-header h2{margin:0;font-size:22px}
.card{background:#fff;border:1px solid #e5e5e5;border-radius:10px;margin-bottom:16px}
.card-section{padding:20px;border-bottom:1px solid #f0f0f0}
.card-section:last-child{border-bottom:none}
.card-section h3{margin:0 0 16px;font-size:16px;color:#1a1a1a}
.card-footer{padding:16px 20px;background:#fafafa;display:flex;justify-content:flex-end;gap:10px}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.grid-3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px}
.field{margin-bottom:16px}
.field label{display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px}
.field input,.field select,.field textarea{width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:6px;font-size:14px}
.field input:focus,.field select:focus,.field textarea:focus{border-color:#16a34a;outline:none;box-shadow:0 0 0 2px rgba(22,163,74,0.1)}
.field small{color:#888;font-size:12px;margin-top:4px;display:block}
.req{color:#dc2626}
.hint{color:#666;font-size:13px;margin:0 0 16px}
.radio-row{display:flex;gap:20px}
.radio-row label{display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal}
.btn-primary{background:#16a34a;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-weight:600}
.btn-primary:hover{background:#15803d}
.btn-outline{background:#fff;color:#444;border:1px solid #ddd;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:500}
.btn-sm{padding:6px 12px;font-size:12px;border-radius:4px;cursor:pointer;border:1px solid #ddd;background:#fff}
.btn-danger{color:#dc2626;border-color:#fecaca}
.alert{padding:12px 16px;border-radius:6px;margin-bottom:16px}
.alert-error{background:#fee2e2;color:#991b1b;border-left:3px solid #dc2626}
.alert-success{background:#dcfce7;color:#166534;border-left:3px solid #16a34a}
.mt-4{margin-top:20px}

/* Topics */
.topics-list{margin-bottom:20px}
.topic-card{border:1px solid #e5e5e5;border-radius:8px;margin-bottom:10px;overflow:hidden}
.topic-header{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#fafafa}
.topic-num{width:26px;height:26px;background:#16a34a;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600}
.topic-name{flex:1;font-weight:600;color:#1a1a1a}
.tag-video{background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:4px;font-size:11px}
.tag-files{background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:4px;font-size:11px}
.topic-btns{display:flex;gap:6px}
.btn-files{background:#fef3c7;border-color:#fcd34d;color:#92400e}
.topic-preview{padding:10px 16px;color:#666;font-size:13px;border-top:1px solid #f0f0f0}
.topic-edit{padding:16px;background:#fff;border-top:1px solid #e5e5e5}
.topic-edit-btns{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
.add-topic{background:#f0fdf4;border:1px dashed #86efac;border-radius:8px;padding:16px;margin-top:16px}
.add-topic h4{margin:0 0 12px;color:#166534;font-size:14px}
.empty-box{text-align:center;padding:30px;color:#888;background:#fafafa;border-radius:6px}
.info-box{background:#fef3c7;border:1px solid #fcd34d;padding:14px;border-radius:6px;color:#92400e}

/* File Upload Styles */
.topic-files{padding:16px;background:#fffbeb;border-top:1px solid #fcd34d}
.files-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.files-header h5{margin:0;font-size:14px;color:#92400e}
.files-list{display:flex;flex-direction:column;gap:8px;margin-bottom:16px}
.file-item{display:flex;align-items:center;gap:10px;padding:10px 12px;background:#fff;border:1px solid #e5e5e5;border-radius:6px}
.file-icon{font-size:20px}
.file-info{flex:1;min-width:0}
.file-name{display:block;color:#1d4ed8;text-decoration:none;font-weight:500;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.file-name:hover{text-decoration:underline}
.file-size{font-size:11px;color:#888}
.no-files{color:#888;font-size:13px;text-align:center;padding:12px}
.upload-area{position:relative;margin-bottom:12px}
.upload-area input[type="file"]{position:absolute;width:100%;height:100%;opacity:0;cursor:pointer;z-index:2}
.upload-label{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;border:2px dashed #d1d5db;border-radius:8px;background:#f9fafb;cursor:pointer;transition:all 0.2s}
.upload-label:hover{border-color:#16a34a;background:#f0fdf4}
.upload-icon{font-size:28px;margin-bottom:6px}
.upload-label span{font-size:14px;color:#374151;font-weight:500}
.upload-label small{font-size:11px;color:#888;margin-top:4px}
.upload-form{border-top:1px dashed #fcd34d;padding-top:12px;margin-top:12px}

@media(max-width:768px){.grid-2,.grid-3{grid-template-columns:1fr}}
</style>

<script>
function toggleEdit(id){
    const el=document.getElementById('edit-'+id);
    el.style.display=el.style.display==='none'?'block':'none';
}

function toggleFiles(id){
    const el=document.getElementById('files-'+id);
    el.style.display=el.style.display==='none'?'block':'none';
}

// Show selected file names
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        const label = this.nextElementSibling;
        if (this.files.length > 0) {
            const names = Array.from(this.files).map(f => f.name).join(', ');
            label.querySelector('span:not(.upload-icon)').textContent = this.files.length + ' file(s) selected';
            label.style.borderColor = '#16a34a';
            label.style.background = '#f0fdf4';
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
