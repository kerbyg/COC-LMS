<?php
/**
 * CIT-LMS - Enhanced Lesson View
 * Features: Topics, Learning Objectives, Prerequisites, Video Support
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

Auth::requireRole('student');

$userId = Auth::id();
$lessonId = $_GET['id'] ?? 0;

if (!$lessonId) { header('Location: my-subjects.php'); exit; }

// Check which columns exist
$lessonCols = array_column(db()->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'lessons'") ?: [], 'column_name');
$topicCols = array_column(db()->fetchAll("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'topic'") ?: [], 'column_name');

$hasObjectives = in_array('learning_objectives', $lessonCols);
$hasPrerequisite = in_array('prerequisite_lesson_id', $lessonCols);
$hasDifficulty = in_array('difficulty', $lessonCols);
$hasVideoUrl = in_array('video_url', $topicCols);

// Get lesson with subject info
$lesson = db()->fetchOne(
    "SELECT l.*, s.subject_code, s.subject_name, so.subject_offered_id,
     CONCAT(u.first_name, ' ', u.last_name) as instructor_name
     FROM lessons l
     JOIN subject s ON l.subject_id = s.subject_id
     JOIN subject_offered so ON so.subject_id = s.subject_id
     LEFT JOIN users u ON l.user_teacher_id = u.users_id
     WHERE l.lesson_id = ? AND l.status = 'published' LIMIT 1",
    [$lessonId]
);

if (!$lesson) { header('Location: my-subjects.php'); exit; }

// Verify enrollment
$enrollment = db()->fetchOne(
    "SELECT * FROM student_subject WHERE user_student_id = ? AND subject_offered_id = ? AND status = 'enrolled'",
    [$userId, $lesson['subject_offered_id']]
);
if (!$enrollment) { header('Location: my-subjects.php'); exit; }

// Check prerequisite (if column exists)
$prerequisiteMet = true;
$prerequisiteLesson = null;
if ($hasPrerequisite && !empty($lesson['prerequisite_lesson_id'])) {
    $prerequisiteLesson = db()->fetchOne("SELECT lesson_id, lesson_title FROM lessons WHERE lesson_id = ?", [$lesson['prerequisite_lesson_id']]);
    $prereqProgress = db()->fetchOne(
        "SELECT * FROM student_progress WHERE user_student_id = ? AND lesson_id = ? AND status = 'completed'",
        [$userId, $lesson['prerequisite_lesson_id']]
    );
    $prerequisiteMet = !empty($prereqProgress);
}

// Get progress status
$progress = db()->fetchOne("SELECT * FROM student_progress WHERE user_student_id = ? AND lesson_id = ?", [$userId, $lessonId]);
$isCompleted = $progress && $progress['status'] == 'completed';

// Get all lessons for navigation
$allLessons = db()->fetchAll(
    "SELECT lesson_id, lesson_title as title, lesson_order as order_number,
     (SELECT CASE WHEN status = 'completed' THEN 1 ELSE 0 END FROM student_progress WHERE lesson_id = l.lesson_id AND user_student_id = ?) as is_completed
     FROM lessons l WHERE subject_id = (SELECT subject_id FROM lessons WHERE lesson_id = ?) AND status = 'published' ORDER BY lesson_order",
    [$userId, $lessonId]
);

$currentIndex = array_search($lessonId, array_column($allLessons, 'lesson_id'));
$prevLesson = $currentIndex > 0 ? $allLessons[$currentIndex - 1] : null;
$nextLesson = $currentIndex < count($allLessons) - 1 ? $allLessons[$currentIndex + 1] : null;

// Get topics
$topics = db()->fetchAll("SELECT * FROM topic WHERE lesson_id = ? ORDER BY topic_order", [$lessonId]) ?: [];

// Get attachments
$attachments = db()->fetchAll("SELECT * FROM lesson_materials WHERE lesson_id = ? ORDER BY uploaded_at", [$lessonId]) ?: [];

// Helper: Convert YouTube URL to embed
function getEmbedUrl($url) {
    if (empty($url)) return '';
    if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $url, $m)) return "https://www.youtube.com/embed/{$m[1]}";
    if (preg_match('/youtu\.be\/([^?]+)/', $url, $m)) return "https://www.youtube.com/embed/{$m[1]}";
    if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) return "https://player.vimeo.com/video/{$m[1]}";
    return $url;
}

$pageTitle = $lesson['lesson_title'];
$currentPage = 'lessons';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>

    <div class="page-content">
        <a href="lessons.php?id=<?= $lesson['subject_offered_id'] ?>" class="back-link">← Back to <?= e($lesson['subject_code']) ?> Lessons</a>

        <?php if (!$prerequisiteMet): ?>
        <!-- Locked Message -->
        <div class="locked-box">
            <div class="locked-icon">🔒</div>
            <h2>Lesson Locked</h2>
            <p>Complete "<strong><?= e($prerequisiteLesson['lesson_title'] ?? 'Previous lesson') ?></strong>" first to unlock this lesson.</p>
            <a href="lesson-view.php?id=<?= $prerequisiteLesson['lesson_id'] ?? '' ?>" class="btn-primary">Go to Required Lesson →</a>
        </div>
        <?php else: ?>

        <div class="lesson-layout">
            <!-- Sidebar -->
            <aside class="lesson-sidebar">
                <div class="sidebar-head">
                    <h3>📖 Lessons</h3>
                    <span><?= count(array_filter($allLessons, fn($l) => $l['is_completed'])) ?>/<?= count($allLessons) ?></span>
                </div>
                <div class="sidebar-list">
                    <?php foreach ($allLessons as $item): ?>
                    <a href="lesson-view.php?id=<?= $item['lesson_id'] ?>" class="sidebar-item <?= $item['lesson_id']==$lessonId?'active':'' ?> <?= $item['is_completed']?'completed':'' ?>">
                        <span class="item-status"><?= $item['is_completed'] ? '✓' : $item['order_number'] ?></span>
                        <span class="item-title"><?= e($item['title']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="lesson-main">
                <!-- Header -->
                <div class="lesson-header">
                    <div class="lesson-badges">
                        <span class="badge-subj"><?= e($lesson['subject_code']) ?></span>
                        <?php if ($hasDifficulty && !empty($lesson['difficulty'])): ?>
                        <span class="badge-diff badge-<?= $lesson['difficulty'] ?>">
                            <?= $lesson['difficulty']=='beginner'?'🟢':($lesson['difficulty']=='intermediate'?'🟡':'🔴') ?> <?= ucfirst($lesson['difficulty']) ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($isCompleted): ?><span class="badge-done">✓ Completed</span><?php endif; ?>
                    </div>
                    <h1><?= e($lesson['lesson_title']) ?></h1>
                    <p class="lesson-meta">👨‍🏫 <?= e($lesson['instructor_name']) ?> • ⏱️ <?= $lesson['estimated_time'] ?? 30 ?> mins</p>
                </div>

                <!-- Learning Objectives -->
                <?php if ($hasObjectives && !empty($lesson['learning_objectives'])): ?>
                <div class="objectives-box">
                    <h3>🎯 Learning Objectives</h3>
                    <div class="objectives-list">
                        <?php foreach (explode("\n", $lesson['learning_objectives']) as $obj):
                            $obj = trim($obj); if (!$obj) continue;
                            $obj = ltrim($obj, '•-* ');
                        ?>
                        <div class="objective-item"><span>☐</span> <?= e($obj) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Lesson Content -->
                <?php if (!empty($lesson['lesson_content'])): ?>
                <div class="content-box">
                    <div class="content-body"><?= $lesson['lesson_content'] ?></div>
                </div>
                <?php endif; ?>

                <!-- Topics -->
                <?php if ($topics): ?>
                <div class="topics-box">
                    <h3>📖 Topics (<?= count($topics) ?>)</h3>
                    <div class="topics-list">
                        <?php foreach ($topics as $i => $t):
                            $embedUrl = $hasVideoUrl ? getEmbedUrl($t['video_url'] ?? '') : '';
                        ?>
                        <div class="topic-item <?= $i==0?'open':'' ?>" id="topic-<?= $t['topic_id'] ?>">
                            <button class="topic-toggle" onclick="toggleTopic(<?= $t['topic_id'] ?>)">
                                <span class="topic-num"><?= $t['topic_order'] ?></span>
                                <span class="topic-title"><?= e($t['topic_title']) ?></span>
                                <?php if ($embedUrl): ?><span class="tag-video">🎬</span><?php endif; ?>
                                <span class="topic-arrow">▼</span>
                            </button>
                            <div class="topic-content">
                                <?php if ($embedUrl): ?>
                                <div class="video-container">
                                    <iframe src="<?= e($embedUrl) ?>" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                                </div>
                                <?php endif; ?>
                                <div class="topic-body"><?= $t['topic_content'] ?: '<p class="no-content">No additional content.</p>' ?></div>
                                <?php if (!empty($t['estimated_time'])): ?>
                                <div class="topic-meta">⏱️ ~<?= $t['estimated_time'] ?> mins</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Attachments -->
                <?php if ($attachments): ?>
                <div class="attachments-box">
                    <h3>📎 Resources</h3>
                    <div class="attachments-list">
                        <?php foreach ($attachments as $f):
                            $ext = strtolower(pathinfo($f['file_name'], PATHINFO_EXTENSION));
                            $icon = match($ext) { 'pdf'=>'📄', 'doc','docx'=>'📝', 'ppt','pptx'=>'📊', 'xls','xlsx'=>'📈', 'zip','rar'=>'📦', 'jpg','jpeg','png','gif'=>'🖼️', 'mp4','avi'=>'🎬', default=>'📁' };
                        ?>
                        <a href="<?= BASE_URL ?>/uploads/materials/<?= e($f['file_path']) ?>" class="attachment-item" target="_blank" download>
                            <span class="file-icon"><?= $icon ?></span>
                            <span class="file-name"><?= e($f['file_name']) ?></span>
                            <span class="dl-icon">⬇️</span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Mark Complete -->
                <div class="actions-box">
                    <?php if (!$isCompleted): ?>
                    <button id="markCompleteBtn" class="btn-complete" data-lesson="<?= $lessonId ?>">✓ Mark as Complete</button>
                    <?php else: ?>
                    <div class="completed-msg">✓ Completed on <?= date('M d, Y', strtotime($progress['completed_at'])) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Navigation -->
                <div class="lesson-nav">
                    <?php if ($prevLesson): ?>
                    <a href="lesson-view.php?id=<?= $prevLesson['lesson_id'] ?>" class="nav-btn">
                        <small>← Previous</small><strong><?= e($prevLesson['title']) ?></strong>
                    </a>
                    <?php else: ?><div></div><?php endif; ?>

                    <?php if ($nextLesson): ?>
                    <a href="lesson-view.php?id=<?= $nextLesson['lesson_id'] ?>" class="nav-btn nav-next">
                        <small>Next →</small><strong><?= e($nextLesson['title']) ?></strong>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
.back-link{color:#16a34a;text-decoration:none;font-size:14px;display:block;margin-bottom:16px}
.lesson-layout{display:grid;grid-template-columns:260px 1fr;gap:20px}

/* Sidebar */
.lesson-sidebar{background:#fff;border:1px solid #e5e5e5;border-radius:10px;position:sticky;top:80px;max-height:calc(100vh - 100px);display:flex;flex-direction:column}
.sidebar-head{padding:14px 16px;background:#fafafa;border-bottom:1px solid #e5e5e5;display:flex;justify-content:space-between;align-items:center}
.sidebar-head h3{font-size:14px;margin:0}
.sidebar-head span{color:#16a34a;font-weight:600;font-size:13px}
.sidebar-list{flex:1;overflow-y:auto;padding:8px}
.sidebar-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:6px;text-decoration:none;color:#555;font-size:13px;margin-bottom:4px}
.sidebar-item:hover{background:#f5f5f5}
.sidebar-item.active{background:#dcfce7;color:#16a34a;font-weight:600}
.sidebar-item.completed .item-status{background:#dcfce7;color:#16a34a}
.item-status{width:24px;height:24px;background:#f0f0f0;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;flex-shrink:0}
.item-title{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* Main */
.lesson-main{min-width:0}
.lesson-header{background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:20px;margin-bottom:16px}
.lesson-badges{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
.badge-subj{background:#16a34a;color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600}
.badge-diff{padding:4px 10px;border-radius:6px;font-size:12px;font-weight:500}
.badge-beginner{background:#dcfce7;color:#166534}
.badge-intermediate{background:#fef3c7;color:#92400e}
.badge-advanced{background:#fee2e2;color:#991b1b}
.badge-done{background:#dcfce7;color:#16a34a;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600}
.lesson-header h1{font-size:22px;margin:0 0 8px;color:#1a1a1a}
.lesson-meta{color:#666;font-size:14px;margin:0}

/* Objectives */
.objectives-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px 20px;margin-bottom:16px}
.objectives-box h3{font-size:15px;margin:0 0 12px;color:#166534}
.objectives-list{display:flex;flex-direction:column;gap:8px}
.objective-item{display:flex;gap:10px;font-size:14px;color:#166534}
.objective-item span{color:#16a34a}

/* Content */
.content-box{background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:20px;margin-bottom:16px}
.content-body{font-size:15px;line-height:1.7;color:#333}
.content-body h2,.content-body h3{margin:20px 0 10px;color:#1a1a1a}
.content-body p{margin:0 0 14px}
.content-body ul,.content-body ol{margin:0 0 14px;padding-left:24px}
.content-body pre{background:#1a1a1a;color:#f5f5f5;padding:14px;border-radius:6px;overflow-x:auto;font-size:13px}
.content-body code{background:#f0f0f0;padding:2px 6px;border-radius:4px;font-size:13px}
.content-body pre code{background:none;padding:0}
.content-body img{max-width:100%;border-radius:6px}

/* Topics */
.topics-box{background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:20px;margin-bottom:16px}
.topics-box h3{font-size:15px;margin:0 0 14px}
.topics-list{display:flex;flex-direction:column;gap:8px}
.topic-item{border:1px solid #e5e5e5;border-radius:8px;overflow:hidden}
.topic-toggle{width:100%;display:flex;align-items:center;gap:10px;padding:12px 14px;background:#fafafa;border:none;cursor:pointer;text-align:left}
.topic-toggle:hover{background:#f5f5f5}
.topic-num{width:26px;height:26px;background:#16a34a;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0}
.topic-title{flex:1;font-weight:600;color:#1a1a1a;font-size:14px}
.tag-video{background:#dbeafe;color:#1d4ed8;padding:2px 6px;border-radius:4px;font-size:11px}
.topic-arrow{color:#888;font-size:11px;transition:transform .2s}
.topic-item.open .topic-arrow{transform:rotate(180deg)}
.topic-content{display:none;padding:14px;border-top:1px solid #e5e5e5}
.topic-item.open .topic-content{display:block}
.topic-body{font-size:14px;line-height:1.6;color:#444}
.topic-body .no-content{color:#999;font-style:italic}
.topic-meta{margin-top:10px;font-size:12px;color:#888}
.video-container{position:relative;padding-bottom:56.25%;height:0;margin-bottom:14px;border-radius:8px;overflow:hidden;background:#000}
.video-container iframe{position:absolute;top:0;left:0;width:100%;height:100%}

/* Attachments */
.attachments-box{background:#fff;border:1px solid #e5e5e5;border-radius:10px;padding:20px;margin-bottom:16px}
.attachments-box h3{font-size:15px;margin:0 0 12px}
.attachments-list{display:flex;flex-direction:column;gap:8px}
.attachment-item{display:flex;align-items:center;gap:10px;padding:10px 14px;background:#fafafa;border-radius:6px;text-decoration:none;color:#333}
.attachment-item:hover{background:#f0f0f0}
.file-icon{font-size:20px}
.file-name{flex:1;font-size:14px}
.dl-icon{color:#888}

/* Actions */
.actions-box{margin-bottom:16px}
.btn-complete{width:100%;padding:14px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer}
.btn-complete:hover{background:#15803d}
.btn-complete:disabled{background:#ccc;cursor:not-allowed}
.completed-msg{background:#dcfce7;color:#16a34a;padding:14px;border-radius:8px;text-align:center;font-weight:500}

/* Nav */
.lesson-nav{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.nav-btn{background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:14px;text-decoration:none}
.nav-btn:hover{border-color:#16a34a}
.nav-btn small{display:block;font-size:12px;color:#888;margin-bottom:4px}
.nav-btn strong{display:block;font-size:14px;color:#1a1a1a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nav-next{text-align:right}

/* Locked */
.locked-box{background:#fff;border:1px solid #e5e5e5;border-radius:12px;padding:60px 40px;text-align:center;max-width:500px;margin:40px auto}
.locked-icon{font-size:48px;margin-bottom:16px}
.locked-box h2{margin:0 0 10px;font-size:22px}
.locked-box p{color:#666;margin:0 0 20px}
.btn-primary{display:inline-block;background:#16a34a;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600}
.btn-primary:hover{background:#15803d}

@media(max-width:900px){
    .lesson-layout{grid-template-columns:1fr}
    .lesson-sidebar{position:static;max-height:none}
    .sidebar-list{max-height:180px}
}
</style>

<script>
function toggleTopic(id){document.getElementById('topic-'+id).classList.toggle('open')}

document.addEventListener('DOMContentLoaded',function(){
    const btn=document.getElementById('markCompleteBtn');
    if(btn){
        btn.addEventListener('click',async function(){
            const id=this.dataset.lesson;
            this.disabled=true;this.textContent='Marking...';
            try{
                const r=await fetch('<?= BASE_URL ?>/api/LessonsAPI.php?action=complete',{
                    method:'POST',headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({lesson_id:id})
                });
                const d=await r.json();
                if(d.success){location.reload()}
                else{alert(d.message||'Failed');this.disabled=false;this.textContent='✓ Mark as Complete'}
            }catch(e){alert('Error');this.disabled=false;this.textContent='✓ Mark as Complete'}
        });
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
