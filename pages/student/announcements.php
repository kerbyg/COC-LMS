<?php
/**
 * CIT-LMS - Announcements Page
 * Shows announcements from enrolled subjects
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$subjectOfferingId = $_GET['id'] ?? null;
$pageTitle = 'Announcements';
$currentPage = 'announcements';

// Get enrolled subjects for filter
$enrolledSubjects = db()->fetchAll(
    "SELECT so.subject_offered_id as subject_offering_id, s.subject_code, s.subject_name
     FROM student_subject ss
     JOIN subject_offered so ON ss.subject_offered_id = so.subject_offered_id
     JOIN subject s ON so.subject_id = s.subject_id
     WHERE ss.user_student_id = ? AND ss.status = 'enrolled'
     ORDER BY s.subject_code",
    [$userId]
);


// ... (keep the top part of your file the same)

// Build query based on filter
if ($subjectOfferingId) {
    // Single subject announcements (Filter specific subject)
    $announcements = db()->fetchAll(
        "SELECT
            a.*,
            s.subject_code,
            s.subject_name,
            CONCAT(u.first_name, ' ', u.last_name) as author_name
         FROM announcement a
         LEFT JOIN subject_offered so ON a.subject_offered_id = so.subject_offered_id
         LEFT JOIN subject s ON so.subject_id = s.subject_id
         LEFT JOIN users u ON a.user_id = u.users_id
         WHERE a.status = 'published' AND a.subject_offered_id = ?
         ORDER BY a.created_at DESC",
        [$subjectOfferingId]
    );

    // Get current subject info for display
    $currentSubject = db()->fetchOne(
        "SELECT so.subject_offered_id, s.subject_code, s.subject_name
         FROM subject_offered so
         JOIN subject s ON so.subject_id = s.subject_id
         WHERE so.subject_offered_id = ?",
        [$subjectOfferingId]
    );
} else {
    // All announcements (Enrolled subjects + General announcements)
    $announcements = db()->fetchAll(
        "SELECT
            a.*,
            s.subject_code,
            s.subject_name,
            CONCAT(u.first_name, ' ', u.last_name) as author_name
         FROM announcement a
         LEFT JOIN subject_offered so ON a.subject_offered_id = so.subject_offered_id
         LEFT JOIN subject s ON so.subject_id = s.subject_id
         LEFT JOIN users u ON a.user_id = u.users_id
         WHERE a.status = 'published'
         AND (
             a.subject_offered_id IN (
                 SELECT subject_offered_id FROM student_subject
                 WHERE user_student_id = ? AND status = 'enrolled'
             )
             OR a.subject_offered_id IS NULL
         )
         ORDER BY a.created_at DESC",
        [$userId]
    );
    $currentSubject = null;
}
// ... (rest of the file remains the same)
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    
    <div class="page-content">
        
        <!-- Page Header -->
        <div class="page-head">
            <div>
                <h1>📢 Announcements</h1>
                <p><?= $currentSubject ? e($currentSubject['subject_code'] . ' - ' . $currentSubject['subject_name']) : 'All enrolled subjects' ?></p>
            </div>
            
            <!-- Filter Dropdown -->
            <div class="filter-dropdown">
                <select id="subjectFilter" onchange="filterBySubject(this.value)">
                    <option value="">All Subjects</option>
                    <?php foreach ($enrolledSubjects as $subj): ?>
                    <option value="<?= $subj['subject_offering_id'] ?>" <?= $subjectOfferingId == $subj['subject_offering_id'] ? 'selected' : '' ?>>
                        <?= e($subj['subject_code']) ?> - <?= e($subj['subject_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Back link if filtered -->
        <?php if ($subjectOfferingId): ?>
        <div class="page-top">
            <a href="announcements.php" class="back-link">← View All Announcements</a>
        </div>
        <?php endif; ?>
        
        <!-- Announcements List -->
        <div class="announcements-container">
            <?php if (empty($announcements)): ?>
                <div class="empty-box">
                    <span>📢</span>
                    <h3>No Announcements</h3>
                    <p>There are no announcements from your enrolled subjects yet.</p>
                </div>
            <?php else: ?>
                <div class="announcements-list">
                    <?php foreach ($announcements as $ann): ?>
                    <div class="announcement-card">
                        <div class="ann-header">
                            <span class="subj-badge"><?= e($ann['subject_code']) ?></span>
                            <span class="ann-date"><?= formatDate($ann['created_at'], DATE_FORMAT_SHORT) ?></span>
                        </div>
                        
                        <h2 class="ann-title"><?= e($ann['title']) ?></h2>
                        
                        <div class="ann-content">
                            <?= nl2br(e($ann['content'])) ?>
                        </div>
                        
                        <div class="ann-footer">
                            <span class="ann-author">
                                👨‍🏫 <?= e($ann['author_name'] ?? 'Instructor') ?>
                            </span>
                            <span class="ann-full-date">
                                <?= date('F d, Y \a\t g:i A', strtotime($ann['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</main>

<style>
/* Announcements Page Styles */

.page-head {
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}
.page-head h1 {
    font-size: 22px;
    color: #1c1917;
    margin: 0 0 4px;
}
.page-head p {
    color: #78716c;
    margin: 0;
    font-size: 14px;
}

/* Filter Dropdown */
.filter-dropdown select {
    padding: 10px 16px;
    border: 1px solid #e7e5e4;
    border-radius: 8px;
    font-size: 14px;
    color: #1c1917;
    background: #fff;
    cursor: pointer;
    min-width: 200px;
}
.filter-dropdown select:focus {
    outline: none;
    border-color: #16a34a;
}

.page-top {
    margin-bottom: 16px;
}
.back-link {
    color: #16a34a;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}
.back-link:hover {
    text-decoration: underline;
}

/* Announcements List */
.announcements-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.announcement-card {
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
    padding: 24px;
    transition: all 0.2s;
}
.announcement-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.ann-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.subj-badge {
    display: inline-block;
    background: #16a34a;
    color: #fff;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}
.ann-date {
    font-size: 13px;
    color: #78716c;
}

.ann-title {
    font-size: 18px;
    color: #1c1917;
    margin: 0 0 16px;
    line-height: 1.4;
}

.ann-content {
    font-size: 15px;
    color: #44403c;
    line-height: 1.7;
    padding: 16px;
    background: #fdfbf7;
    border-radius: 8px;
    margin-bottom: 16px;
}

.ann-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 12px;
    border-top: 1px solid #f5f0e8;
    font-size: 13px;
    color: #78716c;
}
.ann-author {
    font-weight: 500;
}

/* Empty State */
.empty-box {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #f5f0e8;
    border-radius: 12px;
}
.empty-box span {
    font-size: 48px;
    display: block;
    margin-bottom: 12px;
    opacity: 0.5;
}
.empty-box h3 {
    font-size: 18px;
    color: #1c1917;
    margin: 0 0 8px;
}
.empty-box p {
    color: #78716c;
    margin: 0;
    font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
    .page-head {
        flex-direction: column;
    }
    .filter-dropdown select {
        width: 100%;
    }
    .ann-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}
</style>

<script>
function filterBySubject(subjectId) {
    if (subjectId) {
        window.location.href = 'announcements.php?id=' + subjectId;
    } else {
        window.location.href = 'announcements.php';
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>