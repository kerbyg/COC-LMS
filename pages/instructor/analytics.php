<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';
Auth::requireRole('instructor');

$userId = Auth::id();
$pageTitle = 'Analytics';
$currentPage = 'analytics';

$stats = db()->fetchOne("SELECT 
    (SELECT COUNT(DISTINCT ss.user_student_id) FROM student_subject ss JOIN faculty_subject fs ON ss.subject_offered_id = fs.subject_offered_id WHERE fs.user_teacher_id = ?) as students,
    (SELECT COUNT(*) FROM lessons WHERE user_teacher_id = ?) as lessons,
    (SELECT COUNT(*) FROM quiz WHERE user_teacher_id = ?) as quizzes,
    (SELECT COUNT(*) FROM student_quiz_attempts sqa JOIN quiz q ON sqa.quiz_id = q.quiz_id WHERE q.user_teacher_id = ? AND sqa.status = 'completed') as attempts,
    (SELECT AVG(sqa.percentage) FROM student_quiz_attempts sqa JOIN quiz q ON sqa.quiz_id = q.quiz_id WHERE q.user_teacher_id = ? AND sqa.status = 'completed') as avg_score,
    (SELECT COUNT(*) FROM student_quiz_attempts sqa JOIN quiz q ON sqa.quiz_id = q.quiz_id WHERE q.user_teacher_id = ? AND sqa.status = 'completed' AND sqa.passed = 1) as passed",
    [$userId, $userId, $userId, $userId, $userId, $userId]
);

$passRate = $stats['attempts'] > 0 ? round(($stats['passed'] / $stats['attempts']) * 100) : 0;

$bySubject = db()->fetchAll("SELECT s.subject_code, s.subject_name, COUNT(DISTINCT q.quiz_id) as qcount, COUNT(sqa.attempt_id) as attempts, AVG(sqa.percentage) as avg FROM quiz q JOIN subject s ON q.subject_id = s.subject_id LEFT JOIN student_quiz_attempts sqa ON q.quiz_id = sqa.quiz_id AND sqa.status = 'completed' WHERE q.user_teacher_id = ? GROUP BY s.subject_id ORDER BY s.subject_code", [$userId]);

$recent = db()->fetchAll("SELECT sqa.percentage, sqa.passed, q.quiz_title, CONCAT(u.first_name, ' ', u.last_name) as student FROM student_quiz_attempts sqa JOIN quiz q ON sqa.quiz_id = q.quiz_id JOIN users u ON sqa.user_student_id = u.users_id WHERE q.user_teacher_id = ? AND sqa.status = 'completed' ORDER BY sqa.completed_at DESC LIMIT 10", [$userId]);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/instructor_sidebar.php';
?>
<main class="main-content">
    <?php include __DIR__ . '/../../includes/topbar.php'; ?>
    <div class="page-content">
        <div class="page-header"><h2>Analytics</h2><p class="text-muted">Overview of your teaching performance</p></div>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon blue">👥</div><div class="stat-info"><span class="stat-num"><?= $stats['students'] ?></span><span class="stat-label">Students</span></div></div>
            <div class="stat-card"><div class="stat-icon green">📝</div><div class="stat-info"><span class="stat-num"><?= $stats['attempts'] ?></span><span class="stat-label">Quiz Attempts</span></div></div>
            <div class="stat-card"><div class="stat-icon yellow">📊</div><div class="stat-info"><span class="stat-num"><?= round($stats['avg_score'] ?? 0) ?>%</span><span class="stat-label">Avg Score</span></div></div>
            <div class="stat-card"><div class="stat-icon purple">✅</div><div class="stat-info"><span class="stat-num"><?= $passRate ?>%</span><span class="stat-label">Pass Rate</span></div></div>
        </div>
        
        <div class="grid-2">
            <div class="panel">
                <div class="panel-head"><h3>📚 By Subject</h3></div>
                <div class="panel-body">
                    <?php if (empty($bySubject)): ?><p class="empty-msg">No data</p>
                    <?php else: foreach ($bySubject as $s): ?>
                    <div class="subj-row">
                        <div><span class="subj-code"><?= e($s['subject_code']) ?></span><span class="subj-name"><?= e($s['subject_name']) ?></span></div>
                        <div class="subj-stats"><span><?= $s['qcount'] ?> quizzes</span><span><?= $s['attempts'] ?> attempts</span><span class="avg"><?= $s['avg'] ? round($s['avg']).'%' : '—' ?></span></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="panel">
                <div class="panel-head"><h3>🕐 Recent Activity</h3></div>
                <div class="panel-body">
                    <?php if (empty($recent)): ?><p class="empty-msg">No activity</p>
                    <?php else: foreach ($recent as $r): ?>
                    <div class="act-row">
                        <div><span class="act-student"><?= e($r['student']) ?></span><span class="act-quiz"><?= e($r['quiz_title']) ?></span></div>
                        <span class="score-badge <?= $r['passed']?'passed':'failed' ?>"><?= round($r['percentage']) ?>%</span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<style>
.page-header{margin-bottom:24px}
.page-header h2{margin:0 0 4px;font-size:24px}
.text-muted{color:#78716c;margin:0}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:#fff;border:1px solid #e7e5e4;border-radius:12px;padding:20px;display:flex;align-items:center;gap:16px}
.stat-icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px}
.stat-icon.blue{background:#dbeafe}.stat-icon.green{background:#dcfce7}.stat-icon.yellow{background:#fef3c7}.stat-icon.purple{background:#f3e8ff}
.stat-num{display:block;font-size:28px;font-weight:700;color:#1c1917}
.stat-label{font-size:13px;color:#78716c}
.grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
.panel{background:#fff;border:1px solid #e7e5e4;border-radius:12px;overflow:hidden}
.panel-head{padding:16px 20px;background:#faf9f7;border-bottom:1px solid #e7e5e4}
.panel-head h3{margin:0;font-size:15px}
.panel-body{padding:16px 20px}
.empty-msg{text-align:center;color:#a8a29e;padding:20px;margin:0}
.subj-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #f5f5f4}
.subj-row:last-child{border-bottom:none}
.subj-code{background:#f0fdf4;color:#16a34a;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;margin-right:12px}
.subj-name{color:#44403c}
.subj-stats{display:flex;gap:16px;font-size:13px;color:#78716c}
.subj-stats .avg{font-weight:700;color:#16a34a}
.act-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #f5f5f4}
.act-row:last-child{border-bottom:none}
.act-student{display:block;font-weight:500;color:#1c1917}
.act-quiz{font-size:13px;color:#78716c}
.score-badge{padding:4px 12px;border-radius:20px;font-size:13px;font-weight:600}
.score-badge.passed{background:#dcfce7;color:#16a34a}
.score-badge.failed{background:#fee2e2;color:#dc2626}
@media(max-width:1024px){.stats-grid{grid-template-columns:repeat(2,1fr)}.grid-2{grid-template-columns:1fr}}
</style>
<?php include __DIR__ . '/../../includes/footer.php'; ?>