/**
 * Instructor Analytics Page
 * Teaching performance metrics and student progress overview
 */
import { Api } from '../../api.js';

export async function render(container) {
    const [dashRes, subjRes] = await Promise.all([
        Api.get('/DashboardAPI.php?action=instructor'),
        Api.get('/LessonsAPI.php?action=subjects')
    ]);
    const data = dashRes.success ? dashRes.data : {};
    const stats = data.stats || {};
    const classes = data.classes || [];
    const subjects = subjRes.success ? subjRes.data : [];

    // Fetch quiz data per subject for analytics
    const quizPromises = subjects.map(s =>
        Api.get('/QuizzesAPI.php?action=instructor-list&subject_id=' + s.subject_id)
            .then(r => ({ subject: s, quizzes: r.success ? r.data : [] }))
            .catch(() => ({ subject: s, quizzes: [] }))
    );
    const subjectQuizzes = await Promise.all(quizPromises);

    // Compute analytics
    let totalQuestions = 0, totalAttempts = 0, totalAvgScore = 0, quizzesWithScores = 0;
    let publishedLessons = 0, draftLessons = 0, publishedQuizzes = 0, draftQuizzes = 0;
    const subjectAnalytics = [];

    subjectQuizzes.forEach(sq => {
        let subjAttempts = 0, subjAvg = 0, subjQuizCount = 0;
        sq.quizzes.forEach(q => {
            totalQuestions += parseInt(q.question_count || 0);
            const attempts = parseInt(q.attempt_count || 0);
            totalAttempts += attempts;
            if (q.status === 'published') publishedQuizzes++;
            else draftQuizzes++;
            if (q.avg_score && attempts > 0) {
                subjAvg += parseFloat(q.avg_score);
                subjQuizCount++;
                totalAvgScore += parseFloat(q.avg_score);
                quizzesWithScores++;
            }
            subjAttempts += attempts;
        });
        subjectAnalytics.push({
            code: sq.subject.subject_code,
            name: sq.subject.subject_name,
            quizCount: sq.quizzes.length,
            attemptCount: subjAttempts,
            avgScore: subjQuizCount > 0 ? (subjAvg / subjQuizCount).toFixed(1) : '—'
        });
    });

    classes.forEach(c => {
        publishedLessons += parseInt(c.published_lessons || 0);
        draftLessons += (parseInt(c.total_lessons || 0) - parseInt(c.published_lessons || 0));
    });

    const overallAvg = quizzesWithScores > 0 ? (totalAvgScore / quizzesWithScores).toFixed(1) : '—';

    container.innerHTML = `
        <style>
            .page-header { margin-bottom:24px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .page-header p { font-size:14px; color:#737373; margin-top:4px; }

            .analytics-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:14px; margin-bottom:24px; }
            .an-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:18px; text-align:center; }
            .an-num { font-size:26px; font-weight:800; color:#1B4D3E; }
            .an-label { font-size:12px; color:#737373; margin-top:4px; }

            .charts-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
            .chart-card { background:#fff; border:1px solid #e8e8e8; border-radius:14px; overflow:hidden; }
            .chart-card.full { grid-column:1/-1; }
            .chart-header { padding:16px 20px; background:#fafafa; border-bottom:1px solid #e8e8e8; font-weight:700; font-size:15px; color:#262626; }
            .chart-body { padding:20px; }

            .bar-row { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
            .bar-label { min-width:80px; font-size:13px; font-weight:600; color:#404040; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .bar-track { flex:1; background:#f0f0f0; height:10px; border-radius:5px; overflow:hidden; }
            .bar-fill { height:100%; border-radius:5px; transition:width .5s; }
            .bar-fill.green { background:linear-gradient(90deg,#00461B,#2D6A4F); }
            .bar-fill.yellow { background:linear-gradient(90deg,#B45309,#f59e0b); }
            .bar-fill.red { background:linear-gradient(90deg,#b91c1c,#ef4444); }
            .bar-value { min-width:45px; text-align:right; font-size:13px; font-weight:700; color:#262626; }

            .stat-pair { display:flex; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f5f5f5; }
            .stat-pair:last-child { border-bottom:none; }
            .sp-label { font-size:14px; color:#737373; }
            .sp-value { font-size:14px; font-weight:700; color:#262626; }

            .data-table { width:100%; border-collapse:collapse; }
            .data-table th { text-align:left; padding:10px 14px; font-size:12px; font-weight:600; color:#737373; text-transform:uppercase; border-bottom:1px solid #e8e8e8; }
            .data-table td { padding:10px 14px; border-bottom:1px solid #f5f5f5; font-size:14px; }
            .data-table tr:last-child td { border-bottom:none; }

            .badge-sm { padding:3px 8px; border-radius:12px; font-size:11px; font-weight:600; }
            .badge-green { background:#E8F5E9; color:#1B4D3E; }
            .badge-yellow { background:#FEF3C7; color:#B45309; }

            .empty-text { text-align:center; padding:20px; color:#737373; font-size:14px; }
            @media(max-width:768px) { .charts-row { grid-template-columns:1fr; } .analytics-grid { grid-template-columns:1fr 1fr; } }
        </style>

        <div class="page-header">
            <h2>Analytics</h2>
            <p>Teaching performance and student engagement overview</p>
        </div>

        <div class="analytics-grid">
            <div class="an-card"><div class="an-num">${stats.classes||0}</div><div class="an-label">Classes</div></div>
            <div class="an-card"><div class="an-num">${stats.students||0}</div><div class="an-label">Students</div></div>
            <div class="an-card"><div class="an-num">${publishedLessons}</div><div class="an-label">Published Lessons</div></div>
            <div class="an-card"><div class="an-num">${publishedQuizzes}</div><div class="an-label">Published Quizzes</div></div>
            <div class="an-card"><div class="an-num">${totalQuestions}</div><div class="an-label">Total Questions</div></div>
            <div class="an-card"><div class="an-num">${totalAttempts}</div><div class="an-label">Quiz Attempts</div></div>
            <div class="an-card"><div class="an-num">${overallAvg}${overallAvg !== '—' ? '%' : ''}</div><div class="an-label">Avg Score</div></div>
        </div>

        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-header">Subject Performance</div>
                <div class="chart-body">
                    ${subjectAnalytics.length === 0 ? '<div class="empty-text">No subjects assigned</div>' :
                      subjectAnalytics.map(sa => {
                        const pct = sa.avgScore !== '—' ? parseFloat(sa.avgScore) : 0;
                        const cls = pct >= 75 ? 'green' : pct >= 50 ? 'yellow' : sa.avgScore === '—' ? 'green' : 'red';
                        return `<div class="bar-row">
                            <div class="bar-label" title="${esc(sa.name)}">${esc(sa.code)}</div>
                            <div class="bar-track"><div class="bar-fill ${cls}" style="width:${sa.avgScore !== '—' ? pct : 0}%"></div></div>
                            <div class="bar-value">${sa.avgScore}${sa.avgScore !== '—' ? '%' : ''}</div>
                        </div>`;
                      }).join('')}
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">Content Overview</div>
                <div class="chart-body">
                    <div class="stat-pair"><span class="sp-label">Published Lessons</span><span class="sp-value"><span class="badge-sm badge-green">${publishedLessons}</span></span></div>
                    <div class="stat-pair"><span class="sp-label">Draft Lessons</span><span class="sp-value"><span class="badge-sm badge-yellow">${draftLessons}</span></span></div>
                    <div class="stat-pair"><span class="sp-label">Published Quizzes</span><span class="sp-value"><span class="badge-sm badge-green">${publishedQuizzes}</span></span></div>
                    <div class="stat-pair"><span class="sp-label">Draft Quizzes</span><span class="sp-value"><span class="badge-sm badge-yellow">${draftQuizzes}</span></span></div>
                    <div class="stat-pair"><span class="sp-label">Total Questions</span><span class="sp-value">${totalQuestions}</span></div>
                    <div class="stat-pair"><span class="sp-label">Total Attempts</span><span class="sp-value">${totalAttempts}</span></div>
                </div>
            </div>

            <div class="chart-card full">
                <div class="chart-header">Subject Breakdown</div>
                <div class="chart-body">
                    ${subjectAnalytics.length === 0 ? '<div class="empty-text">No data</div>' : `
                    <table class="data-table">
                        <thead><tr><th>Subject</th><th>Quizzes</th><th>Attempts</th><th>Avg Score</th><th>Students</th></tr></thead>
                        <tbody>
                            ${subjectAnalytics.map((sa, i) => {
                                const cls_ = classes.find(c => c.subject_code === sa.code);
                                return `<tr>
                                    <td><strong>${esc(sa.code)}</strong> <span style="color:#737373;font-size:13px">${esc(sa.name)}</span></td>
                                    <td>${sa.quizCount}</td>
                                    <td>${sa.attemptCount}</td>
                                    <td style="font-weight:700">${sa.avgScore}${sa.avgScore !== '—' ? '%' : ''}</td>
                                    <td>${cls_ ? cls_.student_count : '—'}</td>
                                </tr>`;
                            }).join('')}
                        </tbody>
                    </table>`}
                </div>
            </div>
        </div>
    `;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
