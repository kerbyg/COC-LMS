<?php
/**
 * CIT-LMS Instructor - Announcements Management
 * Post and manage class-specific or general announcements
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('instructor');

$userId = Auth::id();
$pageTitle = 'Announcements';
$currentPage = 'announcements';

// Fetch instructor's active class offerings for the target dropdown
$myOfferings = db()->fetchAll(
    "SELECT so.subject_offered_id, s.subject_code, s.subject_name 
     FROM faculty_subject fs 
     JOIN subject_offered so ON fs.subject_offered_id = so.subject_offered_id 
     JOIN subject s ON so.subject_id = s.subject_id 
     WHERE fs.user_teacher_id = ? AND fs.status = 'active' 
     ORDER BY s.subject_code", 
    [$userId]
);

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create New Announcement
    if (isset($_POST['create'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        // FIX: Properly handle subject_offered_id - convert empty string to NULL
        $offeredId = !empty($_POST['subject_offered_id']) ? (int)$_POST['subject_offered_id'] : null;
        $status = $_POST['status'] ?? 'published'; // Match SQL enum

        if (!empty($title) && !empty($content)) {
            // FIXED: Changed query() to execute()
            db()->execute(
                "INSERT INTO announcement (user_id, subject_offered_id, title, content, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                [$userId, $offeredId, $title, $content, $status]
            );
            header("Location: announcements.php?created=1");
            exit;
        }
    }

    // Delete Announcement
    if (isset($_POST['delete_id'])) {
        // FIXED: Changed query() to execute()
        db()->execute(
            "DELETE FROM announcement WHERE announcement_id = ? AND user_id = ?", 
            [(int)$_POST['delete_id'], $userId]
        );
        header("Location: announcements.php?deleted=1"); 
        exit;
    }
}

// Fetch existing announcements with subject context
$announcements = db()->fetchAll(
    "SELECT a.*, s.subject_code 
     FROM announcement a 
     LEFT JOIN subject_offered so ON a.subject_offered_id = so.subject_offered_id 
     LEFT JOIN subject s ON so.subject_id = s.subject_id 
     WHERE a.user_id = ? 
     ORDER BY a.created_at DESC", 
    [$userId]
);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">
        
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>📢 Announcements</h1>
                <p>Broadcast important updates, deadlines, or general news to your students.</p>
            </div>
            <div class="welcome-actions">
                <button class="btn-primary" onclick="document.getElementById('annModal').classList.add('active')">
                    + New Post
                </button>
            </div>
        </div>

        <?php if (isset($_GET['created'])): ?>
            <div class="alert alert-success">Announcement has been successfully broadcasted.</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Announcement has been removed from the feed.</div>
        <?php endif; ?>

        <div class="ann-feed">
            <?php if (empty($announcements)): ?>
                <div class="empty-box">
                    <span>📢</span>
                    <h3>No Announcements Yet</h3>
                    <p>Click "New Post" to communicate with your students.</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $a): ?>
                    <div class="ann-card">
                        <div class="ann-card-head">
                            <div class="ann-info">
                                <span class="ann-target <?= $a['subject_code'] ? 'target-class' : 'target-all' ?>">
                                    <?= $a['subject_code'] ? e($a['subject_code']) : 'All Classes' ?>
                                </span>
                                <h3 class="ann-title"><?= e($a['title']) ?></h3>
                                <span class="ann-date"><?= date('M d, Y • g:i A', strtotime($a['created_at'])) ?></span>
                            </div>
                            <div class="ann-actions">
                                <span class="status-pill status-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
                                <form method="POST" onsubmit="return confirm('Delete this announcement?')">
                                    <input type="hidden" name="delete_id" value="<?= $a['announcement_id'] ?>">
                                    <button class="btn-delete-icon">🗑</button>
                                </form>
                            </div>
                        </div>
                        <div class="ann-body">
                            <?= nl2br(e($a['content'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<div class="modal-overlay" id="annModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>New Announcement</h3>
            <button class="modal-close" onclick="document.getElementById('annModal').classList.remove('active')">×</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label class="field-label">Announcement Title</label>
                    <input type="text" name="title" class="field-input" placeholder="e.g., Final Exam Schedule" required>
                </div>
                <div class="form-group">
                    <label class="field-label">Target Audience</label>
                    <select name="subject_offered_id" class="field-select">
                        <option value="">Post to All My Classes</option>
                        <?php foreach ($myOfferings as $o): ?>
                            <option value="<?= $o['subject_offered_id'] ?>">
                                <?= e($o['subject_code']) ?> - <?= e($o['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="field-label">Message Content</label>
                    <textarea name="content" class="field-input" rows="6" placeholder="Write your message here..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="document.getElementById('annModal').classList.remove('active')">Cancel</button>
                <button type="submit" name="create" class="btn-save-quiz">Post Announcement</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Dashboard Aesthetic Integration */
.welcome-section {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 24px; padding: 24px;
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    border-radius: 16px; color: #fff;
}
.btn-primary { padding: 10px 20px; background: #fff; color: #16a34a; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; }

/* Announcement Cards */
.ann-feed { display: flex; flex-direction: column; gap: 20px; }
.ann-card { background: #fff; border: 1px solid #f5f0e8; border-radius: 16px; padding: 24px; }
.ann-card-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.ann-target { font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 20px; margin-bottom: 8px; display: inline-block; }
.target-class { background: #dcfce7; color: #166534; }
.target-all { background: #dbeafe; color: #1e40af; }
.ann-title { font-size: 18px; margin: 0 0 4px; color: #1c1917; }
.ann-date { font-size: 12px; color: #78716c; }

.ann-actions { display: flex; align-items: center; gap: 12px; }
.status-pill { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; }
.status-published { background: #dcfce7; color: #15803d; }
.status-draft { background: #fee2e2; color: #b91c1c; }
.btn-delete-icon { background: none; border: none; font-size: 18px; cursor: pointer; color: #a8a29e; }
.btn-delete-icon:hover { color: #dc2626; }

.ann-body { color: #44403c; line-height: 1.6; font-size: 15px; border-top: 1px solid #f5f0e8; padding-top: 16px; }

/* Modal Styling */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
.modal-overlay.active { display: flex; }
.modal-box { background: #fff; border-radius: 16px; width: 100%; max-width: 550px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.modal-header { padding: 20px 24px; border-bottom: 1px solid #f5f0e8; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; font-size: 18px; }
.modal-close { background: none; border: none; font-size: 24px; color: #78716c; cursor: pointer; }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; background: #fafaf9; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #f5f0e8; }

.field-label { display: block; font-size: 12px; font-weight: 700; color: #78716c; margin-bottom: 8px; text-transform: uppercase; }
.field-input, .field-select { width: 100%; padding: 12px; border: 1px solid #e7e5e4; border-radius: 8px; font-size: 14px; outline: none; }
.btn-save-quiz { background: #16a34a; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
.btn-cancel { background: none; border: none; color: #78716c; font-weight: 500; cursor: pointer; }

.alert { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; background: #dcfce7; color: #166534; border-left: 4px solid #16a34a; }
.empty-box { text-align: center; padding: 60px; background: #fff; border: 1px solid #f5f0e8; border-radius: 12px; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>