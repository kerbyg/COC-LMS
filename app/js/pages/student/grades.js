/**
 * Student Grades Page
 * View quiz scores grouped by subject
 */
import { Api } from '../../api.js';

export async function render(container) {
    const res = await Api.get('/ProgressAPI.php?action=grades');
    const subjects = res.success ? res.data : [];

    // Overall stats
    let totalQuizzes = 0, totalPassed = 0, allScores = [];
    subjects.forEach(s => {
        s.quizzes.forEach(q => {
            totalQuizzes++;
            if (q.best_score !== null) allScores.push(parseFloat(q.best_score));
            if (q.passed == 1) totalPassed++;
        });
    });
    const overallAvg = allScores.length > 0 ? (allScores.reduce((a,b)=>a+b,0)/allScores.length).toFixed(1) : '—';

    container.innerHTML = `
        <style>
            .page-header { margin-bottom:24px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }

            .summary-row { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
            .sum-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:18px; text-align:center; }
            .sum-num { font-size:26px; font-weight:800; color:#1B4D3E; }
            .sum-label { font-size:12px; color:#737373; margin-top:2px; }

            .subject-group { margin-bottom:24px; }
            .sg-header { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
            .sg-left { display:flex; align-items:center; gap:10px; }
            .sg-code { background:#E8F5E9; color:#1B4D3E; padding:4px 10px; border-radius:6px; font-family:monospace; font-size:13px; font-weight:700; }
            .sg-name { font-size:16px; font-weight:700; color:#262626; }
            .sg-avg { font-size:14px; font-weight:700; color:#1B4D3E; background:#E8F5E9; padding:4px 12px; border-radius:20px; }

            .data-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; border:1px solid #e8e8e8; }
            .data-table th { text-align:left; padding:12px 16px; font-size:12px; font-weight:600; color:#737373; text-transform:uppercase; letter-spacing:.5px; background:#fafafa; border-bottom:1px solid #e8e8e8; }
            .data-table td { padding:12px 16px; border-bottom:1px solid #f5f5f5; font-size:14px; }
            .data-table tr:last-child td { border-bottom:none; }

            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; }
            .badge-passed { background:#E8F5E9; color:#1B4D3E; }
            .badge-failed { background:#FEE2E2; color:#b91c1c; }
            .badge-pending { background:#f5f5f5; color:#737373; }
            .badge-pre { background:#DBEAFE; color:#1E40AF; }
            .badge-post { background:#EDE9FE; color:#6D28D9; }

            .score-bar { background:#f0f0f0; height:6px; border-radius:3px; overflow:hidden; width:80px; display:inline-block; vertical-align:middle; margin-right:6px; }
            .score-fill { height:100%; border-radius:3px; }
            .score-fill.high { background:#1B4D3E; }
            .score-fill.mid { background:#B45309; }
            .score-fill.low { background:#b91c1c; }

            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            @media(max-width:768px) { .summary-row { grid-template-columns:1fr 1fr; } }
        </style>

        <div class="page-header"><h2>My Grades</h2></div>

        <div class="summary-row">
            <div class="sum-card"><div class="sum-num">${subjects.length}</div><div class="sum-label">Subjects</div></div>
            <div class="sum-card"><div class="sum-num">${totalQuizzes}</div><div class="sum-label">Quizzes</div></div>
            <div class="sum-card"><div class="sum-num">${totalPassed}</div><div class="sum-label">Passed</div></div>
            <div class="sum-card"><div class="sum-num">${overallAvg}${overallAvg!=='—'?'%':''}</div><div class="sum-label">Average</div></div>
        </div>

        ${subjects.length === 0 ? '<div class="empty-state-sm">No grades yet. Enroll in subjects and take quizzes to see your grades.</div>' :
          subjects.map(s => `
            <div class="subject-group">
                <div class="sg-header">
                    <div class="sg-left">
                        <span class="sg-code">${esc(s.subject_code)}</span>
                        <span class="sg-name">${esc(s.subject_name)}</span>
                    </div>
                    ${s.avg_score !== null ? `<span class="sg-avg">${s.avg_score}% avg</span>` : ''}
                </div>
                <table class="data-table">
                    <thead><tr><th>Quiz</th><th>Type</th><th>Score</th><th>Status</th><th>Attempts</th></tr></thead>
                    <tbody>
                        ${s.quizzes.map(q => {
                            const score = q.best_score !== null ? parseFloat(q.best_score) : null;
                            const cls = score >= 75 ? 'high' : score >= 50 ? 'mid' : score !== null ? 'low' : '';
                            const typeBadge = q.quiz_type === 'pre_test' ? 'pre' : q.quiz_type === 'post_test' ? 'post' : 'pending';
                            const typeLabel = q.quiz_type === 'pre_test' ? 'Pre-Test' : q.quiz_type === 'post_test' ? 'Post-Test' : 'Quiz';
                            return `<tr>
                                <td style="font-weight:600">${esc(q.quiz_title)}</td>
                                <td><span class="badge badge-${typeBadge}">${typeLabel}</span></td>
                                <td>
                                    ${score !== null ? `
                                        <div class="score-bar"><div class="score-fill ${cls}" style="width:${score}%"></div></div>
                                        <span style="font-weight:700">${score.toFixed(1)}%</span>
                                    ` : '<span style="color:#737373">—</span>'}
                                </td>
                                <td>${q.passed == 1 ? '<span class="badge badge-passed">Passed</span>' :
                                      q.best_score !== null ? '<span class="badge badge-failed">Failed</span>' :
                                      '<span class="badge badge-pending">Not taken</span>'}</td>
                                <td style="color:#737373">${q.attempts || 0}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>
          `).join('')}
    `;
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
