/**
 * Instructor Gradebook Page
 * View quiz scores per subject/section with pass/fail tracking
 */
import { Api } from '../../api.js';

let subjects = [];

export async function render(container) {
    const subjRes = await Api.get('/LessonsAPI.php?action=subjects');
    subjects = subjRes.success ? subjRes.data : [];
    renderPage(container);
}

async function renderPage(container, filterSubject = '') {
    container.innerHTML = `
        <style>
            .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
            .page-header h2 { font-size:22px; font-weight:700; color:#262626; }
            .filters { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
            .filters select, .filters input { padding:9px 14px; border:1px solid #e0e0e0; border-radius:8px; font-size:14px; min-width:220px; }

            .grade-summary { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; margin-bottom:24px; }
            .gs-card { background:#fff; border:1px solid #e8e8e8; border-radius:12px; padding:18px; text-align:center; }
            .gs-num { font-size:26px; font-weight:800; color:#1B4D3E; }
            .gs-label { font-size:12px; color:#737373; margin-top:4px; }

            .quiz-grade-section { margin-bottom:24px; }
            .quiz-grade-header { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
            .qg-title { font-size:16px; font-weight:700; color:#262626; }
            .qg-badge { background:#E8F5E9; color:#1B4D3E; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:600; }

            .data-table { width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; border:1px solid #e8e8e8; }
            .data-table th { text-align:left; padding:12px 16px; font-size:12px; font-weight:600; color:#737373; text-transform:uppercase; letter-spacing:.5px; background:#fafafa; border-bottom:1px solid #e8e8e8; }
            .data-table td { padding:12px 16px; border-bottom:1px solid #f5f5f5; font-size:14px; }
            .data-table tr:last-child td { border-bottom:none; }
            .data-table tr:hover td { background:#fafafa; }

            .badge { padding:4px 10px; border-radius:20px; font-size:11px; font-weight:600; }
            .badge-passed { background:#E8F5E9; color:#1B4D3E; }
            .badge-failed { background:#FEE2E2; color:#b91c1c; }
            .badge-pending { background:#FEF3C7; color:#B45309; }

            .score-bar { background:#f0f0f0; height:6px; border-radius:3px; overflow:hidden; width:80px; display:inline-block; vertical-align:middle; margin-right:6px; }
            .score-fill { height:100%; border-radius:3px; }
            .score-fill.high { background:linear-gradient(90deg,#00461B,#2D6A4F); }
            .score-fill.medium { background:linear-gradient(90deg,#B45309,#f59e0b); }
            .score-fill.low { background:linear-gradient(90deg,#b91c1c,#ef4444); }

            .empty-state-sm { text-align:center; padding:40px; color:#737373; }
            @media(max-width:768px) { .grade-summary { grid-template-columns:1fr 1fr; } .data-table { font-size:13px; } }
        </style>

        <div class="page-header"><h2>Gradebook</h2></div>
        <div class="filters">
            <select id="filter-subject">
                <option value="">All Subjects</option>
                ${subjects.map(s => `<option value="${s.subject_id}" ${filterSubject==s.subject_id?'selected':''}>${esc(s.subject_code)} - ${esc(s.subject_name)}</option>`).join('')}
            </select>
            <input type="text" id="search" placeholder="Search student name...">
        </div>
        <div id="gradebook-content"><div style="text-align:center;padding:40px;color:#737373">Loading grades...</div></div>
    `;

    container.querySelector('#filter-subject').addEventListener('change', (e) => renderPage(container, e.target.value));

    // Load data
    const params = filterSubject ? '&subject_id=' + filterSubject : '';
    const [quizRes, studentsRes] = await Promise.all([
        Api.get('/QuizzesAPI.php?action=instructor-list' + params),
        Api.get('/LessonsAPI.php?action=students' + params)
    ]);
    const quizzes = quizRes.success ? quizRes.data : [];
    const studentsData = studentsRes.success ? studentsRes.data : [];

    // Flatten students from grouped data
    const allStudents = [];
    if (Array.isArray(studentsData)) {
        studentsData.forEach(group => {
            if (group.students) group.students.forEach(s => {
                s._subject_code = group.subject_code;
                s._subject_name = group.subject_name;
                allStudents.push(s);
            });
        });
    }

    // Fetch scores for each quiz
    const scorePromises = quizzes.map(q =>
        Api.get('/QuizAttemptsAPI.php?action=quiz-scores&quiz_id=' + q.quiz_id)
            .then(r => ({ quiz: q, scores: r.success ? r.data : [] }))
            .catch(() => ({ quiz: q, scores: [] }))
    );
    const quizScores = await Promise.all(scorePromises);

    const content = container.querySelector('#gradebook-content');

    // Build summary stats
    let totalAttempts = 0, totalPassed = 0, totalFailed = 0, scoreSum = 0;
    quizScores.forEach(qs => {
        qs.scores.forEach(sc => {
            totalAttempts++;
            scoreSum += parseFloat(sc.percentage || 0);
            if (sc.passed == 1) totalPassed++;
            else totalFailed++;
        });
    });
    const avgScore = totalAttempts > 0 ? (scoreSum / totalAttempts).toFixed(1) : '—';

    content.innerHTML = `
        <div class="grade-summary">
            <div class="gs-card"><div class="gs-num">${quizzes.length}</div><div class="gs-label">Quizzes</div></div>
            <div class="gs-card"><div class="gs-num">${totalAttempts}</div><div class="gs-label">Total Attempts</div></div>
            <div class="gs-card"><div class="gs-num">${totalPassed}</div><div class="gs-label">Passed</div></div>
            <div class="gs-card"><div class="gs-num">${totalFailed}</div><div class="gs-label">Failed</div></div>
            <div class="gs-card"><div class="gs-num">${avgScore}%</div><div class="gs-label">Avg Score</div></div>
        </div>
        ${quizScores.length === 0 ? '<div class="empty-state-sm">No quizzes found. Create quizzes first to see grades.</div>' :
          quizScores.map(qs => {
            const q = qs.quiz;
            const scores = qs.scores;
            return `
            <div class="quiz-grade-section">
                <div class="quiz-grade-header">
                    <span class="qg-title">${esc(q.quiz_title)}</span>
                    <span class="qg-badge">${esc(q.subject_code)} · ${scores.length} attempt(s)</span>
                </div>
                ${scores.length === 0 ? '<div style="padding:16px;color:#737373;font-size:14px;background:#fff;border:1px solid #e8e8e8;border-radius:12px">No attempts yet</div>' : `
                <table class="data-table">
                    <thead><tr><th>Student</th><th>Score</th><th>Percentage</th><th>Status</th><th>Attempt</th><th>Date</th></tr></thead>
                    <tbody>
                        ${scores.map(sc => {
                            const pct = parseFloat(sc.percentage || 0);
                            const cls = pct >= 80 ? 'high' : pct >= 50 ? 'medium' : 'low';
                            return `<tr class="student-row" data-name="${esc((sc.first_name+' '+sc.last_name).toLowerCase())}">
                                <td style="font-weight:600">${esc(sc.first_name)} ${esc(sc.last_name)}</td>
                                <td>${sc.earned_points}/${sc.total_points}</td>
                                <td>
                                    <div class="score-bar"><div class="score-fill ${cls}" style="width:${pct}%"></div></div>
                                    <span style="font-weight:600">${pct.toFixed(1)}%</span>
                                </td>
                                <td><span class="badge badge-${sc.passed==1?'passed':'failed'}">${sc.passed==1?'Passed':'Failed'}</span></td>
                                <td style="color:#737373">#${sc.attempt_number||1}</td>
                                <td style="color:#737373">${sc.completed_at ? new Date(sc.completed_at).toLocaleDateString('en-US',{month:'short',day:'numeric'}) : '—'}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>`}
            </div>`;
          }).join('')}
    `;

    // Search filter
    container.querySelector('#search').addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase();
        content.querySelectorAll('.student-row').forEach(row => {
            row.style.display = row.dataset.name.includes(q) ? '' : 'none';
        });
    });
}

function esc(str) { const d = document.createElement('div'); d.textContent = str||''; return d.innerHTML; }
